<?php

namespace GlpiPlugin\Admanagement;

use InvalidArgumentException;

/**
 * Aplica correções no rascunho local e informa o que ainda precisa de revisão.
 */

final class RevisorDaOperacao {

    /**
     * Altera somente os campos de trabalho permitidos, mantendo vínculo e tipo aprovados intactos.
     */

    public static function aplicar(

        array $rascunho,

        array $entrada

    ): array {

        // Cada nome de campo está associado ao tamanho máximo aceito em bytes.

        $campos = [

            'nome_civil' => 240,

            'nome_social' => 240,

            'matricula' => 80,

            'grupos' => 2000,

            'scripts' => 500,

            'pastas' => 2000,

            'validade_proposta' => 120,

        ];

        // Campos que não fazem parte dessa lista não são copiados para o rascunho.

        foreach ($campos as $campo => $limite) {

            // Altera um campo permitido somente quando a ação enviou explicitamente seu novo valor.

            if (array_key_exists($campo, $entrada)) {

                $multilinha = false;

                // Permite múltiplas linhas apenas nos campos manuais de grupos, Scripts e pastas.

                if ($campo === 'grupos' || $campo === 'scripts' || $campo === 'pastas') {

                    $multilinha = true;

                }

                $rascunho[$campo] = EntradaDaFerramenta::lerTexto($entrada[$campo], $limite, $multilinha);

            }

        }

        // Só permite escolher outro uso de nome quando a origem não o definiu.

        if (

            $rascunho['uso_nome_na_origem'] === null

            && array_key_exists('usar_nome_social', $entrada)

        ) {

            // Os valores vêm do select: '1' para social, '0' para civil e '' para pendente.

            if ($entrada['usar_nome_social'] === '1') {

                $rascunho['usar_nome_social'] = true;

            } elseif ($entrada['usar_nome_social'] === '0') {

                // Registra a escolha de nome civil quando a origem não fixou essa indicação.

                $rascunho['usar_nome_social'] = false;

            } elseif ($entrada['usar_nome_social'] === '') {

                // Mantém a indicação de nome pendente quando o técnico ainda não decidiu seu uso.

                $rascunho['usar_nome_social'] = null;

            } else {

                // Recusa valores diferentes das opções textuais previstas para o uso de nome.

                throw new InvalidArgumentException('Informe como o nome deve ser utilizado nesta revisão.');

            }

        }

        // Criação trabalha com um login novo; renovação trabalha com a conta já informada.

        $campo_login = 'conta_atendida';

        // Usa o login pretendido apenas na criação, preservando a conta atendida da renovação.

        if ($rascunho['tipo'] === 'criacao') {

            $campo_login = 'login_pretendido';

        }

        // Atualiza a identificação de conta permitida somente quando ela foi enviada na revisão.

        if (array_key_exists($campo_login, $entrada)) {

            $rascunho[$campo_login] = EntradaDaFerramenta::lerTexto($entrada[$campo_login], 120);

        }

        // A escolha de destino exige domínio cadastrado e OU efetivamente consultada.

        $rascunho = DestinoDaOperacao::aplicar($rascunho, $entrada);

        unset(

            $rascunho['resumo'],

            $rascunho['confirmacao'],

            $rascunho['consultas_logins'],

            $rascunho['duplicidades']

        );

        // Toda alteração relevante invalida a confirmação anterior.

        // A revisão recebe um novo número e a data em UTC, sem representar execução no AD.

        $rascunho['revisao']++;

        $rascunho['revisado_em'] = gmdate('Y-m-d\TH:i:s\Z');

        return $rascunho;

    }

    /**
     * Determina o nome para composição sem trocar nome social ausente pelo civil silenciosamente.
     */

    public static function obterNomeParaLogin(

        array $rascunho

    ): string {

        // Usa o nome social indicado sem substituir sua ausência pelo nome civil.

        if ($rascunho['usar_nome_social'] === true) {

            return $rascunho['nome_social'];

        }

        // Usa o nome civil quando essa foi a indicação definida para a composição.

        if ($rascunho['usar_nome_social'] === false) {

            return $rascunho['nome_civil'];

        }

        // Sem uma indicação definida, o nome continua pendente para a composição.

        return '';

    }

    /**
     * Expõe pendências reais deste recorte, sem declarar aprovação ou prontidão para executar AD.
     */

    public static function listarPendencias(

        array $rascunho

    ): array {

        $pendencias = $rascunho['avisos_estrutura'];

        $nome_para_login = self::obterNomeParaLogin($rascunho);

        // As pendências da origem são mantidas junto com as encontradas na revisão.

        if ($rascunho['usar_nome_social'] === null) {

            $pendencias[] = 'O uso de nome social não foi determinado pela origem. Revise essa indicação.';

        }

        // Marca como pendência a ausência do nome que deveria compor o login.

        if (trim($nome_para_login) === '') {

            $pendencias[] = 'O nome utilizado na composição de login precisa ser preenchido.';

        }

        // As verificações de login variam conforme o tipo da solicitação.

        if ($rascunho['tipo'] === 'criacao') {

            $candidatos = PoliticaDeLogin::gerarCandidatos($nome_para_login);

            // Exige revisão manual quando a política não produz composições automáticas seguras.

            if ($candidatos === []) {

                $pendencias[] = 'Não foi possível compor candidatos com a regra inicial. É necessária revisão manual.';

            }

            // Marca o login de criação ausente ou inadequado como impedimento da revisão.

            if (

                $rascunho['login_pretendido'] === ''

                || !PoliticaDeLogin::passaNaVerificacaoInicial($rascunho['login_pretendido'])

            ) {

                $pendencias[] = 'O login pretendido precisa de revisão. A política linguística completa continua pendente.';

            }

        } elseif ($rascunho['conta_atendida'] === '') {

            // Exige informar a conta atendida quando uma renovação ainda não tem alvo declarado.

            $pendencias[] = 'Informe a conta atendida na renovação; sua identidade ainda precisa ser conferida no AD.';

        }

        // Estas limitações continuam presentes mesmo quando os campos locais estão preenchidos.

        if (($rascunho['dominio'] ?? '') === '' || ($rascunho['ou'] ?? '') === '') {

            $pendencias[] = 'Domínio/OU ainda não foram selecionados em um catálogo real consultado.';

        }

        // Bloqueia criação sem disponibilidade comprovada e ainda vigente para o login pretendido.

        if (

            $rascunho['tipo'] === 'criacao'

            && (

                (

                    $rascunho['consultas_logins'][$rascunho['login_pretendido'] ?? '']['estado'] ?? ''

                ) !== 'disponivel'

                || (

                    $rascunho['consultas_logins'][$rascunho['login_pretendido'] ?? '']['expira'] ?? 0

                ) <= time()

            )

        ) {

            $pendencias[] = 'A disponibilidade do login não foi comprovada em todos os domínios exigidos.';

        }

        // Bloqueia criação quando duplicidades apresentam conflito, cobertura incompleta ou evidência expirada.

        if (

            $rascunho['tipo'] === 'criacao'

            && (

                ($rascunho['duplicidades']['estado'] ?? '') !== 'sem_conflitos'

                || ($rascunho['duplicidades']['expira'] ?? 0) <= time()

            )

        ) {

            $pendencias[] = 'Duplicidades entre domínios e identidade da conta permanecem pendentes ou apresentam conflito.';

        }

        $pendencias[] = 'A identidade definitiva da renovação e a matriz de atributos/validade ainda exigem homologação.';

        $pendencias[] = 'Efeitos de grupos, Scripts e atributos de criação/renovação ainda precisam das definições registradas no planejamento.';

        // Evita mostrar o mesmo aviso mais de uma vez.

        $pendencias = array_unique($pendencias);

        return array_values($pendencias);

    }

}

