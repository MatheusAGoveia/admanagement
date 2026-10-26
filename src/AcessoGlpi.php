<?php

namespace GlpiPlugin\Admanagement;

use Glpi\Exception\Http\AccessDeniedHttpException;

use Plugin;

use Session;

/**
 * Confere a sessão GLPI e resolve concessões por usuário, grupo ou perfil ativo.
 */

final class AcessoGlpi {

    /**
     * Exige Super-Admin ativo e direito nativo para administrar a configuração global.
     */
    public static function podeConfigurar(): bool {

        // A configuração só fica disponível enquanto o plugin está ativo.

        if (!Plugin::isPluginActive('admanagement')) {

            return false;

        }

        // Sem um usuário conectado, nenhuma permissão administrativa é consultada.

        if (!Session::getLoginUserID()) {

            return false;

        }

        // A interface central corresponde à área técnica do GLPI.

        if (Session::getCurrentInterface() !== 'central') {

            return false;

        }

        // UPDATE é a permissão nativa para alterar configurações do GLPI.

        return ($_SESSION['glpiactiveprofile']['name'] ?? null) === 'Super-Admin'

            && Session::haveRight('config', UPDATE);

    }

    /**
     * Protege leitura e alteração das configurações, inclusive na primeira instalação.
     */

    public static function exigirConfiguracao(): void {

        // O GLPI trata primeiro a ausência de login; depois conferimos a configuração.

        Session::checkLoginUser();

        // Nega a configuração quando o usuário não possui o direito administrativo nativo.

        if (!self::podeConfigurar()) {

            throw new AccessDeniedHttpException('Você não tem permissão para configurar o ADManagement.');

        }

    }

    /**
     * Confere a sessão nativa e aplica a política ao contexto atual em cada chamada.
     */

    public static function podeAcessar(): bool {

        // Primeira etapa: confirmar plugin ativo, login e interface do técnico.

        if (!Plugin::isPluginActive('admanagement')) {

            return false;

        }

        // Nega operação sem usuário conectado ao GLPI.

        if (!Session::getLoginUserID()) {

            return false;

        }

        // Restringe o fluxo operacional à interface central dos técnicos.

        if (Session::getCurrentInterface() !== 'central') {

            return false;

        }

        // A entidade precisa estar definida; sua ausência não equivale à entidade raiz.

        if (!isset($_SESSION['glpiactive_entity'])) {

            return false;

        }

        // A regra própria do plugin não pode substituir a restrição nativa de entidade.

        if (!Session::haveAccessToEntity(Session::getActiveEntity())) {

            return false;

        }

        // Dados ausentes ficam como null para que a política negue o acesso.

        $perfil_atual = null;

        // Obtém o perfil ativo da sessão sem aceitar um perfil enviado pela página.

        if (isset($_SESSION['glpiactiveprofile']['id'])) {

            $perfil_atual = $_SESSION['glpiactiveprofile']['id'];

        }

        $grupos_atuais = null;

        // Normaliza os grupos nativos quando estão disponíveis na sessão.

        if (isset($_SESSION['glpigroups'])) {

            $grupos_atuais = self::normalizarGruposDaSessao($_SESSION['glpigroups']);

        }

        // Reúne os dados atuais da sessão para conferir as três permissões juntas.

        $contexto = [

            'perfil_atual' => $perfil_atual,

            'grupos_atuais' => $grupos_atuais,

            'entidade_atual' => Session::getActiveEntity(),

            // A exceção vem do perfil ativo e do direito nativo, sem aceitar indicadores da requisição.

            'super_administrador' => ($_SESSION['glpiactiveprofile']['name'] ?? null) === 'Super-Admin'

                && Session::haveRight('config', UPDATE),

        ];

        // Confere a estrutura nativa também antes de calcular concessões por usuário, grupo ou perfil.

        if (!ControleDeAcesso::avaliarPerfilGrupoEEntidade(array_replace($contexto, ['super_administrador' => true]), [])) {

            return false;

        }

        // O Super-Admin não depende de um documento próprio válido para reparar a configuração.

        if ($contexto['super_administrador']) {

            return true;

        }

        try {

            $politica = ConfiguracaoDeVisibilidade::consultar();

            // Política salva, mesmo vazia, substitui o acesso legado e nunca herda grupos silenciosamente.

            if ($politica !== null) {

                $nativo = self::montarContextoNativo();

                return PoliticaDeVisibilidade::resolver($nativo, $politica)['papel'] !== 'negado';

            }

        } catch (\DomainException | \InvalidArgumentException $erro) {

            // Documento inválido nega operação; o Super-Admin pode corrigi-lo na configuração.

            return false;

        }

        // Sem política nova, preserva entrada legada exclusivamente com papel técnico.

        $configuracao = $contexto['super_administrador']

            ? []

            : ConfiguracaoDeAcesso::consultar();

        return ControleDeAcesso::avaliarPerfilGrupoEEntidade($contexto, $configuracao);

    }

    /**
     * Reindexa somente o array numérico de grupos nativos, preservando a validação dos IDs.
     */

    private static function normalizarGruposDaSessao(

        mixed $grupos

    ): ?array {

        // O GLPI usa array_unique ao carregar grupos; os índices podem conter lacunas.

        if (!is_array($grupos)) {

            return null;

        }

        // Chaves textuais ou negativas não pertencem ao formato numérico esperado da sessão.

        foreach (array_keys($grupos) as $indice) {

            // Recusa chaves de grupo que não correspondem aos índices numéricos usados pelo GLPI.

            if (!is_int($indice) || $indice < 0) {

                return null;

            }

        }

        // Só reorganiza os índices: os valores continuam sujeitos à política de acesso.

        return array_values($grupos);

    }

    /**
     * Informa se a exceção local de acesso está ativa para identificar o teste nas telas.
     */

    public static function estaComAcessoTemporarioParaTestes(): bool {

        // O administrador precisa abrir a recuperação sem interpretar o documento corrompido.

        if (self::podeConfigurar()) {

            return false;

        }

        // A opção vem somente do arquivo local; texto "true" ou número 1 não ativam a exceção.

        // A política nova substitui também a exceção legada de laboratório.

        if (ConfiguracaoDeVisibilidade::consultar() !== null) {

            return false;

        }

        $configuracao = ConfiguracaoDeAcesso::consultar();

        // Mantém a exceção de laboratório desativada quando a opção local não existe.

        if (!isset($configuracao['acesso_temporario_para_testes'])) {

            return false;

        }

        return $configuracao['acesso_temporario_para_testes'] === true;

    }

    /**
     * Revalida o acesso e entrega os dados nativos usados na fila e nos rascunhos.
     */

    public static function obterContextoDoTecnico(): array {

        // As entradas podem chamar este método diretamente; a autorização é obrigatória aqui.

        self::exigirAcesso();

        $contexto = self::montarContextoNativo();

        $super = $contexto['super_administrador'];

        $politica = $super ? null : ConfiguracaoDeVisibilidade::consultar();

        // A transição mantém somente atribuição pessoal, sem conceder gestão de setores.

        if (!$super && $politica === null) {

            $contexto['visibilidade'] = ['papel' => 'tecnico', 'entidades_ids' => [$contexto['entidade_id']], 'vinculos' => [], 'regras_ids' => []];

        } else {

            // Resolve o alcance a cada requisição, sem receber papéis de URL ou formulário.

            $contexto['visibilidade'] = PoliticaDeVisibilidade::resolver($contexto, $politica ?? []);

        }

        $contexto['assinatura_visibilidade'] = ConfiguracaoDeVisibilidade::assinatura($politica);

        RepositorioDeRascunhos::conferirContexto($contexto);

        return $contexto;

    }

    /**
     * Normaliza identidade e entidades da sessão; jamais usa valores enviados pela página.
     */

    private static function montarContextoNativo(): array {

        $grupos = array_map('intval', array_values($_SESSION['glpigroups'] ?? []));

        $entidades = [];

        // A lista nativa já representa a seleção de entidades, inclusive a recursividade do GLPI.

        foreach ($_SESSION['glpiactiveentities'] ?? [Session::getActiveEntity()] as $id) {

            // Rejeita valores parciais antes de consultar o direito nativo, inclusive para a raiz.

            if ((!is_int($id) && (!is_string($id) || !preg_match('/^(0|[1-9][0-9]*)$/D', $id))) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {

                throw new \InvalidArgumentException('A seleção nativa de entidades está inválida.');

            }

            // A configuração própria nunca amplia os direitos de entidade do GLPI.

            if (Session::haveAccessToEntity((int) $id)) {

                $entidades[] = (int) $id;

            }

        }

        return [

            'tecnico_id' => (int) Session::getLoginUserID(),

            'perfil_id' => (int) $_SESSION['glpiactiveprofile']['id'],

            'grupos_ids' => $grupos,

            'entidade_id' => (int) Session::getActiveEntity(),

            'entidade_ativa_id' => (int) Session::getActiveEntity(),

            'entidades_ativas_ids' => array_values(array_unique($entidades)),

            'super_administrador' => self::podeConfigurar(),

        ];

    }

    /**
     * Protege também a URL direta, antes de qualquer saída da página do técnico.
     */

    public static function exigirAcesso(): void {

        // Lançar a exceção interrompe a requisição em vez de apenas esconder o menu.

        Session::checkLoginUser();

        // Invalida resumos e interrompe a requisição quando o acesso operacional foi revogado.

        if (!self::podeAcessar()) {

            RepositorioDeRascunhos::invalidarTodosDaSessao();

            throw new AccessDeniedHttpException('Acesso não autorizado ao ADManagement.');

        }

    }

}

