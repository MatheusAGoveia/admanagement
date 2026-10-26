<?php

namespace GlpiPlugin\Admanagement;

/**
 * Separa candidatos, disponibilidade e duplicidades, com cobertura explícita de todos os domínios.
 */

final class VerificadorDoDiretorio {

    private \Closure $fabrica;

    /**
     * Usa LDAP real no produto e aceita adaptador de contrato nos testes isolados.
     */

    public function __construct(

        ?callable $fabrica = null

    ) {

        $this->fabrica = $fabrica === null ?

            /**
             * Cria o leitor LDAP real de um domínio quando não foi fornecido um adaptador exclusivo de
             * teste.
             */

            static fn(

                array $dominio

            ): LeitorLdap => new LeitorLdap($dominio)

            : \Closure::fromCallable($fabrica);

    }

    /**
     * Não transforma erro de um domínio obrigatório em um login disponível.
     */

    public function consultarDisponibilidade(

        string $login,

        array $dominios,

        int $selecionado

    ): array {

        // Evita consulta de um login que já falha na verificação inicial de formato ou proteção linguística.

        if (!PoliticaDeLogin::passaNaVerificacaoInicial($login)) {

            return [

                'estado' => 'inadequado',

                'cobertura' => [],

                'consultado_em' => gmdate(DATE_ATOM),

                'expira' => time() + 300

            ];

        }

        $cobertura = [];

        $ocupado = false;

        // Consulta disponibilidade na união do domínio selecionado com todos os obrigatórios.

        foreach ($dominios as $id => $dominio) {

            // Ignora domínios opcionais que não são o alvo selecionado, preservando todos os obrigatórios.

            if (empty($dominio['obrigatorio']) && (int) $id !== $selecionado) {

                continue;

            }

            try {

                $ocupacao = ($this->fabrica)($dominio)->loginOcupado($login);

                $cobertura[$id] = $ocupacao ? 'ocupado' : 'disponivel';

                $ocupado = $ocupado || $ocupacao;

            } catch (\Throwable $erro) {

                // Marca falha de um domínio como cobertura incompleta, nunca como login disponível.

                $cobertura[$id] = 'incompleto';

            }

        }

        $estado = 'nao_consultado';

        // Conclui o estado agregado somente quando existe cobertura do domínio alvo.

        if ($cobertura !== [] && isset($cobertura[$selecionado])) {

            $estado = $ocupado ? 'ocupado' : (in_array('incompleto', $cobertura, true) ? 'incompleto' : 'disponivel');

        }

        // Impede declarar disponibilidade sem uma lista configurada de domínios obrigatórios.

        if (

            $estado === 'disponivel'

            && !array_filter(

                $dominios,

                /**
                 * Identifica as conexões marcadas como obrigatórias para conferir a existência
                 * dessa lista.
                 */

                static fn(

                    array $d

                ): bool => !empty($d['obrigatorio'])

            )

        ) {

            $estado = 'incompleto';

        }

        return [

            'estado' => $estado,

            'cobertura' => $cobertura,

            'consultado_em' => gmdate(DATE_ATOM),

            'expira' => time() + 300

        ];

    }

    /**
     * Compara CPF sem formatação e demais critérios sem persistir os identificadores ou respostas
     * LDAP.
     */

    public function consultarDuplicidades(

        array $pessoa,

        array $dominios,

        int $selecionado

    ): array {

        $cobertura = [];

        $conflitos = [

            'nome' => 0,

            'login' => 0,

            'matricula' => 0,

            'cpf' => 0

        ];

        // Verifica possíveis duplicidades no domínio alvo e em todos os domínios obrigatórios.

        foreach ($dominios as $id => $dominio) {

            // Deixa fora apenas conexões opcionais que não fazem parte do destino selecionado.

            if (empty($dominio['obrigatorio']) && (int) $id !== $selecionado) {

                continue;

            }

            try {

                // Impede conclusão de identidade quando CPF, nome, matrícula ou login estão incompletos.

                if (

                    strlen(self::normalizar('cpf', $pessoa['cpf'] ?? '')) !== 11

                    || trim($pessoa['nome'] ?? '') === ''

                    || trim($pessoa['matricula'] ?? '') === ''

                    || trim($pessoa['login'] ?? '') === ''

                ) {

                    throw new \DomainException('Identificadores incompletos.');

                }

                // Compara cada conta consultada transitoriamente, sem guardar o resultado LDAP completo.

                foreach (($this->fabrica)($dominio)->listarIdentidades() as $conta) {

                    // Avalia separadamente nome, login, matrícula e CPF como critérios de possível duplicidade.

                    foreach (array_keys($conflitos) as $campo) {

                        $esperado = self::normalizar($campo, (string) ($pessoa[$campo] ?? ''));

                        // Conta coincidências apenas entre valores normalizados não vazios do mesmo critério.

                        if (

                            $esperado !== ''

                            && $esperado === self::normalizar($campo, (string) ($conta[$campo] ?? ''))

                        ) {

                            $conflitos[$campo]++;

                        }

                    }

                }

                $cobertura[$id] = 'completo';

            } catch (\Throwable $erro) {

                // Preserva a falha de um domínio como verificação incompleta de duplicidades.

                $cobertura[$id] = 'incompleto';

            }

        }

        $estado = $cobertura === [] || !isset($cobertura[$selecionado]) ? 'nao_consultado' : (

            in_array('incompleto', $cobertura, true) ? 'incompleto' : (array_sum($conflitos) > 0 ? 'possivel_duplicidade' : 'sem_conflitos')

        );

        // Impede declarar ausência de conflito sem uma lista configurada de domínios obrigatórios.

        if (

            $estado === 'sem_conflitos'

            && !array_filter(

                $dominios,

                /**
                 * Seleciona os domínios obrigatórios cuja existência é necessária para concluir a
                 * cobertura.
                 */

                static fn(

                    array $d

                ): bool => !empty($d['obrigatorio'])

            )

        ) {

            $estado = 'incompleto';

        }

        return [

            'estado' => $estado,

            'cobertura' => $cobertura,

            'contagens' => $conflitos,

            'consultado_em' => gmdate(DATE_ATOM),

            'expira' => time() + 300

        ];

    }

    /**
     * Normaliza a representação para comparação, sem usar hash simples como anonimização.
     */

    private static function normalizar(

        string $campo,

        string $valor

    ): string {

        return $campo === 'cpf' ? (preg_replace('/\D/', '', $valor) ?? '') : mb_strtolower(trim($valor), 'UTF-8');

    }

}

