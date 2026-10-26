<?php

// Pontos de entrada que o GLPI consulta para carregar e identificar o plugin.

use Glpi\Plugin\Hooks;

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\MenuDaFerramenta;

// A descoberta não deve encerrar a requisição do GLPI nem alterar seu status HTTP.

if (!defined('GLPI_ROOT') || basename(__DIR__) !== 'admanagement') {

    return;

}

// As declarações são condicionais: um return isolado não impediria funções globais no PHP.

if (!function_exists('plugin_version_admanagement')) {

    /**
     * Registra o menu próprio; a classe de menu verifica o acesso ao montá-lo.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_init_admanagement(): void {

        global $PLUGIN_HOOKS;

        // O hook associa a seção de plugins à classe que monta o menu autorizado.

        $PLUGIN_HOOKS[Hooks::MENU_TOADD]['admanagement'] = [

            'plugins' => MenuDaFerramenta::class,

        ];

        // O link de configuração também precisa respeitar a permissão administrativa.

        if (AcessoGlpi::podeConfigurar()) {

            $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['admanagement'] = 'front/config.php';

        }

    }

    /**
     * Informa identidade e faixa alvo; a compatibilidade operacional exige homologação.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_version_admanagement(): array {

        // Estes dados são exibidos no gerenciamento de plugins do GLPI.

        return [

            'name' => 'ADManagement',

            'version' => '0.6.0',

            'author' => 'MatheusAGoveia',

            'license' => 'Proprietary',

            'homepage' => '',

            'requirements' => [

                'glpi' => [

                    'min' => '11.0.4',

                    'max' => '11.0.7'

                ],

                'php' => ['min' => '8.2'],

            ],

        ];

    }

    /**
     * Recusa carregamento fora da faixa de versões alvo desta base inicial.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_admanagement_check_prerequisites(): bool {

        // Sem a versão carregada pelo GLPI, não é possível aceitar o ambiente.

        if (!defined('GLPI_VERSION')) {

            return false;

        }

        // Mantém o intervalo já declarado: a partir de 11.0.4 e antes de 11.0.7.

        if (version_compare(GLPI_VERSION, '11.0.4', '<')) {

            return false;

        }

        // Recusa versões a partir de 11.0.7, fora da faixa declarada para esta base.

        if (version_compare(GLPI_VERSION, '11.0.7', '>=')) {

            return false;

        }

        // O código exige a versão mínima de PHP definida para esta base do plugin.

        if (version_compare(PHP_VERSION, '8.2', '<')) {

            return false;

        }

        return true;

    }

    /**
     * Permite ativação sem concessões: listas vazias continuam negando a entrada.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_admanagement_check_config(

        $verbose = false

    ): bool {

        return true;

    }

}

