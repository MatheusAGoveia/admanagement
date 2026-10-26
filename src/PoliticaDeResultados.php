<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Mantém AD e fechamento como resultados independentes e fixa o alvo de cada tentativa.
 */

final class PoliticaDeResultados {

    /**
     * Determina a única etapa segura; timeout ou posse perdida nunca comprovam ausência de efeito.
     */

    public static function proximaEtapa(

        array $operacao,

        int $chamado

    ): string {

        // Impede redirecionar a recuperação para um chamado diferente do alvo fixado na operação.

        if (($operacao['chamado_fixo'] ?? null) !== $chamado) {

            throw new DomainException('A tentativa pertence a outro chamado; a finalização não pode ser redirecionada.');

        }

        $ad = $operacao['resultado_ad'] ?? 'DESCONHECIDO';

        // Após sucesso comprovado no AD, permite somente verificar ou concluir a finalização do chamado.

        if ($ad === 'SUCESSO_COMPROVADO') {

            return ($operacao['resultado_glpi'] ?? '') === 'TEXTO_E_FECHAMENTO_VERIFICADOS' ? 'CONCLUIDA' : 'SOMENTE_FINALIZAR_CHAMADO';

        }

        // Exige reconciliação quando execução, timeout, parcialidade ou perda de posse deixam o efeito incerto.

        if (

            in_array($ad, [

                'EM_ANDAMENTO',

                'DESCONHECIDO',

                'PARCIAL',

                'POSSE_PERDIDA'

            ], true)

        ) {

            return 'RECONCILIAR_SEM_REPETIR_AD';

        }

        return 'NOVA_REVISAO_E_ELEGIBILIDADE';

    }

    /**
     * Distingue uma decisão revisada de um executor ainda não homologado, sem opção para ativá-lo
     * por POST.
     */

    public static function exigirExecucaoReal(

        array $pendencias

    ): never {

        // Recusa qualquer execução enquanto houver pendências bloqueadoras na revisão.

        if ($pendencias !== []) {

            throw new DomainException('Há pendências bloqueadoras. Nenhuma execução foi autorizada.');

        }

        throw new DomainException('Execução real indisponível: matriz de efeitos e adaptador de escrita ainda não homologados.');

    }

}

