<?php

// Entrada das ações de importação, correção e descarte do rascunho local.

use Glpi\Exception\Http\AccessDeniedHttpException;

use Glpi\Exception\RedirectException;

use GlpiPlugin\Admanagement\RevisaoDaOperacao;

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\EnderecoDaFerramenta;

use GlpiPlugin\Admanagement\ConfiguracaoDaFila;

use GlpiPlugin\Admanagement\ConsultaDeChamados;

use GlpiPlugin\Admanagement\EntradaDaFerramenta;

use GlpiPlugin\Admanagement\FormularioDaSolicitacao;

use GlpiPlugin\Admanagement\ImportadorDaSolicitacao;

use GlpiPlugin\Admanagement\InterfaceDaFerramenta;

use GlpiPlugin\Admanagement\LeitorDoFormmanager;

use GlpiPlugin\Admanagement\MenuDaFerramenta;

use GlpiPlugin\Admanagement\CoordenadorDaRevisao;

use GlpiPlugin\Admanagement\RepositorioDeOperacoes;

use GlpiPlugin\Admanagement\RepositorioDeRascunhos;

use GlpiPlugin\Admanagement\RevisorDaOperacao;

// Reutiliza as classes, a sessão e a conexão já oferecidas pelo GLPI.

require_once __DIR__ . '/../../../inc/includes.php';

// O GLPI 11 inclui esta entrada dentro de um controlador; importa a conexão para esse escopo.

global $DB;

// --- AUTORIZAÇÃO ANTES DE QUALQUER LEITURA OU SAÍDA ---

$contexto = AcessoGlpi::obterContextoDoTecnico();

$configuracao_da_fila = ConfiguracaoDaFila::consultar();

$categorias = $configuracao_da_fila['categorias_itil'];

// Os endereços usam a URL base do plugin informada pelo GLPI.

$url_plugin = EnderecoDaFerramenta::raiz();

$url_revisao = $url_plugin . '/front/revisao.php';

// Estado inicial usado tanto na abertura da revisão quanto na apresentação de erros.

$chamado_id = null;

$rascunho = null;

$base = null;

$erro_revisao = null;

$origem_mudou = false;

try {

    // Aceita somente abertura por GET ou ação de formulário por POST.

    $metodo = 'GET';

    // Lê o método HTTP informado pelo servidor, mantendo GET como padrão quando ausente.

    if (isset($_SERVER['REQUEST_METHOD'])) {

        $metodo = $_SERVER['REQUEST_METHOD'];

    }

    // Recusa métodos diferentes dos usados para consulta e envio dos formulários.

    if (!in_array($metodo, [

        'GET',

        'POST'

    ], true)) {

        throw new InvalidArgumentException('Utilize uma consulta ou uma ação do formulário.');

    }

    // GET abre a revisão; POST envia uma ação do formulário.

    if ($metodo === 'POST') {

        $entrada = $_POST;

    } else {

        // Obtém os dados da URL na abertura da revisão, sem tratá-los como ação de gravação.

        $entrada = $_GET;

    }

    // O formulário envia chamado_id; a URL de consulta também pode usar id.

    $identificador_recebido = null;

    // Prioriza o identificador explícito do chamado enviado pelo formulário.

    if (isset($entrada['chamado_id'])) {

        $identificador_recebido = $entrada['chamado_id'];

    } elseif (isset($entrada['id'])) {

        // Aceita o parâmetro id usado nos links de abertura da revisão.

        $identificador_recebido = $entrada['id'];

    }

    // Revalida categoria, entidade e leitura nativa do chamado antes de recuperar o trabalho da sessão.

    $chamado_id = EntradaDaFerramenta::lerIdentificador($identificador_recebido);

    $consulta = new ConsultaDeChamados($DB);

    $chamado = $consulta->consultar($chamado_id, $contexto, $categorias);

    // O trabalho e a auditoria pertencem à entidade do chamado, mesmo numa seleção recursiva.

    $contexto['entidade_id'] = $chamado['entities_id'];

    $rascunho = RepositorioDeRascunhos::consultar($chamado_id, $contexto);

    // Descartar trabalho local não exige que o formulário continue aprovado.

    $acao = null;

    // Procura comandos de trabalho somente em requisições POST.

    if ($metodo === 'POST') {

        // Lê a ação quando o formulário efetivamente enviou esse campo.

        if (isset($_POST['acao'])) {

            $acao = $_POST['acao'];

        }

    }

    // Trata o descarte local antes da aprovação da origem, pois descartar não executa efeitos externos.

    if ($metodo === 'POST' && $acao === 'descartar') {

        $confirmar_descarte = null;

        // Obtém a confirmação explícita do descarte, sem presumir aceitação do técnico.

        if (isset($_POST['confirmar_descarte'])) {

            $confirmar_descarte = $_POST['confirmar_descarte'];

        }

        // Recusa o descarte que não foi confirmado pelo campo esperado.

        if ($confirmar_descarte !== '1') {

            throw new InvalidArgumentException('Confirme o descarte do rascunho.');

        }

        $revisao_recebida = null;

        // Lê o número de revisão enviado para impedir que uma aba antiga descarte trabalho recente.

        if (isset($_POST['revisao'])) {

            $revisao_recebida = $_POST['revisao'];

        }

        $revisao_esperada = EntradaDaFerramenta::lerIdentificador($revisao_recebida);

        // Impede descartar um rascunho ausente ou modificado depois da abertura da aba.

        if ($rascunho === null || $rascunho['revisao'] !== $revisao_esperada) {

            throw new DomainException('O rascunho mudou. Recarregue antes de descartar.');

        }

        RepositorioDeOperacoes::doGlpi()->registrar('DESCARTADO', $rascunho, $contexto);

        RepositorioDeRascunhos::descartar($chamado_id, $contexto, $revisao_esperada);

        Session::addMessageAfterRedirect('Rascunho local descartado. O chamado e o formulário foram preservados.');

        Html::redirect($url_plugin . '/front/index.php');

        exit;

    }

    // A origem é relida também no GET; rascunho salvo não mantém uma aprovação antiga válida.

    $leitor = new LeitorDoFormmanager($DB);

    $formularios = $leitor->listarFormularios([$chamado_id]);

    $base = ImportadorDaSolicitacao::importar($chamado, $formularios, $contexto, $categorias);

    // A assinatura detecta se os dados relevantes mudaram depois da importação anterior.

    $origem_mudou = $rascunho !== null && $rascunho['assinatura_origem'] !== $base['assinatura_origem'];

    // Inutiliza o resumo quando origem, contexto, configuração ou evidências observadas perderam validade.

    if (

        $rascunho !== null

        && !RevisaoDaOperacao::continuaValida($rascunho, $base, $contexto)

    ) {

        RepositorioDeRascunhos::invalidarConfirmacao($chamado_id, $contexto);

        $rascunho = RepositorioDeRascunhos::consultar($chamado_id, $contexto);

    }

    // --- AÇÕES POST; O LISTENER NATIVO DO GLPI 11 JÁ CONFERIU O TOKEN CSRF ---

    if ($metodo === 'POST') {

        // Somente as ações explícitas listadas podem prosseguir após a releitura da origem.

        if (

            !is_string($acao)

            || !in_array(

                $acao,

                [

                    'importar',

                    'reimportar',

                    'salvar',

                    'consultar_ous',

                    'consultar_diretorio',

                    'preparar_resumo',

                    'confirmar_revisao'

                ],

                true

            )

        ) {

            throw new InvalidArgumentException('A ação solicitada não está disponível.');

        }

        RepositorioDeRascunhos::comAuditoria(

            /**
             * Agrupa a alteração local e seu evento de auditoria; falha durável reverte apenas o
             * rascunho.
             */

            function () use ($acao, $base, $origem_mudou, $rascunho, $chamado_id, $contexto, $DB): void {

                // Substitui as correções locais pela origem atual somente na ação explícita de reimportação.

                if ($acao === 'reimportar') {

                    // Reimportar substitui as correções, por isso exige uma confirmação separada.

                    $confirmar_reimportacao = null;

                    // Obtém a confirmação exigida para substituir o trabalho corrigido.

                    if (isset($_POST['confirmar_reimportacao'])) {

                        $confirmar_reimportacao = $_POST['confirmar_reimportacao'];

                    }

                    // Bloqueia a reimportação sem consentimento explícito para perder as correções locais.

                    if ($confirmar_reimportacao !== '1') {

                        throw new InvalidArgumentException('Confirme a substituição das correções pelos dados atuais da origem.');

                    }

                    $revisao_recebida = null;

                    // Recupera a revisão esperada pela aba antes de substituir o rascunho.

                    if (isset($_POST['revisao'])) {

                        $revisao_recebida = $_POST['revisao'];

                    }

                    $revisao_esperada = EntradaDaFerramenta::lerIdentificador($revisao_recebida);

                    RepositorioDeRascunhos::guardarInicial($base, true, $revisao_esperada);

                } elseif ($origem_mudou) {

                    // Interrompe ações sobre uma origem diferente da importada até ocorrer nova importação confirmada.

                    throw new DomainException('Os dados relevantes da origem mudaram. Faça nova importação antes de continuar.');

                } elseif ($acao === 'importar') {

                    // Importa sem substituir um rascunho que já exista para o mesmo técnico e chamado.

                    // Sem substituição, uma importação mantém o rascunho que já existe.

                    RepositorioDeRascunhos::guardarInicial($base);

                } else {

                    // Encaminha correções e comandos de revisão ao rascunho já importado.

                    // Salvar exige um rascunho anterior e a revisão esperada pela aba atual.

                    if ($rascunho === null) {

                        throw new DomainException('Importe a solicitação antes de salvar uma revisão.');

                    }

                    $revisao_recebida = null;

                    // Lê a revisão enviada pela aba antes de aplicar suas alterações.

                    if (isset($_POST['revisao'])) {

                        $revisao_recebida = $_POST['revisao'];

                    }

                    $revisao_esperada = EntradaDaFerramenta::lerIdentificador($revisao_recebida);

                    $revisado = $acao === 'salvar'

                        ? RevisorDaOperacao::aplicar(

                            $rascunho,

                            $_POST

                        )

                        : CoordenadorDaRevisao::processar(

                            $acao,

                            $rascunho,

                            $base,

                            $contexto,

                            $_POST,

                            $DB

                        );

                    RepositorioDeRascunhos::salvar($revisado, $revisao_esperada);

                }

                $salvo = RepositorioDeRascunhos::consultar($chamado_id, $contexto);

                $eventos = [

                    'importar' => 'IMPORTADO',

                    'reimportar' => 'REIMPORTADO',

                    'salvar' => 'CORRIGIDO',

                    'consultar_ous' => 'CONSULTA_OUS',

                    'consultar_diretorio' => 'CONSULTA_DIRETORIO',

                    'preparar_resumo' => 'RESUMO_PREPARADO',

                    'confirmar_revisao' => 'REVISAO_CONFIRMADA'

                ];

                RepositorioDeOperacoes::doGlpi()->registrar($eventos[$acao], $salvo, $contexto, $rascunho);

            }

        );

        Session::addMessageAfterRedirect('Dados de trabalho salvos na sessão. Nenhuma operação no AD ou no chamado foi executada.');

        Html::redirect($url_revisao . '?id=' . $chamado_id);

        exit;

    }

} catch (AccessDeniedHttpException | RedirectException $erro) {

    // Preserva a recusa de acesso e o redirecionamento como sinais nativos do kernel GLPI.

    // Devolve ao kernel as recusas de acesso e os redirecionamentos nativos do GLPI 11.

    throw $erro;

} catch (DomainException | InvalidArgumentException $erro) {

    // Apresenta impedimentos de negócio ou entradas inválidas sem expor falhas internas.

    // Conflito de estado usa HTTP 409; entrada inválida usa HTTP 400.

    if (

        $rascunho !== null

        && $chamado_id !== null

        && ($base === null

        || $origem_mudou)

    ) {

        RepositorioDeRascunhos::invalidarConfirmacao($chamado_id, $contexto);

        $rascunho = RepositorioDeRascunhos::consultar($chamado_id, $contexto);

    }

    $erro_revisao = $erro->getMessage();

    // Usa HTTP 409 quando a revisão conflita com o estado atual do trabalho ou da origem.

    if ($erro instanceof DomainException) {

        http_response_code(409);

    } else {

        // Usa HTTP 400 quando o conteúdo enviado não possui o formato esperado.

        http_response_code(400);

    }

} catch (Throwable $erro) {

    // Apresenta erro genérico de infraestrutura sem divulgar SQL ou detalhes da conexão.

    // Falhas internas recebem uma mensagem geral, sem detalhes do banco ou da infraestrutura.

    $erro_revisao = 'Não foi possível carregar a revisão. Confira a conexão GLPI e a estrutura instalada da origem.';

    http_response_code(500);

}

// A fila lateral reutiliza o mesmo escopo e as mesmas regras da página inicial.

// Sua falha é auxiliar e não pode substituir o resultado da revisão selecionada.

$resultado_fila = [

    'chamados' => [],

    'tem_proxima' => false

];

$impedimentos_fila = [];

$erro_fila_lateral = null;

try {

    $consulta_fila = new ConsultaDeChamados($DB);

    $resultado_fila = $consulta_fila->listar($contexto, $categorias);

    $ids_da_fila = [];

    // Coleta os chamados da fila lateral para consultar suas origens em conjunto.

    foreach ($resultado_fila['chamados'] as $chamado_da_fila) {

        $ids_da_fila[] = $chamado_da_fila['id'];

    }

    $leitor_fila = new LeitorDoFormmanager($DB);

    $formularios_da_fila = $leitor_fila->listarFormularios($ids_da_fila);

    // Verifica os impedimentos de cada item lateral sem importar trabalho adicional.

    foreach ($resultado_fila['chamados'] as $chamado_da_fila) {

        try {

            // A leitura para exibir a fila não cria nem substitui rascunhos.

            ImportadorDaSolicitacao::importar($chamado_da_fila, $formularios_da_fila, $contexto, $categorias);

        } catch (DomainException $erro) {

            // Guarda o impedimento específico do item lateral, preservando a revisão principal.

            $impedimentos_fila[$chamado_da_fila['id']] = $erro->getMessage();

        }

    }

} catch (Throwable $erro) {

    // Isola falhas da fila lateral para que não substituam o resultado do chamado selecionado.

    $resultado_fila = [

        'chamados' => [],

        'tem_proxima' => false

    ];

    $erro_fila_lateral = 'Não foi possível atualizar a fila lateral. Volte à fila para tentar novamente.';

}

// --- VISUALIZAÇÃO GET OU EXPLICAÇÃO DE UMA PENDÊNCIA ---

Html::header(

    'Revisão da solicitação',

    $_SERVER['PHP_SELF'],

    'plugins',

    MenuDaFerramenta::class

);

InterfaceDaFerramenta::mostrarInicio(

    $url_plugin,

    'solicitacoes',

    AcessoGlpi::podeConfigurar(),

    AcessoGlpi::estaComAcessoTemporarioParaTestes()

);

?>
<p class="alert alert-info"><?= htmlescape(\GlpiPlugin\Admanagement\PoliticaDeVisibilidade::descrever($contexto)) ?></p>
<div class="contas-layout">
    <div class="contas-coluna-fila">
        <?php

        FormularioDaSolicitacao::mostrarFila($resultado_fila['chamados'], $impedimentos_fila, $url_revisao, $chamado_id);

        ?>
        <?php

        // Informa falha da fila lateral somente quando sua atualização não foi concluída.

        if ($erro_fila_lateral !== null) {

            ?>
            <p class="contas-nota" role="status"><?= htmlescape($erro_fila_lateral) ?></p>
        <?php

        }

        ?>
        <nav class="contas-paginacao" aria-label="Navegação da fila">
            <a href="<?= htmlescape($url_plugin . '/front/index.php') ?>">Voltar à fila</a>
            <?php

            // Indica que a fila completa possui mais chamados além dos itens laterais exibidos.

            if ($resultado_fila['tem_proxima']) {

                ?>
                <span class="contas-nota">Mais chamados na fila completa.</span>
            <?php

            }

            ?>
        </nav>
    </div>
    <div class="contas-trabalho">
        <?php

        // Mostra o erro da revisão quando uma consulta ou ação foi recusada.

        if ($erro_revisao !== null) {

            ?>
            <div class="alert alert-danger" role="alert"><?= htmlescape($erro_revisao) ?></div>
        <?php

        }

        ?>
        <?php

        // Solicita reimportação confirmada quando a origem já não corresponde ao trabalho salvo.

        if ($base !== null && $origem_mudou) {

            ?>
            <div class="alert alert-warning">A revisão anterior não corresponde mais à origem. Reimportar substitui somente suas correções locais.</div>
            <?php

            FormularioDaSolicitacao::mostrarImportacao($chamado_id, $url_revisao, $rascunho);

            ?>
        <?php

        } elseif ($base !== null && $rascunho === null) {

            // Oferece a primeira importação quando há origem válida e nenhum rascunho local.

            ?>
            <?php

            FormularioDaSolicitacao::mostrarImportacao($chamado_id, $url_revisao);

            ?>
        <?php

        } elseif ($base !== null && $rascunho !== null) {

            // Exibe os campos editáveis quando origem e rascunho estão disponíveis.

            ?>
            <?php

            FormularioDaSolicitacao::mostrarRevisao($rascunho, $url_revisao);

            ?>
        <?php

        }

        ?>
        <?php

        // Mostra os painéis vazios quando ainda não existe uma revisão utilizável.

        if ($base === null || $rascunho === null || $origem_mudou) {

            ?>
            <div class="contas-detalhes">
                <?php

                InterfaceDaFerramenta::mostrarFormularioVazio();

                ?>
                <?php

                InterfaceDaFerramenta::mostrarDestinoPendente();

                ?>
            </div>
        <?php

        }

        ?>
    </div>
</div>
<?php

InterfaceDaFerramenta::mostrarFim();

// Encerra a apresentação depois dos formulários ou da mensagem de impedimento.

Html::footer();

