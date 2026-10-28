<?php

// Confere as regras e a apresentação sem depender de uma instalação real do GLPI.

// Os resultados registrados também permitem comparar o código atual com uma cópia anterior.

namespace Glpi\Exception\Http {

    // Permite verificar a negação de acesso sem carregar uma instalação GLPI.

    class AccessDeniedHttpException extends \RuntimeException {

    }

}

namespace {

    use Glpi\Exception\Http\AccessDeniedHttpException;

    use GlpiPlugin\Admanagement\AcessoGlpi;

    use GlpiPlugin\Admanagement\ConfiguracaoDaFila;

    use GlpiPlugin\Admanagement\ConsultaDeChamados;

    use GlpiPlugin\Admanagement\EntradaDaFerramenta;

    use GlpiPlugin\Admanagement\FormularioDaSolicitacao;

    use GlpiPlugin\Admanagement\ImportadorDaSolicitacao;

    use GlpiPlugin\Admanagement\LeitorDoFormmanager;

    use GlpiPlugin\Admanagement\MenuDaFerramenta;

    use GlpiPlugin\Admanagement\PoliticaDeLogin;

    use GlpiPlugin\Admanagement\RepositorioDeRascunhos;

    use GlpiPlugin\Admanagement\RevisorDaOperacao;

    /**

     * Mantém o ancestral exigido pela integração nativa de abas do plugin.

     */

    class CommonGLPI {

    }

    /**

     * Identifica os perfis administrativos sem depender de seu nome de exibição.

     */

    class Profile extends CommonGLPI {

        /**

         * O perfil sintético 4 possui o direito nativo de administrar perfis.

         */

        public static function getSuperAdminProfilesId(): array {

            return [4];

        }

    }

    /**

     * Entrega o mesmo contrato de identidade usado pela API nativa de sessão.

     */

    class SessaoDaRefatoracao {

        /**

         * Devolve o usuário conectado do cenário.

         */

        public function getUserId(): int {

            return $_SESSION['glpiID'];

        }

        /**

         * Devolve exclusivamente o perfil selecionado na sessão.

         */

        public function getProfileId(): int {

            return $_SESSION['glpiactiveprofile']['id'];

        }

        /**

         * Devolve a seleção nativa de entidades do cenário.

         */

        public function getActiveEntitiesIds(): array {

            return $_SESSION['glpiactiveentities'];

        }

        /**

         * Devolve a entidade explicitamente ativa.

         */

        public function getCurrentEntityId(): int {

            return $_SESSION['glpiactive_entity'];

        }

    }

    // Estes substitutos existem somente no teste. Não são dados usados pelo produto.

    define('READ', 1);

    define('UPDATE', 2);

    /**

     * Substitui a persistência GLPI por valores guardados em memória.

     */

    class Config {

        public static array $valores = [];

        public static bool $falhar_gravacao = false;

        /**

         * Devolve os valores guardados em memória para este teste.

         */

        public static function getConfigurationValues(

            $contexto,

            $chaves

        ): array {

            // Obtém a configuração em memória somente quando o contexto foi definido pelo cenário.

            if (isset(self::$valores[$contexto])) {

                return self::$valores[$contexto];

            }

            return [];

        }

        /**

         * Permite simular uma gravação que não foi persistida.

         */

        public static function setConfigurationValues(

            $contexto,

            $valores

        ): void {

            // No dublê, efetiva o salvamento apenas quando não foi solicitado simular uma falha.

            if (!self::$falhar_gravacao) {

                self::$valores[$contexto] = $valores;

            }

        }

    }

    /**

     * Define o contexto de login, interface, entidade e direitos de cada cenário.

     */

    class Session {

        public static bool $administrador = true;

        /**

         * Reproduz o retrato nativo da sessão sem consultar serviços externos.

         */

        public static function getCurrentSessionInfo(): ?SessaoDaRefatoracao {

            return self::getLoginUserID() ? new SessaoDaRefatoracao() : null;

        }

        /**

         * Simula um usuário conectado.

         */

        public static function getLoginUserID(): int {

            return $_SESSION['glpiID'];

        }

        /**

         * Devolve a interface definida pelo cenário.

         */

        public static function getCurrentInterface(): string {

            return $_SESSION['glpiactiveprofile']['interface'];

        }

        /**

         * Devolve a entidade definida pelo cenário.

         */

        public static function getActiveEntity(): int {

            return $_SESSION['glpiactive_entity'];

        }

        /**

         * Representa o direito administrativo, sem concedê-lo no produto.

         */

        public static function haveRight(

            $direito,

            $nivel

        ): int|false {

            // Somente o cenário administrativo permite alterar configurações.

            if ($direito === 'config') {

                return self::$administrador ? ($nivel & UPDATE) ?: false : false;

            }

            return $direito === 'plugin_admanagement' ? ($nivel & READ) ?: false : false;

        }

        /**

         * Permite consultar apenas a entidade usada no cenário.

         */

        public static function haveAccessToEntity(

            $entidade_id,

            $recursivo = false

        ): bool {

            return $entidade_id === 2;

        }

        /**

         * Interrompe o cenário se o usuário não estiver conectado.

         */

        public static function checkLoginUser(): void {

            // Reproduz a negação nativa de uma sessão sem usuário conectado.

            if (!self::getLoginUserID()) {

                throw new AccessDeniedHttpException();

            }

        }

    }

    /**

     * Controla a ativação e o endereço base usados nas verificações de menu.

     */

    class Plugin {

        public static bool $ativo = true;

        /**

         * Simula a ativação do plugin.

         */

        public static function isPluginActive(

            $nome

        ): bool {

            return self::$ativo;

        }

        /**

         * Devolve uma URL local para comparar os links gerados.

         */

        public static function getWebDir(

            $nome

        ): string {

            return '/plugins/admanagement';

        }

    }

    /**

     * Reproduz somente os campos e a leitura de catálogo exigidos pela configuração.

     */

    class Group {

        public array $fields = [];

        /**

         * Simula itens válidos, inexistentes e fora da entidade permitida.

         */

        public function getFromDB(

            $identificador

        ): bool {

            $this->fields = [

                'entities_id' => 2,

                'is_recursive' => false

            ];

            // Usa o ID de contrato reservado para representar um item fora do escopo de entidades.

            if ($identificador === 99) {

                $this->fields['entities_id'] = 3;

            }

            return $identificador !== 404;

        }

    }

    /**

     * Usa as mesmas respostas de catálogo para as categorias dos cenários.

     */

    class ITILCategory extends Group {

    }

    /**

     * Mantém o papel de responsável usado nas ligações de chamados.

     */

    class CommonITILActor {

        public const ASSIGN = 2;

    }

    /**

     * Expõe a tabela de usuários atribuídos ao chamado.

     */

    class Ticket_User {

        /**

         * Mantém o nome da tabela de atores usada nos critérios.

         */

        public static function getTable(): string {

            return 'glpi_tickets_users';

        }

    }

    /**

     * Expõe a tabela de grupos atribuídos ao chamado.

     */

    class Group_Ticket {

        /**

         * Mantém o nome da tabela de grupos usada nos critérios.

         */

        public static function getTable(): string {

            return 'glpi_groups_tickets';

        }

    }

    /**

     * Permite testar a leitura dos chamados e os estados excluídos da fila.

     */

    class Ticket {

        public static array $negados = [];

        /**

         * Mantém o nome da tabela principal usada nos critérios.

         */

        public static function getTable(): string {

            return 'glpi_tickets';

        }

        /**

         * Simula chamados para os quais o GLPI nega a leitura.

         */

        public function can(

            $identificador,

            $direito

        ): bool {

            return !in_array($identificador, self::$negados, true);

        }

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

    }

    /**

     * Registra consultas e fornece linhas e metadados controlados pelos testes.

     */

    class BancoDoTeste {

        public array $linhas = [];

        public array $consultas = [];

        public array $campos = [];

        public bool $tabela_existe = true;

        /**

         * Guarda os critérios para verificar filtros, campos e paginação.

         */

        public function request(

            $criterios

        ): ArrayIterator {

            $this->consultas[] = $criterios;

            return new ArrayIterator($this->linhas);

        }

        /**

         * Simula uma tabela ausente quando o cenário pede isso.

         */

        public function tableExists(

            $tabela

        ): bool {

            return $this->tabela_existe;

        }

        /**

         * Expõe os nomes de colunas que o leitor deve conferir.

         */

        public function listFields(

            $tabela

        ): array {

            return array_fill_keys($this->campos, []);

        }

    }

    /**

     * Substitui o fechamento de formulário e o token nativo na comparação do HTML.

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

         * Marca o fechamento e a presença do token no HTML gerado.

         */

        public static function closeForm(): void {

            echo '<input name="_glpi_csrf_token" value="token-do-teste"></form>';

        }

    }

    /**

     * Reproduz os seletores nativos mantendo os valores de zero, um e texto vazio distintos.

     */

    class Dropdown {

        /**

         * Gera opções escapadas conforme a seleção sintética do cenário.

         */

        public static function showFromArray(string $nome, array $opcoes, array $configuracao): void {

            echo '<select name="' . htmlescape($nome) . '">';

            // Cada opção mantém seu valor textual, inclusive a opção de revisão pendente.

            foreach ($opcoes as $valor => $rotulo) {

                echo '<option value="' . htmlescape($valor) . '"' . ((string) $valor === (string) ($configuracao['value'] ?? '') ? ' selected' : '') . '>' . htmlescape($rotulo) . '</option>';

            }

            echo '</select>';

        }

    }

    /**

     * Substitui o escape nativo somente para testar o HTML sem GLPI.

     */

    function htmlescape(

        $valor

    ): string {

        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');

    }

    /**

     * Compara valor e tipo, registrando o resultado para a comparação entre versões.

     */

    function conferirResultado(

        mixed $obtido,

        mixed $esperado,

        string $cenario

    ): void {

        global $resultados;

        // A comparação estrita detecta diferenças de valor e de tipo entre os resultados.

        if ($obtido !== $esperado) {

            throw new RuntimeException('Falhou: ' . $cenario);

        }

        $resultados[$cenario] = $obtido;

    }

    // Prepara o carregamento das classes que serão verificadas nesta execução.

    $resultados = [];

    $raiz = dirname(__DIR__);

    // Um caminho opcional permite executar estes mesmos testes contra a cópia anterior.

    if (isset($argv[1])) {

        $raiz = $argv[1];

    }

    // Cada arquivo da pasta src é incluído uma única vez nesta execução.

    foreach (glob($raiz . '/src/*.php') as $arquivo) {

        require_once $arquivo;

    }

    // O contexto abaixo pertence aos testes e não é gravado numa sessão de produção.

    $_SESSION = [

        'glpiID' => 9,

        'glpiactiveprofile' => [

            'id' => 4,

            'name' => 'Super-Admin',

            'interface' => 'central'

        ],

        'glpigroups' => [7],

        'glpiactive_entity' => 2,

        'glpiactiveentities' => [2],

    ];

    // IDs devem manter a rejeição de texto parcial, formatos ambíguos e estouro.

    foreach ([

        1,

        '1',

        PHP_INT_MAX,

        (string) PHP_INT_MAX

    ] as $indice => $valor) {

        conferirResultado(

            EntradaDaFerramenta::lerIdentificador($valor),

            (int) $valor,

            'ID válido ' . $indice

        );

    }

    $invalidos = [

        0,

        -1,

        '',

        '0',

        '01',

        '+1',

        ' 1',

        "1\n",

        '1abc',

        true,

        false,

        null,

        1.0,

        [],

        '999999999999999999999999'

    ];

    // Envia valores inválidos ao leitor de identificadores para conferir sua validação estrita.

    foreach ($invalidos as $indice => $valor) {

        $mensagem = null;

        try {

            EntradaDaFerramenta::lerIdentificador($valor);

        } catch (InvalidArgumentException $erro) {

            // Registra a rejeição esperada de um identificador que não pode ser utilizado.

            $mensagem = $erro->getMessage();

        }

        conferirResultado($mensagem, 'O identificador informado é inválido.', 'ID inválido ' . $indice);

    }

    conferirResultado(EntradaDaFerramenta::lerTexto(' nome ', 6), 'nome', 'remoção de espaços');

    conferirResultado(EntradaDaFerramenta::lerTexto("a\nb", 3, true), "a\nb", 'texto multilinha');

    // Exercita tipos, controles, UTF-8 inválido e tamanhos que o leitor de texto deve recusar.

    foreach ([

        [],

        true,

        "a\nb",

        "a\tb",

        "a\0b",

        "\xFF",

        '1234',

        'éé'

    ] as $indice => $valor) {

        $rejeitado = false;

        try {

            EntradaDaFerramenta::lerTexto($valor, 3);

        } catch (InvalidArgumentException $erro) {

            // Confirma que a entrada textual inválida não foi convertida ou aceita silenciosamente.

            $rejeitado = true;

        }

        conferirResultado($rejeitado, true, 'texto inválido ' . $indice);

    }

    // As concessões por perfil ativo são verificadas nas suítes acesso.php e visibilidade.php.

    // Configuração: manter listas vazias, normalização e recusa sem gravação parcial.

    $vazio = ['categorias_itil' => []];

    conferirResultado(ConfiguracaoDaFila::consultar(), $vazio, 'configuração ausente');

    ConfiguracaoDaFila::salvar(['categorias_itil' => ['12', 12]]);

    $configuracao_salva = ['categorias_itil' => [12]];

    conferirResultado(ConfiguracaoDaFila::consultar(), $configuracao_salva, 'categorias normalizadas');

    // Grupos do documento antigo não participam da configuração operacional nem concedem acesso.

    ConfiguracaoDaFila::salvar(['grupos_permitidos' => [7], 'categorias_itil' => [12]]);

    conferirResultado(ConfiguracaoDaFila::consultar(), $configuracao_salva, 'grupo legado ignorado');

    // Seleções inválidas de categorias devem preservar a configuração salva.

    foreach (['12', [0], [true], ['01'], [99], [404], ['categoria' => 12]] as $indice => $categorias) {

        $rejeitado = false;

        try {

            ConfiguracaoDaFila::salvar(['categorias_itil' => $categorias]);

        } catch (InvalidArgumentException $erro) {

            // Confirma rejeição do formato ou catálogo sem gravar parte das opções.

            $rejeitado = true;

        }

        conferirResultado($rejeitado, true, 'seleção de categoria rejeitada ' . $indice);

        conferirResultado(ConfiguracaoDaFila::consultar(), $configuracao_salva, 'configuração preservada ' . $indice);

    }

    Config::$falhar_gravacao = true;

    $rejeitado = false;

    try {

        ConfiguracaoDaFila::salvar([]);

    } catch (RuntimeException $erro) {

        // Confirma que uma gravação não persistida provoca erro em vez de sucesso aparente.

        $rejeitado = true;

    }

    conferirResultado($rejeitado, true, 'falha de persistência');

    Config::$falhar_gravacao = false;

    Session::$administrador = false;

    $rejeitado = false;

    try {

        ConfiguracaoDaFila::salvar([]);

    } catch (AccessDeniedHttpException $erro) {

        // Confirma que salvar sem direito administrativo é bloqueado antes da persistência.

        $rejeitado = true;

    }

    conferirResultado($rejeitado, true, 'configuração exige administrador');

    Session::$administrador = true;

    ConfiguracaoDaFila::salvar([

        'categorias_itil' => ''

    ]);

    conferirResultado(ConfiguracaoDaFila::consultar(), $vazio, 'seleções vazias');

    // Lê documentos JSON inválidos ou sem o contrato esperado para conferir a negativa de permissões.

    foreach (

        [

            'inválido',

            '[]',

            '{}',

            '{"grupos_permitidos":[7],"categorias_itil":[0]}'

        ] as $indice => $json

    ) {

        Config::$valores['admanagement'] = ['grupos_e_categorias' => $json];

        conferirResultado(ConfiguracaoDaFila::consultar(), $vazio, 'configuração inválida ' . $indice);

    }

    conferirResultado(AcessoGlpi::podeConfigurar(), true, 'administrador pode configurar');

    Plugin::$ativo = false;

    conferirResultado(AcessoGlpi::podeConfigurar(), false, 'plugin inativo nega configuração');

    Plugin::$ativo = true;

    conferirResultado(

        MenuDaFerramenta::getMenuContent()['options']['configuracao']['page'],

        '/plugins/admanagement/front/config.php',

        'menu administrativo'

    );

    // Consulta: a paginação conta somente chamados legíveis, sem revelar candidatos recusados.

    $contexto_tecnico = [

        'tecnico_id' => 9,

        'entidade_id' => 2,

        'grupos_ids' => [7]

    ];

    $banco = new BancoDoTeste();

    // Gera 26 chamados de contrato para exercitar página de 25 itens e indicador de continuação.

    for ($indice = 1; $indice <= 26; $indice++) {

        $banco->linhas[] = [

            'id' => (string) $indice,

            'name' => '<teste>',

            'status' => '2',

            'entities_id' => '2',

            'itilcategories_id' => '12',

            'date_mod' => '2026-10-21'

        ];

    }

    $consulta = new ConsultaDeChamados($banco);

    conferirResultado(count($consulta->listar($contexto_tecnico, [])['chamados']), 25, 'categorias vazias não escondem fila autorizada');

    $banco->consultas = [];

    Ticket::$negados = [2];

    $fila = $consulta->listar($contexto_tecnico, [12], 2);

    conferirResultado(count($fila['chamados']), 0, 'segunda página vazia após contar 25 chamados legíveis');

    conferirResultado($fila['tem_proxima'], false, 'chamado ilegível não indica próxima página');

    $primeira_pagina = $consulta->listar($contexto_tecnico, [12]);

    conferirResultado(count($primeira_pagina['chamados']), 25, 'primeira página completa após excluir ilegível');

    conferirResultado(array_column($primeira_pagina['chamados'], 'id'), array_values(array_diff(range(1, 26), [2])), 'paginação preserva exatamente os chamados legíveis');

    conferirResultado($primeira_pagina['tem_proxima'], false, '25 chamados legíveis não anunciam uma página adicional');

    conferirResultado($primeira_pagina['chamados'][0]['id'], 1, 'ID do chamado normalizado');

    $criterios = $banco->consultas[0];

    conferirResultado($criterios['START'], 0, 'leitura começa nos candidatos anteriores ao deslocamento visível');

    conferirResultado($criterios['LIMIT'], 100, 'lote de candidatos limitado');

    conferirResultado($criterios['WHERE']['glpi_tickets_users.users_id'], 9, 'atribuição exclusivamente individual para técnico');

    conferirResultado(

        $criterios['WHERE']['NOT'],

        [

            'glpi_tickets.status' => [

                5,

                6

            ]

        ],

        'exclusão de solucionados e fechados'

    );

    // Guarda a consulta inteira para comparar filtros e estrutura entre versões.

    $resultados['critérios completos'] = $criterios;

    // Páginas inválidas são recusadas sem consultar silenciosamente outro deslocamento.

    foreach ([-1, 0, 100001] as $pagina) {

        $recusou = false;

        try {

            $consulta->listar($contexto_tecnico, [12], $pagina);

        } catch (DomainException $erro) {

            // A exceção é o resultado esperado para paginação fora do intervalo.

            $recusou = true;

        }

        conferirResultado($recusou, true, 'página inválida ' . $pagina);

    }

    $banco->linhas = [$banco->linhas[1]];

    $negado = false;

    try {

        $consulta->consultar(2, $contexto_tecnico, [12]);

    } catch (AccessDeniedHttpException $erro) {

        // Registra a recusa nativa de leitura do chamado selecionado pelo teste.

        $negado = true;

    }

    conferirResultado($negado, true, 'consulta individual exige leitura');

    Ticket::$negados = [];

    $chamado_lido = $consulta->consultar(2, $contexto_tecnico, [12]);

    conferirResultado(

        isset($banco->consultas[array_key_last($banco->consultas)]['WHERE']['glpi_tickets_users.users_id']),

        true,

        'abertura direta mantém atribuição pessoal além da leitura nativa'

    );

    conferirResultado($chamado_lido['id'], 2, 'consulta individual autorizada');

    $contexto_sem_grupo = $contexto_tecnico;

    $contexto_sem_grupo['grupos_ids'] = [];

    $fila = $consulta->listar($contexto_sem_grupo, [12]);

    conferirResultado($fila['tem_proxima'], false, 'página sem registro extra');

    $ultima_consulta = $banco->consultas[count($banco->consultas) - 1];

    conferirResultado(

        $ultima_consulta['WHERE']['glpi_tickets_users.users_id'],

        9,

        'somente atribuição direta sem grupos'

    );

    // Origem: conferir estrutura, normalização de números e vínculo único.

    $banco_origem = new BancoDoTeste();

    $banco_origem->campos = [

        'id',

        'status',

        'name',

        'nome_social',

        'usar_nome_social',

        'matricula',

        'entities_id',

        'req_servico_rede',

        'tipo_servico_rede',

        'usuario_rede',

        'is_deleted',

        'is_active',

        'date_mod',

        'tickets_id_rede',

        'tickets_id_email',

        'tickets_id_sistema_saude',

        'tickets_id_sei'

    ];

    $formulario = [

        'id' => '10',

        'status' => '3',

        'name' => 'Nome Civil Teste',

        'nome_social' => 'Nome Social Teste',

        'usar_nome_social' => '1',

        'matricula' => 'M1',

        'entities_id' => '2',

        'req_servico_rede' => '1',

        'tipo_servico_rede' => 'CADASTRO',

        'usuario_rede' => 'conta.teste',

        'is_deleted' => '0',

        'is_active' => '1',

        'date_mod' => '2026-10-21',

        'tickets_id_rede' => '1',

        'tickets_id_email' => '0',

        'tickets_id_sistema_saude' => '0',

        'tickets_id_sei' => '0',

    ];

    $banco_origem->linhas = [$formulario];

    $leitor = new LeitorDoFormmanager($banco_origem);

    $formularios = $leitor->listarFormularios([1]);

    conferirResultado($formularios[0]['status'], 3, 'status da origem normalizado');

    $resultados['formulários lidos'] = $formularios;

    $resultados['consulta da origem'] = $banco_origem->consultas;

    $leitor->listarFormularios(['chamado_selecionado' => 1]);

    $ultima_consulta = $banco_origem->consultas[count($banco_origem->consultas) - 1];

    conferirResultado(

        $ultima_consulta['WHERE']['OR']['tickets_id_rede'],

        ['chamado_selecionado' => 1],

        'índices recebidos pelo leitor preservados'

    );

    $chamado = [

        'id' => 1,

        'entities_id' => 2

    ];

    $rascunho = ImportadorDaSolicitacao::importar($chamado, $formularios, $contexto_tecnico);

    conferirResultado($rascunho['tipo'], 'criacao', 'importação de cadastro');

    conferirResultado($rascunho['conta_atendida'], '', 'cadastro não importa conta de renovação');

    conferirResultado(

        RevisorDaOperacao::obterNomeParaLogin($rascunho),

        'Nome Social Teste',

        'nome social usado no login'

    );

    $resultados['rascunho importado'] = $rascunho;

    $formularios_alterados = $formularios;

    $formularios_alterados[0]['date_mod'] = 'outra data';

    $mesmo_rascunho = ImportadorDaSolicitacao::importar($chamado, $formularios_alterados, $contexto_tecnico);

    conferirResultado(

        $mesmo_rascunho['assinatura_origem'],

        $rascunho['assinatura_origem'],

        'data isolada não muda assinatura'

    );

    // Exercita vínculo ausente e vínculo duplicado, que não podem produzir uma importação confiável.

    foreach ([

        [],

        [

            $formularios[0],

            $formularios[0]

        ]

    ] as $indice => $origem) {

        $rejeitado = false;

        try {

            ImportadorDaSolicitacao::importar($chamado, $origem, $contexto_tecnico);

        } catch (DomainException $erro) {

            // Confirma o bloqueio esperado quando não há uma única origem de rede.

            $rejeitado = true;

        }

        conferirResultado($rejeitado, true, 'cardinalidade do vínculo ' . $indice);

    }

    // Altera cada condição de elegibilidade para verificar aprovação, serviço, entidade, tipo e papel do ticket.

    foreach (

        [

            'status' => 2,

            'req_servico_rede' => 0,

            'entities_id' => 3,

            'tipo_servico_rede' => 'OUTRO',

            'tickets_id_email' => 1

        ] as $campo => $valor

    ) {

        $origem = $formularios;

        $origem[0][$campo] = $valor;

        $rejeitado = false;

        try {

            ImportadorDaSolicitacao::importar($chamado, $origem, $contexto_tecnico);

        } catch (DomainException $erro) {

            // Confirma a recusa da importação depois de invalidar uma condição da origem.

            $rejeitado = true;

        }

        conferirResultado($rejeitado, true, 'impedimento da importação ' . $campo);

    }

    // Testa identificadores malformados de origem e de vínculo sem permitir seleção ambígua.

    foreach ($invalidos as $indice => $valor) {

        $banco_origem->linhas[0]['id'] = $valor;

        $lidos = $leitor->listarFormularios([1]);

        $esperado = null;

        // Preserva o cenário especial do vínculo zero para testar sua interpretação como ausência.

        if ($valor === 0 || $valor === '0') {

            $esperado = 0;

        }

        conferirResultado($lidos[0]['id'], $esperado, 'número da origem ' . $indice);

    }

    $banco_incompativel = new BancoDoTeste();

    $rejeitado = false;

    try {

        $leitor_incompativel = new LeitorDoFormmanager($banco_incompativel);

        $leitor_incompativel->listarFormularios([1]);

    } catch (DomainException $erro) {

        // Registra a recusa dos identificadores que não demonstram uma origem válida.

        $rejeitado = true;

    }

    conferirResultado($rejeitado, true, 'estrutura incompatível');

    $banco_ausente = new BancoDoTeste();

    $banco_ausente->tabela_existe = false;

    $rejeitado = false;

    try {

        $leitor_ausente = new LeitorDoFormmanager($banco_ausente);

        $leitor_ausente->listarFormularios([1]);

    } catch (DomainException $erro) {

        // Confirma que a falta de uma coluna obrigatória impede consultar a origem.

        $rejeitado = true;

    }

    conferirResultado($rejeitado, true, 'tabela ausente');

    $banco_minimo = new BancoDoTeste();

    $banco_minimo->campos = [

        'id',

        'status',

        'name',

        'entities_id',

        'req_servico_rede',

        'tipo_servico_rede',

        'tickets_id_rede'

    ];

    $banco_minimo->linhas = [

        [

            'id' => '10',

            'status' => '3',

            'name' => 'Nome Teste',

            'entities_id' => '2',

            'req_servico_rede' => '1',

            'tipo_servico_rede' => 'CADASTRO',

            'tickets_id_rede' => '1'

        ]

    ];

    $leitor_minimo = new LeitorDoFormmanager($banco_minimo);

    $origem_minima = $leitor_minimo->listarFormularios([1]);

    $rascunho_minimo = ImportadorDaSolicitacao::importar($chamado, $origem_minima, $contexto_tecnico);

    conferirResultado($rascunho_minimo['usar_nome_social'], null, 'nome ausente fica pendente');

    conferirResultado(

        count($rascunho_minimo['avisos_estrutura']),

        7,

        'avisos dos campos opcionais ausentes'

    );

    $resultados['rascunho mínimo'] = $rascunho_minimo;

    // Renovação mantém a conta atendida e não se transforma em uma criação.

    $origem_renovacao = $formularios;

    $origem_renovacao[0]['tipo_servico_rede'] = 'RENOVAR USUÁRIO';

    $renovacao = ImportadorDaSolicitacao::importar($chamado, $origem_renovacao, $contexto_tecnico);

    conferirResultado($renovacao['tipo'], 'renovacao', 'importação de renovação');

    conferirResultado($renovacao['conta_atendida'], 'conta.teste', 'conta informada na renovação');

    $resultados['renovação importada'] = $renovacao;

    // Exercita as indicações de nome social para distinguir os valores textuais válidos de dados indefinidos.

    foreach ([

        '1',

        '0',

        '',

        null,

        true,

        false,

        1,

        0,

        'outro'

    ] as $indice => $indicacao) {

        $origem_nome = $formularios;

        $origem_nome[0]['usar_nome_social'] = $indicacao;

        $rascunho_nome = ImportadorDaSolicitacao::importar($chamado, $origem_nome, $contexto_tecnico);

        $resultados['indicação de nome da origem ' . $indice] = $rascunho_nome;

    }

    // Revisão: não aceitar alterações no vínculo, domínio ou OU pelo POST.

    $revisado = RevisorDaOperacao::aplicar(

        $rascunho,

        [

            'nome_social' => ' Nome Revisto ',

            'grupos' => "grupo1\ngrupo2",

            'login_pretendido' => 'nome.revisto',

            'chamado_id' => 999,

            'usar_nome_social' => '0',

        ]

    );

    conferirResultado($revisado['revisao'], 2, 'incremento da revisão');

    conferirResultado($revisado['nome_social'], 'Nome Revisto', 'correção do nome');

    conferirResultado($revisado['usar_nome_social'], true, 'uso do nome definido na origem preservado');

    conferirResultado($revisado['chamado_id'], 1, 'vínculo preservado');

    conferirResultado($revisado['dominio'], '', 'domínio preservado');

    conferirResultado($revisado['ou'], '', 'OU preservada');

    // O horário muda a cada execução e não representa diferença de regra entre versões.

    unset($revisado['revisado_em']);

    $resultados['revisão aplicada'] = $revisado;

    $indefinido = $rascunho;

    $indefinido['uso_nome_na_origem'] = null;

    // Confere as três escolhas locais admitidas quando a origem não definiu o uso de nome.

    foreach ([

        '1' => true,

        '0' => false,

        '' => null

    ] as $valor => $esperado) {

        $revisao_nome = RevisorDaOperacao::aplicar($indefinido, ['usar_nome_social' => (string) $valor]);

        conferirResultado($revisao_nome['usar_nome_social'], $esperado, 'escolha do nome ' . $valor);

    }

    // Envia tipos e valores não previstos para garantir que a escolha de nome não sofra coerção.

    foreach ([

        1,

        0,

        true,

        false,

        null,

        'sim'

    ] as $indice => $valor) {

        $rejeitado = false;

        try {

            RevisorDaOperacao::aplicar($indefinido, ['usar_nome_social' => $valor]);

        } catch (InvalidArgumentException $erro) {

            // Registra a rejeição esperada de uma indicação de nome fora do contrato textual.

            $rejeitado = true;

        }

        conferirResultado($rejeitado, true, 'escolha de nome inválida ' . $indice);

    }

    $renovacao_revisada = RevisorDaOperacao::aplicar(

        $renovacao,

        [

            'conta_atendida' => ' conta.corrigida ',

            'login_pretendido' => 'ignorado'

        ]

    );

    conferirResultado(

        $renovacao_revisada['conta_atendida'],

        'conta.corrigida',

        'revisão da conta atendida'

    );

    conferirResultado(

        $renovacao_revisada['login_pretendido'],

        '',

        'renovação não altera login pretendido'

    );

    $resultados['pendências'] = RevisorDaOperacao::listarPendencias($rascunho);

    conferirResultado(

        PoliticaDeLogin::gerarCandidatos('Ana Luiza Campos'),

        [

            'ana.campos',

            'ana.luiza',

            'ana'

        ],

        'ordem dos logins'

    );

    conferirResultado(

        PoliticaDeLogin::gerarCandidatos('Ána Campos Campos'),

        [

            'ana.campos',

            'ana'

        ],

        'acentos e duplicação'

    );

    conferirResultado(

        PoliticaDeLogin::passaNaVerificacaoInicial('anal.santos'),

        false,

        'proteção inicial de login'

    );

    // Rascunhos: isolamento e revisão impedem perda de trabalho em outra aba.

    conferirResultado(

        RepositorioDeRascunhos::guardarInicial($rascunho),

        $rascunho,

        'primeira gravação'

    );

    conferirResultado(

        RepositorioDeRascunhos::consultar(1, [

            'tecnico_id' => 10,

            'entidade_id' => 2

        ]),

        null,

        'isolamento por técnico'

    );

    $novo_rascunho = $rascunho;

    $novo_rascunho['nome_civil'] = 'Outro nome';

    conferirResultado(

        RepositorioDeRascunhos::guardarInicial($novo_rascunho),

        $rascunho,

        'reimportação preserva trabalho por padrão'

    );

    $rejeitado = false;

    try {

        RepositorioDeRascunhos::salvar($novo_rascunho, 2);

    } catch (DomainException $erro) {

        // Confirma que uma aba antiga não sobrescreve a revisão já atualizada.

        $rejeitado = true;

    }

    conferirResultado($rejeitado, true, 'aba antiga não sobrescreve');

    $substituido = RepositorioDeRascunhos::guardarInicial($novo_rascunho, true, 1);

    conferirResultado($substituido['revisao'], 2, 'reimportação confirmada');

    RepositorioDeRascunhos::descartar(1, $contexto_tecnico, 2);

    conferirResultado(

        RepositorioDeRascunhos::consultar(1, $contexto_tecnico),

        null,

        'descarte local'

    );

    // Descartar e importar de novo não pode tornar válida a revisão de uma aba antiga.

    $reimportado = RepositorioDeRascunhos::guardarInicial($rascunho);

    conferirResultado($reimportado['revisao'], 3, 'revisão avança depois do descarte');

    $aba_antiga = RevisorDaOperacao::aplicar($rascunho, ['grupos' => 'correção da aba antiga']);

    $rejeitado = false;

    try {

        RepositorioDeRascunhos::salvar($aba_antiga, 1);

    } catch (DomainException $erro) {

        // Confirma que o descarte exige a revisão corrente do rascunho.

        $rejeitado = true;

    }

    conferirResultado($rejeitado, true, 'aba anterior ao descarte não sobrescreve nova importação');

    conferirResultado(

        RepositorioDeRascunhos::consultar(1, $contexto_tecnico),

        $reimportado,

        'conflito após descarte preserva os dados atuais'

    );

    RepositorioDeRascunhos::descartar(1, $contexto_tecnico, 3);

    conferirResultado(

        RepositorioDeRascunhos::consultar(1, [

            'tecnico_id' => 9,

            'entidade_id' => 3

        ]),

        null,

        'isolamento por entidade'

    );

    // Preenche o limite de vinte rascunhos para exercitar o bloqueio da importação seguinte.

    for ($indice = 1; $indice <= 20; $indice++) {

        $outro_rascunho = $rascunho;

        $outro_rascunho['chamado_id'] = $indice;

        RepositorioDeRascunhos::guardarInicial($outro_rascunho);

    }

    $outro_rascunho['chamado_id'] = 21;

    $rejeitado = false;

    try {

        RepositorioDeRascunhos::guardarInicial($outro_rascunho);

    } catch (DomainException $erro) {

        // Confirma que exceder o limite não remove nem substitui trabalhos existentes.

        $rejeitado = true;

    }

    conferirResultado($rejeitado, true, 'limite de vinte rascunhos');

    // HTML: comparar o conteúdo completo também detecta mudanças nos botões e seleções.

    foreach ([

        true,

        false,

        null

    ] as $indice => $usar_social) {

        $caso = $rascunho;

        $caso['uso_nome_na_origem'] = null;

        $caso['usar_nome_social'] = $usar_social;

        $caso['nome_civil'] = '<script>teste</script>';

        ob_start();

        FormularioDaSolicitacao::mostrarRevisao($caso, '/revisao.php?a=1&b=2');

        $html = ob_get_clean();

        conferirResultado(

            str_contains($html, '<script>teste</script>'),

            false,

            'escape do nome ' . $indice

        );

        $resultados['HTML revisão ' . $indice] = $html;

    }

    ob_start();

    FormularioDaSolicitacao::mostrarImportacao(1, '/revisao.php');

    FormularioDaSolicitacao::mostrarImportacao(1, '/revisao.php', $rascunho);

    $resultados['HTML importação'] = ob_get_clean();

    ob_start();

    FormularioDaSolicitacao::mostrarRevisao($renovacao, '/revisao.php');

    FormularioDaSolicitacao::mostrarRevisao($rascunho, '/revisao.php');

    $resultados['HTML renovação e nome definido'] = ob_get_clean();

    echo count($resultados) . ' verificações concluídas.' . PHP_EOL;

    // O mesmo hash nas duas versões confirma que todos os resultados registrados são iguais.

    echo 'Assinatura dos resultados: ' . hash('sha256', serialize($resultados)) . PHP_EOL;

}
