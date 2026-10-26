<?php

namespace GlpiPlugin\Admanagement;

/**
 * Apresenta atendimentos reais da equipe e mantém o histórico distinto da auditoria.
 */

final class PaginaDaEquipe {

    /**
     * Autoriza o contexto e aplica o filtro de técnico somente dentro da equipe permitida.
     */

    public static function mostrar(

        bool $historico

    ): void {

        global $DB;

        $contexto = AcessoGlpi::obterContextoDoTecnico();

        $configuracao = ConfiguracaoDaFila::consultar();

        $tecnico = isset($_GET['tecnico']) && $_GET['tecnico'] !== '' && $_GET['tecnico'] !== '0' ? EntradaDaFerramenta::lerIdentificador($_GET['tecnico']) : null;

        $pagina = isset($_GET['pagina']) ? EntradaDaFerramenta::lerIdentificador($_GET['pagina']) : 1;

        $resultado = [

            'chamados' => [],

            'tecnicos' => [],

            'tem_proxima' => false

        ];

        $erro = null;

        try {

            $resultado = (new AcompanhamentoDaEquipe($DB))->consultar(

                $contexto,

                $configuracao['categorias_itil'],

                $configuracao['grupos_permitidos'],

                $tecnico,

                $historico,

                $pagina

            );

        } catch (\DomainException $falha) {

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

            AcessoGlpi::podeConfigurar(),

            AcessoGlpi::estaComAcessoTemporarioParaTestes()

        );

        echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>'

            . htmlescape($titulo)

            . '</h2></div><div class="contas-corpo">';

        // Exibe o erro de consulta apenas quando o carregamento da equipe falhou.

        if ($erro !== null) {

            echo '<p class="alert alert-danger">' . htmlescape($erro) . '</p>';

        }

        echo '<p>' . htmlescape(PoliticaDeVisibilidade::descrever($contexto)) . '</p><form method="get" action="' . htmlescape($url) . '"><label class="form-label">Técnico atribuído</label>';

        // O Super-Admin usa o catálogo nativo, respeitando as entidades ativas do seu alcance.

        if (($contexto['visibilidade']['papel'] ?? '') === 'super_administrador') {

            \User::dropdown(['name' => 'tecnico', 'value' => $tecnico ?? 0, 'right' => 'all', 'entity' => $contexto['visibilidade']['entidades_ids'], 'entity_sons' => false, 'width' => '100%', 'ldap_import' => false]);

        } else {

            // Os demais papéis selecionam somente integrantes do alcance já autorizado.

            echo '<select name="tecnico" id="tecnico" class="form-select"><option value="">Todo o meu escopo</option>';

            // Os técnicos disponíveis no filtro nunca substituem o pareamento entre setor e entidade.

            foreach ($resultado['tecnicos'] as $id => $nome) {

                echo '<option value="' . (int) $id . '"' . ($tecnico === (int) $id ? ' selected' : '') . '>' . htmlescape($nome) . '</option>';

            }

            echo '</select>';

        }

        echo '<button class="btn btn-primary my-2">Aplicar filtro</button></form><p>'

            . count($resultado['chamados'])

            . ' chamado(s) visíveis nesta página. O estado do chamado não comprova efeito no AD.</p>';

        echo '<div class="table-responsive"><table class="table"><thead><tr><th>Chamado</th><th>Título</th><th>Estado</th><th>Atualização</th></tr></thead><tbody>';

        // Apresenta cada chamado visível com seu estado real no GLPI.

        foreach ($resultado['chamados'] as $chamado) {

            $status = (int) $chamado['status'] === \Ticket::CLOSED ? 'Fechado' : (

                (int) $chamado['status'] === \Ticket::SOLVED ? 'Solucionado' : 'Em atendimento'

            );

            global $CFG_GLPI;

            $ticket_url = rtrim($CFG_GLPI['root_doc'] ?? '', '/') . '/front/ticket.form.php?id=' . (int) $chamado['id'];

            echo '<tr><td><a href="'

                . htmlescape($ticket_url)

                . '">#'

                . (int) $chamado['id']

                . '</a></td><td>'

                . htmlescape($chamado['name'])

                . '</td><td>'

                . htmlescape($status)

                . '</td><td>'

                . htmlescape($chamado['date_mod'])

                . '</td></tr>';

        }

        // Informa que nenhum atendimento ficou visível no recorte selecionado.

        if ($resultado['chamados'] === []) {

            echo '<tr><td colspan="4">Nenhum atendimento encontrado no recorte autorizado.</td></tr>';

        }

        echo '</tbody></table></div><nav aria-label="Páginas da equipe">';

        $filtro = $tecnico === null ? '' : '&tecnico=' . $tecnico;

        // Oferece retorno à página anterior preservando o filtro de técnico.

        if ($pagina > 1) {

            echo '<a class="btn btn-outline-secondary" href="'

                . htmlescape(

                    $url

                        . '?pagina='

                        . ($pagina - 1)

                        . $filtro

                )

                . '">Anterior</a> ';

        }

        // Oferece a próxima página somente quando a consulta encontrou registros adicionais.

        if ($resultado['tem_proxima']) {

            echo '<a class="btn btn-outline-secondary" href="'

                . htmlescape(

                    $url

                        . '?pagina='

                        . ($pagina + 1)

                        . $filtro

                )

                . '">Próxima</a>';

        }

        echo '</nav></div></section>';

        InterfaceDaFerramenta::mostrarFim();

        \Html::footer();

    }

}

