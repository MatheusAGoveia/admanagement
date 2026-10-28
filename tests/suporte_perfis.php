<?php

use GlpiPlugin\Admanagement\PerfilDaFerramenta;

// Estes dublês existem somente nas suítes isoladas e não acessam banco, GLPI ou AD.

define('READ', 1);

define('UPDATE', 2);

/**

 * Carrega somente classes próprias do diretório do plugin usado na suíte.

 */

spl_autoload_register(

    /**

     * Mantém o contrato PSR-4 sem instalar dependências externas.

     */

    static function (string $classe): void {

        $prefixo = 'GlpiPlugin\\Admanagement\\';

        // Classes nativas são substituídas exclusivamente pelos dublês abaixo.

        if (str_starts_with($classe, $prefixo)) {

            require_once dirname(__DIR__) . '/src/' . substr($classe, strlen($prefixo)) . '.php';

        }

    }

);

/**

 * Representa a exceção de recusa HTTP do GLPI.

 */

class RecusaDeVisibilidade extends RuntimeException {

}

class_alias(RecusaDeVisibilidade::class, 'Glpi\\Exception\\Http\\AccessDeniedHttpException');

/**

 * Representa o ancestral dos itens usados pelas abas nativas.

 */

class CommonGLPI {

}

/**

 * Guarda documentos sintéticos de configuração somente na memória.

 */

class Config {

    public static array $valores = [];

    /**

     * Reproduz a seleção de chaves do contexto próprio.

     */

    public static function getConfigurationValues($contexto, $chaves): array {

        return array_intersect_key(self::$valores, array_flip($chaves));

    }

    /**

     * Preserva chaves não alteradas durante uma gravação do cenário.

     */

    public static function setConfigurationValues($contexto, $valores): void {

        self::$valores = array_replace(self::$valores, $valores);

    }

}

/**

 * Expõe o retrato tipado retornado pela API Session::getCurrentSessionInfo.

 */

class SessaoSintetica {

    /**

     * Devolve o usuário do cenário, sem usar parâmetros HTTP.

     */

    public function getUserId(): int {

        return $_SESSION['glpiID'];

    }

    /**

     * Devolve somente o perfil ativo do cenário.

     */

    public function getProfileId(): int {

        return $_SESSION['glpiactiveprofile']['id'];

    }

    /**

     * Devolve a seleção de entidades sem expandir o escopo.

     */

    public function getActiveEntitiesIds(): array {

        return $_SESSION['glpiactiveentities'];

    }

    /**

     * Devolve a entidade ativa explicitamente selecionada.

     */

    public function getCurrentEntityId(): int {

        return $_SESSION['glpiactive_entity'];

    }

}

/**

 * Mantém os direitos nativos independentes das configurações próprias.

 */

class Session {

    /**

     * Devolve a identidade conectada ou ausência de login.

     */

    public static function getLoginUserID(): int {

        return $_SESSION['glpiID'] ?? 0;

    }

    /**

     * Identifica a interface do perfil ativo.

     */

    public static function getCurrentInterface(): string {

        return $_SESSION['glpiactiveprofile']['interface'] ?? '';

    }

    /**

     * Devolve a entidade selecionada, incluindo a raiz.

     */

    public static function getActiveEntity(): int {

        return $_SESSION['glpiactive_entity'] ?? 0;

    }

    /**

     * Devolve as entidades previamente autorizadas pelo cenário.

     */

    public static function getActiveEntities(): array {

        return $_SESSION['glpiactiveentities'] ?? [];

    }

    /**

     * Mantém a fronteira nativa independente do mapa do plugin.

     */

    public static function haveAccessToEntity($id, $recursivo = false): bool {

        return in_array($id, self::getActiveEntities(), true);

    }

    /**

     * Reproduz o bitmask do perfil ativo, sem consultar outros perfis do usuário.

     */

    public static function haveRight($direito, $nivel): int|false {

        return (((int) ($_SESSION['glpiactiveprofile'][$direito] ?? 0)) & $nivel) ?: false;

    }

    /**

     * Reproduz o objeto oficial de sessão ou ausência de autenticação.

     */

    public static function getCurrentSessionInfo(): ?SessaoSintetica {

        return self::getLoginUserID() ? new SessaoSintetica() : null;

    }

    /**

     * Interrompe uma entrada sem login antes da leitura de recursos.

     */

    public static function checkLoginUser(): void {

        // Uma sessão sem identidade não chega a executar o fluxo protegido.

        if (!self::getLoginUserID()) {

            throw new RecusaDeVisibilidade();

        }

    }

}

/**

 * Controla ativação do plugin e registra a integração com abas no próprio processo.

 */

class Plugin {

    public static array $classes = [];

    /**

     * Devolve a ativação definida exclusivamente pelo cenário.

     */

    public static function isPluginActive($nome): bool {

        return $_SESSION['plugin_ativo'] ?? true;

    }

    /**

     * Registra o vínculo da aba sem modificar uma instalação GLPI.

     */

    public static function registerClass($classe, $opcoes): void {

        self::$classes[$classe] = $opcoes;

    }

}

/**

 * Representa catálogos locais com um item inexistente para testes de validação.

 */

class Group extends CommonGLPI {

    public array $fields = [];

    /**

     * Reproduz existência e campos mínimos do modelo nativo.

     */

    public function getFromDB($id): bool {

        $this->fields = ['id' => $id, 'interface' => 'central', 'name' => 'Registro sintético ' . $id];

        return $id !== 99;

    }

    /**

     * Mantém a verificação de leitura por recurso separada do mapa.

     */

    public function can($id, $direito): bool {

        return $this->getFromDB($id);

    }

    /**

     * Identifica o registro atualmente carregado no dublê.

     */

    public function getID(): int {

        return $this->fields['id'] ?? 0;

    }

    /**

     * Retorna um rótulo sintético para testar apresentação sem dados pessoais.

     */

    public function getName(): string {

        return $this->fields['name'] ?? '';

    }

}

/**

 * Representa perfis centrais e o critério nativo de administração de perfis.

 */

class Profile extends Group {

    public static array $super_ids = [40];

    public static array $matriz = [];

    /**

     * Devolve somente os perfis que o cenário identifica como administradores nativos.

     */

    public static function getSuperAdminProfilesId(): array {

        return self::$super_ids;

    }

    /**

     * Simula o destino nativo de atualização de perfil.

     */

    public static function getFormURL(): string {

        return '/front/profile.form.php';

    }

    /**

     * Captura as opções encaminhadas à matriz nativa sem persistir concessões.

     */

    public function displayRightsChoiceMatrix(array $direitos, array $opcoes): void {

        self::$matriz = $direitos;

        echo 'MATRIZ_NATIVA';

    }

}

/**

 * Representa entidades explicitamente selecionáveis.

 */

class Entity extends Group {

}

/**

 * Registra chaves de direitos sem atribuir valores positivos aos perfis.

 */

class ProfileRight {

    public static array $chaves = [];

    public static int $inclusoes = 0;

    /**

     * Devolve as chaves já registradas pelo cenário.

     */

    public static function getAllPossibleRights(): array {

        return self::$chaves;

    }

    /**

     * Registra nomes com direito zero e marca o número de operações de instalação.

     */

    public static function addProfileRights(array $chaves): bool {

        self::$inclusoes++;

        // O instalador adiciona a chave, sem conceder direitos por usuário ou grupo.

        foreach ($chaves as $chave) {

            self::$chaves[$chave] = 0;

        }

        return true;

    }

}

/**

 * Reproduz somente os helpers visuais necessários aos formulários de perfil e mapa.

 */

class Html {

    /**

     * Produz campos ocultos escapados como o helper nativo.

     */

    public static function hidden($nome, array $opcoes): string {

        return '<input type="hidden" name="' . htmlescape($nome) . '" value="' . htmlescape($opcoes['value']) . '">';

    }

    /**

     * Fecha o formulário e sinaliza a proteção CSRF nativa.

     */

    public static function closeForm(): void {

        echo '<input name="_glpi_csrf_token" value="sintetico"></form>';

    }

}

/**

 * Captura seletores nativos sem montar consultas a catálogos reais.

 */

class Dropdown {

    /**

     * Expõe o nome do seletor para conferir o contrato do formulário.

     */

    public static function show($classe, array $opcoes): void {

        echo '<select name="' . htmlescape($opcoes['name']) . '"></select>';

    }

    /**

     * Reproduz o sufixo automático da seleção múltipla de Dropdown::showFromArray.

     */

    public static function showFromArray($nome, array $opcoes, array $configuracao): void {

        $multiplo = ($configuracao['multiple'] ?? false) === true;

        $nome_enviado = $multiplo ? $nome . '[]' : $nome;

        // O campo oculto nativo permite remover todas as escolhas do seletor múltiplo.

        if ($multiplo) {

            echo '<input type="hidden" name="' . htmlescape($nome) . '" value="">';

        }

        echo '<select name="' . htmlescape($nome_enviado) . '"' . ($multiplo ? ' multiple' : '') . '></select>';

    }

}

/**

 * Escapa somente os valores sintéticos usados pela apresentação.

 */

function htmlescape($valor): string {

    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

}

/**

 * Reinicia a sessão de teste com direitos explícitos e sem acesso de laboratório.

 */

function iniciarSessaoDePerfil(int $direitos = 0, int $perfil_id = 4): void {

    $_SESSION = [

        'glpiID' => 9,

        'glpiactiveprofile' => ['id' => $perfil_id, 'name' => 'Perfil sintético', 'interface' => 'central', PerfilDaFerramenta::DIREITO => $direitos],

        'glpigroups' => [7],

        'glpiactive_entity' => 2,

        'glpiactiveentities' => [2, 3],

    ];

    Config::$valores = [];

}

/**

 * Conta verificações e identifica uma regressão sem depender de um framework externo.

 */

function conferirPerfil(bool $condicao, string $cenario): void {

    $GLOBALS['verificacoes_perfil'] = ($GLOBALS['verificacoes_perfil'] ?? 0) + 1;

    // Uma falha encerra a suíte com o comportamento que deixou de ser preservado.

    if (!$condicao) {

        throw new RuntimeException('Falhou: ' . $cenario);

    }

}

/**

 * Confere uma recusa esperada sem aceitar qualquer exceção como sucesso.

 */

function exigirRecusaDePerfil(callable $acao, string $cenario): void {

    $recusou = false;

    try {

        $acao();

    } catch (InvalidArgumentException | DomainException | RecusaDeVisibilidade $erro) {

        // Somente validação de domínio e autorização satisfazem o cenário.

        $recusou = true;

    }

    conferirPerfil($recusou, $cenario);

}
