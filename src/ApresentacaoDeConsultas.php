<?php

namespace GlpiPlugin\Admanagement;

/**
 * Explica disponibilidade e cobertura sem confundir ausência de resposta com ausência de conta.
 */

final class ApresentacaoDeConsultas {

    /**
     * Usa os resultados reais existentes no trabalho; não produz resultados de demonstração.
     */

    public static function mostrar( array $rascunho ): void {

        $rotulos = [

            'disponivel' => 'disponível nos domínios consultados',

            'ocupado' => 'ocupado',

            'incompleto' => 'consulta incompleta',

            'nao_consultado' => 'não consultado',

            'inadequado' => 'precisa de revisão',

            'completo' => 'consulta completa',

            'sem_conflitos' => 'nenhum conflito encontrado',

            'possivel_duplicidade' => 'possível duplicidade'

        ];

        $dominios = ConfiguracaoDoDiretorio::consultar();

        // Apresenta o estado e a data de cada login efetivamente consultado.

        foreach ($rascunho['consultas_logins'] ?? [] as $login => $consulta) {

            echo '<p><code>' . htmlescape($login) . '</code>: ' . htmlescape(

                ($consulta['expira'] ?? 0) <= time() ? 'consulta expirada' : ($rotulos[$consulta['estado']] ?? 'precisa de revisão')

            ) . '. Consultado em ' . htmlescape($consulta['consultado_em']) . '.</p>';

            echo '<ul>';

            // Detalha a cobertura por domínio para não esconder falhas parciais da consulta.

            foreach ($consulta['cobertura'] as $id => $estado) {

                echo '<li>'

                    . htmlescape($dominios[$id]['nome'] ?? 'Domínio removido')

                    . ': '

                    . htmlescape($rotulos[$estado] ?? 'incompleto')

                    . '</li>';

            }

            echo '</ul>';

        }

        // Mostra o resultado de duplicidades somente quando essa verificação foi registrada no rascunho.

        if (isset($rascunho['duplicidades'])) {

            $consulta = $rascunho['duplicidades'];

            echo '<p>Duplicidades: ' . htmlescape(

                ($consulta['expira'] ?? 0) <= time() ? 'consulta expirada' : ($rotulos[$consulta['estado']] ?? 'precisa de revisão')

            ) . '. A ausência de conflito não comprova a identidade da conta.</p>';

            // Expõe a cobertura de duplicidades de cada domínio consultado.

            foreach ($consulta['cobertura'] as $id => $estado) {

                echo '<p>'

                    . htmlescape($dominios[$id]['nome'] ?? 'Domínio removido')

                    . ': '

                    . htmlescape($rotulos[$estado] ?? 'incompleto')

                    . '.</p>';

            }

        }

        // Avalia opções seguras apenas para criação e depois de existir uma consulta de logins.

        if ($rascunho['tipo'] === 'criacao' && isset($rascunho['consultas_logins'])) {

            $livres = array_filter(

                $rascunho['consultas_logins'],

                /**
                 * Mantém somente logins disponíveis cuja evidência de consulta ainda não expirou.
                 */

                static fn(

                    array $c

                ): bool => $c['estado'] === 'disponivel' && $c['expira'] > time()

            );

            // Orienta revisão ou entrada manual quando nenhuma disponibilidade segura foi comprovada.

            if ($livres === []) {

                echo '<p>Nenhuma opção segura comprovada. Revise as consultas incompletas ou informe um login manual e consulte-o antes de continuar.</p>';

            }

        }

        // Explica que um login ocupado na renovação não prova a identidade nem autoriza alterações.

        if ($rascunho['tipo'] === 'renovacao') {

            echo '<p>Encontrar um login ocupado não comprova a identidade da pessoa nem autoriza mover a conta ou alterar seus atributos.</p>';

        }

    }

}

