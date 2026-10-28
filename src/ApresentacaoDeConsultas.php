<?php

namespace GlpiPlugin\Admanagement;

/**
 * Explica disponibilidade e cobertura sem confundir ausência de resposta com ausência de conta.
 */

final class ApresentacaoDeConsultas {

    /**
     * Mantém os nomes de estados próprios da consulta ao diretório em um único catálogo.
     */

    private const ROTULOS = [

        'disponivel' => 'disponível nos domínios consultados',

        'ocupado' => 'ocupado',

        'incompleto' => 'consulta incompleta',

        'nao_consultado' => 'não consultado',

        'inadequado' => 'precisa de revisão',

        'completo' => 'consulta completa',

        'sem_conflitos' => 'nenhum conflito encontrado',

        'possivel_duplicidade' => 'possível duplicidade'

    ];

    /**
     * Mostra as evidências registradas e as orientações correspondentes ao tipo de operação.
     */

    public static function mostrar(

        array $rascunho

    ): void {

        $dominios = ConfiguracaoDoDiretorio::consultar();

        $agora = time();

        // Cada login exibe apenas a evidência registrada, inclusive sua cobertura parcial.

        foreach ($rascunho['consultas_logins'] ?? [] as $login => $consulta) {

            echo '<p><code>' . htmlescape($login) . '</code>: '

                . htmlescape(self::descreverEstado($consulta, $agora))

                . '. Consultado em ' . htmlescape($consulta['consultado_em']) . '.</p>';

            self::mostrarCobertura($consulta['cobertura'], $dominios);

        }

        // Duplicidades só são apresentadas quando uma consulta explícita produziu essa evidência.

        if (isset($rascunho['duplicidades'])) {

            $consulta = $rascunho['duplicidades'];

            echo '<p>Duplicidades: ' . htmlescape(self::descreverEstado($consulta, $agora))

                . '. A ausência de conflito não comprova a identidade da conta.</p>';

            self::mostrarCobertura($consulta['cobertura'], $dominios);

        }

        // A criação exige alguma disponibilidade comprovada e ainda válida para orientar a escolha.

        if ($rascunho['tipo'] === 'criacao' && isset($rascunho['consultas_logins'])) {

            $livres = array_filter(

                $rascunho['consultas_logins'],

                /**

                 * Mantém somente logins disponíveis cuja evidência ainda não expirou.

                 */

                static fn(

                    array $consulta

                ): bool => $consulta['estado'] === 'disponivel' && $consulta['expira'] > $agora

            );

            // Sem evidência válida, a tela orienta nova consulta e não declara um login seguro.

            if ($livres === []) {

                echo '<p>Nenhuma opção segura comprovada. Revise as consultas incompletas ou informe um login manual e consulte-o antes de continuar.</p>';

            }

        }

        // A presença da conta na renovação não comprova identidade nem autoriza alterações.

        if ($rascunho['tipo'] === 'renovacao') {

            echo '<p>Encontrar um login ocupado não comprova a identidade da pessoa nem autoriza mover a conta ou alterar seus atributos.</p>';

        }

    }

    /**
     * Prioriza a expiração da evidência sobre o resultado que foi obtido no passado.
     */

    private static function descreverEstado(

        array $consulta,

        int $agora

    ): string {

        // Um resultado expirado precisa ser consultado novamente antes de orientar a revisão.

        if (($consulta['expira'] ?? 0) <= $agora) {

            return 'consulta expirada';

        }

        return self::ROTULOS[$consulta['estado']] ?? 'precisa de revisão';

    }

    /**
     * Reúne a apresentação de cobertura para logins e duplicidades sem esconder falhas de domínio.
     */

    private static function mostrarCobertura(

        array $cobertura,

        array $dominios

    ): void {

        echo '<ul>';

        // Domínios removidos continuam identificados como tal na evidência já registrada.

        foreach ($cobertura as $id => $estado) {

            echo '<li>'

                . htmlescape($dominios[$id]['nome'] ?? 'Domínio removido')

                . ': '

                . htmlescape(self::ROTULOS[$estado] ?? 'incompleto')

                . '</li>';

        }

        echo '</ul>';

    }

}