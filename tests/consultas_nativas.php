<?php

use GlpiPlugin\Admanagement\AcompanhamentoDaEquipe;

use GlpiPlugin\Admanagement\ConsultaDeChamados;

// Esta suíte usa somente dados sintéticos e dublês, sem iniciar GLPI, banco ou AD.

define('READ', 1);

/**

 * Representa a recusa de leitura nativa sem carregar o kernel do GLPI.

 */

class RecusaDaConsulta extends RuntimeException {

}

class_alias(RecusaDaConsulta::class, 'Glpi\\Exception\\Http\\AccessDeniedHttpException');

/**

 * Modela somente a fronteira independente de leitura por chamado.

 */

class Ticket {

    public const SOLVED = 5, CLOSED = 6;

    public static array $negados = [];

    /**

     * Fornece o nome da tabela nativa usada nos critérios.

     */

    public static function getTable(): string {

        return 'glpi_tickets';

    }

    /**

     * Recusa leitura explicitamente, mesmo quando a consulta já incluiu o registro.

     */

    public function can(int $id, int $direito): bool {

        return $direito === READ && !in_array($id, self::$negados, true);

    }

}

/**

 * Fornece a constante nativa que distingue responsáveis de solicitantes e observadores.

 */

class CommonITILActor {

    public const ASSIGN = 2;

}

/**

 * Expõe o contrato de nomes da tabela de atribuição individual.

 */

class Ticket_User {

    /**

     * Mantém a qualificação dos campos de usuários nos critérios.

     */

    public static function getTable(): string {

        return 'glpi_tickets_users';

    }

}

/**

 * Expõe o contrato de nomes da tabela de atribuição por grupo.

 */

class Group_Ticket {

    /**

     * Mantém a qualificação dos campos de grupos nos critérios.

     */

    public static function getTable(): string {

        return 'glpi_groups_tickets';

    }

}

/**

 * Fornece o nome da tabela usada pelos filtros complementares do catálogo nativo.

 */

class User {

    /**

     * Devolve somente o nome contratual da tabela de usuários.

     */

    public static function getTable(): string {

        return 'glpi_users';

    }

}

/**

 * Registra o uso da API nativa sem consultar tabelas ou usuários reais.

 */

class Group_User {

    public static array $chamadas = [];

    /**

     * Retorna um integrante repetido para verificar a união de vários setores.

     */

    public static function getGroupUsers(array $grupos, array $condicoes): array {

        self::$chamadas[] = [$grupos, $condicoes];

        return [

            ['id' => 9, 'name' => 'sintetico', 'firstname' => 'Nome', 'realname' => 'Sobrenome'],

            ['id' => 9, 'name' => 'sintetico', 'firstname' => 'Nome', 'realname' => 'Sobrenome'],

        ];

    }

}

/**

 * Distingue a formatação delegada da antiga concatenação manual de nomes.

 */

class DbUtils {

    /**

     * Marca o resultado sintético para comprovar a passagem pela API nativa.

     */

    public function formatUserName($id, $login, $sobrenome, $nome): string {

        return 'Formato nativo: ' . $sobrenome . ', ' . $nome;

    }

}

/**

 * Avalia critérios de leitura sobre chamados artificiais, incluindo os conectivos e a paginação.

 */

class BancoDasConsultas {

    public array $consultas = [];

    /**

     * Recebe registros exclusivos deste processo de teste.

     */

    public function __construct(

        public array $linhas

    ) {

    }

    /**

     * Aplica condições, ordem e limites sem executar SQL ou reproduzir a política de autorização.

     */

    public function request(array $criterios): array {

        $this->consultas[] = $criterios;

        $selecionados = [];

        // O contrato deve usar atribuição ASSIGN nas duas junções nativas.

        foreach ($criterios['LEFT JOIN'] ?? [] as $tabela => $juncao) {

            // Uma troca para solicitante ou observador comprometeria a política.

            if (($juncao[$tabela . '.type'] ?? null) !== CommonITILActor::ASSIGN) {

                throw new RuntimeException('A junção deve conter somente atores responsáveis.');

            }

        }

        // Cada candidato conserva seus próprios campos, sem inferir setor ou usuário.

        foreach ($this->linhas as $linha) {

            $colunas = [

                'glpi_tickets_users.users_id' => $linha['usuarios_atribuidos'],

                'glpi_groups_tickets.groups_id' => $linha['grupos_atribuidos'],

            ];

            // Qualifica os campos de chamado para comparar as condições recebidas.

            foreach ($linha as $campo => $valor) {

                $colunas['glpi_tickets.' . $campo] = $valor;

            }

            // O avaliador genérico não conhece papéis; usa somente as condições fornecidas.

            if (self::atende($criterios['WHERE'], $colunas)) {

                $selecionados[] = $linha;

            }

        }

        usort(

            $selecionados,

            /**

             * Reproduz somente a ordem pública descendente por identificador.

             */

            static function (array $primeiro, array $segundo): int {

                return $segundo['id'] <=> $primeiro['id'];

            }

        );

        return array_slice($selecionados, $criterios['START'] ?? 0, $criterios['LIMIT'] ?? count($selecionados));

    }

    /**

     * Interpreta AND, OR, NOT, igualdade e listas de valores do contrato de consulta.

     */

    private static function atende(array $condicoes, array $colunas, string $conector = 'AND'): bool {

        $resultados = [];

        // Ramos numéricos conservam os parênteses dos conectivos aninhados.

        foreach ($condicoes as $campo => $valor) {

            // Um ramo numérico é um grupo independente de condições AND.

            if (is_int($campo)) {

                $resultados[] = self::atende($valor, $colunas);

            } elseif ($campo === 'AND' || $campo === 'OR') {

                // Conectivos explícitos determinam como combinar seus próprios filhos.

                $resultados[] = self::atende($valor, $colunas, $campo);

            } elseif ($campo === 'NOT') {

                // Inverte apenas o grupo de condições delimitado.

                $resultados[] = !self::atende($valor, $colunas);

            } else {

                // Arrays de atores representam múltiplas atribuições; qualquer igualdade corresponde.

                $atuais = (array) ($colunas[$campo] ?? null);

                $esperados = (array) $valor;

                $resultados[] = array_intersect($atuais, $esperados) !== [];

            }

        }

        return $conector === 'OR' ? in_array(true, $resultados, true) : !in_array(false, $resultados, true);

    }

}

require_once dirname(__DIR__) . '/src/ConsultaDeChamados.php';

require_once dirname(__DIR__) . '/src/AcompanhamentoDaEquipe.php';

require_once dirname(__DIR__) . '/src/RepositorioDeRascunhos.php';

$verificacoes = 0;

/**

 * Interrompe a suíte com o comportamento que deixou de ser respeitado.

 */

function conferirConsulta(bool $condicao, string $cenario): void {

    global $verificacoes;

    $verificacoes++;

    // Uma divergência deve impedir a entrega da alteração.

    if (!$condicao) {

        throw new RuntimeException($cenario);

    }

}

/**

 * Cria chamado artificial com atores atribuídos e campos mínimos da apresentação.

 */

function chamadoSintetico(int $id, int $entidade, array $grupos, array $usuarios, int $estado = 2): array {

    return [

        'id' => $id,

        'name' => 'Chamado sintético ' . $id,

        'status' => $estado,

        'entities_id' => $entidade,

        'itilcategories_id' => 11,

        'date_mod' => '2026-10-26',

        'is_deleted' => 0,

        'grupos_atribuidos' => $grupos,

        'usuarios_atribuidos' => $usuarios,

    ];

}

/**

 * Compara conjuntos visíveis sem acoplar todos os cenários à ordem de apresentação.

 */

function idsDaConsulta(array $resultado): array {

    $ids = array_column($resultado['chamados'], 'id');

    sort($ids);

    return $ids;

}

/**

 * Exige recusa de domínio ou HTTP em tentativas de abertura ou filtro indevido.

 */

function recusarConsulta(callable $acao, string $cenario): void {

    $recusou = false;

    try {

        $acao();

    } catch (DomainException | RecusaDaConsulta $erro) {

        // A recusa explícita é o resultado esperado do cenário.

        $recusou = true;

    }

    conferirConsulta($recusou, $cenario);

}

$contexto = [

    'tecnico_id' => 9,

    'perfil_id' => 4,

    'entidade_id' => 2,

    'entidades_ativas_ids' => [2, 3],

];

$setores = [

    ['grupos_ids' => [6], 'entidades_ids' => [2]],

    ['grupos_ids' => [3], 'entidades_ids' => [3]],

];

$tecnico = $contexto + ['visibilidade' => ['papel' => 'tecnico', 'entidades_ids' => [2, 3], 'vinculos' => []]];

$administrador = $contexto + ['visibilidade' => ['papel' => 'administrador_setor', 'entidades_ids' => [2, 3], 'vinculos' => $setores]];

$super = $contexto + ['visibilidade' => ['papel' => 'super_administrador', 'entidades_ids' => [2, 3], 'vinculos' => []]];

$banco = new BancoDasConsultas([

    chamadoSintetico(1, 2, [6], [9]),

    chamadoSintetico(2, 3, [3], [10]),

    chamadoSintetico(3, 3, [6], [10]),

    chamadoSintetico(4, 2, [3], [10]),

    chamadoSintetico(5, 2, [99], [10]) + ['solicitante' => 9],

    chamadoSintetico(6, 2, [6], [10], Ticket::CLOSED),

    chamadoSintetico(7, 2, [6], [10], Ticket::SOLVED),

    array_replace(chamadoSintetico(8, 2, [6], [9]), ['is_deleted' => 1]),

    chamadoSintetico(9, 2, [6], [9]),

    chamadoSintetico(10, 4, [6], [9]),

]);

Ticket::$negados = [9];

$consulta = new ConsultaDeChamados($banco);

conferirConsulta(idsDaConsulta($consulta->listar($tecnico, [])) === [1], 'técnico vê atribuição individual sem depender de categorias ou grupos');

conferirConsulta(idsDaConsulta($consulta->listar($administrador, [99])) === [1, 2], 'setores preservam os pares grupo e entidade');

conferirConsulta(idsDaConsulta($consulta->listar($super, [])) === [1, 2, 3, 4, 5], 'super respeita entidades, lixeira, estados e leitura nativa');

conferirConsulta(idsDaConsulta($consulta->listarPagina($administrador, true)) === [6, 7], 'histórico inclui solucionados e fechados do setor');

conferirConsulta(idsDaConsulta($consulta->listarPagina($administrador, false, 1, 10)) === [2], 'filtro de técnico reduz o setor');

conferirConsulta(idsDaConsulta($consulta->listarPagina($tecnico, false, 1, 10)) === [], 'filtro de técnico não troca o responsável pessoal');

conferirConsulta($consulta->consultar(2, $administrador, [99])['id'] === 2, 'abertura visível preserva categorias operacionais para a camada de importação');

// IDs sem atribuição, com pares cruzados, fechados ou sem leitura devem ser recusados diretamente.

foreach ([[2, $tecnico], [3, $administrador], [4, $administrador], [5, $tecnico], [6, $administrador], [9, $super], [10, $super]] as [$id, $escopo]) {

    recusarConsulta(

        /**

         * Reproduz acesso direto que não pode contornar o mesmo escopo da fila.

         */

        static function () use ($consulta, $id, $escopo): void {

            $consulta->consultar($id, $escopo, []);

        },

        'recusa direta do chamado ' . $id

    );

}

conferirConsulta($consulta->pertenceAoEscopo(6, $administrador), 'pendência encerrada mantém leitura no setor');

conferirConsulta(!$consulta->pertenceAoEscopo(3, $administrador), 'pendência de par cruzado permanece oculta');

$sem_setor = array_replace($administrador, ['visibilidade' => ['papel' => 'administrador_setor', 'entidades_ids' => [2], 'vinculos' => []]]);

conferirConsulta($consulta->listar($sem_setor, [])['chamados'] === [], 'administrador sem setor não recebe visão global');

$linhas = [];

// A primeira página começa depois de mais de um lote inteiro de registros ilegíveis.

for ($id = 1; $id <= 180; $id++) {

    $linhas[] = chamadoSintetico($id, 2, [6], [9]);

}

Ticket::$negados = range(53, 180);

$paginada = new ConsultaDeChamados(new BancoDasConsultas($linhas));

$pagina1 = $paginada->listar($tecnico, [], 1);

$pagina2 = $paginada->listar($tecnico, [], 2);

$pagina3 = $paginada->listar($tecnico, [], 3);

conferirConsulta(array_column($pagina1['chamados'], 'id') === range(52, 28), 'página um completa após lote ilegível');

conferirConsulta(array_column($pagina2['chamados'], 'id') === range(27, 3), 'página dois não repete nem pula autorizados');

conferirConsulta(array_column($pagina3['chamados'], 'id') === [2, 1], 'última página conserva os autorizados restantes');

conferirConsulta($pagina1['tem_proxima'] && $pagina2['tem_proxima'] && !$pagina3['tem_proxima'], 'continuação exige outro chamado legível');

// Chamados ilegíveis depois da página completa não podem produzir uma próxima página vazia.

Ticket::$negados = range(1, 155);

$ultima = $paginada->listar($tecnico, []);

conferirConsulta(count($ultima['chamados']) === 25 && !$ultima['tem_proxima'], 'somente itens legíveis determinam a continuidade');

// Os limites públicos devem ser recusados sem normalizar para outra página.

foreach ([0, -1, ConsultaDeChamados::LIMITE_DE_PAGINAS + 1] as $pagina) {

    recusarConsulta(

        /**

         * Solicita página fora do intervalo aceito para conferir a validação do contrato.

         */

        static function () use ($paginada, $tecnico, $pagina): void {

            $paginada->listar($tecnico, [], $pagina);

        },

        'página inválida ' . $pagina

    );

}

Ticket::$negados = [];

$equipe = new AcompanhamentoDaEquipe($banco);

conferirConsulta($equipe->listarTecnicos([]) === [] && Group_User::$chamadas === [], 'catálogo vazio não consulta API');

$resultado = $equipe->consultar($administrador, [], 9, false);

conferirConsulta($resultado['tecnicos'] === [9 => 'Formato nativo: Sobrenome, Nome'], 'catálogo deduplica integrantes e usa formatador nativo');

conferirConsulta(Group_User::$chamadas[0] === [[6, 3], ['glpi_users.is_deleted' => 0, 'glpi_users.is_active' => 1]], 'API recebe somente grupos de setor e filtros de usuário ativo');

recusarConsulta(

    /**

     * Um filtro externo não pode enumerar nem ampliar a equipe autorizada.

     */

    static function () use ($equipe, $administrador): void {

        $equipe->consultar($administrador, [], 99, false);

    },

    'filtro externo à equipe é recusado'

);

echo $verificacoes . " verificações de consultas e APIs nativas concluídas.\n";

