<?php

namespace GlpiPlugin\Admanagement;

use Glpi\Exception\Http\AccessDeniedHttpException;

use Plugin;

use Profile;

use Session;

/**

 * Autoriza pelo perfil ativo e preserva as fronteiras nativas de sessão e entidade.

 */

final class AcessoGlpi {

    /**

     * Exige capacidade nativa de administrar perfis e alterar configurações globais.

     */

    public static function podeConfigurar(): bool {

        // Login, plugin ativo e interface central são condições anteriores aos direitos.

        if (!self::sessaoCentralValida()) {

            return false;

        }

        $perfil_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);

        // A identificação nativa funciona também quando o perfil foi renomeado.

        return in_array($perfil_id, array_map('intval', Profile::getSuperAdminProfilesId()), true)

            && Session::haveRight('config', UPDATE);

    }

    /**

     * Protege a configuração mesmo durante recuperação de um documento inválido.

     */

    public static function exigirConfiguracao(): void {

        Session::checkLoginUser();

        // Direito operacional de supervisão não permite configurar o plugin.

        if (!self::podeConfigurar()) {

            throw new AccessDeniedHttpException('Você não tem permissão para configurar o ADManagement.');

        }

    }

    /**

     * Recalcula as capacidades do perfil ativo sem consultar grupos ou concessões pessoais.

     */

    public static function podeAcessar(): bool {

        // Uma entidade indefinida não representa a entidade raiz.

        if (!self::sessaoCentralValida() || !isset($_SESSION['glpiactive_entity']) || !Session::haveAccessToEntity(Session::getActiveEntity())) {

            return false;

        }

        try {

            $contexto = self::montarContextoNativo();

            // O administrador nativo mantém entrada para corrigir documentos antigos ou inválidos.

            if ($contexto['super_administrador']) {

                return PoliticaDeVisibilidade::resolver($contexto, [])['papel'] === 'super_administrador';

            }

            $politica = ConfiguracaoDeVisibilidade::consultar();

            return PoliticaDeVisibilidade::resolver($contexto, $politica ?? ConfiguracaoDeVisibilidade::vazia())['papel'] !== 'negado';

        } catch (\DomainException | \InvalidArgumentException $erro) {

            // Falha no documento ou no contexto nunca concede um alcance alternativo.

            return false;

        }

    }

    /**

     * Entrega identidade, direitos e escopo normalizados da sessão atual.

     */

    public static function obterContextoDoTecnico(): array {

        self::exigirAcesso();

        $contexto = self::montarContextoNativo();

        $politica = $contexto['super_administrador'] ? null : ConfiguracaoDeVisibilidade::consultar();

        $contexto['visibilidade'] = PoliticaDeVisibilidade::resolver($contexto, $politica ?? ConfiguracaoDeVisibilidade::vazia());

        $contexto['assinatura_visibilidade'] = ConfiguracaoDeVisibilidade::assinatura($politica);

        RepositorioDeRascunhos::conferirContexto($contexto);

        return $contexto;

    }

    /**

     * Confere os requisitos comuns sem depender de dados enviados pelo navegador.

     */

    private static function sessaoCentralValida(): bool {

        return Plugin::isPluginActive('admanagement')

            && (bool) Session::getLoginUserID()

            && Session::getCurrentInterface() === 'central'

            && (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) > 0;

    }

    /**

     * Usa o retrato oficial da sessão para evitar reconstruir a identidade por seletores HTTP.

     */

    private static function montarContextoNativo(): array {

        $sessao = Session::getCurrentSessionInfo();

        // Uma sessão incompleta não pode receber direitos por valores padrão.

        if ($sessao === null) {

            throw new \InvalidArgumentException('A sessão GLPI não está autenticada.');

        }

        $entidades = [];

        // A seleção nativa contém somente entidades escolhidas no contexto atual do GLPI.

        foreach ($sessao->getActiveEntitiesIds() as $id) {

            $entidade_id = self::normalizarEntidade($id);

            // A política própria nunca amplia os direitos nativos de entidade.

            if (Session::haveAccessToEntity($entidade_id)) {

                $entidades[] = $entidade_id;

            }

        }

        $contexto = [

            'tecnico_id' => $sessao->getUserId(),

            'perfil_id' => $sessao->getProfileId(),

            'grupos_ids' => [],

            'entidade_id' => $sessao->getCurrentEntityId(),

            'entidade_ativa_id' => $sessao->getCurrentEntityId(),

            'entidades_ativas_ids' => array_values(array_unique($entidades)),

            'super_administrador' => self::podeConfigurar(),

            'direitos_perfil' => [

                'operar' => (bool) Session::haveRight(PerfilDaFerramenta::DIREITO, READ),

                'administrar_setores' => (bool) Session::haveRight(PerfilDaFerramenta::DIREITO, UPDATE),

            ],

        ];

        // Valida também os tipos retornados pela sessão antes de construir consultas.

        if (!PoliticaDeVisibilidade::contextoValido($contexto)) {

            throw new \InvalidArgumentException('O contexto ativo do GLPI está inválido.');

        }

        return $contexto;

    }

    /**

     * Aceita somente identificadores decimais inteiros, incluindo a entidade raiz.

     */

    private static function normalizarEntidade(mixed $valor): int {

        // O iterador do GLPI pode fornecer os identificadores como texto decimal.

        if ((!is_int($valor) && (!is_string($valor) || !preg_match('/^(0|[1-9][0-9]*)$/D', $valor))) || filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {

            throw new \InvalidArgumentException('A seleção nativa de entidades está inválida.');

        }

        return (int) $valor;

    }

    /**

     * Protege URLs diretas e invalida resumos quando o perfil perde acesso.

     */

    public static function exigirAcesso(): void {

        Session::checkLoginUser();

        // Esconder menus não basta: a requisição também precisa ser interrompida.

        if (!self::podeAcessar()) {

            RepositorioDeRascunhos::invalidarTodosDaSessao();

            throw new AccessDeniedHttpException('Acesso não autorizado ao ADManagement.');

        }

    }

}

