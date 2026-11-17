<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Conexão TLS compartilhada pelos adaptadores de leitura e escrita limitada.
 */

class ConexaoLdap {

    protected \LDAP\Connection $conexao;

    /**
     * Abre TLS com validação de certificado e credenciais efêmeras vindas do ambiente.
     */

    public function __construct(

        protected readonly array $dominio

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

            throw new DomainException('A autenticação LDAP não foi concluída.');

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

}
