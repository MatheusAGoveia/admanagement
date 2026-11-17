<?php

namespace GlpiPlugin\Admanagement;

/** Mostra a confirmação dos efeitos reais separada do salvamento da revisão local. */
final class ApresentacaoDaOperacao {

    /** Apresenta renovação/bloqueio com POST explícito e desafio guardado na sessão. */
    public static function mostrar(array $rascunho, string $url): void {
        // Criação conserva o fluxo planejado e não ganha um executor incompleto nesta ampliação.
        if ($rascunho['tipo'] === 'criacao') {
            return;
        }
        $previa = $rascunho['previa_ad'] ?? [];
        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Operação no Active Directory</h2></div><div class="contas-corpo">';
        // O resultado de execução não é substituído pelo aviso genérico de salvar revisão.
        if (isset($rascunho['resultado_ad'])) {
            echo '<p class="alert alert-info">' . htmlescape($rascunho['resultado_ad']['estado'] . ' · ' . $rascunho['resultado_ad']['detalhe']) . '</p>';
        }
        echo '<form method="post" action="' . htmlescape($url) . '">';
        echo \Html::hidden('chamado_id', ['value' => $rascunho['chamado_id']]) . \Html::hidden('revisao', ['value' => $rascunho['revisao']]);
        echo '<p class="contas-nota">Salve os dados acima. Depois consulte os dois domínios para preparar a operação. Mais de uma conta com o mesmo nome impede a transação.</p>';
        // Conferir uma tentativa incerta não encaminha outra escrita no diretório.
        if (isset($rascunho['resultado_ad']['execucao_id']) && $rascunho['resultado_ad']['estado'] !== 'SUCESSO_COMPROVADO') {
            echo '<button class="btn btn-outline-secondary me-2" name="acao" value="reconciliar_ad">Conferir resultado por leitura</button>';
        }
        echo '<button class="btn btn-outline-primary" name="acao" value="preparar_ad">Consultar e preparar ' . ($rascunho['tipo'] === 'renovacao' ? 'renovação' : 'bloqueio') . '</button>';
        // Prévia expirada não deve exibir botão de envio de efeitos.
        if (($previa['estado'] ?? '') === 'PRONTO' && ($previa['expira'] ?? 0) > time()) {
            echo '<hr><dl class="row"><dt class="col-sm-4">Conta e domínio</dt><dd class="col-sm-8">' . htmlescape($previa['conta']['login'] . ' · ' . $previa['dominio_nome']) . '</dd>';
            echo '<dt class="col-sm-4">Identificação</dt><dd class="col-sm-8">' . htmlescape($previa['metodo']) . '</dd>';
            echo '<dt class="col-sm-4">Efeito proposto</dt><dd class="col-sm-8">' . htmlescape($rascunho['tipo'] === 'renovacao' ? 'Nova validade: ' . $previa['validade']['data'] : 'Desativar a conta, preservando as demais propriedades.') . '</dd></dl>';
            // Preenchimento de CPF é mostrado como efeito adicional, nunca escondido na confirmação.
            if ($rascunho['tipo'] === 'renovacao' && trim($previa['conta']['cpf']) === '') {
                echo '<p class="alert alert-warning">O atributo CPF está vazio. Ele será preenchido e confirmado antes de renovar a validade.' . ($previa['metodo'] === 'NOME_UNICO' ? ' Identificação por nome completo único: o CPF informado não foi conferido no AD.' : '') . '</p>';
            }
            echo \Html::hidden('desafio_ad', ['value' => $previa['desafio']]);
            echo '<label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirmar_ad" value="1"> Conferi a conta e autorizo os efeitos apresentados.</label>';
            echo '<button class="btn btn-danger" name="acao" value="executar_ad">' . ($rascunho['tipo'] === 'renovacao' ? 'Renovar validade' : 'Desativar conta') . '</button>';
        } elseif ($previa !== []) {
            // Exibe o impedimento específico e não disponibiliza uma opção para ignorá-lo.
            echo '<p class="alert alert-warning mt-3">' . htmlescape($previa['detalhe'] ?? 'Prévia expirada. Consulte novamente.') . '</p>';
        }
        \Html::closeForm();
        echo '<p class="contas-nota mt-3">A finalização do chamado permanece separada; solucionar ou fechar ainda depende da definição registrada no projeto.</p></div></section>';
    }
}
