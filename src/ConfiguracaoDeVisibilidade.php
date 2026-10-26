<?php

namespace GlpiPlugin\Admanagement;

use InvalidArgumentException;

/**
 * Valida e persiste setores e concessões sem alterar grupos, usuários ou perfis nativos.
 */

final class ConfiguracaoDeVisibilidade {

    /**
     * Distingue instalação legada de política nova vazia; documento corrompido bloqueia a operação.
     */

    public static function consultar(): ?array {

        $valores = \Config::getConfigurationValues('admanagement', ['visibilidade']);

        // Somente a ausência real da chave permite o legado; nenhum conteúdo JSON representa ausência.

        if (!array_key_exists('visibilidade', $valores)) {

            return null;

        }

        return self::validar(ConfiguracaoOperacional::consultar('visibilidade'));

    }

    /**
     * Normaliza IDs de formulário com validação decimal estrita, incluindo zero apenas para entidade.
     */

    public static function normalizarLista(

        mixed $valores,

        bool $raiz = false

    ): array {

        // O seletor múltiplo GLPI pode representar a remoção de todas as opções por texto vazio.

        if ($valores === '') {

            $valores = [];

        }

        // Um campo isolado ou associativo não pode ser interpretado como uma lista de concessões.

        if (!is_array($valores) || !array_is_list($valores)) {

            throw new InvalidArgumentException('Envie uma lista válida de identificadores.');

        }

        $ids = [];

        // Valida a lista inteira antes de remover duplicidades.

        foreach ($valores as $valor) {

            // A entidade zero corresponde somente à raiz explicitamente escolhida.

            if ($raiz && ($valor === 0 || $valor === '0')) {

                $ids[] = 0;

            } else {

                // Demais identificadores precisam ser positivos e sem coerção parcial.

                $ids[] = EntradaDaFerramenta::lerIdentificador($valor);

            }

        }

        return array_values(array_unique($ids));

    }

    /**
     * Verifica o documento completo, inclusive referências entre regras e setores.
     */

    public static function validar(

        array $dados

    ): array {

        // A versão e as duas listas são obrigatórias; não aplicar permissões padrão em documento parcial.

        if (($dados['versao'] ?? null) !== 1 || !isset($dados['setores'], $dados['regras']) || !is_array($dados['setores']) || !is_array($dados['regras']) || !array_is_list($dados['setores']) || !array_is_list($dados['regras'])) {

            throw new InvalidArgumentException('A política de visibilidade está inválida. Revise a configuração.');

        }

        $setores = [];

        $ids = [];

        // Cada setor exige nome, grupos e entidades; filhos não são incluídos automaticamente.

        foreach ($dados['setores'] as $setor) {

            // Rejeita elementos escalares ou nomes vazios antes de ler os campos.

            if (!is_array($setor) || !is_string($setor['nome'] ?? null) || trim($setor['nome']) === '' || strlen($setor['nome']) > 200) {

                throw new InvalidArgumentException('Informe um nome de setor de até 200 bytes.');

            }

            $id = EntradaDaFerramenta::lerIdentificador($setor['id'] ?? null);

            $grupos = self::normalizarLista($setor['grupos_ids'] ?? null);

            $entidades = self::normalizarLista($setor['entidades_ids'] ?? null, true);

            // Um setor não pode representar todos os grupos ou todas as entidades por seleção vazia.

            if (isset($ids[$id]) || $grupos === [] || $entidades === []) {

                throw new InvalidArgumentException('Cada setor exige ID único, grupos responsáveis e entidades explícitas.');

            }

            $ids[$id] = true;

            $setores[] = ['id' => $id, 'nome' => EntradaDaFerramenta::lerTexto($setor['nome'], 200), 'grupos_ids' => $grupos, 'entidades_ids' => $entidades];

        }

        $regras = [];

        $ids_regras = [];

        // Nenhuma regra pode referenciar um setor ausente ou conceder um papel desconhecido.

        foreach ($dados['regras'] as $regra) {

            // Confere tipos e valores antes de aceitar um alvo de usuário, grupo ou perfil.

            if (!is_array($regra) || !in_array($regra['origem'] ?? null, ['usuario', 'grupo', 'perfil'], true) || !in_array($regra['papel'] ?? null, ['tecnico', 'administrador_setor'], true) || !is_bool($regra['ativa'] ?? null)) {

                throw new InvalidArgumentException('A regra contém origem, papel ou estado inválido.');

            }

            $id = EntradaDaFerramenta::lerIdentificador($regra['id'] ?? null);

            $alvo = EntradaDaFerramenta::lerIdentificador($regra['alvo_id'] ?? null);

            $setores_da_regra = self::normalizarLista($regra['setores_ids'] ?? null);

            $entidades = self::normalizarLista($regra['entidades_ids'] ?? null, true);

            // Regras técnicas não guardam setores; regras administrativas exigem ao menos um.

            if (isset($ids_regras[$id]) || $entidades === [] || ($regra['papel'] === 'administrador_setor' && $setores_da_regra === []) || ($regra['papel'] === 'tecnico' && $setores_da_regra !== [])) {

                throw new InvalidArgumentException('Confira entidades, setores e identificador único da regra.');

            }

            // Recusa referências removidas, em vez de interpretar ausência como acesso global.

            foreach ($setores_da_regra as $setor_id) {

                // Cada vínculo deve apontar para um setor existente neste documento.

                if (!isset($ids[$setor_id])) {

                    throw new InvalidArgumentException('A regra referencia um setor que não existe.');

                }

            }

            $ids_regras[$id] = true;

            $regras[] = ['id' => $id, 'origem' => $regra['origem'], 'alvo_id' => $alvo, 'papel' => $regra['papel'], 'setores_ids' => $setores_da_regra, 'entidades_ids' => $entidades, 'ativa' => $regra['ativa']];

        }

        return ['versao' => 1, 'setores' => $setores, 'regras' => $regras];

    }

    /**
     * Cria, atualiza ou exclui um cadastro após conferir autorização, revisão e catálogos GLPI.
     */

    public static function alterar(

        array $entrada

    ): void {

        AcessoGlpi::exigirConfiguracao();

        $atual = self::consultar();

        // Uma aba antiga não pode sobrescrever configuração alterada por outro administrador.

        if (!is_string($entrada['revisao_politica'] ?? null) || !hash_equals(self::assinatura($atual), $entrada['revisao_politica'])) {

            throw new InvalidArgumentException('A configuração mudou. Recarregue antes de salvar.');

        }

        $dados = $atual ?? ['versao' => 1, 'setores' => [], 'regras' => []];

        $tipo = $entrada['tipo_cadastro'] ?? null;

        // Aceita somente as duas coleções administrativas conhecidas.

        if (!in_array($tipo, ['setores', 'regras'], true)) {

            throw new InvalidArgumentException('Cadastro de visibilidade inválido.');

        }

        $id = ($entrada['cadastro_id'] ?? '') === '' ? 0 : EntradaDaFerramenta::lerIdentificador($entrada['cadastro_id']);

        $excluir = ($entrada['excluir'] ?? null) === '1';

        // Exclusão sem ID não pode ativar ou apagar uma política por engano.

        if ($excluir && $id === 0) {

            throw new InvalidArgumentException('Informe o cadastro a excluir.');

        }

        // A exclusão de setor exige primeiro remover as concessões que o utilizam.

        if ($excluir && $tipo === 'setores') {

            // Confere todas as regras, inclusive as inativas, para não deixar referências penduradas.

            foreach ($dados['regras'] as $regra) {

                // Impede remover um setor ainda referenciado por uma concessão.

                if (in_array($id, $regra['setores_ids'], true)) {

                    throw new InvalidArgumentException('Remova o setor das regras de acesso antes de excluí-lo.');

                }

            }

        }

        $novo = null;

        // Monta dados novos somente para operações de criação ou edição.

        if (!$excluir) {

            $ids = array_column($dados[$tipo], 'id');

            $id = $id ?: ($ids === [] ? 1 : max($ids) + 1);

            // Setores usam grupos responsáveis; a unidade do formulário não define a fila.

            if ($tipo === 'setores') {

                $novo = ['id' => $id, 'nome' => $entrada['nome_setor'] ?? null, 'grupos_ids' => self::normalizarLista($entrada['grupos_ids'] ?? []), 'entidades_ids' => self::normalizarLista($entrada['entidades_ids'] ?? [], true)];

            } else {

                // A origem determina qual seletor fornece o alvo; campos restantes não concedem acesso.

                $origem = $entrada['origem'] ?? '';

                // Recusa estruturas enviadas no lugar da origem antes de formar o nome do seletor.

                if (!in_array($origem, ['usuario', 'grupo', 'perfil'], true)) {

                    throw new InvalidArgumentException('Origem de permissão inválida.');

                }

                $novo = ['id' => $id, 'origem' => $origem, 'alvo_id' => $entrada[$origem . '_id'] ?? null, 'papel' => $entrada['papel'] ?? '', 'setores_ids' => self::normalizarLista($entrada['setores_ids'] ?? []), 'entidades_ids' => self::normalizarLista($entrada['entidades_ids'] ?? [], true), 'ativa' => ($entrada['ativa'] ?? null) === '1'];

            }

        }

        $encontrado = false;

        $lista = [];

        // Substitui somente o cadastro solicitado, preservando os demais setores e regras.

        foreach ($dados[$tipo] as $item) {

            // A edição ou exclusão incide somente sobre o identificador explícito.

            if ($item['id'] === $id) {

                $encontrado = true;

                continue;

            }

            $lista[] = $item;

        }

        // IDs enviados para edição/exclusão precisam existir; criação exige campo vazio.

        if (($entrada['cadastro_id'] ?? '') !== '' && !$encontrado) {

            throw new InvalidArgumentException('O cadastro informado não existe.');

        }

        // Uma nova versão substitui a anterior; na exclusão nenhum item é acrescentado.

        if ($novo !== null) {

            $lista[] = $novo;

        }

        $dados[$tipo] = $lista;

        $dados = self::validar($dados);

        self::conferirCatalogos($dados);

        // A primeira gravação registra o legado para consulta; nunca converte grupos em gestores.

        if ($atual === null) {

            ConfiguracaoOperacional::salvar('acesso_anterior_a_visibilidade', \Config::getConfigurationValues('admanagement', ['acesso_operacional', 'grupos_e_categorias']));

        }

        ConfiguracaoOperacional::salvar('visibilidade', $dados);

    }

    /**
     * Confere existência e leitura de cada alvo, grupo e entidade antes da gravação.
     */

    private static function conferirCatalogos(

        array $dados

    ): void {

        // Confere todos os setores para manter a consistência também durante edições de regras.

        foreach ($dados['setores'] as $setor) {

            self::conferirEntidades($setor['entidades_ids']);

            // Grupos responsáveis precisam existir e estar no escopo nativo do administrador.

            foreach ($setor['grupos_ids'] as $id) {

                self::conferirAlvo('grupo', $id);

            }

        }

        // Concessões individuais também exigem usuário ativo e acessível no GLPI.

        foreach ($dados['regras'] as $regra) {

            self::conferirEntidades($regra['entidades_ids']);

            self::conferirAlvo($regra['origem'], $regra['alvo_id']);

        }

    }

    /**
     * Valida entidades explícitas sem expandir filhos ou aceitar curingas.
     */

    private static function conferirEntidades(

        array $ids

    ): void {

        // Cada entidade deve existir e estar disponível ao administrador que está configurando.

        foreach ($ids as $id) {

            $entidade = new \Entity();

            // Nem mesmo uma regra administrativa pode apontar para entidade fora do direito nativo.

            if (!$entidade->getFromDB($id) || !\Session::haveAccessToEntity($id)) {

                throw new InvalidArgumentException('Selecione entidades existentes no seu escopo GLPI.');

            }

        }

    }

    /**
     * Usa o modelo nativo correspondente para impedir referências inexistentes ou inacessíveis.
     */

    private static function conferirAlvo(

        string $origem,

        int $id

    ): void {

        $classe = match ($origem) {'usuario' => \User::class, 'grupo' => \Group::class, 'perfil' => \Profile::class};

        $item = new $classe();

        // O direito por recurso GLPI é obrigatório também para catálogos administrativos.

        if (!$item->can($id, READ)) {

            throw new InvalidArgumentException('O usuário, grupo ou perfil não existe ou está fora do seu direito de leitura.');

        }

        // Um usuário removido ou desativado não pode receber uma nova concessão.

        if ($origem === 'usuario' && (!empty($item->fields['is_deleted']) || empty($item->fields['is_active']))) {

            throw new InvalidArgumentException('Selecione um usuário ativo no GLPI.');

        }

    }

    /**
     * Identifica a versão administrativa sem confiar em contadores enviados pela página.
     */

    public static function assinatura(

        ?array $dados

    ): string {

        return hash('sha256', json_encode($dados, JSON_THROW_ON_ERROR));

    }

    /**
     * Identifica o documento bruto para confirmar a recuperação de uma configuração corrompida.
     */

    public static function assinaturaDoDocumento(): string {

        $valores = \Config::getConfigurationValues('admanagement', ['visibilidade']);

        return hash('sha256', json_encode($valores['visibilidade'] ?? null, JSON_THROW_ON_ERROR));

    }

    /**
     * Preserva o documento inválido e reinicia somente a política própria após confirmação administrativa.
     */

    public static function restaurar(

        array $entrada

    ): void {

        AcessoGlpi::exigirConfiguracao();

        // A confirmação identifica o documento efetivamente apresentado, impedindo sobrescrever uma correção recente.

        if (($entrada['confirmar_restauracao'] ?? null) !== '1' || !is_string($entrada['revisao_documento'] ?? null) || !hash_equals(self::assinaturaDoDocumento(), $entrada['revisao_documento'])) {

            throw new InvalidArgumentException('Confirme a recuperação da configuração apresentada.');

        }

        $corrompida = false;

        try {

            self::consultar();

        } catch (\DomainException | InvalidArgumentException $erro) {

            // Recuperação se aplica somente a documento que a validação não consegue utilizar.

            $corrompida = true;

        }

        // Uma política válida deve ser editada pelos cadastros, sem apagar suas concessões em bloco.

        if (!$corrompida) {

            throw new InvalidArgumentException('A política está válida. Utilize a edição de setores e permissões.');

        }

        $valores = \Config::getConfigurationValues('admanagement', ['visibilidade']);

        ConfiguracaoOperacional::salvar('backup_visibilidade_corrompida', ['documento' => $valores['visibilidade'], 'instante' => gmdate(DATE_ATOM)]);

        ConfiguracaoOperacional::salvar('visibilidade', ['versao' => 1, 'setores' => [], 'regras' => []]);

    }

}

