<?php

namespace GlpiPlugin\Admanagement;

/**
 * Organiza a apresentação PHP sem consultar dados ou executar ações do técnico.
 */

final class InterfaceDaFerramenta {

    /**
     * Abre a área visual com navegação, título e os limites reais desta etapa.
     */

    public static function mostrarInicio(

        string $url_plugin,

        string $tela,

        bool $pode_configurar,

        bool $acesso_temporario

    ): void {

        // A configuração muda somente a apresentação; sua autorização pertence à entrada PHP.

        $esta_na_configuracao = in_array($tela, [

            'config',

            'configuracao'

        ], true);

        $titulo = 'Solicitações';

        $subtitulo = 'Importe um chamado da sua fila e revise os dados da solicitação.';

        // Ajusta a orientação inicial quando a página aberta é a configuração administrativa.

        if ($esta_na_configuracao) {

            $titulo = 'Configurações';

            $subtitulo = 'Defina setores, permissões por usuário, grupo ou perfil e categorias operacionais.';

        }

        $titulos = [

            'overview' => 'Visão geral dos chamados',

            'historico' => 'Histórico de chamados',

            'auditoria' => 'Auditoria',

            'pendencias' => 'Pendências'

        ];

        // Seleciona o título específico da tela quando existe um rótulo conhecido para ela.

        if (isset($titulos[$tela])) {

            $titulo = $titulos[$tela];

            $subtitulo = 'Acompanhamento no escopo autorizado do GLPI.';

        }

        // O tema fica restrito ao conteúdo do plugin e não altera a navegação global do GLPI.

        self::mostrarEstilos();

        echo '<div class="contas-area">';

        self::mostrarNavegacao($url_plugin, $tela, $pode_configurar);

        // Os textos fixos descrevem funcionalidades presentes, sem anunciar operação no AD.

        echo '<header class="contas-titulo"><h1>' . htmlescape($titulo) . '</h1>';

        echo '<p>' . htmlescape($subtitulo) . '</p></header>';

        // Mostra o aviso de laboratório quando a exceção local de acesso está explicitamente ativa.

        if ($acesso_temporario) {

            // A exceção de testes continua visível e não afirma suspensão dos direitos nativos.

            echo '<div class="contas-aviso contas-aviso-testes" role="status"><i class="ti ti-alert-triangle" aria-hidden="true"></i>';

            echo '<div><strong>Acesso temporário para testes.</strong> Exceção local aplicável somente enquanto a política nova não foi ativada. Permissões nativas do GLPI continuam válidas.</div></div>';

        }

        // Importar e salvar são ações de revisão local, sem escrita no AD ou no chamado.

        echo '<div class="contas-aviso" role="note"><i class="ti ti-info-circle" aria-hidden="true"></i>';

        echo '<div><strong>Importação e revisão.</strong> Salvar a revisão não executa a operação nem fecha o chamado. Consultas de diretório exigem conexão própria configurada e comando explícito; resultado indisponível permanece não consultado.</div></div>';

    }

    /**
     * Fecha exclusivamente a área de apresentação aberta por mostrarInicio.
     */

    public static function mostrarFim(): void {

        echo '</div>';

    }

    /**
     * Mostra o espaço de domínio e OU indisponível enquanto não houver conexão própria.
     */

    public static function mostrarDestinoPendente(): void {

        // Os controles não possuem name e não integram os dados de nenhum POST.

        echo '<aside class="contas-cartao contas-destino" aria-labelledby="contas-destino-titulo">';

        echo '<div class="contas-cabecalho"><h2 id="contas-destino-titulo">Domínio e OU de destino</h2>';

        echo '<p class="contas-subtitulo">Seleção manual após configurar a conexão.</p></div>';

        echo '<div class="contas-corpo"><fieldset disabled>';

        echo '<div class="contas-campo"><label for="contas-dominio-pendente">Domínio</label>';

        echo '<select class="form-select" id="contas-dominio-pendente"><option>Conexão não configurada</option></select></div>';

        echo '<div class="contas-campo"><label for="contas-ou-pendente">OU</label>';

        echo '<input class="form-control" id="contas-ou-pendente" placeholder="Nenhuma OU consultada"></div>';

        echo '<div class="contas-campo"><label for="contas-busca-ou-pendente">Buscar OU</label>';

        echo '<input class="form-control" type="search" id="contas-busca-ou-pendente" placeholder="Buscar OUs..."></div>';

        echo '</fieldset>';

        // Nenhum domínio, destino ou resultado de consulta é inventado para preencher o painel.

        echo '<p class="contas-vazio contas-vazio-destino">A conexão própria com o AD ainda não está configurada. O catálogo de domínios e OUs não foi consultado.</p>';

        echo '</div></aside>';

    }

    /**
     * Orienta a seleção de um chamado e apresenta os campos sem criar rascunho ou formulário.
     */

    public static function mostrarFormularioVazio(): void {

        echo '<section class="contas-cartao" aria-labelledby="contas-solicitacao-vazia-titulo">';

        echo '<div class="contas-cabecalho"><h2 id="contas-solicitacao-vazia-titulo">Dados da solicitação</h2>';

        echo '<p class="contas-subtitulo">Selecione um chamado para importar e revisar os dados.</p></div>';

        echo '<div class="contas-corpo"><p class="contas-nota">Escolha um chamado disponível na fila e acione a importação para iniciar a revisão.</p>';

        echo '<fieldset disabled><div class="contas-campos">';

        // São somente rótulos da tela inicial; os controles vazios não enviam dados.

        $campos = [

            'nome-civil' => 'Nome civil',

            'nome-social' => 'Nome social',

            'matricula' => 'Matrícula',

            'login' => 'Login',

        ];

        // Apresenta os campos desabilitados do estado inicial sem preencher dados fictícios.

        foreach ($campos as $identificador => $rotulo) {

            echo '<div class="contas-campo"><label for="contas-vazio-'

                . $identificador

                . '">'

                . htmlescape($rotulo)

                . '</label>';

            echo '<input class="form-control" id="contas-vazio-' . $identificador . '" value=""></div>';

        }

        // Grupos e Scripts continuam campos manuais e ficam vazios antes da importação.

        echo '<div class="contas-campo"><label for="contas-vazio-grupos">Grupos</label>';

        echo '<textarea class="form-control" rows="3" id="contas-vazio-grupos"></textarea></div>';

        echo '<div class="contas-campo"><label for="contas-vazio-scripts">Scripts</label>';

        echo '<textarea class="form-control" rows="3" id="contas-vazio-scripts"></textarea></div>';

        echo '</div></fieldset></div></section>';

    }

    /**
     * Mantém destinos reais distintos para fila, equipe, histórico, pendências e auditoria.
     */

    private static function mostrarNavegacao(

        string $url_plugin,

        string $tela,

        bool $pode_configurar

    ): void {

        $abas = [

            'overview' => [

                'Visão Geral',

                'overview.php'

            ],

            'solicitacoes' => [

                'Solicitações',

                'index.php'

            ],

            'historico' => [

                'Histórico',

                'historico.php'

            ],

            'pendencias' => [

                'Pendências',

                'pending.php'

            ],

            'auditoria' => [

                'Auditoria',

                'audit.php'

            ]

        ];

        // Inclui o atalho administrativo somente para usuários com direito nativo de configuração.

        if ($pode_configurar) {

            $abas['configuracao'] = [

                'Configurações',

                'config.php'

            ];

        }

        echo '<nav class="contas-navegacao" aria-label="Áreas da ferramenta">';

        // Cria os links das telas autorizadas e destaca a aba atual.

        foreach ($abas as $chave => [

            $rotulo,

            $arquivo

        ]) {

            echo '<a class="contas-aba'

                . ($tela === $chave ? ' contas-aba-ativa' : '')

                . '" href="'

                . htmlescape(

                    $url_plugin

                        . '/front/'

                        . $arquivo

                )

                . '">'

                . htmlescape($rotulo)

                . '</a>';

        }

        echo '</nav>';

    }

    /**
     * Produz o tema compacto e responsivo em PHP, com seletores restritos à área do plugin.
     */

    private static function mostrarEstilos(): void {

        // A referência orienta proporções e acabamento; este CSS não importa regras ou scripts dela.

        echo <<<'HTML'
<style>
.contas-area {
    --contas-roxo: #786eb9;
    --contas-borda: #dfe3eb;
    --contas-texto: #34415a;
    --contas-suave: #667891;
    color: var(--contas-texto);
    background: #f5f6fa;
    padding: 0 .25rem 1rem;
    font-size: .875rem;
}
.contas-area, .contas-area * { box-sizing: border-box; }
.contas-area .contas-navegacao {
    display: flex;
    gap: .15rem;
    overflow-x: auto;
    padding: .25rem;
    margin-bottom: .75rem;
    background: #fff;
    border: 1px solid var(--contas-borda);
    border-radius: 6px;
}
.contas-area .contas-aba {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: .3rem;
    padding: .45rem .65rem;
    border-radius: 5px;
    color: var(--contas-texto);
    text-decoration: none;
    font-size: .82rem;
}
.contas-area .contas-aba-ativa { background: var(--contas-roxo); color: #fff; }
.contas-area .contas-aba[aria-disabled="true"] { color: #778195; cursor: default; }
.contas-area .contas-titulo { margin-bottom: .75rem; padding-bottom: .65rem; border-bottom: 1px solid var(--contas-borda); }
.contas-area .contas-titulo h1 { margin: 0; color: var(--contas-texto); font-size: 1.35rem; line-height: 1.3; }
.contas-area .contas-titulo p { margin: .15rem 0 0; color: var(--contas-suave); font-size: .82rem; }
.contas-area .contas-aviso {
    display: flex;
    align-items: flex-start;
    gap: .65rem;
    margin-bottom: .75rem;
    padding: .55rem .75rem;
    background: #f8f7fc;
    border: 1px solid #e2def3;
    border-radius: 6px;
    font-size: .82rem;
    line-height: 1.45;
}
.contas-area .contas-aviso > i { padding-top: .1rem; font-size: 1.1rem; }
.contas-area .contas-aviso-testes { background: #fffaf0; border-color: #f4dfaf; }
.contas-area .contas-layout { display: grid; grid-template-columns: minmax(235px, 21%) minmax(0, 1fr); gap: .75rem; align-items: start; }
.contas-area .contas-trabalho { min-width: 0; }
.contas-area .contas-detalhes { display: grid; grid-template-columns: minmax(0, 1fr) minmax(255px, 28%); gap: .75rem; align-items: start; }
.contas-area .contas-cartao {
    min-width: 0;
    background: #fff;
    border: 1px solid var(--contas-borda);
    border-radius: 6px;
    overflow: hidden;
    box-shadow: none;
}
.contas-area .contas-cabecalho {
    padding: .65rem .8rem;
    border-bottom: 1px solid var(--contas-borda);
    background: #fafbfc;
}
.contas-area .contas-cabecalho h2 { margin: 0; color: var(--contas-texto); font-size: .95rem; font-weight: 600; line-height: 1.25; }
.contas-area .contas-subtitulo { margin: .15rem 0 0; color: var(--contas-suave); font-size: .78rem; line-height: 1.35; }
.contas-area .contas-corpo { padding: 1rem 1.2rem; }
.contas-area .contas-lista { max-height: calc(100vh - 16rem); overflow: auto; }
.contas-area .contas-item { display: block; padding: .9rem 1rem; border-bottom: 1px solid var(--contas-borda); overflow-wrap: anywhere; }
.contas-area .contas-item:last-child { border-bottom: 0; }
.contas-area .contas-item-ativo { border-left: 3px solid var(--contas-roxo); background: #f4f1fc; }
.contas-area .contas-item-topo { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .35rem; }
.contas-area .contas-etiqueta { display: inline-block; border-radius: 5px; padding: .15rem .4rem; background: #f1eff9; color: #6d62b0; font-size: .75rem; }
.contas-area .contas-metadados { margin: .35rem 0; color: var(--contas-suave); font-size: .76rem; line-height: 1.5; }
.contas-area .contas-campos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.contas-area .contas-campo { min-width: 0; margin-bottom: .75rem; }
.contas-area .contas-campos .contas-campo { margin-bottom: 0; }
.contas-area .contas-linha-inteira { grid-column: 1 / -1; }
.contas-area label, .contas-area .form-label { display: block; margin-bottom: .25rem; color: var(--contas-texto); font-size: .8rem; font-weight: 500; }
.contas-area .form-control, .contas-area .form-select {
    width: 100%;
    min-width: 0;
    min-height: 40px;
    padding: .5rem .7rem;
    color: var(--contas-texto);
    background-color: #fff;
    border: 1px solid var(--contas-borda);
    border-radius: 6px;
    box-shadow: 0 1px 1px rgba(30, 41, 59, .03);
    font: inherit;
}
.contas-area textarea.form-control { resize: vertical; white-space: pre-wrap; }
.contas-area .form-control:disabled, .contas-area .form-select:disabled, .contas-area fieldset:disabled .form-control, .contas-area fieldset:disabled .form-select { background-color: #f8f9fc; color: #8790a0; cursor: not-allowed; }
.contas-area .form-control::placeholder { color: #8b98ab; opacity: 1; }
.contas-area fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
.contas-area form { margin: 0; }
.contas-area .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .3rem;
    min-height: 38px;
    max-width: 100%;
    padding: .5rem .8rem;
    border: 1px solid var(--contas-roxo);
    border-radius: 5px;
    background: #fff;
    color: var(--contas-roxo);
    font: inherit;
    font-weight: 500;
    text-align: center;
    text-decoration: none;
    white-space: normal;
    cursor: pointer;
}
.contas-area .btn-primary { background: var(--contas-roxo); color: #fff; }
.contas-area .btn-primary:hover, .contas-area .btn-primary:active { background: #695fa8; border-color: #695fa8; color: #fff; }
.contas-area .btn-outline-secondary { border-color: var(--contas-borda); color: var(--contas-texto); }
.contas-area .btn-outline-danger { border-color: #bb4545; color: #a82f2f; }
.contas-area .btn:disabled { opacity: .55; cursor: not-allowed; }
.contas-area .btn:focus-visible, .contas-area .contas-aba:focus-visible, .contas-area a:focus-visible, .contas-area summary:focus-visible { outline: 2px solid #6d5fb2; outline-offset: 3px; }
.contas-area .form-control:focus, .contas-area .form-select:focus { outline: 2px solid #c8bff1; outline-offset: 1px; border-color: var(--contas-roxo); box-shadow: none; }
.contas-area .contas-nota, .contas-area .form-hint { margin: .35rem 0 .8rem; color: var(--contas-suave); font-size: .8rem; line-height: 1.45; }
.contas-area .contas-rodape { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-top: 1rem; padding-top: .9rem; border-top: 1px solid var(--contas-borda); }
.contas-area .contas-rodape .contas-nota { margin: 0; }
.contas-area .contas-sugestoes { display: flex; flex-wrap: wrap; gap: .4rem 1.2rem; margin: .4rem 0 .75rem; padding-left: 1.2rem; }
.contas-area .contas-sugestoes code { color: #6d62b0; }
.contas-area .contas-pendencias { margin-top: .75rem; padding: .8rem; border: 1px solid #f4dfaf; border-radius: 6px; background: #fffaf0; font-size: .8rem; }
.contas-area .contas-pendencias h2, .contas-area .contas-pendencias h3 { margin: 0 0 .5rem; font-size: .95rem; }
.contas-area .contas-pendencias ul { margin: 0 0 .5rem; padding-left: 1.2rem; }
.contas-area .contas-pendencias p:last-child { margin-bottom: 0; }
.contas-area .contas-paginacao { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; padding: .75rem; border-top: 1px solid var(--contas-borda); font-size: .8rem; }
.contas-area .contas-paginacao .btn { min-height: 32px; padding: .3rem .5rem; font-size: .78rem; }
.contas-area .contas-vazio { margin: 0; padding: 1rem; color: var(--contas-suave); font-size: .82rem; line-height: 1.5; }
.contas-area .contas-vazio-destino { margin-top: .75rem; padding: .65rem; border: 1px solid var(--contas-borda); border-radius: 5px; font-size: .78rem; }
.contas-area .contas-destino { position: sticky; top: .5rem; }
.contas-area .contas-configuracao { max-width: 1000px; }
.contas-area .contas-configuracao .select2-container { max-width: 100%; }
.contas-area .form-check { display: flex; align-items: flex-start; gap: .5rem; font-weight: 400; }
.contas-area .form-check-input { flex: 0 0 auto; margin-top: .2rem; }
.contas-area .alert { border-radius: 6px; overflow-wrap: anywhere; }
.contas-area .text-muted { color: var(--contas-suave) !important; }
@media (max-width: 1199.98px) {
    .contas-area .contas-layout { grid-template-columns: minmax(220px, 28%) minmax(0, 1fr); }
    .contas-area .contas-detalhes { grid-template-columns: minmax(0, 1fr); }
    .contas-area .contas-destino { position: static; }
}
@media (max-width: 819.98px) {
    .contas-area .contas-layout { grid-template-columns: minmax(0, 1fr); }
    .contas-area .contas-lista { max-height: 19rem; }
    .contas-area .contas-corpo { padding: 1rem; }
}
@media (max-width: 575.98px) {
    .contas-area { padding-right: 0; padding-left: 0; }
    .contas-area .contas-campos { grid-template-columns: minmax(0, 1fr); }
    .contas-area .contas-rodape { align-items: stretch; flex-direction: column; }
    .contas-area .contas-rodape .btn { width: 100%; }
}
</style>
HTML;

    }

}

