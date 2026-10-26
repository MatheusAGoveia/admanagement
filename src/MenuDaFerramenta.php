<?php

namespace GlpiPlugin\Admanagement;

/**
 * Define os links do plugin que o usuário atual pode visualizar no menu GLPI.
 */

final class MenuDaFerramenta {

    /**
     * Entrega ao menu nativo somente a entrada autorizada para o contexto atual.
     *
     * O nome e a assinatura deste ponto de entrada são contratos do GLPI e precisam ser
     * preservados.
     */

    public static function getMenuContent(): array {

        $pode_acessar = AcessoGlpi::podeAcessar();

        $pode_configurar = AcessoGlpi::podeConfigurar();

        // Sem uma dessas permissões, o plugin não acrescenta entradas ao menu.

        if (!$pode_acessar && !$pode_configurar) {

            return [];

        }

        // Quem tem acesso operacional entra na fila; os demais administradores configuram.

        $pagina = '/front/config.php';

        // Direciona o técnico à fila quando o contexto possui acesso operacional.

        if ($pode_acessar) {

            $pagina = '/front/index.php';

        }

        // O GLPI 11 atende plugins e marketplace pela rota canônica /plugins/.

        $menu = [

            'title' => 'ADManagement',

            'page' => EnderecoDaFerramenta::raiz() . $pagina,

            'icon' => 'ti ti-users',

        ];

        // O atalho administrativo aparece somente para quem pode alterar a configuração.

        if ($pode_configurar) {

            $menu['options']['configuracao'] = [

                'title' => 'Configuração',

                'page' => EnderecoDaFerramenta::raiz() . '/front/config.php',

                'icon' => 'ti ti-settings',

            ];

            $menu['links']['config'] = EnderecoDaFerramenta::raiz() . '/front/config.php';

        }

        return $menu;

    }

}

