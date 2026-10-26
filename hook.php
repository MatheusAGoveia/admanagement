<?php

// Funções que o GLPI chama durante a instalação e a desinstalação do plugin.

// A descoberta não deve encerrar a requisição do GLPI nem alterar seu status HTTP.

if (!defined('GLPI_ROOT') || basename(__DIR__) !== 'admanagement') {

    return;

}

// As declarações são condicionais: um return isolado não impediria funções globais no PHP.

if (!function_exists('plugin_admanagement_install')) {

    /**
     * Conclui a instalação inicial sem tabelas, direitos concedidos ou alterações externas.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_admanagement_install(): bool {

        // A instalação atual somente confere os requisitos já declarados no setup.

        return plugin_admanagement_check_prerequisites();

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

