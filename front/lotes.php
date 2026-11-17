<?php

// O kernel GLPI 12 prepara sessão, conexão e proteção de origem antes desta entrada PHP.
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\RedirectException;
use GlpiPlugin\Admanagement\AcessoGlpi;
use GlpiPlugin\Admanagement\EnderecoDaFerramenta;
use GlpiPlugin\Admanagement\ExecucaoDoDiretorio;
use GlpiPlugin\Admanagement\InterfaceDaFerramenta;
use GlpiPlugin\Admanagement\EntradaDaFerramenta;
use GlpiPlugin\Admanagement\LeitorDePlanilhas;
use GlpiPlugin\Admanagement\Lote;
use GlpiPlugin\Admanagement\MenuDaFerramenta;
use GlpiPlugin\Admanagement\PaginaDeLotes;
use GlpiPlugin\Admanagement\ProcessadorDeLotes;

global $DB;
AcessoGlpi::exigirAcesso();
// Acesso ao plugin ou administração global não concede automaticamente operações em lotes.
if (!AcessoGlpi::podeOperarLotes()) {
    throw new AccessDeniedHttpException('Seu perfil ativo não possui o direito de operar lotes do ADManagement.');
}
$contexto = AcessoGlpi::obterContextoDoTecnico();
$url = EnderecoDaFerramenta::raiz() . '/front/lotes.php';
$erro = null;
$estado = null;
$historico = [];
$lotes = [];
$historico_id = null;
$_SESSION['admanagement_novo_lote_desafio'] ??= bin2hex(random_bytes(32));

// Não executa instalações, concessões ou comunicação LDAP ao abrir a tela por GET.
try {
    // O ciclo nativo de atualização deve criar as estruturas antes do uso da nova versão.
    if (!$DB->tableExists(Lote::getTable()) || !$DB->tableExists(ExecucaoDoDiretorio::getTable())) {
        throw new DomainException('Atualize o ADManagement em Configurar > Plug-ins para preparar as tabelas próprias de lotes.');
    }
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // Nenhum verbo alternativo serve como atalho para execução.
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        throw new InvalidArgumentException('Use GET para consultar ou POST para enviar uma ação.');
    }
    // A presença do botão no POST distingue importar, consultar, confirmar e executar.
    if ($metodo === 'POST') {
        $acao = $_POST['acao'] ?? '';
        // Planilha inválida não deixa o upload anterior selecionado como alternativa silenciosa.
        if ($acao === 'importar' || ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)) {
            ProcessadorDeLotes::invalidar();
            $nova_necessidade = null;
            // Nova solicitação é diferente de reenvio do arquivo e exige um desafio ainda não consumido.
            if (($_POST['novo_atendimento'] ?? '') === '1') {
                // O servidor não confia em um identificador de necessidade arbitrário do upload.
                if (!is_string($_POST['novo_desafio'] ?? null) || !hash_equals($_SESSION['admanagement_novo_lote_desafio'], $_POST['novo_desafio'])) {
                    throw new DomainException('Reabra a página e confirme novamente o novo atendimento.');
                }
                $nova_necessidade = $_SESSION['admanagement_novo_lote_desafio'];
                $_SESSION['admanagement_novo_lote_desafio'] = bin2hex(random_bytes(32));
            }
            $arquivo = $_FILES['planilha'] ?? [];
            // Apenas temporário de upload real é aceito; caminho local nunca é argumento de formulário.
            if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($arquivo['tmp_name'] ?? null) || !is_uploaded_file($arquivo['tmp_name'])) {
                throw new DomainException('O upload não foi concluído. Envie XLSX ou CSV com até 2 MiB.');
            }
            $linhas = LeitorDePlanilhas::ler($arquivo['tmp_name'], (string) $arquivo['name']);
            ProcessadorDeLotes::importar($linhas, $contexto, $nova_necessidade);
        } elseif ($acao === 'consultar') {
            // Consulta só prepara evidências e não executa objetivos da planilha.
            ProcessadorDeLotes::consultar($contexto);
        } elseif ($acao === 'confirmar') {
            // Confirmação fixa as linhas e os efeitos, sem escrever no AD.
            ProcessadorDeLotes::confirmar($_POST, $contexto);
        } elseif ($acao === 'executar') {
            // Este é o único comando que encaminha uma linha confirmada ao executor.
            ProcessadorDeLotes::executarProxima($_POST, $contexto);
        } elseif ($acao === 'reconciliar') {
            // Reconciliar nunca despacha outra escrita, mesmo que ainda haja linhas confirmadas.
            ProcessadorDeLotes::reconciliar($_POST, $contexto);
        } elseif ($acao === 'exportar') {
            // Exportação só utiliza o lote autorizado da sessão atual.
            $atual = ProcessadorDeLotes::obter($contexto);
            // Não exporta conteúdo de outro operador ou um lote inexistente.
            if ($atual === null) {
                throw new DomainException('Não há lote em revisão para exportar.');
            }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="admanagement-lote-' . $atual['id'] . '.csv"');
            header('Cache-Control: no-store');
            echo ProcessadorDeLotes::exportar($atual);
            return;
        } else {
            // Não interpreta campos livres como comando LDAP ou ação de lote.
            throw new InvalidArgumentException('Ação de lote desconhecida.');
        }
        Html::redirect($url);
    }
    $estado = ProcessadorDeLotes::obter($contexto);
    $lotes = Lote::listarDoOperador($contexto);
    // O histórico durável é limitado ao lote ativo cuja autoria já foi conferida pelo modelo.
    if ($estado !== null) {
        $historico = ExecucaoDoDiretorio::doLote($estado['id']);
    }
    // Histórico pode ser reaberto sem depender de CPF/nome ou da planilha na sessão.
    if (isset($_GET['historico'])) {
        $historico_id = EntradaDaFerramenta::lerIdentificador($_GET['historico']);
        $lote_historico = new Lote();
        // A lista de links não é usada como autorização para o ID recebido.
        if (!$lote_historico->getFromDB($historico_id)) {
            throw new DomainException('Lote não encontrado.');
        }
        $lote_historico->conferirAutoria($contexto);
        $historico = ExecucaoDoDiretorio::doLote($historico_id);
    }
} catch (AccessDeniedHttpException | RedirectException $excecao) {
    // Os sinais nativos de autorização e redirecionamento retornam ao kernel sem conversão.
    throw $excecao;
} catch (DomainException | InvalidArgumentException $excecao) {
    // Erros de negócio são seguros para apresentação; o upload anterior não é restaurado.
    $erro = $excecao->getMessage();
    http_response_code(409);
    $estado = $DB->tableExists(Lote::getTable()) ? ProcessadorDeLotes::obter($contexto) : null;
} catch (Throwable) {
    // SQL, credenciais e respostas LDAP não são expostos na mensagem de infraestrutura.
    $erro = 'Não foi possível concluir a ação. Confira a atualização do plugin e a configuração do servidor.';
    http_response_code(500);
}

Html::header('Lotes de contas', $_SERVER['PHP_SELF'], 'plugins', MenuDaFerramenta::class);
InterfaceDaFerramenta::mostrarInicio(EnderecoDaFerramenta::raiz(), 'lotes', AcessoGlpi::podeConfigurar());
// O mesmo layout exibe impedimentos sem apresentar uma confirmação de sucesso fictícia.
if ($erro !== null) {
    echo '<p class="alert alert-danger" role="alert">' . htmlescape($erro) . '</p>';
}
PaginaDeLotes::mostrar($estado, $historico, $url, $lotes, $historico_id, $_SESSION['admanagement_novo_lote_desafio']);
InterfaceDaFerramenta::mostrarFim();
Html::footer();
