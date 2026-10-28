<?php

namespace GlpiPlugin\Admanagement;

/**
 * Apresenta a equipe autorizada usando os rótulos, datas e links nativos do GLPI.
 */

final class PaginaDaEquipe {

    /**
     * Consulta os atendimentos permitidos e organiza os filtros, a tabela e a navegação.
     */

    public static function mostrar(

        bool $historico

    ): void {

        global $DB;

        $contexto = AcessoGlpi::obterContextoDoTecnico();

        $configuracao = ConfiguracaoDaFila::consultar();

        $tecnico = null;

        $pagina = 1;

        $resultado = [

            'chamados' => [],

            'tecnicos' => [],

            'tem_proxima' => false

        ];

        $erro = null;

        try {

            // Ausência, vazio e zero representam a consulta de todo o escopo autorizado.

            if (isset($_GET['tecnico']) && $_GET['tecnico'] !== '' && $_GET['tecnico'] !== '0') {

                $tecnico = EntradaDaFerramenta::lerIdentificador($_GET['tecnico']);

            }

            $pagina = isset($_GET['pagina']) ? EntradaDaFerramenta::lerIdentificador($_GET['pagina']) : 1;

            $resultado = (new AcompanhamentoDaEquipe($DB))->consultar(

                $contexto,

                $configuracao['categorias_itil'],

                $tecnico,

                $historico,

                $pagina

            );

        } catch (\DomainException | \InvalidArgumentException $falha) {

            // Mostra filtros ou páginas recusados como entrada inválida da consulta da equipe.

            $erro = $falha->getMessage();

            http_response_code(400);

        } catch (\Throwable $falha) {

            // Informa falha de leitura sem expor detalhes internos da infraestrutura.

            $erro = 'Não foi possível consultar os atendimentos da equipe.';

            http_response_code(500);

        }

        $tela = $historico ? 'historico' : 'overview';

        $titulo = $historico ? 'Histórico de chamados' : 'Visão geral dos chamados';

        $url = EnderecoDaFerramenta::raiz() . '/front/' . ($historico ? 'historico.php' : 'overview.php');

        \Html::header($titulo, $_SERVER['PHP_SELF'], 'plugins', MenuDaFerramenta::class);

        InterfaceDaFerramenta::mostrarInicio(

            EnderecoDaFerramenta::raiz(),

            $tela,

            AcessoGlpi::podeConfigurar()

        );

        echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>'

            . htmlescape($titulo)

            . '</h2></div><div class="contas-corpo">';

        // Exibe o erro de consulta apenas quando o carregamento da equipe falhou.

        if ($erro !== null) {

            echo '<p class="alert alert-danger">' . htmlescape($erro) . '</p>';

        }

        echo '<p>' . htmlescape(PoliticaDeVisibilidade::descrever($contexto)) . '</p>';

        self::mostrarFiltro($contexto, $resultado['tecnicos'], $tecnico, $url);

        self::mostrarChamados($resultado['chamados']);

        self::mostrarPaginacao($url, $pagina, $tecnico, $resultado['tem_proxima']);

        echo '</div></section>';

        InterfaceDaFerramenta::mostrarFim();

        \Html::footer();

    }

    /**
     * Apresenta o catálogo nativo ao superadministrador e as opções autorizadas aos demais perfis.
     */

    private static function mostrarFiltro(

        array $contexto,

        array $tecnicos,

        ?int $tecnico,

        string $url

    ): void {

        echo '<form method="get" action="' . htmlescape($url) . '">';

        // O catálogo geral respeita as entidades ativas do perfil superadministrador.

        if (($contexto['visibilidade']['papel'] ?? '') === 'super_administrador') {

            echo '<label class="form-label">Técnico atribuído</label>';

            \User::dropdown([

                'name' => 'tecnico',

                'value' => $tecnico ?? 0,

                'right' => 'all',

                'entity' => $contexto['visibilidade']['entidades_ids'],

                'entity_sons' => false,

                'width' => '100%',

                'ldap_import' => false

            ]);

        } else {

            // A seleção filtra um alcance já autorizado e nunca concede acesso a outro setor.

            echo '<label class="form-label" for="dropdown_tecnico_admanagement">Técnico atribuído</label>';

            \Dropdown::showFromArray(

                'tecnico',

                ['' => 'Todo o meu escopo'] + $tecnicos,

                [

                    'value' => $tecnico === null ? '' : (string) $tecnico,

                    'rand' => '_admanagement',

                    'width' => '100%',

                    'noselect2' => true

                ]

            );

        }

        echo '<button class="btn btn-primary my-2">Aplicar filtro</button></form>';

    }

    /**
     * Mostra os chamados reais com os nomes de estado, links e formato de data mantidos pelo GLPI.
     */

    private static function mostrarChamados(

        array $chamados

    ): void {

        echo '<p>' . count($chamados) . ' chamado(s) visíveis nesta página. O estado do chamado não comprova efeito no AD.</p>';

        echo '<div class="table-responsive"><table class="table"><thead><tr><th>Chamado</th><th>Título</th><th>Estado</th><th>Atualização</th></tr></thead><tbody>';

        // O próprio GLPI resolve o formulário e traduz cada estado conforme o idioma da sessão.

        foreach ($chamados as $chamado) {

            $url_chamado = \Ticket::getFormURLWithID((int) $chamado['id']);

            $estado = (string) \Ticket::getStatus((int) $chamado['status']);

            $atualizacao = (string) \Html::convDateTime($chamado['date_mod']);

            echo '<tr><td><a href="'

                . htmlescape($url_chamado)

                . '">#'

                . (int) $chamado['id']

                . '</a></td><td>'

                . htmlescape($chamado['name'])

                . '</td><td>'

                . htmlescape($estado)

                . '</td><td>'

                . htmlescape($atualizacao)

                . '</td></tr>';

        }

        // Informa que nenhum atendimento ficou visível no recorte selecionado.

        if ($chamados === []) {

            echo '<tr><td colspan="4">Nenhum atendimento encontrado no recorte autorizado.</td></tr>';

        }

        echo '</tbody></table></div>';

    }

    /**
     * Preserva o filtro do técnico e oferece somente as páginas existentes da consulta.
     */

    private static function mostrarPaginacao(

        string $url,

        int $pagina,

        ?int $tecnico,

        bool $tem_proxima

    ): void {

        echo '<nav aria-label="Páginas da equipe">';

        $filtro = $tecnico === null ? '' : '&tecnico=' . $tecnico;

        // A primeira página não possui uma página anterior disponível.

        if ($pagina > 1) {

            echo '<a class="btn btn-outline-secondary" href="'

                . htmlescape($url . '?pagina=' . ($pagina - 1) . $filtro)

                . '">Anterior</a> ';

        }

        // O serviço consulta um registro adicional para comprovar que a próxima página existe.

        if ($tem_proxima) {

            echo '<a class="btn btn-outline-secondary" href="'

                . htmlescape($url . '?pagina=' . ($pagina + 1) . $filtro)

                . '">Próxima</a>';

        }

        echo '</nav>';

    }

}
