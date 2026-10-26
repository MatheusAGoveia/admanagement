<?php

namespace GlpiPlugin\Admanagement;

use Html;

/**
 * Mostra a fila e os formulários em PHP, escapando os textos vindos dos dados.
 */

final class FormularioDaSolicitacao {

    /**
     * Mostra a fila real e permite importar somente as linhas sem impedimento identificado.
     */

    public static function mostrarFila(

        array $chamados,

        array $impedimentos,

        string $url_revisao,

        ?int $chamado_selecionado = null

    ): void {

        // A quantidade corresponde somente à página consultada, sem presumir um total global.

        echo '<aside class="contas-cartao contas-fila" aria-labelledby="contas-titulo-fila">';

        echo '<div class="contas-cabecalho"><div><h2 id="contas-titulo-fila">Chamados pendentes</h2>';

        echo '<p>Selecione para importar e revisar os dados.</p></div>';

        echo '<span class="contas-etiqueta" title="Chamados nesta página">' . count($chamados) . '</span></div>';

        echo '<div class="contas-lista">';

        // A lista vazia mantém a explicação e a mesma estrutura visual das demais páginas.

        if ($chamados === []) {

            echo '<div class="contas-vazio">Nenhum chamado foi encontrado nesta página para seu papel, setores, atribuições e entidades autorizadas.</div>';

        }

        // Monta um item lateral para cada chamado realmente retornado pela consulta autorizada.

        foreach ($chamados as $chamado) {

            $chamado_id = $chamado['id'];

            $classe_selecao = '';

            // Destaca o chamado cuja revisão está aberta no painel de trabalho.

            if ($chamado_id === $chamado_selecionado) {

                $classe_selecao = ' contas-item-ativo';

            }

            // O título pertence ao chamado real; não extrai nome ou CPF de seu conteúdo.

            echo '<article class="contas-item' . $classe_selecao . '">';

            echo '<div class="contas-item-topo"><strong>#' . $chamado_id . '</strong>';

            echo '<span class="contas-etiqueta">Solicitação</span></div>';

            echo '<p class="contas-titulo-chamado">' . htmlescape($chamado['name']) . '</p>';

            echo '<p class="contas-metadados">Categoria #'

                . $chamado['itilcategories_id']

                . ' · Estado #'

                . $chamado['status']

                . '</p>';

            // Chamados com impedimento mostram a razão; os demais oferecem a importação.

            if (isset($impedimentos[$chamado_id])) {

                echo '<p class="contas-impedimento">' . htmlescape($impedimentos[$chamado_id]) . '</p>';

            } else {

                // Oferece importação somente quando a elegibilidade do item foi conferida sem impedimento.

                echo '<form method="post" action="' . htmlescape($url_revisao) . '">';

                echo '<input type="hidden" name="chamado_id" value="' . $chamado_id . '">';

                echo '<button class="btn btn-outline-primary btn-sm w-100" name="acao" value="importar">Importar / continuar revisão</button>';

                // O helper inclui o token; a validação acontece no listener nativo GLPI 11.

                Html::closeForm();

            }

            echo '</article>';

        }

        echo '</div></aside>';

    }

    /**
     * Apresenta origem e dados de trabalho, sem transformar salvar revisão em autorização de AD.
     */

    public static function mostrarRevisao(

        array $rascunho,

        string $url_revisao

    ): void {

        $nome_operacao = 'Renovação';

        $titulo_operacao = 'Renovação de usuário';

        // Adapta o título do formulário ao cadastro, mantendo renovação como operação distinta.

        if ($rascunho['tipo'] === 'criacao') {

            $nome_operacao = 'Criação';

            $titulo_operacao = 'Cadastro de usuário';

        }

        // O formulário permanece separado dos POSTs da fila e do descarte do rascunho.

        echo '<form class="contas-formulario-revisao" method="post" action="' . htmlescape($url_revisao) . '">';

        self::mostrarIdentificadores($rascunho);

        echo '<div class="contas-detalhes"><section class="contas-cartao contas-revisao">';

        echo '<div class="contas-cabecalho"><div><h2>'

            . $titulo_operacao

            . '</h2><p>Revise os dados da solicitação antes de salvar.</p></div></div>';

        echo '<div class="contas-corpo"><div class="contas-origem">';

        echo '<span>Chamado <strong>#' . $rascunho['chamado_id'] . '</strong></span>';

        echo '<span>Formulário <strong>#' . $rascunho['formulario_id'] . '</strong></span>';

        echo '<span>Revisão <strong>' . $rascunho['revisao'] . '</strong></span></div>';

        // O tipo e os vínculos continuam fixados pela importação aprovada, sem seletor de operação.

        echo '<p class="contas-nota">Operação solicitada: <strong>'

            . $nome_operacao

            . '</strong>. A origem permanece somente de leitura; suas correções ficam na sessão.</p>';

        echo '<div class="contas-campos">';

        self::mostrarCampo('nome_civil', 'Nome civil', $rascunho['nome_civil'], 240);

        self::mostrarCampo('nome_social', 'Nome social', $rascunho['nome_social'], 240);

        self::mostrarCampo('matricula', 'Matrícula', $rascunho['matricula'], 80);

        echo '<div class="contas-campo">';

        self::mostrarUsoDoNome($rascunho);

        echo '</div></div>';

        // A composição de login é exibida separadamente dos dados de domínio e OU.

        self::mostrarLogin($rascunho);

        // Os campos manuais aceitam mais de uma linha, mas não executam seus valores.

        echo '<h3 class="contas-subtitulo">Preenchimento manual</h3><div class="contas-campos">';

        self::mostrarCampo('grupos', 'Grupos', $rascunho['grupos'], 2000, true);

        self::mostrarCampo('scripts', 'Scripts', $rascunho['scripts'], 500, true);

        self::mostrarCampo(

            'pastas',

            'Pastas e permissões propostas (sem aplicação automática)',

            $rascunho['pastas'] ?? '',

            2000,

            true

        );

        self::mostrarCampo(

            'validade_proposta',

            'Validade proposta (regra definitiva pendente)',

            $rascunho['validade_proposta'] ?? '',

            120

        );

        echo '<button class="btn btn-outline-primary mt-2" name="acao" value="consultar_diretorio">Consultar login e duplicidades no AD configurado</button>';

        ApresentacaoDeConsultas::mostrar($rascunho);

        echo '</div><p class="contas-nota">Grupos e Scripts são dados de trabalho. Não são aplicados ao AD nem executados como scripts.</p>';

        echo '<div class="contas-rodape"><span class="contas-nota">Salvar mantém suas correções para continuar a revisão.</span>';

        echo '<button class="btn btn-primary" name="acao" value="salvar"><i class="ti ti-device-floppy" aria-hidden="true"></i> Salvar revisão de trabalho</button></div>';

        echo '</div></section>';

        // O destino vem de um catálogo real e sua seleção é validada na próxima ação.

        DestinoDaOperacao::mostrar($rascunho);

        echo '</div>';

        Html::closeForm();

        RevisaoDaOperacao::mostrar($rascunho, $url_revisao);

        $pendencias = RevisorDaOperacao::listarPendencias($rascunho);

        self::mostrarPendencias($pendencias);

        // O descarte usa outro formulário e exige confirmação explícita do técnico.

        echo '<details class="contas-descarte"><summary>Descartar este rascunho</summary>';

        echo '<form method="post" action="' . htmlescape($url_revisao) . '">';

        self::mostrarIdentificadores($rascunho);

        echo '<label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_descarte" value="1" required> Confirmo o descarte somente das minhas correções locais.</label>';

        echo '<button class="btn btn-outline-danger mt-2" name="acao" value="descartar">Descartar rascunho</button>';

        Html::closeForm();

        echo '</details>';

    }

    /**
     * Exibe a escolha de nome e só permite alterá-la quando não veio definida da origem.
     */

    private static function mostrarUsoDoNome(

        array $rascunho

    ): void {

        // Uma escolha já definida pela origem é apenas informada ao técnico.

        if ($rascunho['uso_nome_na_origem'] !== null) {

            $nome_utilizado = 'civil';

            // Indica o nome social quando essa é a escolha definida pela origem.

            if ($rascunho['usar_nome_social']) {

                $nome_utilizado = 'social';

            }

            echo '<span class="form-label">Nome para composição do login</span>';

            echo '<p class="contas-nota">Nome utilizado: <strong>'

                . $nome_utilizado

                . '</strong>, conforme a origem.</p>';

            return;

        }

        // Sem definição na origem, o select mostra a escolha atual do rascunho.

        $selecionado = '';

        // Recupera a escolha local já preenchida quando o uso de nome ainda não veio definido na origem.

        if ($rascunho['usar_nome_social'] !== null) {

            // Marca a opção de nome social correspondente ao estado atual do rascunho.

            if ($rascunho['usar_nome_social']) {

                $selecionado = '1';

            } else {

                // Marca a opção de nome civil quando essa é a escolha atual.

                $selecionado = '0';

            }

        }

        $opcoes = [

            '' => 'Precisa de revisão',

            '0' => 'Usar nome civil',

            '1' => 'Usar nome social',

        ];

        echo '<label class="form-label" for="usar_nome_social">Uso de nome social não determinado na origem</label>';

        echo '<select class="form-select mb-3" id="usar_nome_social" name="usar_nome_social">';

        // Apresenta as opções pendente, nome civil e nome social no seletor de revisão.

        foreach ($opcoes as $valor => $rotulo) {

            $atributo_selecionado = '';

            // Marca somente a opção correspondente ao valor atual da indicação de nome.

            if ((string) $valor === $selecionado) {

                $atributo_selecionado = ' selected';

            }

            echo '<option value="' . $valor . '"' . $atributo_selecionado . '>' . $rotulo . '</option>';

        }

        echo '</select>';

    }

    /**
     * Permite iniciar ou refazer a importação com ação POST explícita, preservando o trabalho por
     * padrão.
     */

    public static function mostrarImportacao(

        int $chamado_id,

        string $url_revisao,

        ?array $rascunho = null

    ): void {

        // Um trabalho já existente oferece reimportação; um novo oferece importação.

        $acao = 'importar';

        $texto_botao = 'Importar solicitação';

        // Inclui a revisão esperada quando a importação vai substituir trabalho já existente.

        if ($rascunho !== null) {

            $acao = 'reimportar';

            $texto_botao = 'Reimportar dados atuais';

        }

        echo '<form method="post" action="' . htmlescape($url_revisao) . '">';

        echo '<input type="hidden" name="chamado_id" value="' . $chamado_id . '">';

        // Exige confirmação de substituição quando o técnico está reimportando um rascunho corrigido.

        if ($rascunho !== null) {

            echo '<input type="hidden" name="revisao" value="' . $rascunho['revisao'] . '">';

            echo '<label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmar_reimportacao" value="1" required> Confirmo substituir minhas correções pelos dados atuais da origem.</label>';

        }

        echo '<button class="btn btn-primary" name="acao" value="' . $acao . '">' . $texto_botao . '</button>';

        Html::closeForm();

    }

    /**
     * Exibe composições textuais sem atribuir disponibilidade ou segurança ainda não comprovadas.
     */

    private static function mostrarLogin(

        array $rascunho

    ): void {

        echo '<h3 class="contas-subtitulo">Conta de rede</h3>';

        // A renovação informa a conta existente e não sugere um novo nome para ela.

        if ($rascunho['tipo'] === 'renovacao') {

            self::mostrarCampo('conta_atendida', 'Login da conta atendida', $rascunho['conta_atendida'], 120);

            echo '<p class="form-hint">Informado pela solicitação; a conta ainda não foi identificada no AD. A renovação não sugere renomear a conta.</p>';

            return;

        }

        // Na criação, as sugestões são apenas composições textuais, ainda sem consulta ao AD.

        self::mostrarCampo(

            'login_pretendido',

            'Login pretendido para revisão',

            $rascunho['login_pretendido'],

            120

        );

        $nome_para_login = RevisorDaOperacao::obterNomeParaLogin($rascunho);

        $candidatos = PoliticaDeLogin::gerarCandidatos($nome_para_login);

        echo '<div class="contas-sugestoes"><p>Composições na ordem definida, <strong>sem consulta de disponibilidade</strong>:</p>';

        // Orienta inserir um login manual quando a política não consegue gerar candidatos adequados.

        if ($candidatos === []) {

            echo '<p class="text-warning">Não foi possível compor candidatos com a regra inicial. Revise o nome e o login manualmente.</p>';

        } else {

            // Apresenta as composições autorizadas sem afirmar que estão disponíveis no diretório.

            echo '<ol>';

            // Exibe os candidatos na mesma ordem determinada pela política de login.

            foreach ($candidatos as $candidato) {

                echo '<li><code>' . htmlescape($candidato) . '</code> - não verificado no AD.</li>';

            }

            echo '</ol>';

        }

        echo '<p class="contas-nota">A política linguística completa também permanece pendente. Nenhum destes nomes é apresentado como login disponível.</p></div>';

    }

    /**
     * Mantém HTML e escape dos campos em uma única implementação legível.
     */

    private static function mostrarCampo(

        string $campo,

        string $rotulo,

        string $valor,

        int $limite,

        bool $multilinha = false

    ): void {

        // O mesmo helper mostra um input simples ou uma área de texto com várias linhas.

        echo '<div class="contas-campo"><label class="form-label" for="'

            . $campo

            . '">'

            . htmlescape($rotulo)

            . '</label>';

        // Usa área de texto para os campos manuais que admitem múltiplas linhas.

        if ($multilinha) {

            echo '<textarea class="form-control" rows="3" id="'

                . $campo

                . '" name="'

                . $campo

                . '" maxlength="'

                . $limite

                . '">'

                . htmlescape($valor)

                . '</textarea>';

        } else {

            // Usa campo de uma linha para textos que não admitem quebras de linha.

            echo '<input class="form-control" id="'

                . $campo

                . '" name="'

                . $campo

                . '" maxlength="'

                . $limite

                . '" value="'

                . htmlescape($valor)

                . '">';

        }

        echo '</div>';

    }

    /**
     * Inclui identificadores de transporte, sempre conferidos contra o estado do servidor no POST.
     */

    private static function mostrarIdentificadores(

        array $rascunho

    ): void {

        // Campos ocultos transportam o ID e a revisão; não substituem a conferência no servidor.

        echo '<input type="hidden" name="chamado_id" value="' . $rascunho['chamado_id'] . '">';

        echo '<input type="hidden" name="revisao" value="' . $rascunho['revisao'] . '">';

    }

    /**
     * Mostra as lacunas atuais sem convertê-las em aprovações ou validações concluídas.
     */

    private static function mostrarPendencias(

        array $pendencias

    ): void {

        echo '<section class="contas-cartao contas-pendencias" aria-labelledby="contas-titulo-pendencias">';

        echo '<div class="contas-cabecalho"><h2 id="contas-titulo-pendencias">Pendências da revisão</h2><span class="contas-etiqueta">'

            . count($pendencias)

            . '</span></div>';

        echo '<div class="contas-corpo"><ul>';

        // Lista cada pendência ainda existente para orientar a revisão do técnico.

        foreach ($pendencias as $pendencia) {

            echo '<li>' . htmlescape($pendencia) . '</li>';

        }

        echo '</ul><p class="contas-nota mb-0">Salvar o rascunho não executa AD, não altera a origem e não fecha o chamado.</p></div></section>';

    }

}

