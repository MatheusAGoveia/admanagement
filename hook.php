<?php

// Funções que o GLPI chama durante a instalação e a desinstalação do plugin.

// A descoberta não deve encerrar a requisição do GLPI nem alterar seu status HTTP.

if (!defined('GLPI_ROOT') || basename(__DIR__) !== 'admanagement') {

    return;

}

// As declarações são condicionais: um return isolado não impediria funções globais no PHP.

if (!function_exists('plugin_admanagement_install')) {

    /**
     * Registra os direitos nativos sem conceder permissões ou modificar dados externos.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_admanagement_install(): bool {

        // A chave é criada sem valores concedidos; cada perfil exige configuração explícita.

        return plugin_admanagement_check_prerequisites()

            && \GlpiPlugin\Admanagement\PerfilDaFerramenta::registrarDireito();

    }

    /**
     * Desinstala a base sem apagar configurações locais ou dados de outros plugins.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_admanagement_uninstall(): bool {

        // Esta base não possui tabelas próprias a remover durante a desinstalação.

        return true;

    }

}

