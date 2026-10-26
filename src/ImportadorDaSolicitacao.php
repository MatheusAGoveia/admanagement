<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Transforma um formulário aprovado e vinculado ao chamado em dados de trabalho locais.
 */

final class ImportadorDaSolicitacao {

    /**
     * Confere vínculo, papel, aprovação atual e serviço antes de formar o rascunho próprio.
     */

    public static function importar(

        array $chamado,

        array $formularios,

        array $contexto,

        ?array $categorias = null

    ): array {

        // Categorias operacionais impedem a importação, mas não escondem chamados autorizados.

        if ($categorias !== null && !in_array($chamado['itilcategories_id'], $categorias, true)) {

            throw new DomainException($categorias === [] ? 'Configure as categorias operacionais para permitir importação.' : 'Fora das categorias operacionais: visível para consulta, com importação indisponível.');

        }

        // Junta todas as correspondências de rede antes de escolher qualquer formulário.

        $correspondencias = [];

        // Examina todas as origens encontradas antes de decidir se o vínculo de rede é único.

        foreach ($formularios as $formulario) {

            $vinculo_rede = null;

            // Obtém o vínculo de rede quando a origem fornece esse identificador.

            if (isset($formulario['tickets_id_rede'])) {

                $vinculo_rede = $formulario['tickets_id_rede'];

            }

            // Separa a origem cujo papel de rede aponta para o chamado selecionado.

            if ($vinculo_rede === $chamado['id']) {

                $correspondencias[] = $formulario;

            }

            // O mesmo chamado não pode representar rede e outro serviço ao mesmo tempo.

            foreach ([

                'tickets_id_email',

                'tickets_id_sistema_saude',

                'tickets_id_sei'

            ] as $campo) {

                $vinculo_outro_servico = null;

                // Lê um vínculo de outro serviço somente quando a coluna está disponível no registro.

                if (isset($formulario[$campo])) {

                    $vinculo_outro_servico = $formulario[$campo];

                }

                // Bloqueia a importação quando o mesmo ticket aparece associado a um papel que não é rede.

                if ($vinculo_outro_servico === $chamado['id']) {

                    throw new DomainException('O chamado também aparece em outro papel de serviço. O vínculo precisa ser revisado.');

                }

            }

        }

        // É necessário exatamente um formulário vinculado ao chamado de rede.

        if (count($correspondencias) === 0) {

            throw new DomainException('Não há formulário vinculado a este chamado de rede. É necessária revisão do vínculo.');

        }

        // Recusa escolher silenciosamente entre múltiplos formulários vinculados ao mesmo chamado de rede.

        if (count($correspondencias) > 1) {

            throw new DomainException('Há mais de um formulário vinculado ao chamado. A importação não pode escolher um deles automaticamente.');

        }

        $formulario = $correspondencias[0];

        $formulario_id = null;

        // Obtém o identificador da origem antes de formar o rascunho de trabalho.

        if (isset($formulario['id'])) {

            $formulario_id = $formulario['id'];

        }

        // Recusa importar uma origem sem identificador inteiro positivo e confiável.

        if (!is_int($formulario_id) || $formulario_id <= 0) {

            throw new DomainException('O registro do formulário não possui identificador válido.');

        }

        // Na origem, status 3 significa aprovado e req_servico_rede 1 solicita rede.

        if ($formulario['status'] !== 3 || $formulario['req_servico_rede'] !== 1) {

            throw new DomainException('O formulário não está aprovado ou não solicita serviço de rede.');

        }

        // O formulário e o chamado precisam pertencer à mesma entidade.

        if ($formulario['entities_id'] !== $chamado['entities_id']) {

            throw new DomainException('As entidades do formulário e do chamado divergem. Revise o vínculo.');

        }

        // Traduz somente os dois serviços previstos, mantendo os nomes exatos da origem.

        $tipos = [

            'CADASTRO' => 'criacao',

            'RENOVAR USUÁRIO' => 'renovacao'

        ];

        // Bloqueia tipos de solicitação diferentes dos valores exatos de cadastro e renovação definidos.

        if (!isset($tipos[$formulario['tipo_servico_rede']])) {

            throw new DomainException('O serviço solicitado não corresponde a criação ou renovação de usuário.');

        }

        // Campos opcionais não encontrados permanecem pendentes, sem inventar valores.

        $indicacao_nome_social = '';

        // Lê a indicação original de uso do nome social quando esse campo está disponível.

        if (isset($formulario['usar_nome_social'])) {

            $indicacao_nome_social = (string) $formulario['usar_nome_social'];

        }

        $usar_nome_social = null;

        // Mantém o uso obrigatório de nome social indicado pelo valor textual esperado da origem.

        if ($indicacao_nome_social === '1') {

            $usar_nome_social = true;

        } elseif ($indicacao_nome_social === '0') {

            // Mantém o uso de nome civil indicado explicitamente pela origem.

            $usar_nome_social = false;

        }

        // A assinatura permite perceber mudanças nos dados relevantes da origem.

        $origem_relevante = $formulario;

        // Uma mudança isolada na data não representa alteração dos dados importados.

        unset($origem_relevante['date_mod']);

        // Ordena as chaves para gerar a mesma assinatura quando o conteúdo for igual.

        ksort($origem_relevante);

        $origem_json = json_encode($origem_relevante, JSON_THROW_ON_ERROR);

        $assinatura_origem = hash('sha256', $origem_json);

        // Os textos opcionais começam vazios e só recebem dados se vierem da origem.

        $nome_civil = '';

        // Importa o nome civil disponível sem inventar um valor ausente.

        if (isset($formulario['name'])) {

            $nome_civil = (string) $formulario['name'];

        }

        $nome_social = '';

        // Importa o nome social disponível para respeitar a escolha definida na origem.

        if (isset($formulario['nome_social'])) {

            $nome_social = (string) $formulario['nome_social'];

        }

        $matricula = '';

        // Importa a matrícula fornecida como dado de trabalho separado do login.

        if (isset($formulario['matricula'])) {

            $matricula = (string) $formulario['matricula'];

        }

        $tipo = $tipos[$formulario['tipo_servico_rede']];

        // usuario_rede é o alvo informado; login_rede é espelhamento e não é lido.

        $conta_atendida = '';

        // Trata o usuário de rede informado como conta atendida somente na renovação.

        if ($tipo === 'renovacao') {

            // Obtém a conta existente declarada pela origem quando seu campo foi fornecido.

            if (isset($formulario['usuario_rede'])) {

                $conta_atendida = (string) $formulario['usuario_rede'];

            }

        }

        $avisos_estrutura = [];

        // Preserva os avisos de estrutura para que campos ausentes continuem visíveis como pendências.

        if (isset($formulario['avisos_estrutura'])) {

            $avisos_estrutura = $formulario['avisos_estrutura'];

        }

        // Monta apenas o rascunho. Importar não salva a origem nem executa ações no AD.

        return [

            'chamado_id' => $chamado['id'],

            'formulario_id' => $formulario['id'],

            'entidade_id' => $chamado['entities_id'],

            'tecnico_id' => $contexto['tecnico_id'],

            'tecnico_original' => $contexto['tecnico_id'],

            'papel' => 'rede',

            'tipo' => $tipo,

            'assinatura_origem' => $assinatura_origem,

            'nome_civil' => $nome_civil,

            'nome_social' => $nome_social,

            'usar_nome_social' => $usar_nome_social,

            'uso_nome_na_origem' => $usar_nome_social,

            'matricula' => $matricula,

            'conta_atendida' => $conta_atendida,

            'login_pretendido' => '',

            'dominio' => '',

            'ou' => '',

            'grupos' => '',

            'scripts' => '',

            'pastas' => '',

            'validade_proposta' => '',

            'avisos_estrutura' => $avisos_estrutura,

            'revisao' => 1,

            'revisado_em' => null,

        ];

    }

}

