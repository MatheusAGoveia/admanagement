<?php

// Entrada administrativa de setores, concessões, categorias operacionais e conexão de leitura.

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\EnderecoDaFerramenta;

use GlpiPlugin\Admanagement\MenuDaFerramenta;

use GlpiPlugin\Admanagement\ConfiguracaoOperacional;

use GlpiPlugin\Admanagement\ConfiguracaoDoDiretorio;

use GlpiPlugin\Admanagement\ApresentacaoDaConfiguracao;

use Glpi\Exception\RedirectException;

use GlpiPlugin\Admanagement\ConfiguracaoDaFila;

use GlpiPlugin\Admanagement\ConfiguracaoDeVisibilidade;

use GlpiPlugin\Admanagement\InterfaceDaFerramenta;

// Carrega a sessão, as classes e a conexão que pertencem ao GLPI.

require_once __DIR__ . '/../../../inc/includes.php';

// A permissão administrativa é conferida antes de mostrar ou salvar a tela.

AcessoGlpi::exigirConfiguracao();

$erro_configuracao = null;

$configuracao = ConfiguracaoDaFila::consultar();

// No GLPI 11, o listener nativo valida e consome o token CSRF antes desta entrada.

// Não validar novamente aqui: um segundo consumo recusaria um POST legítimo.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        // A própria classe de configuração valida os IDs e o escopo das entidades.

        $acao_configuracao = $_POST['acao_configuracao'] ?? 'salvar_fila';

        // Encaminha cada ação para sua validação antes de alterar configurações próprias.

        if ($acao_configuracao === 'salvar_visibilidade') {

            ConfiguracaoDeVisibilidade::alterar($_POST);

        } elseif ($acao_configuracao === 'restaurar_visibilidade') {

            // A recuperação preserva o documento inválido e exige confirmação do Super-Admin.

            ConfiguracaoDeVisibilidade::restaurar($_POST);

        } elseif ($acao_configuracao === 'salvar_acesso') {

            // O endpoint legado continua validado, mas não altera a política nova nem concede setores.

            ConfiguracaoOperacional::salvarAcesso($_POST);

        } elseif ($acao_configuracao === 'salvar_diretorio') {

            // Salva os parâmetros da conexão de leitura, sem testar a rede ou gravar no AD.

            ConfiguracaoDoDiretorio::salvar($_POST);

        } elseif ($acao_configuracao === 'salvar_fila') {

            // Altera categorias operacionais preservando os grupos anteriores para a transição.

            $entrada_fila = $_POST;

            $entrada_fila['grupos_permitidos'] = $configuracao['grupos_permitidos'];

            ConfiguracaoDaFila::salvar($entrada_fila);

        } else {

            // Recusa comandos administrativos que não fazem parte das ações conhecidas.

            throw new InvalidArgumentException('Ação administrativa inválida.');

        }

        Session::addMessageAfterRedirect('Configuração salva e conferida.');

        // Limpa o menu da sessão para que os links sejam montados com a nova configuração.

        unset($_SESSION['glpimenu']);

        // O redirecionamento evita reenviar o POST ao atualizar a página.

        Html::redirect(EnderecoDaFerramenta::raiz() . '/front/config.php');

        exit;

    } catch (RedirectException $erro) {

        // Devolve o redirecionamento ao kernel GLPI, evitando tratá-lo como erro de gravação.

        throw $erro;

    } catch (InvalidArgumentException | RuntimeException $erro) {

        // Exibe falhas de validação ou persistência sem anunciar sucesso da configuração.

        // A falha é mostrada na mesma tela, sem anunciar sucesso na gravação.

        $erro_configuracao = $erro->getMessage();

        http_response_code(400);

    }

}

// A apresentação só começa depois de autorizar e tratar uma eventual gravação.

Html::header(

    'Configuração - ADManagement',

    $_SERVER['PHP_SELF'],

    'plugins',

    MenuDaFerramenta::class,

    'configuracao'

);

InterfaceDaFerramenta::mostrarInicio(

    EnderecoDaFerramenta::raiz(),

    'configuracao',

    true,

    AcessoGlpi::estaComAcessoTemporarioParaTestes()

);

?>
<div class="contas-cartao contas-configuracao">
    <div class="contas-cabecalho"><h2>Configuração do ADManagement</h2></div>
    <div class="contas-corpo">
            <p>Somente o Super-Admin configura setores, concessões e critérios operacionais.</p>
            <?php

            // Apresenta o erro de configuração somente quando a ação anterior falhou.

            if ($erro_configuracao !== null) {

                ?>
                <div class="alert alert-danger" role="alert"><?= htmlescape($erro_configuracao) ?></div>
            <?php

            }

            ?>
            <form method="post" action="<?= htmlescape(EnderecoDaFerramenta::raiz() . '/front/config.php') ?>">
                <input type="hidden" name="acao_configuracao" value="salvar_fila">
                <div class="mb-4">
                    <h2 class="h3">Categorias ITIL da operação</h2>
                    <p>Selecione as categorias que permitem importar solicitações de rede. Chamados autorizados de outras categorias continuam visíveis, com motivo do bloqueio de importação.</p>
                    <?php

                    // Os colchetes preservam a lista de categorias, inclusive com uma só seleção.

                    Dropdown::show(

                        ITILCategory::class,

                        [

                            'name' => 'categorias_itil[]',

                            'value' => $configuracao['categorias_itil'],

                            'multiple' => true,

                            'width' => '100%',

                            'addicon' => false,

                            'aria_label' => 'Categorias ITIL da operação',

                        ]

                    );

                    ?>
                    <p class="form-hint">Seleção exata, sem inclusão automática de subcategorias. Sem seleção, a importação fica indisponível e os chamados continuam visíveis no escopo autorizado.</p>
                </div>
                <button type="submit" class="btn btn-primary">Salvar configurações</button>
                <?php

                Html::closeForm();

                ?>
    </div>
</div>
<?php

ApresentacaoDaConfiguracao::mostrarAcesso(EnderecoDaFerramenta::raiz() . '/front/config.php');

ApresentacaoDaConfiguracao::mostrarDiretorios(EnderecoDaFerramenta::raiz() . '/front/config.php');

InterfaceDaFerramenta::mostrarFim();

// Finaliza a página usando a estrutura visual padrão do GLPI.

Html::footer();

