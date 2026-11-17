<?php

// Pontos de entrada que o GLPI consulta para carregar e identificar o plugin.

use Glpi\Plugin\Hooks;

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\MenuDaFerramenta;

use GlpiPlugin\Admanagement\PerfilDaFerramenta;

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

        // O GLPI apresenta e persiste os direitos na edição nativa dos perfis.

        Plugin::registerClass(PerfilDaFerramenta::class, ['addtabon' => Profile::class]);

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

            'version' => '0.8.1',

            'author' => 'MatheusAGoveia',

            'license' => 'Proprietary',

            'homepage' => '',

            'requirements' => [

                'glpi' => [

                    'min' => '12.0.0',

                    'max' => '12.0.2'

                ],

                'php' => [

                    'min' => '8.3',

                    'max' => '9.0',

                    'exts' => [

                        'json' => ['required' => true],

                        'mbstring' => ['required' => true],

                    ],

                ],

            ],

        ];

    }

    /**
     * Recusa releases, runtimes e extensões fora do alvo GLPI 12/PHP 8.3.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    function plugin_admanagement_check_prerequisites(): bool {

        // Sem a versão carregada pelo GLPI, não é possível aceitar o ambiente.

        if (!defined('GLPI_VERSION')) {

            return false;

        }

        // Aceita somente as releases verificadas; sufixos de desenvolvimento permanecem fora do alvo.

        if (!in_array(GLPI_VERSION, ['12.0.0', '12.0.1'], true)) {

            return false;

        }

        $requisitos_php = plugin_version_admanagement()['requirements']['php'];

        // Usa os mesmos limites informados ao gerenciador nativo de plugins e ao Composer.

        if (

            version_compare(PHP_VERSION, $requisitos_php['min'], '<')

            || version_compare(PHP_VERSION, $requisitos_php['max'], '>=')

        ) {

            return false;

        }

        // A instalação manual pelo GLPI também precisa conferir as extensões usadas pelas regras.

        if (!extension_loaded('json') || !extension_loaded('mbstring')) {

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

