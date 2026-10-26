<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Único adaptador LDAP do produto: possui exclusivamente métodos de leitura.
 */

final class LeitorLdap {

    private \LDAP\Connection $conexao;

    /**
     * Abre TLS com validação de certificado e credenciais efêmeras vindas do ambiente.
     */

    public function __construct(

        private readonly array $dominio

    ) {

        // Interrompe a leitura quando a extensão LDAP necessária não está carregada no PHP.

        if (!extension_loaded('ldap')) {

            throw new DomainException('A extensão LDAP não está disponível. Consulta não realizada.');

        }

        $bind = getenv($dominio['bind_env']);

        $senha = getenv($dominio['senha_env']);

        // Bloqueia a conexão se as referências de ambiente não fornecerem bind e senha válidos.

        if (!is_string($bind) || $bind === '' || !is_string($senha) || $senha === '') {

            throw new DomainException('A credencial da conexão não está disponível no ambiente do servidor.');

        }

        // Recusa iniciar uma conexão sem LDAPS ou STARTTLS.

        if (!in_array($dominio['seguranca'], [

            'LDAPS',

            'STARTTLS'

        ], true)) {

            throw new DomainException('A conexão precisa usar TLS.');

        }

        // A CA deve estar configurada no cliente LDAP; nunca desativar sua validação.

        if (!ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND)) {

            throw new DomainException('Não foi possível exigir a validação do certificado LDAP.');

        }

        $protocolo = $dominio['seguranca'] === 'LDAPS' ? 'ldaps' : 'ldap';

        $conexao = @ldap_connect($protocolo . '://' . $dominio['host'] . ':' . $dominio['porta']);

        // Interrompe o fluxo quando o cliente não consegue criar a conexão LDAP.

        if ($conexao === false) {

            throw new DomainException('Não foi possível abrir a conexão LDAP.');

        }

        $this->conexao = $conexao;

        ldap_set_option($conexao, LDAP_OPT_PROTOCOL_VERSION, 3);

        ldap_set_option($conexao, LDAP_OPT_REFERRALS, 0);

        ldap_set_option($conexao, LDAP_OPT_NETWORK_TIMEOUT, 5);

        // Exige concluir a negociação TLS antes de autenticar no modo STARTTLS.

        if ($dominio['seguranca'] === 'STARTTLS' && !@ldap_start_tls($conexao)) {

            throw new DomainException('Não foi possível estabelecer o canal TLS.');

        }

        // Recusa consultas quando a autenticação com a credencial de leitura não foi concluída.

        if (!@ldap_bind($conexao, $bind, $senha)) {

            throw new DomainException('A autenticação de leitura LDAP não foi concluída.');

        }

        unset($senha, $bind);

    }

    /**
     * Encerra a conexão sem gravar ou registrar a credencial utilizada.
     */

    public function __destruct() {

        // Encerra a conexão somente quando ela chegou a ser inicializada.

        if (isset($this->conexao)) {

            @ldap_unbind($this->conexao);

        }

    }

    /**
     * Consulta todas as OUs da Base DN, incluindo subárvores, sem restrição por operação ou
     * entidade.
     */

    public function listarOus(): array {

        $ous = [];

        // Transforma cada OU real consultada em nome e DN utilizáveis na árvore.

        foreach (

            $this->pesquisar('(objectClass=organizationalUnit)', [

                'ou',

                'distinguishedName'

            ]) as $linha

        ) {

            $dn = (string) ($linha['distinguishedname'][0] ?? $linha['dn'] ?? '');

            // Recusa um caminho fora da Base DN cadastrada em vez de apresentá-lo como destino válido.

            if (!NomeDistinto::pertenceAoDominio($dn, $this->dominio['base_dn'])) {

                throw new DomainException('A consulta retornou um caminho fora do domínio.');

            }

            $ous[] = [

                'dn' => $dn,

                'nome' => (string) ($linha['ou'][0] ?? $dn)

            ];

        }

        usort(

            $ous,

            /**
             * Ordena os caminhos das OUs sem distinguir maiúsculas de minúsculas.
             */

            static fn(

                array $a,

                array $b

            ): int => strcasecmp($a['dn'], $b['dn'])

        );

        return $ous;

    }

    /**
     * Retorna ocupação comprovada por consulta exata; falhas são exceções, nunca disponibilidade.
     */

    public function loginOcupado(

        string $login

    ): bool {

        $filtro = '(&(objectClass=user)(sAMAccountName=' . ldap_escape($login, '', LDAP_ESCAPE_FILTER) . '))';

        return $this->pesquisar($filtro, ['sAMAccountName']) !== [];

    }

    /**
     * Lê somente os quatro identificadores necessários, transitoriamente, para comparação local.
     */

    public function listarIdentidades(): array {

        $cpf = $this->dominio['atributo_cpf'] ?? '';

        $matricula = $this->dominio['atributo_matricula'] ?? '';

        // Impede cobertura conclusiva de identidade sem os atributos homologados de CPF e matrícula.

        if ($cpf === '' || $matricula === '') {

            throw new DomainException('Os atributos de CPF e matrícula precisam ser homologados para concluir a cobertura.');

        }

        $usuarios = [];

        // Extrai transitoriamente os quatro identificadores necessários para comparação de duplicidades.

        foreach (

            $this->pesquisar(

                '(&(objectClass=user)(objectCategory=person))',

                [

                    'displayName',

                    'sAMAccountName',

                    $cpf,

                    $matricula

                ]

            ) as $linha

        ) {

            $usuarios[] = [

                'nome' => (string) ($linha['displayname'][0] ?? ''),

                'login' => (string) ($linha['samaccountname'][0] ?? ''),

                'cpf' => (string) ($linha[strtolower($cpf)][0] ?? ''),

                'matricula' => (string) ($linha[strtolower($matricula)][0] ?? ''),

            ];

        }

        return $usuarios;

    }

    /**
     * Percorre páginas LDAP e recusa limites, referrals ou erros que tornariam a cobertura
     * incompleta.
     */

    private function pesquisar(

        string $filtro,

        array $atributos

    ): array {

        $cookie = '';

        $linhas = [];

        $inicio = microtime(true);

        // Percorre no máximo duzentas páginas LDAP, respeitando o prazo total de leitura.

        for ($pagina = 0; $pagina < 200; $pagina++) {

            $controles = [

                [

                    'oid' => LDAP_CONTROL_PAGEDRESULTS,

                    'iscritical' => true,

                    'value' => [

                        'size' => 500,

                        'cookie' => $cookie

                    ]

                ]

            ];

            $resultado = @ldap_search(

                $this->conexao,

                $this->dominio['base_dn'],

                $filtro,

                array_values(array_unique($atributos)),

                0,

                0,

                5,

                LDAP_DEREF_NEVER,

                $controles

            );

            // Interrompe a consulta quando o servidor não devolve um resultado LDAP utilizável.

            if ($resultado === false) {

                throw new DomainException('Consulta LDAP incompleta.');

            }

            $codigo = 0;

            $retorno = [];

            $referrals = [];

            // Recusa erro, referral ou resultado não confirmado para não anunciar uma cobertura parcial como completa.

            if (

                !ldap_parse_result($this->conexao, $resultado, $codigo, $dn, $mensagem, $referrals, $retorno)

                || $codigo !== 0

                || $referrals !== []

            ) {

                throw new DomainException('O diretório não confirmou uma consulta completa.');

            }

            $entradas = ldap_get_entries($this->conexao, $resultado);

            // Bloqueia a leitura quando as entradas retornadas não podem ser interpretadas.

            if ($entradas === false) {

                throw new DomainException('Não foi possível ler o resultado LDAP.');

            }

            // Acumula as entradas da página atual antes de solicitar a continuação.

            for ($indice = 0; $indice < $entradas['count']; $indice++) {

                $linhas[] = $entradas[$indice];

            }

            $cookie = (string) ($retorno[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '');

            // Encerra com sucesso a paginação quando o servidor não informa um cookie de continuação.

            if ($cookie === '') {

                return $linhas;

            }

            // Interrompe novas páginas ao exceder o prazo, mantendo o resultado como incompleto.

            if (microtime(true) - $inicio > 20) {

                break;

            }

        }

        throw new DomainException('A consulta atingiu o limite de leitura e permanece incompleta.');

    }

}

