<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/** Consulta o diretório sem modificar contas, grupos ou permissões. */
final class LeitorLdap extends ConexaoLdap {

    /** Pesquisa somente grupos G-ADM-, com filtro literal e limite explícito. */
    public function buscarGrupos(string $termo): array {
        $termo = EntradaDaFerramenta::lerTexto($termo, 200);
        $partes = preg_split('~[\s\\\\/_-]+~u', $termo, -1, PREG_SPLIT_NO_EMPTY);
        // Termo vazio não deve enumerar todos os grupos da organização por acidente.
        if ($partes === []) {
            throw new DomainException('Digite um trecho do nome do grupo para pesquisar.');
        }
        $padrao = '*';
        // Caracteres de filtro permanecem literais mesmo quando o técnico digita asteriscos.
        foreach ($partes as $parte) {
            $padrao .= ldap_escape($parte, '', LDAP_ESCAPE_FILTER) . '*';
        }
        $linhas = $this->pesquisar('(&(objectCategory=group)(name=G-ADM-*)(|(name=' . $padrao . ')(sAMAccountName=' . $padrao . ')))', ['name', 'sAMAccountName', 'objectGUID', 'description']);
        // Não apresentar um subconjunto silencioso como resultado completo.
        if (count($linhas) > 500) {
            throw new DomainException('Mais de 500 grupos encontrados. Use um trecho mais específico.');
        }
        $grupos = [];
        // Retém domínio/localização e GUID; nome não é chave de identidade.
        foreach ($linhas as $linha) {
            $nome = (string) ($linha['name'][0] ?? '');
            $guid = (string) ($linha['objectguid'][0] ?? '');
            // A projeção é validada também na fronteira de retorno do adaptador.
            if (!str_starts_with(mb_strtoupper($nome), 'G-ADM-') || strlen($guid) !== 16) {
                throw new DomainException('O AD devolveu um grupo sem identidade válida.');
            }
            $grupos[] = ['nome' => $nome, 'guid' => bin2hex($guid), 'dn' => $linha['dn'], 'descricao' => (string) ($linha['description'][0] ?? '')];
        }
        return $grupos;
    }

    /** Lê contas para confirmar nomes completos nos dois domínios, incluindo desativadas. */
    public function listarContasParaOperacao(int $dominio): array {
        $resultado = @ldap_read($this->conexao, '', '(objectClass=*)', ['defaultNamingContext']);
        $raiz = $resultado === false ? [] : ldap_get_entries($this->conexao, $resultado);
        $contexto = (string) ($raiz[0]['defaultnamingcontext'][0] ?? '');
        // Busca limitada a uma OU não comprova ausência de homônimos no domínio inteiro.
        if ($contexto === '' || strcasecmp(preg_replace('/\s*,\s*/', ',', $contexto), preg_replace('/\s*,\s*/', ',', $this->dominio['base_dn'])) !== 0) {
            throw new DomainException('A consulta de identidade exige Base DN igual à raiz do domínio confirmada pelo servidor.');
        }
        return $this->lerContas('(&(objectCategory=person)(objectClass=user))', $dominio);
    }

    /** Resolve a conta pelo GUID, que permanece estável mesmo após renomeação ou mudança de OU. */
    public function lerConta(string $guid, int $dominio): array {
        // GUID nunca é um filtro LDAP arbitrário recebido do navegador.
        if (!preg_match('/^[a-f0-9]{32}$/D', $guid)) {
            throw new DomainException('Identificador de conta inválido.');
        }
        $filtro = '(objectGUID=' . ldap_escape(hex2bin($guid), '', LDAP_ESCAPE_FILTER) . ')';
        $contas = $this->lerContas($filtro, $dominio);
        // Ausência e multiplicidade impedem usar a identidade revisada anteriormente.
        if (count($contas) !== 1) {
            throw new DomainException('A conta identificada não corresponde mais a uma ocorrência única.');
        }
        return $contas[0];
    }

    /** Confere no esquema se CPF é um atributo simples e gravável, sem confundir Escritório. */
    public function conferirAtributoCpf(): void {
        $atributo = $this->dominio['atributo_cpf'] ?? '';
        // A escrita nunca usa um atributo de destino inferido nem o campo Escritório.
        if ($atributo === '' || in_array(strtolower($atributo), ['office', 'physicaldeliveryofficename'], true)) {
            throw new DomainException('Homologue o atributo CPF de destino antes de renovar.');
        }
        $raiz = @ldap_read($this->conexao, '', '(objectClass=*)', ['schemaNamingContext']);
        $dados = $raiz === false ? [] : ldap_get_entries($this->conexao, $raiz);
        $base = $dados[0]['schemanamingcontext'][0] ?? '';
        // Falha de consulta ao esquema não equivale a confirmação do atributo.
        if ($base === '') {
            throw new DomainException('Não foi possível conferir o esquema do atributo CPF.');
        }
        $consulta = @ldap_search($this->conexao, $base, '(lDAPDisplayName=' . ldap_escape($atributo, '', LDAP_ESCAPE_FILTER) . ')', ['isSingleValued', 'systemOnly'], 0, 2, 5);
        $linhas = $consulta === false ? [] : ldap_get_entries($this->conexao, $consulta);
        // CPF vazio só será adicionado em atributo de valor único, sem escrita em campo de sistema.
        if (($linhas['count'] ?? 0) !== 1 || strtoupper($linhas[0]['issinglevalued'][0] ?? '') !== 'TRUE' || strtoupper($linhas[0]['systemonly'][0] ?? '') === 'TRUE') {
            throw new DomainException('O esquema não confirmou um atributo CPF simples e gravável.');
        }
    }

    /** Normaliza uma projeção mínima sem armazenar respostas LDAP completas na auditoria. */
    private function lerContas(string $filtro, int $dominio): array {
        $atributos = ['name', 'displayName', 'sAMAccountName', 'objectGUID', 'userAccountControl', 'accountExpires', 'physicalDeliveryOfficeName'];
        $cpf = $this->dominio['atributo_cpf'] ?? '';
        // A ausência do atributo é preservada e exige homologação antes de seu preenchimento.
        if ($cpf !== '') {
            $atributos[] = $cpf;
        }
        $contas = [];
        // Nenhum filtro elimina contas desativadas da verificação de homônimos.
        foreach ($this->pesquisar($filtro, array_unique($atributos)) as $linha) {
            $guid = (string) ($linha['objectguid'][0] ?? '');
            $controle = $linha['useraccountcontrol'][0] ?? '';
            // Identidade ou estado incompletos não serão interpretados como uma conta ativa.
            if (strlen($guid) !== 16 || !ctype_digit((string) $controle)) {
                throw new DomainException('O AD não confirmou identidade e estado de todas as contas consultadas.');
            }
            $contas[] = [
                'guid' => bin2hex($guid), 'dominio' => $dominio, 'dn' => $linha['dn'],
                'nome' => (string) ($linha['name'][0] ?? ''), 'nome_exibicao' => (string) ($linha['displayname'][0] ?? ''),
                'login' => (string) ($linha['samaccountname'][0] ?? ''), 'controle' => (string) $controle,
                'ativa' => (((int) $controle & 2) === 0), 'validade' => (string) ($linha['accountexpires'][0] ?? ''),
                'cpf' => (string) ($linha[strtolower($cpf)][0] ?? ''), 'escritorio' => (string) ($linha['physicaldeliveryofficename'][0] ?? ''),
            ];
        }
        return $contas;
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

                array $primeira_ou,

                array $segunda_ou

            ): int => strcasecmp($primeira_ou['dn'], $segunda_ou['dn'])

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

    protected function pesquisar(

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

