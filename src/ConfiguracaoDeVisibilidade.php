<?php

namespace GlpiPlugin\Admanagement;

use InvalidArgumentException;

/**

 * Persiste somente o mapa perfil-setor; direitos continuam exclusivamente no GLPI.

 */

final class ConfiguracaoDeVisibilidade {

    /**

     * Retorna ausência real ou um mapa atual válido; documentos antigos exigem migração explícita.

     */

    public static function consultar(): ?array {

        $valores = \Config::getConfigurationValues('admanagement', ['visibilidade']);

        // Ausência de mapa não substitui nem concede direitos nativos.

        if (!array_key_exists('visibilidade', $valores)) {

            return null;

        }

        return self::validar(ConfiguracaoOperacional::consultar('visibilidade'));

    }

    /**

     * Fornece o formato vazio atual sem reativar configurações legadas.

     */

    public static function vazia(): array {

        return ['versao' => 2, 'setores' => [], 'regras' => []];

    }

    /**

     * Valida listas completas e aceita zero somente para a entidade raiz.

     */

    public static function normalizarLista(mixed $valores, bool $raiz = false): array {

        return EntradaDaFerramenta::lerListaDeIdentificadores($valores, $raiz);

    }

    /**

     * Confere setores, vínculos exclusivos de perfil e referências sem conceder capacidades.

     */

    public static function validar(array $dados): array {

        // A mudança de versão exige confirmação e impede interpretar concessões antigas como atuais.

        if (($dados['versao'] ?? null) !== 2 || !isset($dados['setores'], $dados['regras']) || !is_array($dados['setores']) || !is_array($dados['regras']) || !array_is_list($dados['setores']) || !array_is_list($dados['regras'])) {

            throw new InvalidArgumentException('O mapa de setores exige atualização ou recuperação para controle exclusivo por perfil.');

        }

        $setores = [];

        $ids_setores = [];

        // Setores são pares explícitos de grupos atribuídos e entidades, sem expansão implícita.

        foreach ($dados['setores'] as $setor) {

            // Nomes vazios ou estruturas parciais nunca representam setores globais.

            if (!is_array($setor) || !is_string($setor['nome'] ?? null) || trim($setor['nome']) === '' || strlen($setor['nome']) > 200) {

                throw new InvalidArgumentException('Informe um nome de setor de até 200 bytes.');

            }

            $id = EntradaDaFerramenta::lerIdentificador($setor['id'] ?? null);

            $grupos = self::normalizarLista($setor['grupos_ids'] ?? null);

            $entidades = self::normalizarLista($setor['entidades_ids'] ?? null, true);

            // Seleções vazias e IDs repetidos não podem ampliar ou tornar ambíguo o escopo.

            if (isset($ids_setores[$id]) || $grupos === [] || $entidades === []) {

                throw new InvalidArgumentException('Cada setor exige ID único, grupos responsáveis e entidades explícitas.');

            }

            $ids_setores[$id] = true;

            $setores[] = ['id' => $id, 'nome' => EntradaDaFerramenta::lerTexto($setor['nome'], 200), 'grupos_ids' => $grupos, 'entidades_ids' => $entidades];

        }

        $regras = [];

        $ids_regras = [];

        // Um vínculo de perfil limita a supervisão nativa; não concede operação ou administração.

        foreach ($dados['regras'] as $regra) {

            // Usuários, grupos e papéis do documento antigo são incompatíveis com o cadastro atual.

            if (!is_array($regra) || ($regra['origem'] ?? null) !== 'perfil' || array_key_exists('papel', $regra) || !is_bool($regra['ativa'] ?? null)) {

                throw new InvalidArgumentException('Vincule somente perfis; as capacidades são definidas na matriz nativa do GLPI.');

            }

            $id = EntradaDaFerramenta::lerIdentificador($regra['id'] ?? null);

            $perfil_id = EntradaDaFerramenta::lerIdentificador($regra['alvo_id'] ?? null);

            $setores_da_regra = self::normalizarLista($regra['setores_ids'] ?? null);

            $entidades = self::normalizarLista($regra['entidades_ids'] ?? null, true);

            // Todo supervisor exige setores e entidades expressamente definidos.

            if (isset($ids_regras[$id]) || $setores_da_regra === [] || $entidades === []) {

                throw new InvalidArgumentException('Confira entidades, setores e identificador único do vínculo de perfil.');

            }

            // Referências removidas jamais significam acesso a qualquer setor.

            foreach ($setores_da_regra as $setor_id) {

                // O setor deve existir no mesmo documento que o vínculo.

                if (!isset($ids_setores[$setor_id])) {

                    throw new InvalidArgumentException('O vínculo referencia um setor que não existe.');

                }

            }

            $ids_regras[$id] = true;

            $regras[] = ['id' => $id, 'origem' => 'perfil', 'alvo_id' => $perfil_id, 'setores_ids' => $setores_da_regra, 'entidades_ids' => $entidades, 'ativa' => $regra['ativa']];

        }

        return ['versao' => 2, 'setores' => $setores, 'regras' => $regras];

    }

    /**

     * Salva um cadastro após autorização, conferência de revisão e validação dos catálogos.

     */

    public static function alterar(array $entrada): void {

        AcessoGlpi::exigirConfiguracao();

        $atual = self::consultar();

        // O hash impede uma aba antiga de sobrescrever alterações de outro administrador.

        if (!is_string($entrada['revisao_politica'] ?? null) || !hash_equals(self::assinatura($atual), $entrada['revisao_politica'])) {

            throw new InvalidArgumentException('A configuração mudou. Recarregue antes de salvar.');

        }

        $dados = $atual ?? self::vazia();

        $tipo = $entrada['tipo_cadastro'] ?? null;

        // Somente as coleções administrativas conhecidas podem ser alteradas.

        if (!in_array($tipo, ['setores', 'regras'], true)) {

            throw new InvalidArgumentException('Cadastro de visibilidade inválido.');

        }

        $id = ($entrada['cadastro_id'] ?? '') === '' ? 0 : EntradaDaFerramenta::lerIdentificador($entrada['cadastro_id']);

        $excluir = ($entrada['excluir'] ?? null) === '1';

        // Exclusão sem identificador não deve criar ou reiniciar um documento.

        if ($excluir && $id === 0) {

            throw new InvalidArgumentException('Informe o cadastro a excluir.');

        }

        // Setores referenciados precisam ser retirados dos vínculos antes da exclusão.

        if ($excluir && $tipo === 'setores') {

            // Vínculos inativos também preservam referências para edição posterior.

            foreach ($dados['regras'] as $regra) {

                // A exclusão não pode deixar um vínculo administrativo incompleto.

                if (in_array($id, $regra['setores_ids'], true)) {

                    throw new InvalidArgumentException('Remova o setor dos vínculos de perfil antes de excluí-lo.');

                }

            }

        }

        $novo = null;

        // Somente criação e edição precisam interpretar os campos do cadastro.

        if (!$excluir) {

            $ids = array_column($dados[$tipo], 'id');

            $id = $id ?: ($ids === [] ? 1 : max($ids) + 1);

            // O setor identifica os grupos atribuídos ao chamado, sem autorizar seus integrantes.

            if ($tipo === 'setores') {

                $novo = ['id' => $id, 'nome' => $entrada['nome_setor'] ?? null, 'grupos_ids' => self::normalizarLista($entrada['grupos_ids'] ?? []), 'entidades_ids' => self::normalizarLista($entrada['entidades_ids'] ?? [], true)];

            } else {

                // Requisições antigas não podem conceder direito por origem ou papel enviado.

                if (($entrada['origem'] ?? 'perfil') !== 'perfil' || array_key_exists('papel', $entrada)) {

                    throw new InvalidArgumentException('O controle de acesso é exclusivo do perfil ativo; ajuste os direitos no GLPI.');

                }

                $novo = ['id' => $id, 'origem' => 'perfil', 'alvo_id' => $entrada['perfil_id'] ?? null, 'setores_ids' => self::normalizarLista($entrada['setores_ids'] ?? []), 'entidades_ids' => self::normalizarLista($entrada['entidades_ids'] ?? [], true), 'ativa' => ($entrada['ativa'] ?? null) === '1'];

            }

        }

        $encontrado = false;

        $lista = [];

        // Modifica somente o identificador solicitado, preservando os demais cadastros.

        foreach ($dados[$tipo] as $item) {

            // A edição substitui e a exclusão remove exatamente a linha identificada.

            if ($item['id'] === $id) {

                $encontrado = true;

                continue;

            }

            $lista[] = $item;

        }

        // Um identificador de edição inexistente não pode virar criação silenciosa.

        if (($entrada['cadastro_id'] ?? '') !== '' && !$encontrado) {

            throw new InvalidArgumentException('O cadastro informado não existe.');

        }

        // Exclusões não acrescentam uma linha substituta.

        if ($novo !== null) {

            $lista[] = $novo;

        }

        $dados[$tipo] = $lista;

        $dados = self::validar($dados);

        self::conferirCatalogos($dados);

        ConfiguracaoOperacional::salvar('visibilidade', $dados);

    }

    /**

     * Confere existência e leitura dos setores e perfis sem alterar recursos do GLPI.

     */

    private static function conferirCatalogos(array $dados): void {

        // Os setores precisam continuar íntegros também quando somente um vínculo é editado.

        foreach ($dados['setores'] as $setor) {

            self::conferirEntidades($setor['entidades_ids']);

            // Grupos representam responsáveis pelos chamados e precisam existir.

            foreach ($setor['grupos_ids'] as $id) {

                $grupo = new \Group();

                // Direito de leitura por recurso evita referências administrativas inacessíveis.

                if (!$grupo->can($id, READ)) {

                    throw new InvalidArgumentException('Selecione grupos existentes e acessíveis no GLPI.');

                }

            }

        }

        // O mapa é restrito a perfis centrais que o administrador consegue consultar.

        foreach ($dados['regras'] as $regra) {

            self::conferirEntidades($regra['entidades_ids']);

            $perfil = new \Profile();

            // Um perfil removido ou de autoatendimento não pode supervisionar o plugin técnico.

            if (!$perfil->can($regra['alvo_id'], READ) || ($perfil->fields['interface'] ?? null) !== 'central') {

                throw new InvalidArgumentException('Selecione um perfil central existente e acessível no GLPI.');

            }

        }

    }

    /**

     * Mantém a configuração restrita às entidades explicitamente acessíveis.

     */

    private static function conferirEntidades(array $ids): void {

        // Cada entidade precisa existir e estar dentro do alcance nativo do administrador.

        foreach ($ids as $id) {

            $entidade = new \Entity();

            // A configuração do plugin nunca cria direitos sobre outras entidades.

            if (!$entidade->getFromDB($id) || !\Session::haveAccessToEntity($id)) {

                throw new InvalidArgumentException('Selecione entidades existentes no seu escopo GLPI.');

            }

        }

    }

    /**

     * Prepara uma migração recuperável sem ativá-la nem conceder direitos nativos.

     */

    public static function consultarMigracao(): ?array {

        $dados = ConfiguracaoOperacional::consultar('visibilidade');

        // Somente a estrutura anterior conhecida pode oferecer migração automática dos mapas.

        if (($dados['versao'] ?? null) !== 1 || !isset($dados['setores'], $dados['regras']) || !is_array($dados['setores']) || !is_array($dados['regras']) || !array_is_list($dados['setores']) || !array_is_list($dados['regras'])) {

            return null;

        }

        $novo = self::vazia();

        $novo['setores'] = $dados['setores'];

        // Apenas relações administrativas de perfil preservam o mesmo significado setorial.

        foreach ($dados['regras'] as $regra) {

            // Usuários, grupos e regras técnicas antigas nunca viram permissões de perfil.

            if (!is_array($regra) || ($regra['origem'] ?? null) !== 'perfil' || ($regra['papel'] ?? null) !== 'administrador_setor') {

                continue;

            }

            unset($regra['papel']);

            $novo['regras'][] = $regra;

        }

        return self::validar($novo);

    }

    /**

     * Identifica a revisão do mapa sem confiar em contadores enviados pelo formulário.

     */

    public static function assinatura(?array $dados): string {

        return hash('sha256', json_encode($dados, JSON_THROW_ON_ERROR));

    }

    /**

     * Identifica o documento bruto para confirmar a migração ou recuperação exibida.

     */

    public static function assinaturaDoDocumento(): string {

        $valores = \Config::getConfigurationValues('admanagement', ['visibilidade']);

        return hash('sha256', json_encode($valores['visibilidade'] ?? null, JSON_THROW_ON_ERROR));

    }

    /**

     * Preserva o original e aplica somente a migração ou recuperação confirmada.

     */

    public static function restaurar(array $entrada): void {

        AcessoGlpi::exigirConfiguracao();

        // O administrador confirma exatamente o documento que viu na tela.

        if (($entrada['confirmar_restauracao'] ?? null) !== '1' || !is_string($entrada['revisao_documento'] ?? null) || !hash_equals(self::assinaturaDoDocumento(), $entrada['revisao_documento'])) {

            throw new InvalidArgumentException('Confirme a atualização ou recuperação da configuração apresentada.');

        }

        $invalida = false;

        try {

            self::consultar();

        } catch (\DomainException | InvalidArgumentException $erro) {

            // Somente documentos antigos ou inválidos podem passar por esta recuperação.

            $invalida = true;

        }

        // Um POST repetido não deve apagar um mapa atual e válido.

        if (!$invalida) {

            throw new InvalidArgumentException('O mapa está válido. Utilize a edição de setores e vínculos.');

        }

        $novo = null;

        try {

            $novo = self::consultarMigracao();

        } catch (\DomainException | InvalidArgumentException $erro) {

            // Um documento impossível de migrar permanece preservado integralmente no backup.

            $novo = null;

        }

        $valores = \Config::getConfigurationValues('admanagement', ['visibilidade']);

        ConfiguracaoOperacional::salvar('backup_visibilidade_anterior', ['documento' => $valores['visibilidade'], 'instante' => gmdate(DATE_ATOM)]);

        ConfiguracaoOperacional::salvar('visibilidade', $novo ?? self::vazia());

    }

}

