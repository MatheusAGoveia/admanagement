<?php

namespace GlpiPlugin\Admanagement;

/**

 * Combina direitos do perfil ativo com vínculos explícitos de setor e entidade.

 */

final class PoliticaDeVisibilidade {

    /**

     * Preserva supervisão setorial, atribuição pessoal e administração nativa global.

     */

    public static function resolver(array $contexto, array $configuracao): array {

        $negado = ['papel' => 'negado', 'entidades_ids' => [], 'vinculos' => [], 'regras_ids' => []];

        // Um perfil incompleto não recebe papel, mesmo com um indicador administrativo.

        if (!self::contextoValido($contexto)) {

            return $negado;

        }

        $entidades = $contexto['entidades_ativas_ids'];

        // O adaptador fornece esta capacidade exclusivamente a partir do perfil nativo ativo.

        if (($contexto['super_administrador'] ?? false) === true) {

            return ['papel' => 'super_administrador', 'entidades_ids' => $entidades, 'vinculos' => [], 'regras_ids' => []];

        }

        $direitos = $contexto['direitos_perfil'] ?? [];

        // A supervisão prevalece e nunca recua para atribuições pessoais fora dos setores.

        if (($direitos['administrar_setores'] ?? false) === true) {

            $vinculos = self::resolverSetores($contexto, $configuracao);

            // Um supervisor sem mapa válido permanece bloqueado.

            if ($vinculos === []) {

                return $negado;

            }

            return [

                'papel' => 'administrador_setor',

                'entidades_ids' => array_values(array_unique(array_merge(...array_column($vinculos, 'entidades_ids')))),

                'vinculos' => $vinculos,

                'regras_ids' => array_values(array_unique(array_column($vinculos, 'regra_id'))),

            ];

        }

        // O direito READ é suficiente para a fila pessoal, sem depender de grupos ou mapas.

        if (($direitos['operar'] ?? false) === true) {

            return ['papel' => 'tecnico', 'entidades_ids' => $entidades, 'vinculos' => [], 'regras_ids' => []];

        }

        return $negado;

    }

    /**

     * Relaciona o perfil ativo aos setores sem cruzar grupos e entidades de vínculos diferentes.

     */

    private static function resolverSetores(array $contexto, array $configuracao): array {

        // Documentos antigos nunca ativam concessões automaticamente.

        if (($configuracao['versao'] ?? null) !== 2) {

            return [];

        }

        $setores = array_column($configuracao['setores'] ?? [], null, 'id');

        $vinculos = [];

        // Somente vínculos ativos do perfil selecionado participam da supervisão.

        foreach ($configuracao['regras'] ?? [] as $regra) {

            // Campos antigos de usuário ou grupo são rejeitados mesmo em chamadas diretas.

            if (($regra['ativa'] ?? false) !== true || ($regra['origem'] ?? null) !== 'perfil' || ($regra['alvo_id'] ?? null) !== $contexto['perfil_id']) {

                continue;

            }

            // Cada setor mantém o próprio par de grupos responsáveis e entidades.

            foreach ($regra['setores_ids'] as $setor_id) {

                $setor = $setores[$setor_id] ?? null;

                // Referências ausentes não significam acesso a todos os setores.

                if ($setor === null) {

                    continue;

                }

                $entidades = array_values(array_intersect($contexto['entidades_ativas_ids'], $regra['entidades_ids'], $setor['entidades_ids']));

                // Uma entidade fora do mapa ou da seleção nativa não entra na consulta.

                if ($entidades !== []) {

                    $vinculos[] = ['setor_id' => $setor_id, 'nome' => $setor['nome'], 'grupos_ids' => $setor['grupos_ids'], 'entidades_ids' => $entidades, 'regra_id' => $regra['id']];

                }

            }

        }

        return $vinculos;

    }

    /**

     * Recusa identidade incompleta e escopo nativo vazio antes de autorizar consultas.

     */

    public static function contextoValido(array $contexto): bool {

        // Usuário e perfil são positivos; somente entidade aceita a raiz zero.

        foreach (['tecnico_id' => 1, 'perfil_id' => 1, 'entidade_id' => 0] as $campo => $minimo) {

            // Não converte valores parciais nem indicadores recebidos por formulário.

            if (!isset($contexto[$campo]) || !is_int($contexto[$campo]) || $contexto[$campo] < $minimo) {

                return false;

            }

        }

        $entidades = $contexto['entidades_ativas_ids'] ?? null;

        // A ausência de seleção não concede uma entidade padrão.

        if (!is_array($entidades) || !array_is_list($entidades) || $entidades === [] || !in_array($contexto['entidade_id'], $entidades, true)) {

            return false;

        }

        // Todo identificador precisa ser válido, mesmo quando não corresponde a um mapa.

        foreach ($entidades as $id) {

            // Uma entidade inválida compromete o contexto completo.

            if (!is_int($id) || $id < 0) {

                return false;

            }

        }

        return true;

    }

    /**

     * Explica o alcance efetivo sem apresentar grupos como concessões de acesso.

     */

    public static function descrever(array $contexto): string {

        $escopo = $contexto['visibilidade'] ?? ['papel' => 'negado', 'entidades_ids' => [], 'vinculos' => []];

        $rotulo = match ($escopo['papel']) {

            'super_administrador' => 'Superadministrador: todos os chamados permitidos pelo GLPI',

            'administrador_setor' => 'Administrador de setor: ' . implode(', ', array_unique(array_column($escopo['vinculos'], 'nome'))),

            'tecnico' => 'Técnico: somente chamados atribuídos individualmente a você',

            // Ausência de autorização nunca é apresentada como acesso técnico.

            default => 'Sem escopo autorizado',

        };

        return $rotulo . '. Entidades ativas autorizadas: ' . implode(', ', $escopo['entidades_ids']) . '. Categorias operacionais limitam a importação, não esta exibição.';

    }

}

