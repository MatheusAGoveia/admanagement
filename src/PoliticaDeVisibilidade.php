<?php

namespace GlpiPlugin\Admanagement;

/**
 * Resolve concessões positivas de usuário, grupo ou perfil sem consultar entradas HTTP.
 */

final class PoliticaDeVisibilidade {

    /**
     * Calcula o maior papel e mantém grupos e entidades pareados em cada setor autorizado.
     */

    public static function resolver(

        array $contexto,

        array $configuracao

    ): array {

        $negado = ['papel' => 'negado', 'entidades_ids' => [], 'vinculos' => [], 'regras_ids' => []];

        // Um contexto incompleto não pode conceder papel, mesmo com o indicador administrativo.

        if (!self::contextoValido($contexto)) {

            return $negado;

        }

        $entidades = $contexto['entidades_ativas_ids'] ?? [$contexto['entidade_id']];

        // O adaptador GLPI fornece este indicador exclusivamente da sessão nativa.

        if (($contexto['super_administrador'] ?? false) === true) {

            return ['papel' => 'super_administrador', 'entidades_ids' => $entidades, 'vinculos' => [], 'regras_ids' => []];

        }

        $setores = [];

        // Indexa os setores já validados para encontrar somente vínculos explícitos.

        foreach ($configuracao['setores'] ?? [] as $setor) {

            $setores[$setor['id']] = $setor;

        }

        $entidades_tecnicas = [];

        $vinculos = [];

        $regras = [];

        // Avalia todas as concessões; uma regra técnica nunca revoga uma concessão administrativa.

        foreach ($configuracao['regras'] ?? [] as $regra) {

            // Regras inativas ou destinadas a outra identidade não participam do escopo.

            if (($regra['ativa'] ?? false) !== true || !self::corresponde($regra, $contexto)) {

                continue;

            }

            $entidades_da_regra = array_values(array_intersect($entidades, $regra['entidades_ids']));

            // Uma regra em outra entidade não libera entrada no contexto ativo.

            if ($entidades_da_regra === []) {

                continue;

            }

            $regras[] = $regra['id'];

            // O técnico recebe somente as entidades explícitas de sua concessão pessoal.

            if ($regra['papel'] === 'tecnico') {

                $entidades_tecnicas = array_merge($entidades_tecnicas, $entidades_da_regra);

                continue;

            }

            // Mantém cada setor junto de suas entidades para evitar cruzamento entre dois setores.

            foreach ($regra['setores_ids'] as $id) {

                $setor = $setores[$id] ?? null;

                // Referência ausente nunca cria um setor ou concede todos os grupos.

                if ($setor === null) {

                    continue;

                }

                $entidades_do_setor = array_values(array_intersect($entidades_da_regra, $setor['entidades_ids']));

                // Um setor sem entidade comum não participa da autorização.

                if ($entidades_do_setor !== []) {

                    $vinculos[] = ['setor_id' => $id, 'nome' => $setor['nome'], 'grupos_ids' => $setor['grupos_ids'], 'entidades_ids' => $entidades_do_setor];

                }

            }

        }

        // Administração prevalece e permanece limitada aos setores, inclusive sobre atribuições pessoais externas.

        if ($vinculos !== []) {

            $entidades_setoriais = array_merge(...array_column($vinculos, 'entidades_ids'));

            return ['papel' => 'administrador_setor', 'entidades_ids' => array_values(array_unique($entidades_setoriais)), 'vinculos' => $vinculos, 'regras_ids' => $regras];

        }

        // Uma concessão técnica válida permite trabalhar apenas sobre atribuição individual.

        if ($entidades_tecnicas !== []) {

            return ['papel' => 'tecnico', 'entidades_ids' => array_values(array_unique($entidades_tecnicas)), 'vinculos' => [], 'regras_ids' => $regras];

        }

        return $negado;

    }

    /**
     * Compara o alvo ao usuário conectado, aos grupos atuais ou exclusivamente ao perfil ativo.
     */

    private static function corresponde(

        array $regra,

        array $contexto

    ): bool {

        return match ($regra['origem']) {

            'usuario' => $regra['alvo_id'] === $contexto['tecnico_id'],

            'grupo' => in_array($regra['alvo_id'], $contexto['grupos_ids'], true),

            'perfil' => $regra['alvo_id'] === $contexto['perfil_id'],

            // Origem desconhecida não corresponde a nenhuma identidade.

            default => false,

        };

    }

    /**
     * Recusa contexto adulterado antes de calcular qualquer concessão.
     */

    public static function contextoValido(

        array $contexto

    ): bool {

        // Usuário e perfil devem ser inteiros positivos; a entidade raiz é explicitamente zero.

        foreach (['tecnico_id', 'perfil_id', 'entidade_id'] as $campo) {

            // Valores ausentes, negativos ou convertidos de texto não são contexto normalizado.

            if (!isset($contexto[$campo]) || !is_int($contexto[$campo]) || $contexto[$campo] < ($campo === 'entidade_id' ? 0 : 1)) {

                return false;

            }

        }

        $listas = ['grupos_ids' => 1, 'entidades_ativas_ids' => 0];

        // Confere todas as listas por inteiro, sem aproveitar fragmentos de um contexto inválido.

        foreach ($listas as $campo => $minimo) {

            $lista = $contexto[$campo] ?? ($campo === 'entidades_ativas_ids' ? [$contexto['entidade_id']] : null);

            // Arrays associativos não são listas confiáveis de identidade ou escopo.

            if (!is_array($lista) || !array_is_list($lista)) {

                return false;

            }

            // Confere inclusive os IDs que não correspondem a uma regra atual.

            foreach ($lista as $id) {

                // Um único ID inválido recusa o contexto completo.

                if (!is_int($id) || $id < $minimo) {

                    return false;

                }

            }

        }

        return true;

    }

    /**
     * Explica o papel efetivo para todas as telas que apresentam chamados.
     */

    public static function descrever(

        array $contexto

    ): string {

        $escopo = $contexto['visibilidade'] ?? ['papel' => 'tecnico', 'entidades_ids' => [$contexto['entidade_id']], 'vinculos' => []];

        $rotulo = match ($escopo['papel']) {

            'super_administrador' => 'Super-Admin: todos os chamados permitidos pelo GLPI',

            'administrador_setor' => 'Administrador de setor: ' . implode(', ', array_unique(array_column($escopo['vinculos'], 'nome'))),

            'tecnico' => 'Técnico: somente chamados atribuídos individualmente a você',

            // Um papel recusado mantém a orientação explícita sem prometer acesso.

            default => 'Sem escopo autorizado',

        };

        return $rotulo . '. Entidades ativas autorizadas: ' . implode(', ', $escopo['entidades_ids']) . '. Categorias operacionais limitam a importação, não esta exibição.';

    }

}

