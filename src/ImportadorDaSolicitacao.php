<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Transforma um formulário aprovado e vinculado ao chamado em dados locais de revisão.
 */

final class ImportadorDaSolicitacao {

    /**
     * Confere categoria, aprovação, entidade e serviço antes de formar o rascunho de trabalho.
     */

    public static function importar(

        array $chamado,

        array $formularios,

        array $contexto,

        ?array $categorias = null

    ): array {

        // Categorias operacionais impedem importação, sem esconder chamados já autorizados.

        if ($categorias !== null && !in_array($chamado['itilcategories_id'], $categorias, true)) {

            throw new DomainException(

                $categorias === []

                    ? 'Configure as categorias operacionais para permitir importação.'

                    : 'Fora das categorias operacionais: visível para consulta, com importação indisponível.'

            );

        }

        $formulario = self::localizarFormulario($chamado['id'], $formularios);

        $formulario_id = $formulario['id'] ?? null;

        // O adaptador entrega IDs normalizados; uma origem incompleta não pode gerar um rascunho.

        if (!is_int($formulario_id) || $formulario_id <= 0) {

            throw new DomainException('O registro do formulário não possui identificador válido.');

        }

        // Na origem, status 3 significa aprovado e req_servico_rede 1 solicita serviço de rede.

        if (($formulario['status'] ?? null) !== 3 || ($formulario['req_servico_rede'] ?? null) !== 1) {

            throw new DomainException('O formulário não está aprovado ou não solicita serviço de rede.');

        }

        // Uma origem de outra entidade não pode ser usada para revisar este chamado.

        if (($formulario['entities_id'] ?? null) !== $chamado['entities_id']) {

            throw new DomainException('As entidades do formulário e do chamado divergem. Revise o vínculo.');

        }

        $tipos = [

            'CADASTRO' => 'criacao',

            'RENOVAR USUÁRIO' => 'renovacao',

        ];

        $tipo = $tipos[$formulario['tipo_servico_rede'] ?? ''] ?? null;

        // A tradução aceita somente os valores exatos de criação e renovação definidos na origem.

        if ($tipo === null) {

            throw new DomainException('O serviço solicitado não corresponde a criação ou renovação de usuário.');

        }

        $usar_nome_social = match ((string) ($formulario['usar_nome_social'] ?? '')) {

            '1' => true,

            '0' => false,

            // Ausência de indicação permanece pendente, sem escolher outro nome automaticamente.

            default => null,

        };

        $origem_relevante = $formulario;

        // Uma mudança isolada de data não representa alteração dos dados efetivamente importados.

        unset($origem_relevante['date_mod']);

        ksort($origem_relevante);

        // Importar cria dados locais; não grava no formulário, no chamado ou no diretório.

        return [

            'chamado_id' => $chamado['id'],

            'formulario_id' => $formulario_id,

            'entidade_id' => $chamado['entities_id'],

            'tecnico_id' => $contexto['tecnico_id'],

            'tecnico_original' => $contexto['tecnico_id'],

            'papel' => 'rede',

            'tipo' => $tipo,

            'assinatura_origem' => hash('sha256', json_encode($origem_relevante, JSON_THROW_ON_ERROR)),

            'nome_civil' => (string) ($formulario['name'] ?? ''),

            'nome_social' => (string) ($formulario['nome_social'] ?? ''),

            'usar_nome_social' => $usar_nome_social,

            'uso_nome_na_origem' => $usar_nome_social,

            'matricula' => (string) ($formulario['matricula'] ?? ''),

            // usuario_rede identifica a conta atendida; login_rede é espelhamento e não é importado.

            'conta_atendida' => $tipo === 'renovacao' ? (string) ($formulario['usuario_rede'] ?? '') : '',

            'login_pretendido' => '',

            'dominio' => '',

            'ou' => '',

            'grupos' => '',

            'scripts' => '',

            'pastas' => '',

            'validade_proposta' => '',

            'avisos_estrutura' => $formulario['avisos_estrutura'] ?? [],

            'revisao' => 1,

            'revisado_em' => null,

        ];

    }

    /**
     * Exige vínculo único de rede e recusa o chamado que também esteja associado a outro serviço.
     */

    private static function localizarFormulario(

        int $chamado_id,

        array $formularios

    ): array {

        $correspondencias = [];

        // Examina todas as origens antes de escolher; a ordem dos registros não define o vínculo.

        foreach ($formularios as $formulario) {

            // A correspondência precisa apontar exatamente para o chamado de rede selecionado.

            if (($formulario['tickets_id_rede'] ?? null) === $chamado_id) {

                $correspondencias[] = $formulario;

            }

            // O mesmo chamado não pode representar rede e outro serviço simultaneamente.

            foreach (['tickets_id_email', 'tickets_id_sistema_saude', 'tickets_id_sei'] as $campo) {

                // A origem não pode fornecer o ticket selecionado como destino de outro serviço.

                if (($formulario[$campo] ?? null) === $chamado_id) {

                    throw new DomainException('O chamado também aparece em outro papel de serviço. O vínculo precisa ser revisado.');

                }

            }

        }

        // Não inventa uma origem quando nenhum vínculo de rede foi encontrado.

        if ($correspondencias === []) {

            throw new DomainException('Não há formulário vinculado a este chamado de rede. É necessária revisão do vínculo.');

        }

        // Múltiplas origens precisam de revisão humana, sem escolha automática da primeira.

        if (count($correspondencias) > 1) {

            throw new DomainException('Há mais de um formulário vinculado ao chamado. A importação não pode escolher um deles automaticamente.');

        }

        return $correspondencias[0];

    }

}
