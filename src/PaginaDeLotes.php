<?php

namespace GlpiPlugin\Admanagement;

/** Usa os cartões e componentes nativos já adotados na interface do plugin. */
final class PaginaDeLotes {

    /** Mostra importação, revisão, confirmação e resultados sem JavaScript próprio. */
    public static function mostrar(?array $estado, array $historico, string $url, array $lotes = [], ?int $historico_id = null, string $novo_desafio = ''): void {
        echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>Importar lote</h2></div><div class="contas-corpo">';
        echo '<p>Envie XLSX com uma aba ou CSV UTF-8. Colunas: <strong>CPF, nome, objetivo</strong> e <strong>vínculo</strong> nas renovações. Objetivo: renovar ou bloquear. Até 500 linhas e 2 MiB.</p>';
        echo '<p class="contas-nota">A busca consulta os dois domínios. Mais de uma conta com o mesmo nome bloqueia a linha. Lotes são independentes de chamados.</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . htmlescape($url) . '"><div class="input-group"><input class="form-control" type="file" name="planilha" accept=".csv,.xlsx" required><button class="btn btn-primary" name="acao" value="importar">Importar para revisão</button></div>';
        echo \Html::hidden('novo_desafio', ['value' => $novo_desafio]);
        echo '<details class="mt-2"><summary>Novo atendimento para uma planilha já utilizada</summary><label class="form-check mt-2"><input type="checkbox" class="form-check-input" name="novo_atendimento" value="1"> Iniciar outra solicitação independente. Sem marcar, o mesmo conteúdo retoma o lote original.</label><p class="contas-nota">Use apenas para uma necessidade posterior. Contas e regras serão consultadas novamente; importar não executa AD.</p></details>';
        \Html::closeForm();
        echo '</div></section>';
        // Metadados mínimos permitem voltar aos resultados sem guardar a planilha original.
        if ($lotes !== []) {
            echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Meus lotes nesta entidade</h2></div><div class="contas-corpo d-flex flex-wrap gap-2">';
            // Cada link será novamente autorizado pelo modelo na entrada PHP.
            foreach ($lotes as $lote) {
                echo '<a class="btn btn-outline-secondary" href="' . htmlescape($url . '?historico=' . $lote['id']) . '">Lote #' . (int) $lote['id'] . ' · ' . (int) $lote['total'] . ' linhas</a>';
            }
            echo '</div></section>';
        }
        // O histórico persistente pode ser consultado sem o arquivo original nem sessão com dados pessoais.
        if ($historico !== []) {
            echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Resultados registrados</h2></div><div class="contas-corpo table-responsive"><table class="table"><thead><tr><th>Execução</th><th>Operação</th><th>Situação</th><th>Etapa</th></tr></thead><tbody>';
            // O histórico utiliza IDs e estados, não um inventário durável de CPF/nome.
            foreach ($historico as $registro) {
                echo '<tr><td>#' . (int) $registro['id'] . '</td><td>' . htmlescape($registro['tipo']) . '</td><td>' . htmlescape($registro['estado']) . '</td><td>' . htmlescape($registro['etapa']);
                // A ação de reconciliação apenas lê o efeito; não representa uma nova tentativa.
                if ($registro['estado'] !== 'SUCESSO_COMPROVADO') {
                    echo '<form method="post" action="' . htmlescape($url) . '">';
                    echo \Html::hidden('execucao_id', ['value' => $registro['id']]) . \Html::hidden('lote_id', ['value' => $registro['lotes_id']]);
                    echo '<button class="btn btn-sm btn-outline-primary mt-1" name="acao" value="reconciliar">Conferir resultado por leitura</button>';
                    \Html::closeForm();
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div></section>';
        }
        // A página inicial não precisa apresentar uma tabela vazia como lote de demonstração.
        if ($estado === null) {
            return;
        }
        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><div><h2>Revisão do lote #' . $estado['id'] . '</h2><p>' . count($estado['linhas']) . ' linhas · revisão ' . $estado['revisao'] . '</p></div></div><div class="contas-corpo">';
        echo '<form method="post" action="' . htmlescape($url) . '"><button class="btn btn-outline-primary mb-3" name="acao" value="consultar">Consultar os dois domínios</button> <button class="btn btn-outline-secondary mb-3" name="acao" value="exportar">Exportar revisão</button>';
        echo \Html::hidden('revisao', ['value' => $estado['revisao']]);
        echo '<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Selecionar</th><th>Pessoa</th><th>Objetivo</th><th>Vínculo</th><th>Conta / domínio</th><th>Proposta</th><th>Situação</th></tr></thead><tbody>';
        // A tabela preserva os erros da planilha e os resultados específicos de cada transação.
        foreach ($estado['linhas'] as $indice => $linha) {
            $previa = $linha['previa'] ?? [];
            $resultado = $linha['resultado'] ?? [];
            $situacao = $resultado['estado'] ?? $previa['estado'] ?? ($linha['erros'] === [] ? 'AGUARDANDO_CONSULTA' : 'PLANILHA_INVALIDA');
            $pronta = $linha['erros'] === [] && $situacao === 'PRONTO' && ($previa['expira'] ?? 0) > time();
            echo '<tr><td><input class="form-check-input" type="checkbox" name="linhas[]" value="' . $indice . '"' . ($pronta ? '' : ' disabled') . '></td>';
            echo '<td><strong>' . htmlescape($linha['nome']) . '</strong><br><small>Linha ' . $linha['linha'] . ' · CPF ***.***.***-' . htmlescape(substr($linha['cpf'], -2)) . '</small></td>';
            echo '<td>' . htmlescape($linha['tipo']) . '</td><td>' . htmlescape($linha['vinculo']) . '</td>';
            echo '<td>' . htmlescape(($previa['conta']['login'] ?? '') . ' ' . ($previa['dominio_nome'] ?? '')) . '</td>';
            echo '<td>' . htmlescape($previa['validade']['data'] ?? ($pronta ? 'Desativar conta' : '')) . '</td><td><strong>' . htmlescape($situacao) . '</strong>';
            $detalhes = array_merge($linha['erros'], [$resultado['detalhe'] ?? $previa['detalhe'] ?? '']);
            // CPF vazio gera um efeito adicional que precisa ficar visível antes da confirmação.
            if ($pronta && $linha['tipo'] === 'renovacao' && trim($previa['conta']['cpf']) === '') {
                $detalhes[] = 'Preencher e confirmar CPF vazio antes de renovar. Método: ' . $previa['metodo'] . '.';
                // Nome único não deve aparecer como CPF já conferido no AD.
                if ($previa['metodo'] === 'NOME_UNICO') {
                    $detalhes[] = 'O CPF informado na planilha não foi conferido no AD.';
                }
            }
            echo '<br><small>' . htmlescape(implode(' ', $detalhes)) . '</small></td></tr>';
        }
        echo '</tbody></table></div><label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirmar_lote" value="1"> Conferi as linhas selecionadas e autorizo os efeitos apresentados.</label>';
        echo '<button class="btn btn-primary" name="acao" value="confirmar">Confirmar linhas selecionadas</button>';
        \Html::closeForm();
        $confirmacao = $estado['confirmacao'] ?? [];
        // Cada continuação é um POST protegido; recarregar a página não executa a próxima conta.
        if (($confirmacao['fila'] ?? []) !== [] && ($confirmacao['expira'] ?? 0) > time()) {
            echo '<form method="post" action="' . htmlescape($url) . '" class="mt-3 alert alert-warning">';
            echo \Html::hidden('desafio', ['value' => $confirmacao['desafio']]);
            echo '<p>' . count($confirmacao['fila']) . ' operações confirmadas aguardando processamento. Cada clique executa uma linha, com nova verificação no AD.</p>';
            echo '<button class="btn btn-danger" name="acao" value="executar">Executar próxima linha confirmada</button>';
            \Html::closeForm();
        }
        echo '</div></section>';
    }
}
