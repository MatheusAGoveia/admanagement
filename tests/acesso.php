<?php

// Verifica permissões, configuração e entradas HTTP numa cópia temporária do plugin.

// As classes abaixo substituem o GLPI somente durante esta execução de teste.

namespace Glpi\Exception\Http {

    // Substitui somente o contrato de erro HTTP para os testes locais sem GLPI.

    class AccessDeniedHttpException extends \RuntimeException {

    }

}

namespace Glpi\Plugin {

    // Mantém o identificador oficial do hook no ambiente isolado de testes.

    final class Hooks {

        public const MENU_TOADD = 'menu_toadd';

        public const CONFIG_PAGE = 'config_page';

    }

}

namespace {

    use Glpi\Exception\Http\AccessDeniedHttpException;

    use GlpiPlugin\Admanagement\AcessoGlpi;

    use GlpiPlugin\Admanagement\ControleDeAcesso;

    use GlpiPlugin\Admanagement\MenuDaFerramenta;

    use GlpiPlugin\Admanagement\ConfiguracaoDaFila;

    // Mantém o valor do direito usado pelo código de configuração.

    define('UPDATE', 2);

    define('READ', 1);

    /**
     * Guarda as configurações em memória para verificar leituras e gravações.
     */

    class Config {

        public static array $valores = [];

        public static bool $falhar_gravacao = false;

        /**
         * Simula a leitura do contexto próprio sem acessar o banco.
         */

        public static function getConfigurationValues(

            $contexto,

            $chaves

        ): array {

            return self::$valores[$contexto] ?? [];

        }

        /**
         * Simula persistência ou falha para verificar a releitura após gravar.
         */

        public static function setConfigurationValues(

            $contexto,

            $valores

        ): void {

            // No dublê de configuração, persiste o valor somente quando o cenário não simula falha de gravação.

            if (!self::$falhar_gravacao) {

                self::$valores[$contexto] = array_replace(self::$valores[$contexto] ?? [], $valores);

            }

        }

    }

    /**
     * Representa o catálogo mínimo de grupos para os cenários de validação.
     */

    class Group {

        public array $fields = [];

        /**
         * Simula catálogo com item existente, inexistente e de entidade não autorizada.
         */

        public function getFromDB(

            $id

        ): bool {

            $this->fields = [

                'entities_id' => $id === 99 ? 3 : 2,

                'is_recursive' => false

            ];

            return $id !== 404;

        }

    }

    /**
     * Reutiliza o catálogo de teste para validar categorias com as mesmas condições.
     */

    class ITILCategory extends Group {

    }

    /**
     * Reutiliza o catálogo sintético para representar perfis administrativos.
     */

    class Profile extends Group {

    }

    /**
     * Reutiliza o catálogo sintético para representar entidades administrativas.
     */

    class Entity extends Group {

    }

    /**
     * Reproduz o seletor de usuários sem consultar dados pessoais reais.
     */

    class User extends Group {

        /**
         * Mostra o campo individual para conferir sua presença na configuração.
         */

        public static function dropdown(array $opcoes): void {

            echo '<select name="' . $opcoes['name'] . '"></select>';

        }

    }

    /**
     * Mostra apenas a marcação necessária para conferir os seletores da tela.
     */

    class Dropdown {

        /**
         * Preserva o nome recebido, como o helper nativo; multiple não acrescenta colchetes.
         */

        public static function show(

            $classe,

            $opcoes

        ): void {

            echo '<select multiple name="' . $opcoes['name'] . '"></select>';

        }

    }

    /**
     * Fornece o contrato de tabela para a fila vazia do teste administrativo.
     */

    class Ticket_User {

        /**
         * Identifica os atores individuais na consulta compartilhada.
         */

        public static function getTable(): string { return 'glpi_tickets_users'; }

    }

    /**
     * Fornece o contrato de grupos responsáveis durante a renderização da fila vazia.
     */

    class Group_Ticket {

        /**
         * Identifica os atores coletivos na consulta compartilhada.
         */

        public static function getTable(): string { return 'glpi_groups_tickets'; }

    }

    /**
     * Fornece estados e tabela nativos para a consulta sem registros deste cenário.
     */

    class Ticket {

        public const SOLVED = 5, CLOSED = 6;

        /**
         * Identifica a tabela de chamados, sem executar leitura externa.
         */

        public static function getTable(): string { return 'glpi_tickets'; }

    }

    /**
     * Mantém o papel atribuído no contrato de consulta.
     */

    class CommonITILActor {

        public const ASSIGN = 2;

    }

    $GLOBALS['DB'] =

    /**
     * Retorna uma fila vazia sem depender de infraestrutura externa nos testes administrativos.
     */

    new class {

        /**
         * Permite renderizar o estado vazio real da fila autorizada.
         */

        public function request(array $criterios): array { return []; }

    };

    /**
     * Escapa mensagens e URLs do formulário durante os testes de renderização.
     */

    function htmlescape(

        $valor

    ): string {

        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

    }

    /**
     * Interrompe o redirecionamento sem encerrar todo o processo dos testes.
     */

    class RedirecionamentoDoTeste extends \Exception {

    }

    /**
     * Reproduz as consultas de sessão usadas na autorização do plugin.
     */

    class Session {

        public static bool $administrador = false;

        /**
         * Simula exclusivamente o direito administrativo de atualizar configuração.
         */

        public static function haveRight(

            $direito,

            $nivel

        ): bool {

            return self::$administrador && $direito === 'config' && $nivel === UPDATE;

        }

        /**
         * Simula o registro de mensagem após o redirecionamento.
         */

        public static function addMessageAfterRedirect(

            $mensagem

        ): void {

        }

        /**
         * Simula o identificador nativo da sessão nos cenários locais.
         */

        public static function getLoginUserID() {

            return $_SESSION['glpiID'] ?? false;

        }

        /**
         * Simula a interface do perfil atualmente selecionado.
         */

        public static function getCurrentInterface() {

            return $_SESSION['glpiactiveprofile']['interface'] ?? false;

        }

        /**
         * Simula a entidade atual, incluindo o valor padrão do core.
         */

        public static function getActiveEntity() {

            return $_SESSION['glpiactive_entity'] ?? 0;

        }

        /**
         * Fornece o escopo nativo escolhido no laboratório para os seletores administrativos.
         */

        public static function getActiveEntities(): array {

            return $_SESSION['glpiactiveentities'] ?? [];

        }

        /**
         * Simula a restrição de entidades nativa, independente da configuração própria.
         */

        public static function haveAccessToEntity(

            $id,

            $recursivo = false

        ) {

            return in_array($id, $_SESSION['glpiactiveentities'] ?? [], true);

        }

        /**
         * Interrompe entradas sem sessão autenticada no teste de URL direta.
         */

        public static function checkLoginUser(): void {

            // Reproduz a recusa nativa de uma sessão sem usuário conectado.

            if (!self::getLoginUserID()) {

                throw new AccessDeniedHttpException();

            }

        }

    }

    /**
     * Fornece ativação e URL do plugin sem depender do carregamento do GLPI.
     */

    class Plugin {

        public static bool $ativo = true;

        /**
         * Simula a ativação do plugin sem consultar um banco GLPI.
         */

        public static function isPluginActive(

            $chave

        ): bool {

            return self::$ativo && $chave === 'admanagement';

        }

        /**
         * Simula a URL retornada pelo resolvedor nativo, inclusive marketplace.
         */

        public static function getWebDir(

            $chave

        ): string {

            return '/marketplace/' . $chave;

        }

    }

    /**
     * Marca os efeitos dos helpers visuais e simula o redirecionamento.
     */

    class Html {

        /**
         * Marca a renderização para detectar conteúdo exibido antes de autorizar.
         */

        public static function header(

            ...$argumentos

        ): void {

            echo 'CABECALHO';

        }

        /**
         * Marca o encerramento da página autorizada.
         */

        public static function footer(): void {

            echo 'RODAPE';

        }

        /**
         * Simula o token inserido pelo helper nativo no fechamento do formulário.
         */

        public static function closeForm(): void {

            echo '<input name="_glpi_csrf_token" value="token-teste"></form>';

        }

        /**
         * Interrompe o fluxo após redirecionamento sem encerrar a suíte inteira.
         */

        public static function redirect(

            $url

        ): void {

            throw new RedirecionamentoDoTeste($url);

        }

    }

    /**
     * Compara o resultado esperado e interrompe o teste com identificação do cenário.
     */

    function conferir(

        bool $condicao,

        string $cenario

    ): void {

        // Interrompe a suíte e identifica o cenário cuja condição esperada não foi atendida.

        if (!$condicao) {

            throw new \RuntimeException('Falhou: ' . $cenario);

        }

        echo 'OK: ' . $cenario . PHP_EOL;

    }

    // Todos os arquivos de trabalho ficam numa cópia isolada no diretório temporário.

    $raiz = dirname(__DIR__);

    $temporario = sys_get_temp_dir() . '/admanagement-test-' . bin2hex(random_bytes(6));

    $plugin = $temporario . '/plugins/admanagement';

    mkdir($plugin, 0700, true);

    try {

        // Os testes escrevem configurações apenas numa cópia isolada temporária.

        foreach ([

            'src',

            'config',

            'front'

        ] as $pasta) {

            $arquivos = new \RecursiveIteratorIterator(

                new \RecursiveDirectoryIterator($raiz . '/' . $pasta, \FilesystemIterator::SKIP_DOTS)

            );

            // Copia os arquivos do plugin para o laboratório isolado usado pelos testes de acesso.

            foreach ($arquivos as $arquivo) {

                // Ignora diretórios durante a cópia dos arquivos que formam o cenário.

                if (!$arquivo->isFile()) {

                    continue;

                }

                $destino = $plugin . substr($arquivo->getPathname(), strlen($raiz));

                // Cria a pasta de destino necessária antes de copiar o arquivo do cenário.

                if (!is_dir(dirname($destino))) {

                    mkdir(dirname($destino), 0700, true);

                }

                copy($arquivo->getPathname(), $destino);

            }

        }

        mkdir($temporario . '/inc');

        file_put_contents($temporario . '/inc/includes.php', '<?php // Bootstrap simulado para testar a entrada.');

        // O autoload aponta para a cópia temporária, preservando os arquivos do projeto.

        spl_autoload_register(

            /**
             * Carrega as classes do plugin pela cópia temporária para não modificar o projeto
             * original.
             */

            function (

                string $classe

            ) use ($plugin): void {

                // Resolve somente as classes próprias da cópia usada pelo teste.

                $prefixo = 'GlpiPlugin\\Admanagement\\';

                // Carrega somente o namespace próprio que pertence ao plugin em teste.

                if (str_starts_with($classe, $prefixo)) {

                    require $plugin . '/src/' . str_replace('\\', '/', substr($classe, strlen($prefixo))) . '.php';

                }

            }

        );

        // Os três bits de combinação alternam perfil, grupo e entidade entre válido e inválido.

        $contexto = [

            'perfil_atual' => 4,

            'grupos_atuais' => [7],

            'entidade_atual' => 2

        ];

        $configuracao = [

            'perfis_permitidos' => [4],

            'grupos_permitidos' => [7],

            'entidades_permitidas' => [2]

        ];

        // Exercita as oito combinações possíveis das permissões de perfil, grupo e entidade.

        for ($combinacao = 0; $combinacao < 8; $combinacao++) {

            $caso = $contexto;

            $caso['perfil_atual'] = ($combinacao & 1) ? 4 : 5;

            $caso['grupos_atuais'] = ($combinacao & 2) ? [7] : [8];

            $caso['entidade_atual'] = ($combinacao & 4) ? 2 : 3;

            conferir(

                ControleDeAcesso::avaliarPerfilGrupoEEntidade($caso, $configuracao) === ($combinacao === 7),

                'perfil E grupo E entidade: ' . $combinacao

            );

        }

        // Confere formatos que não podem passar a autorizar após a modularização.

        foreach ([

            '01',

            ' 4',

            '+4',

            "4\n",

            4.0,

            true,

            null

        ] as $perfil_invalido) {

            // Substitui somente o perfil para isolar sua influência na autorização.

            $caso = array_replace($contexto, ['perfil_atual' => $perfil_invalido]);

            // Mantém o acesso negado mesmo com grupo e entidade válidos.

            conferir(

                !ControleDeAcesso::avaliarPerfilGrupoEEntidade($caso, $configuracao),

                'perfil com formato inválido: ' . var_export($perfil_invalido, true)

            );

        }

        // Perfil zero não é permitido, mesmo que apareça explicitamente na configuração.

        conferir(

            !ControleDeAcesso::avaliarPerfilGrupoEEntidade(

                array_replace($contexto, ['perfil_atual' => 0]),

                array_replace($configuracao, ['perfis_permitidos' => [0]])

            ),

            'perfil zero não autoriza'

        );

        // O grupo permitido no início da lista não pode esconder um item inválido posterior.

        conferir(

            !ControleDeAcesso::avaliarPerfilGrupoEEntidade(array_replace($contexto, [

                'grupos_atuais' => [

                    7,

                    'invalido'

                ]

            ]), $configuracao),

            'lista de grupos parcialmente válida não autoriza'

        );

        // Aplica a mesma rejeição integral à lista de grupos da configuração.

        conferir(

            !ControleDeAcesso::avaliarPerfilGrupoEEntidade($contexto, array_replace($configuracao, [

                'grupos_permitidos' => [

                    7,

                    false

                ]

            ])),

            'configuração de grupos parcialmente válida não autoriza'

        );

        // A presença da entidade correta também não permite ignorar um item inválido posterior.

        conferir(

            !ControleDeAcesso::avaliarPerfilGrupoEEntidade(

                $contexto,

                array_replace($configuracao, [

                    'entidades_permitidas' => [

                        2,

                        false

                    ]

                ])

            ),

            'configuração de entidades parcialmente válida não autoriza'

        );

        // Uma configuração associativa não deve ser reinterpretada como lista de perfis.

        conferir(

            !ControleDeAcesso::avaliarPerfilGrupoEEntidade(

                $contexto,

                array_replace($configuracao, ['perfis_permitidos' => ['perfil' => 4]])

            ),

            'lista associativa não autoriza'

        );

        // Entidade ausente não significa entidade raiz, mesmo quando a raiz estiver autorizada.

        conferir(

            !ControleDeAcesso::avaliarPerfilGrupoEEntidade(

                array_replace($contexto, ['entidade_atual' => null]),

                array_replace($configuracao, ['entidades_permitidas' => [0]])

            ),

            'ausência de entidade não vira raiz'

        );

        // Remove cada lista obrigatória por vez para comprovar que ausência de configuração nega acesso.

        foreach (array_keys($configuracao) as $chave) {

            $caso = $configuracao;

            $caso[$chave] = [];

            conferir(

                !ControleDeAcesso::avaliarPerfilGrupoEEntidade($contexto, $caso),

                'lista vazia: ' . $chave

            );

            $caso[$chave] = [true];

            conferir(

                !ControleDeAcesso::avaliarPerfilGrupoEEntidade($contexto, $caso),

                'configuração com ID inválido: ' . $chave

            );

        }

        // Testa formatos inválidos de entidade sem permitir conversão acidental para raiz ou curinga.

        foreach ([

            true,

            -1,

            2.5,

            '2abc',

            '*',

            null,

            '999999999999999999999999'

        ] as $valor) {

            $caso = $contexto;

            $caso['entidade_atual'] = $valor;

            conferir(

                !ControleDeAcesso::avaliarPerfilGrupoEEntidade($caso, $configuracao),

                'entidade inválida: ' . var_export($valor, true)

            );

        }

        conferir(

            ControleDeAcesso::avaliarPerfilGrupoEEntidade(

                [

                    'perfil_atual' => '4',

                    'grupos_atuais' => ['7'],

                    'entidade_atual' => '2'

                ],

                $configuracao

            ),

            'IDs decimais do banco'

        );

        conferir(

            ControleDeAcesso::avaliarPerfilGrupoEEntidade(

                [

                    'perfil_atual' => 4,

                    'grupos_atuais' => [

                        8,

                        7

                    ],

                    'entidade_atual' => 0

                ],

                array_replace($configuracao, ['entidades_permitidas' => [0]])

            ),

            'raiz explicitamente autorizada e um dos grupos'

        );

        conferir(

            !ControleDeAcesso::avaliarPerfilGrupoEEntidade($contexto, array_replace($configuracao, ['entidades_permitidas' => [0]])),

            'raiz não é curinga'

        );

        // A partir daqui, os testes combinam a política própria com a sessão nativa simulada.

        $_SESSION = [

            'glpiID' => 9,

            'glpiactiveprofile' => [

                'id' => 4,

                'interface' => 'central'

            ],

            'glpigroups' => [7],

            'glpiactive_entity' => 2,

            'glpiactiveentities' => [2]

        ];

        // O Super-Admin ativo dispensa as listas próprias, preservando a sessão e os direitos nativos.

        $sessao_antes_do_super_admin = $_SESSION;

        $_SESSION['glpiactiveprofile']['name'] = 'Super-Admin';

        $_SESSION['glpigroups'] = [];

        Session::$administrador = true;

        conferir(AcessoGlpi::podeAcessar(), 'Super-Admin acessa com listas vazias e sem grupo');

        conferir(AcessoGlpi::obterContextoDoTecnico()['grupos_ids'] === [], 'Super-Admin não fabrica grupo no contexto');

        conferir(str_ends_with(MenuDaFerramenta::getMenuContent()['page'], '/front/index.php'), 'menu operacional disponível ao Super-Admin');

        $sessao_super_admin = $_SESSION;

        // Outro ID válido pode identificar o perfil nominal ativo sem depender do número quatro.

        $_SESSION['glpiactiveprofile']['id'] = 12;

        conferir(AcessoGlpi::podeAcessar(), 'exceção usa identidade nominal e direito nativo, sem ID fixo');

        $_SESSION = $sessao_super_admin;

        // O nome do perfil não substitui o direito administrativo nativo.

        Session::$administrador = false;

        conferir(!AcessoGlpi::podeAcessar(), 'nome Super-Admin sem config UPDATE não dispensa listas');

        Session::$administrador = true;

        // Um administrador de outro perfil continua sujeito às três listas próprias.

        $_SESSION['glpiactiveprofile']['name'] = 'Admin';

        $_GET['super_administrador'] = true;

        $_POST['super_administrador'] = true;

        conferir(!AcessoGlpi::podeAcessar(), 'outro administrador e indicador HTTP não recebem a exceção');

        unset($_GET['super_administrador'], $_POST['super_administrador']);

        $_SESSION = $sessao_super_admin;

        // Revoga cada parte nativa para conferir que a exceção não libera contexto inválido.

        foreach ([

            ['glpiID', null],

            ['glpiactiveentities', []],

            ['glpiactive_entity', null],

            ['glpigroups', 'invalido'],

            ['glpiactiveprofile', ['id' => 4, 'name' => 'Super-Admin', 'interface' => 'helpdesk']],

            ['glpiactiveprofile', ['id' => '04', 'name' => 'Super-Admin', 'interface' => 'central']],

        ] as [$chave_nativa, $valor_nativo]) {

            $_SESSION = array_replace($sessao_super_admin, [$chave_nativa => $valor_nativo]);

            conferir(!AcessoGlpi::podeAcessar(), 'Super-Admin mantém restrição nativa: ' . $chave_nativa);

        }

        $_SESSION = $sessao_super_admin;

        Plugin::$ativo = false;

        conferir(!AcessoGlpi::podeAcessar(), 'Super-Admin não acessa plugin desativado');

        Plugin::$ativo = true;

        // A política aceita somente o indicador booleano calculado pelo adaptador da sessão.

        foreach ([true, false, 'true', 1] as $indicador_super_admin) {

            conferir(

                ControleDeAcesso::avaliarPerfilGrupoEEntidade(

                    array_replace($contexto, ['grupos_atuais' => [], 'super_administrador' => $indicador_super_admin]),

                    []

                ) === ($indicador_super_admin === true),

                'indicador Super-Admin estrito: ' . var_export($indicador_super_admin, true)

            );

        }

        $_SESSION = $sessao_antes_do_super_admin;

        Session::$administrador = false;

        conferir(!AcessoGlpi::podeAcessar(), 'entrega nega operação sem configurar perfis e entidades');

        conferir(!AcessoGlpi::estaComAcessoTemporarioParaTestes(), 'entrega desabilita bypass');

        file_put_contents($plugin

            . '/config/acesso.php', '<?php return ["acesso_temporario_para_testes" => true, "perfis_permitidos" => [], "entidades_permitidas" => []];');

        // Bypass somente no laboratório descartável deste teste.

        conferir(AcessoGlpi::podeAcessar(), 'liberação temporária entregue permite acesso operacional');

        conferir(

            AcessoGlpi::estaComAcessoTemporarioParaTestes(),

            'liberação temporária identificada'

        );

        conferir(!AcessoGlpi::podeConfigurar(), 'liberação temporária não concede administração');

        $sessao_do_teste_temporario = $_SESSION;

        // O GLPI pode preservar lacunas nos índices ao remover grupos duplicados.

        $_SESSION['glpigroups'] = array_unique([

            7,

            7,

            8

        ]);

        conferir(AcessoGlpi::podeAcessar(), 'grupos nativos com índices esparsos permitem acesso temporário');

        conferir(

            AcessoGlpi::obterContextoDoTecnico()['grupos_ids'] === [7, 8],

            'contexto reindexa grupos nativos sem alterar seus IDs'

        );

        $_SESSION = $sessao_do_teste_temporario;

        // A exceção precisa permitir técnico sem grupo, sem fabricar um grupo de atribuição.

        $_SESSION['glpigroups'] = [];

        conferir(AcessoGlpi::podeAcessar(), 'teste temporário não exige grupo autorizado');

        conferir(

            AcessoGlpi::obterContextoDoTecnico()['grupos_ids'] === [],

            'sem grupo mantém contexto vazio'

        );

        // As condições nativas continuam negando antes de mostrar conteúdo.

        foreach (

            [

                [

                    'glpiID',

                    null

                ],

                [

                    'glpiactiveentities',

                    []

                ],

                [

                    'glpiactiveprofile',

                    [

                        'id' => 4,

                        'interface' => 'helpdesk'

                    ]

                ],

                [

                    'glpigroups',

                    'invalido'

                ],

            ] as [$chave, $valor]

        ) {

            $_SESSION = array_replace($sessao_do_teste_temporario, [$chave => $valor]);

            conferir(!AcessoGlpi::podeAcessar(), 'liberação preserva restrição nativa/contexto: ' . $chave);

        }

        $_SESSION = $sessao_do_teste_temporario;

        ob_start();

        require $plugin . '/front/index.php';

        $saida = ob_get_clean();

        conferir(

            str_contains($saida, 'Acesso temporário para testes'),

            'aviso visível na fila temporária'

        );

        // Configuração local ausente, false ou valores parecidos não ativam a exceção.

        foreach ([

            false,

            1,

            'true'

        ] as $opcao) {

            $restrita = [

                'perfis_permitidos' => [],

                'entidades_permitidas' => [],

                'acesso_temporario_para_testes' => $opcao,

            ];

            file_put_contents(

                $plugin . '/config/acesso.php',

                '<?php return ' . var_export($restrita, true) . ';'

            );

            conferir(

                !AcessoGlpi::podeAcessar(),

                'somente booleano true libera: ' . var_export($opcao, true)

            );

        }

        file_put_contents($plugin

            . '/config/acesso.php', '<?php return ["perfis_permitidos" => [], "entidades_permitidas" => []];');

        conferir(!AcessoGlpi::podeAcessar(), 'sem exceção, listas vazias bloqueiam acesso');

        conferir(

            !AcessoGlpi::estaComAcessoTemporarioParaTestes(),

            'exceção ausente fica desativada'

        );

        file_put_contents(

            $plugin . '/config/acesso.php',

            '<?php return ' . var_export($configuracao, true) . ';'

        );

        Config::$valores['admanagement'] = ['grupos_e_categorias' => '{"grupos_permitidos":[7],"categorias_itil":[]}'];

        conferir(AcessoGlpi::podeAcessar(), 'contexto completo autorizado');

        // O formato nativo esparso também deve funcionar com a política completa ativada.

        $grupos_anteriores = $_SESSION['glpigroups'];

        $_SESSION['glpigroups'] = array_unique([

            7,

            7,

            8

        ]);

        conferir(AcessoGlpi::podeAcessar(), 'grupos nativos esparsos atendem à política completa');

        // Confere que grupos nativos com chaves ou valores inválidos não são liberados pela reindexação.

        foreach (

            [

                ['grupo' => 7],

                [-1 => 7],

                [

                    0 => 7,

                    2 => '8abc'

                ],

                [

                    0 => 8,

                    2 => 9

                ],

            ] as $grupos_invalidos

        ) {

            $_SESSION['glpigroups'] = $grupos_invalidos;

            conferir(

                !AcessoGlpi::podeAcessar(),

                'reindexar não libera grupo inválido ou não autorizado: ' . json_encode($grupos_invalidos)

            );

        }

        $_SESSION['glpigroups'] = $grupos_anteriores;

        conferir(

            MenuDaFerramenta::getMenuContent()['page'] === '/plugins/admanagement/front/index.php',

            'menu usa rota canônica GLPI 11 mesmo com plugin no marketplace'

        );

        $sessao_valida = $_SESSION;

        // Revoga cada componente do contexto nativo para conferir a recusa do acesso direto.

        foreach (

            [

                [

                    'glpiID',

                    null

                ],

                [

                    'glpigroups',

                    []

                ],

                [

                    'glpiactive_entity',

                    3

                ],

                [

                    'glpiactiveentities',

                    []

                ],

                [

                    'glpiactiveprofile',

                    [

                        'id' => 5,

                        'interface' => 'central'

                    ]

                ],

                [

                    'glpiactiveprofile',

                    [

                        'id' => 4,

                        'interface' => 'helpdesk'

                    ]

                ]

            ] as [$chave, $valor]

        ) {

            $_SESSION = array_replace($sessao_valida, [$chave => $valor]);

            conferir(

                !AcessoGlpi::podeAcessar() && MenuDaFerramenta::getMenuContent() === [],

                'nega contexto alterado: ' . $chave

            );

            ob_start();

            $negado = false;

            try {

                require $plugin . '/front/index.php';

            } catch (AccessDeniedHttpException $erro) {

                // Registra a recusa esperada da URL direta no cenário de contexto alterado.

                $negado = true;

            }

            $saida = ob_get_clean();

            conferir($negado && $saida === '', 'URL direta bloqueada antes da saída: ' . $chave);

        }

        // Confere a desativação do plugin antes da renderização de qualquer conteúdo.

        $_SESSION = $sessao_valida;

        Plugin::$ativo = false;

        conferir(!AcessoGlpi::podeAcessar(), 'plugin desativado');

        ob_start();

        $negado = false;

        try {

            require $plugin . '/front/index.php';

        } catch (AccessDeniedHttpException $erro) {

            // Confirma que um plugin desativado não permite entrar pela URL operacional.

            $negado = true;

        }

        $saida = ob_get_clean();

        conferir($negado && $saida === '', 'URL direta de plugin desativado');

        Plugin::$ativo = true;

        // O contexto deve ser obtido pela mesma entrada usada nas telas PHP.

        conferir(

            array_intersect_key(AcessoGlpi::obterContextoDoTecnico(), array_flip(['tecnico_id', 'perfil_id', 'grupos_ids', 'entidade_id'])) === [

                'tecnico_id' => (int) $sessao_valida['glpiID'],

                'perfil_id' => (int) $sessao_valida['glpiactiveprofile']['id'],

                'grupos_ids' => [7],

                'entidade_id' => 2,

            ],

            'contexto do técnico disponível e normalizado'

        );

        // Recuperar contexto também deve negar acesso depois de uma troca de perfil.

        $_SESSION['glpiactiveprofile']['id'] = 5;

        $negado = false;

        try {

            AcessoGlpi::obterContextoDoTecnico();

        } catch (AccessDeniedHttpException $erro) {

            // Confirma que a obtenção do contexto revalida a autorização antes de devolver IDs.

            $negado = true;

        }

        conferir($negado, 'contexto revalida autorização antes de fornecer os IDs');

        $_SESSION = $sessao_valida;

        ob_start();

        require $plugin . '/front/index.php';

        $saida = ob_get_clean();

        conferir(

            str_contains($saida, 'CABECALHO') && str_contains($saida, 'RODAPE'),

            'URL autorizada renderiza a página'

        );

        // A configuração usa direitos administrativos, sem conceder acesso operacional.

        Config::$valores = [];

        conferir(

            ConfiguracaoDaFila::consultar() === ['grupos_permitidos' => [], 'categorias_itil' => []],

            'grupos e categorias inicialmente vazios'

        );

        conferir(!AcessoGlpi::podeConfigurar(), 'técnico não administra configuração');

        $negado = false;

        try {

            ConfiguracaoDaFila::salvar([]);

        } catch (AccessDeniedHttpException $erro) {

            // Verifica que um técnico sem direito administrativo não consegue salvar configuração.

            $negado = true;

        }

        conferir($negado && Config::$valores === [], 'salvamento sem direito administrativo não grava');

        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();

        try {

            require $plugin . '/front/config.php';

        } catch (AccessDeniedHttpException $erro) {

            // Confirma que a URL administrativa também recusa o usuário sem o direito exigido.

            $negado = true;

        }

        $saida = ob_get_clean();

        conferir($negado && $saida === '', 'URL da configuração bloqueada antes da saída');

        Session::$administrador = true;

        conferir(!AcessoGlpi::podeConfigurar(), 'config UPDATE sem perfil Super-Admin não configura');

        $_SESSION['glpiactiveprofile']['name'] = 'Super-Admin';

        conferir(

            AcessoGlpi::podeConfigurar() && AcessoGlpi::podeAcessar(),

            'Super-Admin configura e opera mesmo sem grupos operacionais'

        );

        conferir(

            str_ends_with(MenuDaFerramenta::getMenuContent()['page'], '/front/index.php'),

            'menu disponibiliza operação ao Super-Admin'

        );

        ConfiguracaoDaFila::salvar([

            'grupos_permitidos' => [

                '7',

                7

            ],

            'categorias_itil' => [

                '12',

                15

            ]

        ]);

        conferir(

            ConfiguracaoDaFila::consultar() === ['grupos_permitidos' => [7], 'categorias_itil' => [

                12,

                15

            ]],

            'salva e relê seleções normalizadas'

        );

        conferir(AcessoGlpi::podeAcessar(), 'grupos da tela alimentam a autorização');

        $antes = Config::$valores;

        // Envia seleções malformadas ou fora do escopo para conferir que nenhuma delas altera a configuração.

        foreach ([

            '7',

            [0],

            [-1],

            [true],

            ['7abc'],

            [[7]],

            [99],

            [404]

        ] as $invalido) {

            $rejeitado = false;

            try {

                ConfiguracaoDaFila::salvar(['grupos_permitidos' => $invalido]);

            } catch (\InvalidArgumentException $erro) {

                // Registra a rejeição esperada dos grupos inválidos sem aceitar uma gravação parcial.

                $rejeitado = true;

            }

            conferir(

                $rejeitado && Config::$valores === $antes,

                'entrada inválida ou grupo fora do escopo não altera configuração: ' . json_encode($invalido)

            );

        }

        $rejeitado = false;

        try {

            ConfiguracaoDaFila::salvar([

                'grupos_permitidos' => [7],

                'categorias_itil' => [404]

            ]);

        } catch (\InvalidArgumentException $erro) {

            // Confirma que uma categoria inexistente impede salvar o conjunto de grupos e categorias.

            $rejeitado = true;

        }

        conferir($rejeitado && Config::$valores === $antes, 'categoria inexistente não grava grupos parcialmente');

        Config::$falhar_gravacao = true;

        $rejeitado = false;

        try {

            ConfiguracaoDaFila::salvar([]);

        } catch (\RuntimeException $erro) {

            // Confirma que a falta de persistência efetiva não é apresentada como sucesso.

            $rejeitado = true;

        }

        conferir($rejeitado, 'falha de persistência não anuncia sucesso');

        Config::$falhar_gravacao = false;

        ob_start();

        require $plugin . '/front/config.php';

        $saida = ob_get_clean();

        conferir(

            str_contains($saida, 'name="grupos_ids[]"') && str_contains($saida, 'name="usuario_id"') && str_contains($saida, 'name="categorias_itil[]"') && str_contains($saida, '_glpi_csrf_token'),

            'formulário renderiza seletores como listas e token nativo'

        );

        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Reproduz a decodificação PHP dos nomes com [] enviados pelo formulário.

        parse_str('grupos_permitidos%5B%5D=8&categorias_itil%5B%5D=12', $_POST);

        $redirecionado = false;

        ob_start();

        try {

            require $plugin . '/front/config.php';

        } catch (RedirecionamentoDoTeste $erro) {

            // Captura o redirecionamento nativo reproduzido pelo teste depois do POST válido.

            $redirecionado = true;

        }

        $saida = ob_get_clean();

        conferir(

            $redirecionado && $saida === '' && ConfiguracaoDaFila::consultar()['grupos_permitidos'] === [7],

            'POST salva e redireciona antes da renderização'

        );

        ConfiguracaoDaFila::salvar(['grupos_permitidos' => [8], 'categorias_itil' => [12]]);

        unset($_SESSION['glpiactiveprofile']['name']);

        conferir(!AcessoGlpi::podeAcessar(), 'remoção do grupo revoga acesso legado na próxima consulta');

        $_SESSION['glpiactiveprofile']['name'] = 'Super-Admin';

        ConfiguracaoDaFila::salvar([]);

        conferir(

            ConfiguracaoDaFila::consultar() === ['grupos_permitidos' => [], 'categorias_itil' => []],

            'limpa completamente as duas seleções'

        );

        ConfiguracaoDaFila::salvar([

            'grupos_permitidos' => '',

            'categorias_itil' => ''

        ]);

        conferir(

            ConfiguracaoDaFila::consultar() === ['grupos_permitidos' => [], 'categorias_itil' => []],

            'aceita seleção vazia enviada pelo helper nativo'

        );

        unset($_SESSION['glpiactiveprofile']['name']);

        Config::$valores['admanagement'] = ['grupos_e_categorias' => 'json inválido'];

        conferir(!AcessoGlpi::podeAcessar(), 'configuração corrompida não libera grupos');

        Session::$administrador = false;

        file_put_contents($plugin . '/config/acesso.php', '<?php return "invalido";');

        conferir(!AcessoGlpi::podeAcessar(), 'arquivo de configuração inválido');

        unlink($plugin . '/config/acesso.php');

        conferir(!AcessoGlpi::podeAcessar(), 'arquivo de configuração ausente');

        // O ciclo de carga usa apenas os hooks próprios, sem instalar o GLPI ou acessar serviços.

        define('GLPI_ROOT', $temporario);

        define('GLPI_VERSION', '11.0.6');

        require $raiz . '/setup.php';

        require $raiz . '/hook.php';

        plugin_init_admanagement();

        conferir(

            $PLUGIN_HOOKS['menu_toadd']['admanagement']['plugins'] === MenuDaFerramenta::class,

            'registro do menu'

        );

        Session::$administrador = true;

        $_SESSION['glpiactiveprofile']['name'] = 'Super-Admin';

        plugin_init_admanagement();

        conferir(

            $PLUGIN_HOOKS['config_page']['admanagement'] === 'front/config.php',

            'link de configuração no gerenciamento de plugins'

        );

        conferir(

            plugin_version_admanagement()['name'] === 'ADManagement' && plugin_admanagement_install() && plugin_admanagement_uninstall(),

            'identidade e ciclo sem persistência'

        );

    } finally {

        // Remove o laboratório temporário mesmo quando alguma verificação da suíte falha.

        // Remove apenas a árvore aleatória criada por esta execução no diretório temporário.

        $arquivos = new \RecursiveIteratorIterator(

            new \RecursiveDirectoryIterator($temporario, \FilesystemIterator::SKIP_DOTS),

            \RecursiveIteratorIterator::CHILD_FIRST

        );

        // Apaga os itens do laboratório em ordem que permita remover os diretórios por último.

        foreach ($arquivos as $arquivo) {

            // Remove o diretório que ficou vazio após a exclusão dos seus arquivos de teste.

            if ($arquivo->isDir()) {

                rmdir($arquivo->getPathname());

            } else {

                // Remove somente o arquivo temporário identificado pelo iterador do laboratório.

                unlink($arquivo->getPathname());

            }

        }

        rmdir($temporario);

    }

}

