<?php

// Percorre as entradas PHP em processos isolados, sem instalar GLPI ou acessar AD.

// Os substitutos deste arquivo pertencem exclusivamente aos testes.

// A inclusão reproduz o escopo local do controlador legado, sem executar o kernel real do GLPI.

namespace Glpi\Exception\Http {

    /**
     * Representa a negação nativa durante o teste das entradas.
     */

    class AccessDeniedHttpException extends \RuntimeException {

    }

}

namespace Glpi\Exception {

    /**
     * Representa o sinal de redirecionamento; mensagem e código guardam os dados só do teste.
     */

    class RedirectException extends \Exception {

    }

}

namespace {

    use Glpi\Exception\Http\AccessDeniedHttpException;

    use Glpi\Exception\RedirectException;

    /**
     * Mantém o ancestral nativo usado pela aba de permissões do plugin.
     */

    class CommonGLPI {

    }

    /**
     * Identifica administradores por capacidade nativa, independentemente do nome do perfil.
     */

    class Profile extends CommonGLPI {

        /**
         * Reserva o perfil sintético 40 para a administração global dos cenários.
         */

        public static function getSuperAdminProfilesId(): array {

            return [40];

        }

        /**
         * Devolve o endereço de administração dos perfis sem carregar o GLPI real.
         */

        public static function getSearchURL(): string {

            return '/front/profile.php';

        }

    }

    /**
     * Reproduz o retrato tipado de identidade e entidades oferecido pela sessão nativa.
     */

    class SessaoDoFluxo {

        /**
         * Devolve somente a identidade conectada do cenário.
         */

        public function getUserId(): int {

            return $_SESSION['glpiID'];

        }

        /**
         * Devolve somente o perfil ativo, sem juntar perfis vinculados ao usuário.
         */

        public function getProfileId(): int {

            return $_SESSION['glpiactiveprofile']['id'];

        }

        /**
         * Devolve a seleção explícita das entidades nativas.
         */

        public function getActiveEntitiesIds(): array {

            return $_SESSION['glpiactiveentities'];

        }

        /**
         * Devolve a entidade ativa, mantendo a entidade raiz quando selecionada.
         */

        public function getCurrentEntityId(): int {

            return $_SESSION['glpiactive_entity'];

        }

    }

    define('READ', 1);

    define('UPDATE', 2);

    /**
     * Fornece somente a configuração operacional exigida pelo cenário.
     */

    class Config {

        /**
         * Devolve IDs que existem apenas na cópia temporária de teste.
         */

        public static function getConfigurationValues(

            $contexto,

            $chaves

        ): array {

            $valores = ['grupos_e_categorias' => '{"categorias_itil":[11]}'];

            // Cada requisição pode fornecer uma política nova inteiramente sintética.

            if (isset($GLOBALS['cenario_do_fluxo']['visibilidade'])) {

                $valores['visibilidade'] = json_encode($GLOBALS['cenario_do_fluxo']['visibilidade'], JSON_THROW_ON_ERROR);

            }

            return array_intersect_key($valores, array_flip($chaves));

        }

    }

    /**
     * Reproduz os contratos de sessão usados nas entradas PHP.
     */

    class Session {

        /**
         * Entrega o retrato nativo apenas quando o cenário possui usuário conectado.
         */

        public static function getCurrentSessionInfo(): ?SessaoDoFluxo {

            return self::getLoginUserID() ? new SessaoDoFluxo() : null;

        }


        /**
         * Obtém o usuário definido pelo cenário.
         */

        public static function getLoginUserID() {

            return $_SESSION['glpiID'];

        }

        /**
         * Obtém a interface definida pelo cenário.
         */

        public static function getCurrentInterface(): string {

            return $_SESSION['glpiactiveprofile']['interface'];

        }

        /**
         * Obtém a entidade definida pelo cenário.
         */

        public static function getActiveEntity(): int {

            return $_SESSION['glpiactive_entity'];

        }

        /**
         * Restringe o cenário à sua entidade de teste.
         */

        public static function haveAccessToEntity(

            $entidade,

            $recursivo = false

        ): bool {

            return in_array($entidade, $_SESSION['glpiactiveentities'] ?? [2], true);

        }

        /**
         * Mantém o técnico sem direitos administrativos.
         */

        public static function haveRight(

            $direito,

            $nivel

        ): int|false {

            // Configuração é uma capacidade administrativa separada do direito operacional.

            if ($direito === 'config') {

                return ($GLOBALS['cenario_do_fluxo']['configurar'] ?? false) ? $nivel : false;

            }

            return (((int) ($_SESSION['glpiactiveprofile'][$direito] ?? 0)) & $nivel) ?: false;

        }

        /**
         * Interrompe entradas sem autenticação.
         */

        public static function checkLoginUser(): void {

            // Reproduz a recusa nativa de acesso quando o cenário não tem usuário conectado.

            if (!self::getLoginUserID()) {

                throw new AccessDeniedHttpException();

            }

        }

        /**
         * Guarda a mensagem que acompanharia o redirecionamento real.
         */

        public static function addMessageAfterRedirect(

            $mensagem

        ): void {

            $_SESSION['mensagens_do_teste'][] = $mensagem;

        }

    }

    /**
     * Fornece a ativação e o endereço base apenas ao processo de teste.
     */

    class Plugin {

        /**
         * Mantém o plugin ativo no cenário autorizado.
         */

        public static function isPluginActive(

            $nome

        ): bool {

            return true;

        }

        /**
         * Usa o caminho esperado das entradas de um plugin nativo.
         */

        public static function getWebDir(

            $nome

        ): string {

            return '/plugins/admanagement';

        }

    }

    /**
     * Mantém a constante do papel de atribuição.
     */

    class CommonITILActor {

        public const ASSIGN = 2;

    }

    /**
     * Fornece o nome da tabela de técnicos atribuídos.
     */

    class Ticket_User {

        /**
         * Devolve o nome esperado pela consulta.
         */

        public static function getTable(): string {

            return 'glpi_tickets_users';

        }

    }

    /**
     * Fornece o nome da tabela de grupos atribuídos.
     */

    class Group_Ticket {

        /**
         * Devolve o nome esperado pela consulta.
         */

        public static function getTable(): string {

            return 'glpi_groups_tickets';

        }

    }

    /**
     * Permite negar o direito nativo independentemente da consulta de fila.
     */

    class Ticket {

        /**

         * Identifica o estado do chamado usando o contrato nativo de apresentação.

         */

        public static function getStatus(int $estado): string {

            return 'Estado sintético ' . $estado;

        }

        /**

         * Constrói o destino sintético de abertura do chamado.

         */

        public static function getFormURLWithID(int $id): string {

            return '/front/ticket.form.php?id=' . $id;

        }



        public const SOLVED = 5;

        public const CLOSED = 6;

        public static bool $leitura_permitida = true;

        /**
         * Devolve a tabela consultada pela fila.
         */

        public static function getTable(): string {

            return 'glpi_tickets';

        }

        /**
         * Reproduz o resultado da autorização nativa do cenário.
         */

        public function can(

            $identificador,

            $direito

        ): bool {

            return self::$leitura_permitida;

        }

    }

    /**
     * Reproduz a apresentação e o sinal de redirecionamento tratado fora da entrada PHP.
     */

    class Html {

        /**

         * Reproduz o escape dos campos ocultos emitidos pelos formulários nativos.

         */

        public static function hidden(string $nome, array $opcoes): string {

            return '<input type="hidden" name="' . htmlescape($nome) . '" value="' . htmlescape($opcoes['value']) . '">';

        }

        /**

         * Mantém tipo e escape dos campos textuais gerados pelo helper nativo.

         */

        public static function input(string $nome, array $opcoes): string {

            return '<input name="' . htmlescape($nome) . '" value="' . htmlescape($opcoes['value'] ?? '') . '">';

        }

        /**

         * Retorna o instante sintético para verificar a renderização sem configuração regional externa.

         */

        public static function convDateTime(string $instante): string {

            return $instante;

        }



        /**
         * Marca o início da saída autorizada.
         */

        public static function header(

            ...$argumentos

        ): void {

            echo '<header>Teste da entrada PHP</header>';

        }

        /**
         * Marca o encerramento da saída.
         */

        public static function footer(): void {

            echo '<footer>Fim da entrada</footer>';

        }

        /**
         * Mantém o contrato de fechamento do formulário, sem validar o listener do GLPI.
         */

        public static function closeForm(): void {

            echo '<input name="_glpi_csrf_token" value="exclusivo-do-teste"></form>';

        }

        /**
         * Lança a exceção do contrato GLPI 11; a entrada precisa deixá-la chegar ao controlador.
         */

        public static function redirect(

            $url

        ): never {

            throw new RedirectException($url, 302);

        }

    }

    /**
     * Reproduz o seletor nativo de usuários nos testes da visão global.
     */

    /**
     * Reproduz o seletor nativo sem consultas nem scripts de uma instalação real.
     */

    class Dropdown {

        /**
         * A API de catálogo preserva o nome recebido, inclusive os colchetes de seleção múltipla.
         */

        public static function show(string $classe, array $opcoes): void {

            echo '<select name="' . htmlescape($opcoes['name']) . '"></select>';

        }

        /**

         * Gera opções escapadas conforme a seleção sintética do cenário.

         */

        public static function showFromArray(string $nome, array $opcoes, array $configuracao): void {

            $nome_campo = $nome . (!empty($configuracao['multiple']) ? '[]' : '');

            echo '<select name="' . htmlescape($nome_campo) . '">';

            // Cada opção mantém seu valor textual, inclusive a opção de revisão pendente.

            foreach ($opcoes as $valor => $rotulo) {

                echo '<option value="' . htmlescape($valor) . '"' . ((string) $valor === (string) ($configuracao['value'] ?? '') ? ' selected' : '') . '>' . htmlescape($rotulo) . '</option>';

            }

            echo '</select>';

        }

    }

    /**
     * Reproduz o seletor nativo de usuários nos testes da visão global.
     */

    class User {

        /**
         * Mantém o nome do filtro para verificar sua integração à página.
         */

        public static function dropdown(array $opcoes): void {

            echo '<select name="' . $opcoes['name'] . '"></select>';

        }

    }

    /**
     * Escapa os textos durante a renderização local.
     */

    function htmlescape(

        $valor

    ): string {

        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

    }

    /**
     * Devolve registros controlados; não interpreta SQL nem comprova consultas em MariaDB.
     */

    class BancoDoFluxo {

        public array $consultas = [];

        /**
         * Recebe os registros e as condições de falha do cenário.
         */

        public function __construct(

            private array $cenario

        ) {

        }

        /**
         * Distingue as duas leituras e interrompe qualquer tabela inesperada.
         */

        public function request(

            $criterios

        ): ArrayIterator {

            $this->consultas[] = $criterios;

            // Simula falha interna do banco para conferir que a página não revela SQL.

            if (!empty($this->cenario['falha_banco'])) {

                throw new \RuntimeException('SQL_INTERNO_QUE_NAO_PODE_APARECER');

            }

            // Retorna os técnicos do cenário quando a página consulta os integrantes da equipe.

            if ($criterios['FROM'] === 'glpi_users') {

                return new ArrayIterator([

                    [

                        'id' => 9,

                        'name' => 'tecnico'

                    ]

                ]);

            }

            // Fornece os chamados do cenário para as consultas operacionais reproduzidas pela suíte.

            if ($criterios['FROM'] === 'glpi_tickets') {

                // Reproduz a ausência do chamado para testar o acesso direto a um recurso não disponível.

                if (!empty($this->cenario['sem_chamado'])) {

                    return new ArrayIterator([]);

                }

                $identificador = $criterios['WHERE']['AND'][0]['glpi_tickets.id'] ?? 1;

                // Não fornece um chamado diferente do único ID conhecido neste cenário isolado.

                if ($identificador !== 1) {

                    return new ArrayIterator([]);

                }

                $linha = [

                    'id' => 1,

                    'name' => 'Chamado <script>inseguro</script>',

                    'status' => 2,

                    'entities_id' => $this->cenario['entidade_chamado'] ?? 2,

                    'itilcategories_id' => $this->cenario['categoria_chamado'] ?? 11,

                    'date_mod' => '2026-10-21 10:00:00',

                    'is_deleted' => 0,

                ];

                $campos = [

                    'glpi_tickets_users.users_id' => $this->cenario['tecnico_atribuido'] ?? 9,

                    'glpi_groups_tickets.groups_id' => $this->cenario['grupo_atribuido'] ?? 7,

                ];

                // Os critérios da classe real são avaliados antes de devolver o registro da página.

                foreach ($linha as $campo => $valor) {

                    $campos['glpi_tickets.' . $campo] = $valor;

                }

                return new ArrayIterator(self::atendeCriterios($criterios['WHERE'], $campos) ? [$linha] : []);

            }

            // Retorna as origens de contrato somente para a tabela formmanager esperada pela aplicação.

            if ($criterios['FROM'] === 'glpi_plugin_formmanager_forms_atual') {

                return new ArrayIterator($this->cenario['formularios']);

            }

            throw new \RuntimeException('Tabela inesperada no teste.');

        }

        /**
         * Avalia conectivos e comparações do contrato de consulta sobre dados sintéticos.
         */

        private static function atendeCriterios(array $condicoes, array $campos, string $conector = 'AND'): bool {

            $resultados = [];

            // Ramos numéricos preservam o AND de cada par de grupo e entidade.

            foreach ($condicoes as $campo => $valor) {

                // Um ramo agrupado usa AND, independentemente do conector externo.

                if (is_int($campo)) {

                    $resultados[] = self::atendeCriterios($valor, $campos);

                } elseif ($campo === 'AND' || $campo === 'OR') {

                    // Conectivos explícitos conservam a precedência da consulta real.

                    $resultados[] = self::atendeCriterios($valor, $campos, $campo);

                } elseif ($campo === 'NOT') {

                    // Estados encerrados são retirados da fila operacional.

                    $resultados[] = !self::atendeCriterios($valor, $campos);

                } else {

                    // Compara um valor escalar ou a lista explícita de identificadores.

                    $resultados[] = is_array($valor) ? in_array($campos[$campo] ?? null, $valor, true) : ($campos[$campo] ?? null) === $valor;

                }

            }

            return $conector === 'OR' ? in_array(true, $resultados, true) : !in_array(false, $resultados, true);

        }

        /**
         * Mantém a tabela de origem presente neste teste.
         */

        public function tableExists(

            $tabela

        ): bool {

            return true;

        }

        /**
         * Permite retirar uma coluna obrigatória para testar a mensagem controlada.
         */

        public function listFields(

            $tabela

        ): array {

            $campos = array_fill_keys(array_keys($this->cenario['formularios'][0]), []);

            // Retira a coluna de aprovação para reproduzir uma instalação com estrutura incompatível.

            if (!empty($this->cenario['sem_status'])) {

                unset($campos['status']);

            }

            return $campos;

        }

    }

    /**
     * Confere uma condição e conta os cenários efetivamente executados.
     */

    function conferirFluxo(

        bool $condicao,

        string $descricao

    ): void {

        // Interrompe a suíte informando qual comportamento HTTP esperado não foi observado.

        if (!$condicao) {

            throw new \RuntimeException('Falhou: ' . $descricao);

        }

        $GLOBALS['verificacoes_do_fluxo']++;

    }

    /**
     * Executa uma entrada em outro processo, preservando o redirecionamento por exceção.
     */

    function executarEntrada(

        array $cenario,

        string $pasta

    ): array {

        $entrada = $pasta . '/entrada.json';

        $cenario['resultado'] = $pasta . '/resultado.json';

        file_put_contents($entrada, json_encode($cenario, JSON_THROW_ON_ERROR));

        $processo = proc_open(

            [

                PHP_BINARY,

                __FILE__,

                '--requisicao',

                $entrada

            ],

            [

                1 => [

                    'pipe',

                    'w'

                ],

                2 => [

                    'pipe',

                    'w'

                ]

            ],

            $canais

        );

        // Recusa continuar quando o processo que executaria a entrada PHP não pode ser iniciado.

        if (!is_resource($processo)) {

            throw new \RuntimeException('Não foi possível iniciar o processo de teste.');

        }

        $saida = stream_get_contents($canais[1]);

        $erros = stream_get_contents($canais[2]);

        fclose($canais[1]);

        fclose($canais[2]);

        $codigo = proc_close($processo);

        // Trata erro de processo ou saída inesperada como falha real do cenário HTTP.

        if ($codigo !== 0 || $saida !== '' || $erros !== '') {

            throw new \RuntimeException('Erro no processo: ' . $saida . $erros);

        }

        return json_decode(file_get_contents($cenario['resultado']), true, 512, JSON_THROW_ON_ERROR);

    }

    // O processo filho executa uma única entrada, com os dados recebidos do teste principal.

    if (($argv[1] ?? '') === '--requisicao') {

        $cenario = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);

        $GLOBALS['cenario_do_fluxo'] = $cenario;

        define('GLPI_VAR_DIR', dirname($cenario['plugin'], 2) . '/var');

        $_SESSION = $cenario['sessao'];

        $_GET = $cenario['get'] ?? [];

        $_POST = $cenario['post'] ?? [];

        $_SERVER['REQUEST_METHOD'] = $cenario['metodo'] ?? 'GET';

        $_SERVER['PHP_SELF'] = '/plugins/admanagement/front/' . $cenario['tela'];

        Ticket::$leitura_permitida = $cenario['leitura_permitida'] ?? true;

        $GLOBALS['DB'] = new BancoDoFluxo($cenario);

        $redirecionamento_do_teste = null;

        http_response_code(200);

        ob_start();

        // A captura ocorre também depois que o controlador de teste recebe o redirecionamento.

        register_shutdown_function(

            /**
             * Captura HTML, sessão, status, redirecionamento e consultas no encerramento do
             * processo de teste.
             */

            function () use ($cenario): void {

                // Guarda somente evidências do cenário isolado, nunca dados de uma instalação.

                file_put_contents(

                    $cenario['resultado'],

                    json_encode(

                        [

                            'codigo' => http_response_code(),

                            'html' => ob_get_clean(),

                            'sessao' => $_SESSION,

                            'url' => $GLOBALS['redirecionamento_do_teste'],

                            'consultas' => $GLOBALS['DB']->consultas,

                        ],

                        JSON_THROW_ON_ERROR

                    )

                );

            }

        );

        spl_autoload_register(

            /**
             * Carrega as classes próprias a partir da cópia temporária usada por esta requisição.
             */

            function (

                string $classe

            ) use ($cenario): void {

                // Resolve apenas o namespace próprio na cópia temporária.

                $prefixo = 'GlpiPlugin\\Admanagement\\';

                // Restringe o carregamento automático ao namespace do plugin que está sendo exercitado.

                if (str_starts_with($classe, $prefixo)) {

                    require $cenario['plugin'] . '/src/' . substr($classe, strlen($prefixo)) . '.php';

                }

            }

        );

        try {

            // O GLPI 11 inclui a entrada em escopo de método; o front precisa importar seus globais.

            (

                /**
                 * Inclui a entrada em escopo local para reproduzir o controlador legado do GLPI.
                 */

                static function (

                    string $arquivo

                ): void {

                    require $arquivo;

                }

            )($cenario['plugin'] . '/front/' . $cenario['tela']);

        } catch (AccessDeniedHttpException $erro) {

            // Converte a recusa nativa reproduzida em resposta 403 observável pelo teste.

            http_response_code(403);

        } catch (RedirectException $redirecionamento) {

            // Guarda o destino do redirecionamento sem transformar esse sinal em erro de página.

            // O tratamento fica fora da entrada, sem reproduzir o kernel ou a resposta nativa inteira.

            $GLOBALS['redirecionamento_do_teste'] = $redirecionamento->getMessage();

            http_response_code($redirecionamento->getCode());

        }

        exit;

    }

    // Toda autorização vem do perfil sintético; arquivos legados não concedem permissões.

    $verificacoes_do_fluxo = 0;

    $pasta = sys_get_temp_dir() . '/admanagement-fluxo-' . bin2hex(random_bytes(6));

    $plugin = $pasta . '/plugins/admanagement';

    mkdir($plugin . '/config', 0700, true);

    mkdir($plugin . '/src');

    mkdir($plugin . '/front');

    mkdir($pasta . '/inc');

    try {

        // Prepara os diretórios de código necessários para executar as entradas na cópia isolada.

        foreach ([

            'src',

            'front'

        ] as $diretorio) {

            // Copia cada arquivo PHP do diretório correspondente para o laboratório desta suíte.

            foreach (glob(dirname(__DIR__) . '/' . $diretorio . '/*.php') as $arquivo) {

                copy($arquivo, $plugin . '/' . $diretorio . '/' . basename($arquivo));

            }

        }

        file_put_contents($pasta . '/inc/includes.php', '<?php // Bootstrap exclusivo dos testes.');

        file_put_contents($plugin

            . '/config/acesso.php', '<?php return ["perfis_permitidos" => [4], "entidades_permitidas" => [2]];');

        $base = [

            'plugin' => $plugin,

            'tela' => 'revisao.php',

            'get' => ['id' => '1'],

            'sessao' => [

                'glpiID' => 9,

                'glpiactiveprofile' => [

                    'id' => 4,

                    'interface' => 'central',

                    'plugin_admanagement' => READ

                ],

                'glpigroups' => [7],

                'glpiactive_entity' => 2,

                'glpiactiveentities' => [2],

            ],

            'formularios' => [

                [

                    'id' => '101',

                    'status' => '3',

                    'name' => 'Ana Civil Campos',

                    'nome_social' => 'Ana Luiza Campos',

                    'usar_nome_social' => '1',

                    'matricula' => '123',

                    'entities_id' => '2',

                    'req_servico_rede' => '1',

                    'tipo_servico_rede' => 'CADASTRO',

                    'usuario_rede' => '',

                    'tickets_id_rede' => '1',

                    'tickets_id_email' => '0',

                    'tickets_id_sistema_saude' => '0',

                    'tickets_id_sei' => '0',

                ]

            ],

        ];

        // Os cenários compartilham identidades, sem transformar seus mapas em concessões de direitos.

        $politica_tecnica = ['versao' => 2, 'setores' => [], 'regras' => []];

        $individual = array_replace($base, ['visibilidade' => $politica_tecnica]);

        $politica_setorial = [

            'versao' => 2,

            'setores' => [['id' => 1, 'nome' => 'Rede', 'grupos_ids' => [6], 'entidades_ids' => [2]]],

            'regras' => [['id' => 1, 'origem' => 'perfil', 'alvo_id' => 4, 'setores_ids' => [1], 'entidades_ids' => [2], 'ativa' => true]],

        ];

        $super_global = array_replace($individual, ['configurar' => true]);

        $super_global['sessao']['glpiactiveprofile']['id'] = 40;

        // Esta partição mantém as regressões de perfis separadas das demais requisições HTTP.

        if (($argv[1] ?? '') === '--perfis-nativos') {

            // O direito nativo READ autoriza o técnico sem concessões pessoais ou participação em grupos.

            $politica_tecnica = ['versao' => 2, 'setores' => [], 'regras' => []];

            $individual = array_replace($base, ['visibilidade' => $politica_tecnica]);

            $individual['sessao']['glpigroups'] = [];

            conferirFluxo(executarEntrada($individual, $pasta)['codigo'] === 200, 'HTTP direito do perfil ativo sem grupo');

            $outro_atribuido = array_replace($individual, ['tecnico_atribuido' => 10, 'metodo' => 'POST', 'post' => ['chamado_id' => '1', 'acao' => 'importar']]);

            $recusa_individual = executarEntrada($outro_atribuido, $pasta);

            conferirFluxo($recusa_individual['codigo'] === 403 && !isset($recusa_individual['sessao']['admanagement_rascunhos']), 'POST direto não importa chamado de outro técnico');

            $politica_setorial = $politica_tecnica;

            $politica_setorial['setores'] = [['id' => 1, 'nome' => 'Rede', 'grupos_ids' => [6], 'entidades_ids' => [2]]];

            $politica_setorial['regras'] = [[

                'id' => 1,

                'origem' => 'perfil',

                'alvo_id' => 4,

                'setores_ids' => [1],

                'entidades_ids' => [2],

                'ativa' => true,

            ]];

            $setorial = array_replace($outro_atribuido, ['visibilidade' => $politica_setorial, 'grupo_atribuido' => 6]);

            $setorial['sessao']['glpiactiveprofile']['plugin_admanagement'] = UPDATE;

            conferirFluxo(executarEntrada($setorial, $pasta)['codigo'] === 302, 'administrador importa chamado do setor atribuído a outro técnico');

            $setorial['grupo_atribuido'] = 3;

            conferirFluxo(executarEntrada($setorial, $pasta)['codigo'] === 403, 'POST de administrador não ultrapassa setor');

            $fora_categoria = array_replace($individual, ['tela' => 'index.php', 'get' => [], 'categoria_chamado' => 12]);

            $visivel_sem_importar = executarEntrada($fora_categoria, $pasta);

            conferirFluxo($visivel_sem_importar['codigo'] === 200 && str_contains($visivel_sem_importar['html'], '#1') && str_contains($visivel_sem_importar['html'], 'Fora das categorias operacionais'), 'categoria não esconde chamado e explica impedimento');

            $fora_categoria['tela'] = 'revisao.php';

            $fora_categoria['metodo'] = 'POST';

            $fora_categoria['post'] = ['chamado_id' => '1', 'acao' => 'importar'];

            conferirFluxo(executarEntrada($fora_categoria, $pasta)['codigo'] === 409, 'POST mantém categoria operacional');

            $filha = array_replace($individual, ['entidade_chamado' => 3, 'metodo' => 'POST', 'post' => ['chamado_id' => '1', 'acao' => 'importar']]);

            $filha['sessao']['glpiactiveentities'] = [2, 3];

            $filha['formularios'][0]['entities_id'] = '3';

            $importacao_filha = executarEntrada($filha, $pasta);

            conferirFluxo($importacao_filha['codigo'] === 302 && isset($importacao_filha['sessao']['admanagement_rascunhos'][9][3][1]), 'rascunho é guardado na entidade real do chamado filho');

            $filha['sessao'] = $importacao_filha['sessao'];

            $filha['metodo'] = 'GET';

            $filha['post'] = [];

            conferirFluxo(executarEntrada($filha, $pasta)['codigo'] === 200, 'reabre revisão na entidade filha autorizada');

            // Preparar em entidade filha mantém o contexto ativo da sessão e a autoria na entidade real.

            $preparo_filha = $filha;

            $preparo_filha['metodo'] = 'POST';

            $preparo_filha['post'] = ['chamado_id' => '1', 'acao' => 'preparar_resumo', 'revisao' => '1'];

            $resumo_filha = executarEntrada($preparo_filha, $pasta);

            conferirFluxo($resumo_filha['codigo'] === 302, 'prepara resumo do chamado em entidade filha');

            $leitura_filha = $filha;

            $leitura_filha['sessao'] = $resumo_filha['sessao'];

            $resumo_reaberto = executarEntrada($leitura_filha, $pasta);

            conferirFluxo(

                $resumo_reaberto['codigo'] === 200

                && isset($resumo_reaberto['sessao']['admanagement_rascunhos'][9][3][1]['resumo']['desafio'])

                && $resumo_reaberto['sessao']['admanagement_rascunhos'][9][3][1]['revisao'] === 1,

                'reabrir entidade filha não invalida resumo sem troca de contexto'

            );

            $confirmar_filha = $preparo_filha;

            $confirmar_filha['sessao'] = $resumo_reaberto['sessao'];

            $confirmar_filha['post'] = [

                'chamado_id' => '1',

                'acao' => 'confirmar_revisao',

                'revisao' => '1',

                'confirmar_revisao' => '1',

                'desafio' => $resumo_filha['sessao']['admanagement_rascunhos'][9][3][1]['resumo']['desafio'],

            ];

            $filha_confirmada = executarEntrada($confirmar_filha, $pasta);

            conferirFluxo(

                $filha_confirmada['codigo'] === 302

                && isset($filha_confirmada['sessao']['admanagement_rascunhos'][9][3][1]['confirmacao']),

                'confirma resumo do chamado filho mantendo a entidade ativa original'

            );

            $troca_entidade = $leitura_filha;

            $troca_entidade['sessao']['glpiactive_entity'] = 3;

            $entidade_observada = executarEntrada($troca_entidade, $pasta);

            conferirFluxo(

                $entidade_observada['codigo'] === 200

                && !isset($entidade_observada['sessao']['admanagement_rascunhos'][9][3][1]['resumo'])

                && $entidade_observada['sessao']['admanagement_rascunhos'][9][3][1]['revisao'] === 2,

                'troca real da entidade ativa invalida resumo mesmo quando o chamado continua autorizado'

            );

            $confirmar_filha['sessao'] = $entidade_observada['sessao'];

            $confirmar_filha['sessao']['glpiactive_entity'] = 2;

            conferirFluxo(

                executarEntrada($confirmar_filha, $pasta)['codigo'] === 409,

                'restaurar entidade ativa não recupera desafio invalidado'

            );

            $super_global = array_replace($outro_atribuido, ['visibilidade' => ['versao' => 2, 'setores' => [], 'regras' => []], 'configurar' => true]);

            $super_global['sessao']['glpiactiveprofile']['name'] = 'Administrador renomeado';

            $super_global['sessao']['glpiactiveprofile']['id'] = 40;

            conferirFluxo(executarEntrada($super_global, $pasta)['codigo'] === 302, 'Super-Admin importa sem atribuição pessoal e sem regras próprias');

            // Registra uma ação de outro ator para conferir a hierarquia da auditoria real da ferramenta.

            $outro_ator = $setorial;

            $outro_ator['grupo_atribuido'] = 6;

            $outro_ator['sessao']['glpiID'] = 10;

            conferirFluxo(executarEntrada($outro_ator, $pasta)['codigo'] === 302, 'outro ator autorizado registra evento sintético de auditoria');

            $auditoria_setorial = array_replace($setorial, [

                'tela' => 'audit.php',

                'metodo' => 'GET',

                'get' => [],

                'post' => [],

                'grupo_atribuido' => 6,

            ]);

            $eventos_do_setor = executarEntrada($auditoria_setorial, $pasta);

            conferirFluxo(

                $eventos_do_setor['codigo'] === 200 && str_contains($eventos_do_setor['html'], '<td>#10</td>'),

                'administrador consulta eventos de outro ator somente no setor do perfil'

            );

            $auditoria_setorial['grupo_atribuido'] = 3;

            $eventos_fora_do_setor = executarEntrada($auditoria_setorial, $pasta);

            conferirFluxo(

                $eventos_fora_do_setor['codigo'] === 200 && !str_contains($eventos_fora_do_setor['html'], '<td>#10</td>'),

                'administrador não lê evento depois que chamado deixa o setor autorizado'

            );

            $auditoria_global = array_replace($super_global, ['tela' => 'audit.php', 'metodo' => 'GET', 'get' => [], 'post' => []]);

            $eventos_globais = executarEntrada($auditoria_global, $pasta);

            conferirFluxo(

                $eventos_globais['codigo'] === 200 && str_contains($eventos_globais['html'], '<td>#10</td>'),

                'superadministrador consulta eventos de outros atores dentro das entidades nativas'

            );

            $auditoria_pessoal = array_replace($individual, ['tela' => 'audit.php', 'get' => []]);

            $eventos_pessoais = executarEntrada($auditoria_pessoal, $pasta);

            conferirFluxo(

                $eventos_pessoais['codigo'] === 200

                && str_contains($eventos_pessoais['html'], '<td>#9</td>')

                && !str_contains($eventos_pessoais['html'], '<td>#10</td>'),

                'técnico consulta somente seus eventos nos chamados ainda atribuídos a ele'

            );

            $auditoria_pessoal['tecnico_atribuido'] = 10;

            $eventos_reatribuidos = executarEntrada($auditoria_pessoal, $pasta);

            conferirFluxo(

                $eventos_reatribuidos['codigo'] === 200 && !str_contains($eventos_reatribuidos['html'], '<td>#9</td>'),

                'perda de atribuição oculta evento pessoal apesar do direito nativo de leitura'

            );

            // A configuração precisa apresentar o mapa, além de orientar a edição dos direitos nativos.

            $configuracao = array_replace($super_global, ['tela' => 'config.php', 'metodo' => 'GET', 'get' => [], 'post' => []]);

            $mapa_visivel = executarEntrada($configuracao, $pasta);

            conferirFluxo(

                $mapa_visivel['codigo'] === 200

                && str_contains($mapa_visivel['html'], 'name="perfil_id"')

                && str_contains($mapa_visivel['html'], 'name="setores_ids[]"')

                && !str_contains($mapa_visivel['html'], 'name="setores_ids[][]"'),

                'configuração apresenta vínculo por perfil e seletor múltiplo plano'

            );

            $configuracao['visibilidade'] = ['versao' => 1, 'setores' => [], 'regras' => []];

            $migracao_visivel = executarEntrada($configuracao, $pasta);

            conferirFluxo(

                $migracao_visivel['codigo'] === 200 && str_contains($migracao_visivel['html'], 'restaurar_visibilidade'),

                'configuração permite confirmar migração sem ocultar o documento anterior'

            );

            // Parâmetros malformados precisam produzir erro de entrada, sem consulta ao banco.

            foreach ([['tecnico' => 'abc'], ['pagina' => ['1']]] as $filtro_invalido) {

                $filtro_recusado = executarEntrada(array_replace($super_global, ['tela' => 'overview.php', 'get' => $filtro_invalido]), $pasta);

                conferirFluxo($filtro_recusado['codigo'] === 400 && $filtro_recusado['consultas'] === [], 'filtro malformado da equipe retorna 400 antes do banco');

            }

            echo $verificacoes_do_fluxo . ' verificações HTTP de perfis nativos concluídas.' . PHP_EOL;

            return;

        }

        $fila = executarEntrada(array_replace($base, [

            'tela' => 'index.php',

            'get' => []

        ]), $pasta);

        conferirFluxo($fila['codigo'] === 200, 'fila autorizada abre');

        conferirFluxo(

            str_contains($fila['html'], '&lt;script&gt;inseguro&lt;/script&gt;'),

            'título da fila escapado'

        );

        conferirFluxo(!isset($fila['sessao']['admanagement_rascunhos']), 'abrir fila não importa');

        $pagina = executarEntrada(

            array_replace($base, [

                'tela' => 'index.php',

                'get' => ['pagina' => '100001']

            ]),

            $pasta

        );

        conferirFluxo(

            $pagina['codigo'] === 400 && $pagina['consultas'] === [],

            'página fora do limite não consulta'

        );

        $leitura = executarEntrada($base, $pasta);

        conferirFluxo($leitura['codigo'] === 200, 'GET da importação abre');

        conferirFluxo(!isset($leitura['sessao']['admanagement_rascunhos']), 'GET não cria rascunho');

        $post = array_replace(

            $base,

            [

                'metodo' => 'POST',

                'post' => [

                    'chamado_id' => '1',

                    'acao' => 'importar'

                ]

            ]

        );

        $importado = executarEntrada($post, $pasta);

        conferirFluxo(

            $importado['codigo'] === 302 && $importado['html'] === '',

            'importação redireciona antes da saída'

        );

        conferirFluxo(

            $importado['url'] === '/plugins/admanagement/front/revisao.php?id=1',

            'redireciona ao chamado correto'

        );

        $rascunho = $importado['sessao']['admanagement_rascunhos'][9][2][1];

        conferirFluxo(

            $rascunho['formulario_id'] === 101 && $rascunho['revisao'] === 1,

            'importa vínculo real do cenário'

        );

        $base['sessao'] = $importado['sessao'];

        // Índices esparsos fazem parte do formato que a sessão real pode produzir.

        $grupos_esparsos = $base;

        $grupos_esparsos['sessao']['glpigroups'] = [

            0 => 7,

            2 => 8

        ];

        $revisao_esparsa = executarEntrada($grupos_esparsos, $pasta);

        conferirFluxo($revisao_esparsa['codigo'] === 200, 'revisão aceita grupos nativos com lacunas');

        $revisao = executarEntrada($base, $pasta);

        conferirFluxo(

            str_contains($revisao['html'], 'ana.campos') && str_contains($revisao['html'], 'ana.luiza'),

            'sugere a partir do nome social'

        );

        $salvar = array_replace(

            $base,

            [

                'metodo' => 'POST',

                'post' => [

                    'chamado_id' => '1',

                    'acao' => 'salvar',

                    'revisao' => '1',

                    'grupos' => '<script>grupo</script>',

                    'scripts' => 'logon.cmd',

                    'formulario_id' => '999',

                    'tecnico_id' => '999',

                    'ou' => 'injetada',

                ],

            ]

        );

        $salvo = executarEntrada($salvar, $pasta);

        conferirFluxo($salvo['codigo'] === 302, 'salvar revisão redireciona');

        $rascunho = $salvo['sessao']['admanagement_rascunhos'][9][2][1];

        conferirFluxo(

            $rascunho['revisao'] === 2 && $rascunho['scripts'] === 'logon.cmd',

            'salva campos manuais e avança revisão'

        );

        conferirFluxo(

            $rascunho['formulario_id'] === 101 && $rascunho['tecnico_id'] === 9 && $rascunho['ou'] === '',

            'ignora alterações de campos protegidos'

        );

        $base['sessao'] = $salvo['sessao'];

        $exibido = executarEntrada($base, $pasta);

        conferirFluxo(

            !str_contains($exibido['html'], '<script>grupo</script>'),

            'revisão escapa conteúdo manual'

        );

        $salvar['sessao'] = $base['sessao'];

        $conflito = executarEntrada($salvar, $pasta);

        conferirFluxo($conflito['codigo'] === 409, 'aba antiga recebe conflito');

        conferirFluxo(

            $conflito['sessao']['admanagement_rascunhos'][9][2][1] === $rascunho,

            'conflito preserva rascunho'

        );

        // Executa o grupo específico de regressões de resumo e invalidação quando solicitado pelo runner.

        if (($argv[1] ?? '') === '--regras-vigentes') {

            // Confirmação local nunca executa AD; os testes seguem POST -> redirect -> GET.

            $preparo = executarEntrada(

                array_replace(

                    $base,

                    [

                        'metodo' => 'POST',

                        'post' => [

                            'chamado_id' => '1',

                            'acao' => 'preparar_resumo',

                            'revisao' => '2'

                        ]

                    ]

                ),

                $pasta

            );

            conferirFluxo($preparo['codigo'] === 302, 'preparar resumo é uma ação POST');

            $preparado = $preparo['sessao']['admanagement_rascunhos'][9][2][1];

            conferirFluxo(

                isset($preparado['resumo']['desafio']) && !isset($preparado['confirmacao']),

                'preparo não é confirmação'

            );

            $confirmacao = array_replace(

                $base,

                [

                    'sessao' => $preparo['sessao'],

                    'metodo' => 'POST',

                    'post' => [

                        'chamado_id' => '1',

                        'acao' => 'confirmar_revisao',

                        'revisao' => '2',

                        'confirmar_revisao' => '1',

                        'desafio' => $preparado['resumo']['desafio']

                    ]

                ]

            );

            $confirmado = executarEntrada($confirmacao, $pasta);

            conferirFluxo(

                $confirmado['codigo'] === 302 && isset($confirmado['sessao']['admanagement_rascunhos'][9][2][1]['confirmacao']),

                'confirma revisão de trabalho'

            );

            $confirmacao['sessao'] = $confirmado['sessao'];

            conferirFluxo(

                executarEntrada($confirmacao, $pasta)['codigo'] === 409,

                'desafio consumido não confirma novamente'

            );

            $mudanca = array_replace($base, ['sessao' => $preparo['sessao']]);

            $mudanca['formularios'][0]['matricula'] = '456';

            $observado = executarEntrada($mudanca, $pasta);

            conferirFluxo(

                !isset($observado['sessao']['admanagement_rascunhos'][9][2][1]['resumo']) && $observado['sessao']['admanagement_rascunhos'][9][2][1]['revisao'] === 3,

                'origem observada invalida desafio definitivamente'

            );

            $confirmacao['sessao'] = $observado['sessao'];

            conferirFluxo(

                executarEntrada($confirmacao, $pasta)['codigo'] === 409,

                'origem voltar ao conteúdo antigo não revive revisão'

            );

            $revogacao = array_replace($base, ['sessao' => $preparo['sessao']]);

            $revogacao['formularios'][0]['status'] = '2';

            $revogado_resumo = executarEntrada($revogacao, $pasta);

            conferirFluxo(

                !isset($revogado_resumo['sessao']['admanagement_rascunhos'][9][2][1]['resumo']),

                'aprovação revogada apaga resumo anterior'

            );

            $sem_perfil_revisado = array_replace($base, ['sessao' => $preparo['sessao']]);

            $sem_perfil_revisado['sessao']['glpiactiveprofile']['id'] = 5;

            $sem_perfil_revisado['sessao']['glpiactiveprofile']['plugin_admanagement'] = 0;

            $recusa_revisao = executarEntrada($sem_perfil_revisado, $pasta);

            conferirFluxo(

                $recusa_revisao['codigo'] === 403 && !isset($recusa_revisao['sessao']['admanagement_rascunhos'][9][2][1]['resumo']),

                'acesso revogado invalida mesmo antes de consultar recurso'

            );

            $confirmacao['sessao'] = $recusa_revisao['sessao'];

            $confirmacao['sessao']['glpiactiveprofile']['id'] = 4;

            $confirmacao['sessao']['glpiactiveprofile']['plugin_admanagement'] = READ;

            conferirFluxo(

                executarEntrada($confirmacao, $pasta)['codigo'] === 409,

                'restaurar acesso não restaura desafio antigo'

            );

            $negacao_ticket = executarEntrada(

                array_replace($base, [

                    'sessao' => $preparo['sessao'],

                    'leitura_permitida' => false

                ]),

                $pasta

            );

            conferirFluxo(

                $negacao_ticket['codigo'] === 403 && !isset($negacao_ticket['sessao']['admanagement_rascunhos'][9][2][1]['resumo']),

                'perda de leitura no ticket invalida desafio'

            );

            $sem_ldap = executarEntrada(

                array_replace(

                    $base,

                    [

                        'metodo' => 'POST',

                        'post' => [

                            'chamado_id' => '1',

                            'acao' => 'consultar_ous',

                            'revisao' => '2',

                            'dominio' => '999'

                        ]

                    ]

                ),

                $pasta

            );

            conferirFluxo(

                $sem_ldap['codigo'] === 400 && $sem_ldap['sessao']['admanagement_rascunhos'][9][2][1]['revisao'] === 2,

                'domínio não configurado não inventa catálogo nem salva'

            );

            // Abre as quatro páginas novas para conferir autorização e renderização pelo mesmo contrato HTTP.

            foreach ([

                'overview.php',

                'historico.php',

                'pending.php',

                'audit.php'

            ] as $tela_nova) {

                $pagina_nova = executarEntrada(array_replace($base, [

                    'tela' => $tela_nova,

                    'get' => []

                ]), $pasta);

                conferirFluxo($pagina_nova['codigo'] === 200, 'nova página nativa ' . $tela_nova);

            }

            echo $verificacoes_do_fluxo . ' verificações HTTP de revisão vigente concluídas.' . PHP_EOL;

            return;

        }

        $alterado = $base;

        $alterado['formularios'][0]['matricula'] = '456';

        $origem = executarEntrada($alterado, $pasta);

        conferirFluxo(

            str_contains($origem['html'], 'confirmar_reimportacao'),

            'mudança na origem exige reimportação confirmada'

        );

        $revogado = $base;

        $revogado['formularios'][0]['status'] = '2';

        $revogado['metodo'] = 'POST';

        $revogado['post'] = [

            'chamado_id' => '1',

            'acao' => 'salvar',

            'revisao' => '2'

        ];

        $negado = executarEntrada($revogado, $pasta);

        conferirFluxo(

            $negado['codigo'] === 409 && !str_contains($negado['html'], 'name="grupos"'),

            'aprovação revogada bloqueia revisão'

        );

        $descartar = array_replace(

            $base,

            [

                'metodo' => 'POST',

                'post' => [

                    'chamado_id' => '1',

                    'acao' => 'descartar',

                    'revisao' => '2',

                    'confirmar_descarte' => '1'

                ],

            ]

        );

        $descartado = executarEntrada($descartar, $pasta);

        conferirFluxo($descartado['codigo'] === 302, 'descarte confirmado redireciona');

        $post['sessao'] = $descartado['sessao'];

        $novo = executarEntrada($post, $pasta);

        conferirFluxo(

            $novo['sessao']['admanagement_rascunhos'][9][2][1]['revisao'] === 3,

            'nova importação não recicla revisão'

        );

        $salvar['sessao'] = $novo['sessao'];

        $antigo = executarEntrada($salvar, $pasta);

        conferirFluxo($antigo['codigo'] === 409, 'aba anterior ao descarte permanece inválida');

        // Testa ausência do chamado e perda de leitura nativa como recusas independentes do acesso direto.

        foreach ([

            'sem_chamado' => true,

            'leitura_permitida' => false

        ] as $chave => $valor) {

            $sem_acesso = executarEntrada(array_replace($base, [$chave => $valor]), $pasta);

            conferirFluxo(

                $sem_acesso['codigo'] === 403 && $sem_acesso['html'] === '',

                'URL direta respeita ' . $chave

            );

        }

        $perfil = $base;

        $perfil['sessao']['glpiactiveprofile']['id'] = 5;

        $perfil['sessao']['glpiactiveprofile']['plugin_admanagement'] = 0;

        $sem_perfil = executarEntrada($perfil, $pasta);

        conferirFluxo(

            $sem_perfil['codigo'] === 403 && $sem_perfil['consultas'] === [],

            'perfil revogado bloqueia antes do banco'

        );

        $esquema = executarEntrada(array_replace($base, ['sem_status' => true]), $pasta);

        conferirFluxo(

            $esquema['codigo'] === 409 && str_contains($esquema['html'], 'Campos ausentes: status'),

            'esquema incompatível tem explicação'

        );

        $falha = executarEntrada(array_replace($base, ['falha_banco' => true]), $pasta);

        conferirFluxo(

            $falha['codigo'] === 500 && !str_contains($falha['html'], 'SQL_INTERNO'),

            'erro interno não expõe SQL'

        );

        // Um arquivo legado de laboratório não pode conceder direitos ao perfil ativo.

        file_put_contents(

            $plugin . '/config/acesso.php',

            '<?php return ["perfis_permitidos" => [], "entidades_permitidas" => [], "acesso_temporario_para_testes" => true];'

        );

        $temporario_liberado = executarEntrada($perfil, $pasta);

        conferirFluxo($temporario_liberado['codigo'] === 403, 'exceção legada não autoriza perfil sem direitos');

        conferirFluxo(

            !str_contains($temporario_liberado['html'], 'Acesso temporário para testes'),

            'não existe fluxo visual de acesso temporário'

        );

        $sem_grupo = $base;

        $sem_grupo['sessao']['glpigroups'] = [];

        $sem_grupo_liberado = executarEntrada($sem_grupo, $pasta);

        conferirFluxo($sem_grupo_liberado['codigo'] === 200, 'perfil autorizado opera sem grupo');

        $direito_negado = executarEntrada(array_replace($base, ['leitura_permitida' => false]), $pasta);

        conferirFluxo(

            $direito_negado['codigo'] === 403 && $direito_negado['html'] === '',

            'direito do perfil preserva leitura nativa'

        );

        $aprovacao_negada = executarEntrada($revogado, $pasta);

        conferirFluxo($aprovacao_negada['codigo'] === 409, 'direito do perfil não ignora aprovação da origem');

        $overview_super = $super_global;

        $overview_super['tela'] = 'overview.php';

        $overview_super['metodo'] = 'GET';

        $overview_super['get'] = [];

        $overview_super['post'] = [];

        $resultado_overview_super = executarEntrada($overview_super, $pasta);

        conferirFluxo($resultado_overview_super['codigo'] === 200 && str_contains($resultado_overview_super['html'], 'name="tecnico"'), 'visão global oferece filtro nativo por técnico');

        $pendencia_fora = array_replace($base, ['tela' => 'pending.php', 'visibilidade' => $politica_tecnica, 'tecnico_atribuido' => 10]);

        $pendencias_ocultas = executarEntrada($pendencia_fora, $pasta);

        conferirFluxo($pendencias_ocultas['codigo'] === 200 && !str_contains($pendencias_ocultas['html'], 'Chamado #1'), 'Pendências não expõe chamado após perder atribuição');

        echo $verificacoes_do_fluxo . ' verificações de fluxo concluídas.' . PHP_EOL;

    } finally {

        // Limpa os arquivos de cenário mesmo se uma das requisições testadas falhar.

        // Remove somente a árvore aleatória criada por este teste dentro do diretório temporário.

        $arquivos = new \RecursiveIteratorIterator(

            new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS),

            \RecursiveIteratorIterator::CHILD_FIRST

        );

        // Percorre a árvore temporária em ordem que permita remover primeiro seus arquivos e filhos.

        foreach ($arquivos as $arquivo) {

            // Apaga a pasta temporária somente depois de remover o conteúdo do cenário.

            if ($arquivo->isDir()) {

                rmdir($arquivo->getPathname());

            } else {

                // Remove o arquivo que pertence à cópia isolada do teste.

                unlink($arquivo->getPathname());

            }

        }

        rmdir($pasta);

    }

}

