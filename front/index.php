<?php

// Entrada da fila: carrega o GLPI, consulta os dados autorizados e mostra a página.

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

// A entrada depende do bootstrap oficial, no diretório plugins/ ou marketplace/.

require_once __DIR__ . '/../../../inc/includes.php';

// O GLPI 11 inclui esta entrada dentro de um controlador; importa a conexão para esse escopo.

global $DB;

// A autorização ocorre no servidor antes do cabeçalho ou de qualquer conteúdo.

AcessoGlpi::exigirAcesso();

// Prepara o contexto de consulta e o estado inicial da página.

$contexto = AcessoGlpi::obterContextoDoTecnico();

$configuracao_da_fila = ConfiguracaoDaFila::consultar();

$categorias = $configuracao_da_fila['categorias_itil'];

$pagina = 1;

$resultado = [

    'chamados' => [],

    'tem_proxima' => false

];

$impedimentos = [];

$erro_fila = null;

$url_plugin = EnderecoDaFerramenta::raiz();

// --- LEITURA DA FILA REAL, SEM ALTERAR CHAMADOS OU FORMULÁRIOS ---

try {

    $pagina_recebida = 1;

    // Usa a página enviada na URL somente depois de validar seu identificador.

    if (isset($_GET['pagina'])) {

        $pagina_recebida = $_GET['pagina'];

    }

    $pagina = EntradaDaFerramenta::lerIdentificador($pagina_recebida);

    // Recusa uma página que a consulta limitaria silenciosamente a outro número.

    if ($pagina > ConsultaDeChamados::LIMITE_DE_PAGINAS) {

        throw new InvalidArgumentException('A página solicitada ultrapassa o limite de navegação da fila.');

    }

    // Exibe chamados autorizados mesmo sem categorias operacionais; a importação explica o impedimento.

    // Primeiro busca os chamados; depois lê os formulários ligados a esses IDs.

    $consulta = new ConsultaDeChamados($DB);

    $resultado = $consulta->listar($contexto, $categorias, $pagina);

    $leitor = new LeitorDoFormmanager($DB);

    // Reúne os IDs para buscar os formulários em uma única consulta.

    $chamado_ids = [];

    // Reúne os IDs dos chamados para consultar os vínculos da origem em conjunto.

    foreach ($resultado['chamados'] as $chamado) {

        $chamado_ids[] = $chamado['id'];

    }

    $formularios = $leitor->listarFormularios($chamado_ids);

    // Confere a elegibilidade de cada chamado sem criar rascunhos durante a listagem.

    foreach ($resultado['chamados'] as $chamado) {

        try {

            // Aqui a importação só confere os dados; o resultado não é salvo.

            ImportadorDaSolicitacao::importar($chamado, $formularios, $contexto, $categorias);

        } catch (DomainException $erro) {

            // Associa o impedimento da origem ao chamado correspondente para explicá-lo na fila.

            // O impedimento pertence a esse chamado e não impede mostrar os demais.

            $impedimentos[$chamado['id']] = $erro->getMessage();

        }

    }

} catch (DomainException | InvalidArgumentException $erro) {

    // Apresenta entradas inválidas ou impedimentos de negócio como erro da consulta.

    // Entradas inválidas e pendências conhecidas podem ser explicadas ao técnico.

    $erro_fila = $erro->getMessage();

    http_response_code(400);

} catch (Throwable $erro) {

    // Oculta detalhes internos do banco ao informar uma falha inesperada de leitura.

    // Não expor SQL, stack trace ou informações da infraestrutura na página do técnico.

    $erro_fila = 'Não foi possível consultar a fila. Verifique a conexão GLPI e a estrutura instalada do formmanager.';

    http_response_code(500);

}

// Uma falha da origem não pode apresentar botões de importação como se a elegibilidade estivesse conferida.

if ($erro_fila !== null) {

    // Preserva os chamados já lidos, mostrando o impedimento para cada um.

    foreach ($resultado['chamados'] as $chamado_visivel) {

        $impedimentos[$chamado_visivel['id']] = 'Importação não verificada: ' . $erro_fila;

    }

}

// --- APRESENTAÇÃO PHP ---

Html::header('ADManagement', $_SERVER['PHP_SELF'], 'plugins', MenuDaFerramenta::class);

InterfaceDaFerramenta::mostrarInicio(

    $url_plugin,

    'solicitacoes',

    AcessoGlpi::podeConfigurar()

);

?>
<?php

// Mostra o motivo que impediu carregar a fila.

if ($erro_fila !== null) {

    ?>
    <div class="alert alert-danger" role="alert"><?= htmlescape($erro_fila) ?></div>
<?php

} elseif ($categorias === []) {

    // Orienta configurar as categorias quando a fila ainda não possui um recorte definido.

    ?>
    <div class="alert alert-warning">Nenhuma categoria operacional está configurada. Seus chamados continuam visíveis; a importação depende dessa configuração.</div>
<?php

}

?>
<p class="alert alert-info"><?= htmlescape(\GlpiPlugin\Admanagement\PoliticaDeVisibilidade::descrever($contexto)) ?></p>
<div class="contas-layout">
    <div class="contas-coluna-fila">
        <?php

        FormularioDaSolicitacao::mostrarFila($resultado['chamados'], $impedimentos, $url_plugin . '/front/revisao.php');

        ?>
        <?php

        // Apresenta a paginação apenas quando a consulta da fila pode ser utilizada.

        if ($erro_fila === null) {

            ?>
            <nav class="contas-paginacao" aria-label="Páginas da fila">
                <?php

                // Oferece retorno à página anterior sem criar uma página zero.

                if ($pagina > 1) {

                    ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlescape($url_plugin

                        . '/front/index.php?pagina='

                        . ($pagina - 1)) ?>">Anterior</a>
                <?php

                }

                ?>
                <span>Página <?= $pagina ?></span>
                <?php

                // Oferece avanço somente quando há registros adicionais e o limite de páginas permite.

                if ($resultado['tem_proxima'] && $pagina < ConsultaDeChamados::LIMITE_DE_PAGINAS) {

                    ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlescape($url_plugin

                        . '/front/index.php?pagina='

                        . ($pagina + 1)) ?>">Próxima</a>
                <?php

                }

                ?>
            </nav>
        <?php

        }

        ?>
    </div>
    <div class="contas-trabalho contas-detalhes">
        <?php

        InterfaceDaFerramenta::mostrarFormularioVazio();

        ?>
        <?php

        InterfaceDaFerramenta::mostrarDestinoPendente();

        ?>
    </div>
</div>
<?php

InterfaceDaFerramenta::mostrarFim();

// Encerra a estrutura visual iniciada pelo cabeçalho nativo.

Html::footer();

