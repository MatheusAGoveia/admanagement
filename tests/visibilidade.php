<?php

use GlpiPlugin\Admanagement\PoliticaDeVisibilidade;

use GlpiPlugin\Admanagement\ConfiguracaoDeVisibilidade;

use GlpiPlugin\Admanagement\ConsultaDeChamados;

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\RevisaoDaOperacao;

require_once dirname(__DIR__) . '/src/PoliticaDeVisibilidade.php';

spl_autoload_register(

    /**
     * Carrega somente classes próprias sem instalar GLPI nem consultar serviços externos.
     */

    static function (string $classe): void {

        $prefixo = 'GlpiPlugin\\Admanagement\\';

        // Não tenta carregar classes nativas, substituídas por dublês exclusivos desta suíte.

        if (str_starts_with($classe, $prefixo)) {

            require_once dirname(__DIR__) . '/src/' . substr($classe, strlen($prefixo)) . '.php';

        }

    }

);

define('READ', 1);

define('UPDATE', 2);

/**
 * Representa a recusa HTTP nativa, sem carregar o kernel do GLPI.
 */

class RecusaDeVisibilidade extends RuntimeException {

}

class_alias(RecusaDeVisibilidade::class, 'Glpi\\Exception\\Http\\AccessDeniedHttpException');

/**
 * Guarda apenas documentos sintéticos de configuração no próprio processo.
 */

class Config {

    public static array $valores = [];

    /**
     * Reproduz leitura de chaves do contexto próprio.
     */

    public static function getConfigurationValues($contexto, $chaves): array {

        return array_intersect_key(self::$valores, array_flip($chaves));

    }

    /**
     * Preserva chaves não alteradas, como a persistência nativa de configurações.
     */

    public static function setConfigurationValues($contexto, $valores): void {

        self::$valores = array_replace(self::$valores, $valores);

    }

}

/**
 * Fornece identidade, direitos e entidades sintéticas para exercitar o adaptador real.
 */

class Session {

    /**
     * Identifica exclusivamente o usuário conectado do cenário.
     */

    public static function getLoginUserID(): int {

        return $_SESSION['glpiID'] ?? 0;

    }

    /**
     * Reproduz a seleção de interface da sessão.
     */

    public static function getCurrentInterface(): string {

        return $_SESSION['glpiactiveprofile']['interface'] ?? '';

    }

    /**
     * Devolve a entidade selecionada sem inferir entidades filhas.
     */

    public static function getActiveEntity(): int {

        return $_SESSION['glpiactive_entity'] ?? 0;

    }

    /**
     * Devolve o escopo recursivo previamente escolhido no laboratório.
     */

    public static function getActiveEntities(): array {

        return $_SESSION['glpiactiveentities'] ?? [];

    }

    /**
     * Mantém uma fronteira nativa independente das concessões do plugin.
     */

    public static function haveAccessToEntity($id, $recursivo = false): bool {

        return in_array($id, self::getActiveEntities(), true);

    }

    /**
     * Simula o direito de configuração, sem concedê-lo através dos formulários.
     */

    public static function haveRight($direito, $nivel): bool {

        return $direito === 'config' && $nivel === UPDATE && ($_SESSION['configurar'] ?? false);

    }

    /**
     * Recusa sessões sem login antes das consultas.
     */

    public static function checkLoginUser(): void {

        // Login ausente interrompe o cenário, como a proteção nativa.

        if (!self::getLoginUserID()) {

            throw new RecusaDeVisibilidade();

        }

    }

}

/**
 * Mantém o plugin ativo somente enquanto o cenário autoriza seu uso.
 */

class Plugin {

    /**
     * A desativação não pode ser contornada por uma concessão individual.
     */

    public static function isPluginActive($nome): bool {

        return $_SESSION['plugin_ativo'] ?? true;

    }

}

/**
 * Representa catálogos sintéticos com itens acessíveis, removidos ou desativados.
 */

class Group {

    public array $fields = [];

    /**
     * Reproduz existência e campos mínimos para validar a gravação administrativa.
     */

    public function getFromDB($id): bool {

        $this->fields = ['is_active' => $id !== 88, 'is_deleted' => 0, 'entities_id' => 2];

        return $id !== 99;

    }

    /**
     * Mantém existência e leitura por recurso como condições separadas da configuração.
     */

    public function can($id, $direito): bool {

        return $this->getFromDB($id);

    }

}

/**
 * Reutiliza o catálogo mínimo para usuários de teste sem dados pessoais.
 */

class User extends Group {

}

/**
 * Reutiliza o catálogo mínimo para perfis de teste.
 */

class Profile extends Group {

}

/**
 * Reutiliza o catálogo mínimo para entidades de teste.
 */

class Entity extends Group {

}

/**
 * Mantém o papel de responsável distinto de solicitantes e observadores.
 */

class CommonITILActor {

    public const ASSIGN = 2;

}

/**
 * Fornece o nome de tabela usado pelo contrato de consulta.
 */

class Ticket_User {

    /**
     * Devolve a tabela de atores individuais.
     */

    public static function getTable(): string {

        return 'glpi_tickets_users';

    }

}

/**
 * Fornece o nome da tabela de grupos responsáveis.
 */

class Group_Ticket {

    /**
     * Devolve a tabela de atores coletivos.
     */

    public static function getTable(): string {

        return 'glpi_groups_tickets';

    }

}

/**
 * Nega um recurso conhecido independentemente do alcance concedido pelo plugin.
 */

class Ticket {

    public const SOLVED = 5, CLOSED = 6;

    /**
     * Devolve a tabela de chamados do contrato GLPI.
     */

    public static function getTable(): string {

        return 'glpi_tickets';

    }

    /**
     * O chamado 12 simula uma recusa nativa posterior ao filtro próprio.
     */

    public function can($id, $direito): bool {

        return $id !== 12;

    }

}

/**
 * Avalia os critérios sobre registros sintéticos para provar isolamento entre setores e técnicos.
 * Não substitui homologação do SQL no banco GLPI real.
 */

class BancoDaVisibilidade {

    public array $consultas = [];

    public array $linhas = [];

    /**
     * Monta chamados com pares de entidade, grupo e técnico deliberadamente conflitantes.
     */

    public function __construct() {

        $cenarios = [[1, 2, 6, 9], [2, 2, 6, 10], [3, 2, 3, 9], [4, 3, 3, 10], [5, 3, 6, 10], [6, 2, 0, 9], [7, 2, 6, 0], [8, 2, 6, 9], [9, 2, 6, 9], [10, 4, 6, 9], [11, 2, 3, 10], [12, 2, 6, 9]];

        // Nenhum dado deste catálogo pertence à instalação do usuário.

        foreach ($cenarios as [$id, $entidade, $grupo, $tecnico]) {

            $this->linhas[] = ['id' => $id, 'name' => 'Chamado sintético ' . $id, 'status' => $id === 8 ? 5 : 2, 'entities_id' => $entidade, 'itilcategories_id' => $id === 2 ? 12 : 11, 'is_deleted' => $id === 9 ? 1 : 0, 'date_mod' => '2026-10-23', 'grupo' => $grupo, 'tecnico' => $tecnico];

        }

    }

    /**
     * Aplica filtros antes da paginação e registra as condições encaminhadas pela classe real.
     */

    public function request(array $criterios): array {

        $this->consultas[] = $criterios;

        $linhas = [];

        // Filtra chamados em memória sem interpretar SQL ou acessar serviços.

        foreach ($this->linhas as $linha) {

            $colunas = ['glpi_tickets_users.users_id' => $linha['tecnico'], 'glpi_groups_tickets.groups_id' => $linha['grupo']];

            // Acrescenta os campos da tabela principal com seus nomes qualificados.

            foreach ($linha as $campo => $valor) {

                $colunas['glpi_tickets.' . $campo] = $valor;

            }

            // O avaliador preserva AND, OR e NOT e não substitui a leitura por recurso.

            if (self::atende($criterios['WHERE'], $colunas)) {

                $linhas[] = $linha;

            }

        }

        return array_slice($linhas, $criterios['START'] ?? 0, $criterios['LIMIT'] ?? 100);

    }

    /**
     * Reproduz os conectivos usados no contrato para detectar vazamento por cruzamento de pares.
     */

    private static function atende(array $condicoes, array $colunas, string $conector = 'AND'): bool {

        $resultados = [];

        // Cada subarray numérico corresponde a um ramo AND, como no iterador nativo.

        foreach ($condicoes as $campo => $valor) {

            // Ramos numéricos agrupam critérios sem herdar o OR externo.

            if (is_int($campo)) {

                $resultados[] = self::atende($valor, $colunas);

            } elseif ($campo === 'AND' || $campo === 'OR') {

                // Conectivos explícitos conservam a precedência dos grupos de critérios.

                $resultados[] = self::atende($valor, $colunas, $campo);

            } elseif ($campo === 'NOT') {

                // O histórico e a fila são conjuntos complementares de estados.

                $resultados[] = !self::atende($valor, $colunas);

            } else {

                // Compara igualdade escalar ou pertencimento a listas de IDs.

                $resultados[] = is_array($valor) ? in_array($colunas[$campo] ?? null, $valor, true) : ($colunas[$campo] ?? null) === $valor;

            }

        }

        return $conector === 'OR' ? in_array(true, $resultados, true) : !in_array(false, $resultados, true);

    }

}

$verificacoes_visibilidade = 0;

/**
 * Confere o resultado de autorização sem depender de uma instalação GLPI.
 */

function conferirVisibilidade(bool $condicao, string $cenario): void {

    global $verificacoes_visibilidade;

    $verificacoes_visibilidade++;

    // Uma diferença interrompe a suíte com o nome do comportamento comprometido.

    if (!$condicao) {

        throw new RuntimeException($cenario);

    }

}

$contexto = ['tecnico_id' => 9, 'perfil_id' => 4, 'grupos_ids' => [7], 'entidade_id' => 2, 'entidades_ativas_ids' => [2, 3]];

$setores = [

    ['id' => 1, 'nome' => 'Rede', 'grupos_ids' => [6], 'entidades_ids' => [2]],

    ['id' => 2, 'nome' => 'Suporte', 'grupos_ids' => [3], 'entidades_ids' => [3]],

];

$regra = ['id' => 1, 'origem' => 'usuario', 'alvo_id' => 9, 'papel' => 'administrador_setor', 'setores_ids' => [1], 'entidades_ids' => [2, 3], 'ativa' => true];

$configuracao = ['versao' => 1, 'setores' => $setores, 'regras' => [$regra]];

$escopo = PoliticaDeVisibilidade::resolver($contexto, $configuracao);

conferirVisibilidade($escopo['papel'] === 'administrador_setor', 'usuário pode receber administração de setor');

conferirVisibilidade($escopo['vinculos'] === [['setor_id' => 1, 'nome' => 'Rede', 'grupos_ids' => [6], 'entidades_ids' => [2]]], 'entidades e grupos ficam pareados por setor');

conferirVisibilidade(PoliticaDeVisibilidade::resolver(array_replace($contexto, ['tecnico_id' => 10]), $configuracao)['papel'] === 'negado', 'concessão individual não alcança outro usuário');

/**
 * Obtém IDs ordenados para comparar conjuntos, independentemente da ordenação visual.
 */

function idsVisiveis(array $resultado): array {

    $ids = array_column($resultado['chamados'], 'id');

    sort($ids);

    return $ids;

}

/**
 * Executa uma tentativa que precisa falhar sem capturar sucesso como recusa.
 */

function exigirRecusaVisibilidade(callable $acao, string $cenario): void {

    $recusou = false;

    try {

        $acao();

    } catch (InvalidArgumentException | DomainException | RecusaDeVisibilidade $erro) {

        // Somente as recusas esperadas satisfazem o comportamento de proteção.

        $recusou = true;

    }

    conferirVisibilidade($recusou, $cenario);

}

// Cada origem funciona sozinha; nenhuma combinação AND de usuário, grupo e perfil é exigida.

foreach (['usuario' => 9, 'grupo' => 7, 'perfil' => 4] as $origem => $id) {

    $caso = $configuracao;

    $caso['regras'][0] = array_replace($regra, ['origem' => $origem, 'alvo_id' => $id]);

    conferirVisibilidade(PoliticaDeVisibilidade::resolver($contexto, $caso)['papel'] === 'administrador_setor', 'origem independente: ' . $origem);

}

$inativa = $configuracao;

$inativa['regras'][0]['ativa'] = false;

conferirVisibilidade(PoliticaDeVisibilidade::resolver($contexto, $inativa)['papel'] === 'negado', 'regra inativa não concede papel');

$duas = $configuracao;

$duas['regras'][] = array_replace($regra, ['id' => 2, 'origem' => 'grupo', 'alvo_id' => 7, 'setores_ids' => [2]]);

$uniao = PoliticaDeVisibilidade::resolver($contexto, $duas);

conferirVisibilidade(count($uniao['vinculos']) === 2, 'união de setores explicitamente concedidos');

$tecnica = ['versao' => 1, 'setores' => [], 'regras' => [array_replace($regra, ['papel' => 'tecnico', 'setores_ids' => []])]];

$papel_tecnico = PoliticaDeVisibilidade::resolver($contexto, $tecnica);

$duas['regras'][] = array_replace($tecnica['regras'][0], ['id' => 3]);

conferirVisibilidade(PoliticaDeVisibilidade::resolver($contexto, $duas)['papel'] === 'administrador_setor', 'concessão técnica não revoga administração');

$super = PoliticaDeVisibilidade::resolver(array_replace($contexto, ['super_administrador' => true]), []);

conferirVisibilidade($super['papel'] === 'super_administrador', 'Super-Admin dispensa regras próprias');

conferirVisibilidade(PoliticaDeVisibilidade::resolver(array_replace($contexto, ['grupos_ids' => ['7abc'], 'super_administrador' => true]), [])['papel'] === 'negado', 'Super-Admin preserva validação estrutural');

$banco = new BancoDaVisibilidade();

$consulta = new ConsultaDeChamados($banco);

$ctx_tecnico = array_replace($contexto, ['visibilidade' => $papel_tecnico]);

$ctx_setor = array_replace($contexto, ['visibilidade' => $escopo]);

$ctx_uniao = array_replace($contexto, ['visibilidade' => $uniao]);

$ctx_super = array_replace($contexto, ['visibilidade' => $super]);

conferirVisibilidade(idsVisiveis($consulta->listar($ctx_tecnico, [11])) === [1, 3, 6], 'técnico vê somente atribuição individual, inclusive sem grupo');

conferirVisibilidade(idsVisiveis($consulta->listar($ctx_setor, [11])) === [1, 2, 7], 'administrador vê grupo responsável, inclusive outra categoria e outro técnico');

conferirVisibilidade(idsVisiveis($consulta->listar($ctx_uniao, [])) === [1, 2, 4, 7], 'união não cruza Rede da entidade 2 com Rede da entidade 3');

conferirVisibilidade(idsVisiveis($consulta->listar($ctx_super, [])) === [1, 2, 3, 4, 5, 6, 7, 11], 'Super-Admin preserva entidades, lixeira, estados e leitura nativa');

conferirVisibilidade(idsVisiveis($consulta->listarPagina($ctx_setor, false, 1, 9)) === [1], 'filtro de técnico conserva setor');

conferirVisibilidade(idsVisiveis($consulta->listarPagina($ctx_tecnico, false, 1, 10)) === [], 'filtro não substitui atribuição pessoal');

conferirVisibilidade(idsVisiveis($consulta->listarPagina($ctx_super, false, 1, null, [12])) === [2], 'filtro visual explícito restringe o conjunto');

conferirVisibilidade(idsVisiveis($consulta->listarPagina($ctx_setor, true)) === [8], 'histórico usa o mesmo setor e somente solucionados/fechados');

conferirVisibilidade($consulta->consultar(2, $ctx_setor, [11])['id'] === 2, 'abertura visível não é bloqueada por categoria operacional');

// O mesmo ID precisa ser recusado tanto na consulta direta quanto depois de perder a atribuição.

foreach ([[2, $ctx_tecnico], [3, $ctx_setor], [5, $ctx_uniao], [10, $ctx_super], [12, $ctx_super], [8, $ctx_super]] as [$id, $ctx]) {

    exigirRecusaVisibilidade(

        /**
         * Exercita a abertura direta de recurso fora do alcance atual.
         */

        static function () use ($consulta, $id, $ctx): void {

            $consulta->consultar($id, $ctx, []);

        },

        'recusa direta do chamado ' . $id

    );

}

$ultimo = $banco->consultas[0];

conferirVisibilidade($ultimo['LEFT JOIN']['glpi_tickets_users']['glpi_tickets_users.type'] === 2 && $ultimo['LEFT JOIN']['glpi_groups_tickets']['glpi_groups_tickets.type'] === 2, 'solicitante e observador não entram nas junções de atribuição');

$assinatura = RevisaoDaOperacao::assinaturaDoContexto($ctx_tecnico);

conferirVisibilidade($assinatura !== RevisaoDaOperacao::assinaturaDoContexto($ctx_setor), 'mudança de papel invalida resumo');

conferirVisibilidade($assinatura !== RevisaoDaOperacao::assinaturaDoContexto(array_replace($ctx_tecnico, ['assinatura_visibilidade' => 'outra'])), 'mudança de configuração invalida resumo');

$_SESSION = ['glpiID' => 9, 'glpiactiveprofile' => ['id' => 4, 'name' => 'Técnico', 'interface' => 'central'], 'glpigroups' => [], 'glpiactive_entity' => 2, 'glpiactiveentities' => [2, 3]];

Config::$valores['visibilidade'] = json_encode($tecnica);

conferirVisibilidade(AcessoGlpi::podeAcessar(), 'concessão individual funciona sem grupo e sem listas legadas');

$atual = AcessoGlpi::obterContextoDoTecnico();

conferirVisibilidade($atual['visibilidade']['papel'] === 'tecnico' && $atual['tecnico_id'] === 9, 'adaptador entrega papel e usuário da sessão');

$_GET = ['super_administrador' => '1', 'usuario_id' => 9, 'papel' => 'super_administrador'];

$_SESSION['glpiID'] = 10;

conferirVisibilidade(!AcessoGlpi::podeAcessar(), 'URL não substitui usuário alvo');

$_SESSION['glpiID'] = 9;

Config::$valores['visibilidade'] = json_encode(['versao' => 1, 'setores' => [], 'regras' => []]);

conferirVisibilidade(!AcessoGlpi::podeAcessar(), 'política nova vazia não herda acesso anterior');

Config::$valores['visibilidade'] = '{invalido';

conferirVisibilidade(!AcessoGlpi::podeAcessar(), 'política corrompida nega operação');

Config::$valores['visibilidade'] = '{"ausente":true}';

conferirVisibilidade(!AcessoGlpi::podeAcessar(), 'documento parcial não simula ausência da política nova');

Config::$valores['visibilidade'] = '{invalido';

$_SESSION['glpiactiveprofile']['name'] = 'Super-Admin';

$_SESSION['configurar'] = true;

conferirVisibilidade(AcessoGlpi::podeAcessar() && AcessoGlpi::podeConfigurar(), 'Super-Admin mantém configuração e entrada para reparar política');

$documento_corrompido = Config::$valores['visibilidade'];

ConfiguracaoDeVisibilidade::restaurar(['confirmar_restauracao' => '1', 'revisao_documento' => ConfiguracaoDeVisibilidade::assinaturaDoDocumento()]);

conferirVisibilidade(ConfiguracaoDeVisibilidade::consultar()['regras'] === [] && json_decode(Config::$valores['backup_visibilidade_corrompida'], true)['documento'] === $documento_corrompido, 'recuperação salva backup bruto e reinicia política fechada');

exigirRecusaVisibilidade(

    /**
     * Recuperação não deve apagar uma política válida por um POST repetido.
     */

    static function (): void { ConfiguracaoDeVisibilidade::restaurar(['confirmar_restauracao' => '1', 'revisao_documento' => ConfiguracaoDeVisibilidade::assinaturaDoDocumento()]); },

    'recuperação não apaga política válida'

);

conferirVisibilidade(!$consulta->pertenceAoEscopo(3, $ctx_setor) && $consulta->pertenceAoEscopo(8, $ctx_setor), 'metadados de pendências respeitam setor inclusive no chamado encerrado');

Config::$valores = [];

$entrada_setor = ['tipo_cadastro' => 'setores', 'cadastro_id' => '', 'nome_setor' => 'Rede', 'grupos_ids' => ['6'], 'entidades_ids' => ['2'], 'revisao_politica' => ConfiguracaoDeVisibilidade::assinatura(null)];

ConfiguracaoDeVisibilidade::alterar($entrada_setor);

$salvo = ConfiguracaoDeVisibilidade::consultar();

conferirVisibilidade($salvo['setores'][0]['grupos_ids'] === [6] && isset(Config::$valores['acesso_anterior_a_visibilidade']), 'grava setor normalizado e preserva acesso anterior');

$entrada_regra = ['tipo_cadastro' => 'regras', 'cadastro_id' => '', 'origem' => 'usuario', 'usuario_id' => '9', 'papel' => 'administrador_setor', 'setores_ids' => ['1'], 'entidades_ids' => ['2'], 'ativa' => '1', 'revisao_politica' => ConfiguracaoDeVisibilidade::assinatura($salvo)];

ConfiguracaoDeVisibilidade::alterar($entrada_regra);

conferirVisibilidade(ConfiguracaoDeVisibilidade::consultar()['regras'][0]['alvo_id'] === 9, 'concessão individual é salva com alvo explícito');

exigirRecusaVisibilidade(

    /**
     * Uma aba com versão antiga não deve sobrescrever a concessão recém-gravada.
     */

    static function () use ($entrada_regra): void { ConfiguracaoDeVisibilidade::alterar($entrada_regra); },

    'revisão administrativa antiga é recusada'

);

$antes = Config::$valores;

$entrada_regra['revisao_politica'] = ConfiguracaoDeVisibilidade::assinatura(ConfiguracaoDeVisibilidade::consultar());

// Usuário inativo, inexistente, ID parcial e concessão sem setor não podem ser persistidos.

foreach ([['usuario_id' => 88], ['usuario_id' => 99], ['usuario_id' => '9abc'], ['setores_ids' => []], ['entidades_ids' => [4]], ['papel' => 'super_administrador']] as $mudanca) {

    exigirRecusaVisibilidade(

        /**
         * Reproduz uma tentativa inválida de concessão administrativa.
         */

        static function () use ($entrada_regra, $mudanca): void { ConfiguracaoDeVisibilidade::alterar(array_replace($entrada_regra, $mudanca)); },

        'concessão inválida não grava: ' . json_encode($mudanca)

    );

    conferirVisibilidade(Config::$valores === $antes, 'falha não produz concessão parcial');

}

$atualizacao = array_replace($entrada_regra, ['cadastro_id' => '1', 'ativa' => '0']);

ConfiguracaoDeVisibilidade::alterar($atualizacao);

conferirVisibilidade(PoliticaDeVisibilidade::resolver($contexto, ConfiguracaoDeVisibilidade::consultar())['papel'] === 'negado', 'editar concessão para inativa revoga o papel');

$remover_setor = ['tipo_cadastro' => 'setores', 'cadastro_id' => '1', 'excluir' => '1', 'revisao_politica' => ConfiguracaoDeVisibilidade::assinatura(ConfiguracaoDeVisibilidade::consultar())];

exigirRecusaVisibilidade(

    /**
     * Um setor referenciado deve ser preservado mesmo quando a regra está inativa.
     */

    static function () use ($remover_setor): void { ConfiguracaoDeVisibilidade::alterar($remover_setor); },

    'não exclui setor referenciado por regra inativa'

);

$remover_regra = array_replace($remover_setor, ['tipo_cadastro' => 'regras']);

ConfiguracaoDeVisibilidade::alterar($remover_regra);

$remover_setor['revisao_politica'] = ConfiguracaoDeVisibilidade::assinatura(ConfiguracaoDeVisibilidade::consultar());

ConfiguracaoDeVisibilidade::alterar($remover_setor);

conferirVisibilidade(ConfiguracaoDeVisibilidade::consultar() === ['versao' => 1, 'setores' => [], 'regras' => []], 'exclusão explícita preserva política nova vazia sem retornar ao legado');

$_SESSION['glpiactiveprofile']['name'] = 'Administrador de setor';

exigirRecusaVisibilidade(

    /**
     * Direito nativo de configuração sem Super-Admin não permite salvar concessões.
     */

    static function () use ($entrada_regra): void { ConfiguracaoDeVisibilidade::alterar($entrada_regra); },

    'administrador de setor não altera configuração global'

);

echo $verificacoes_visibilidade . " verificações de visibilidade concluídas.\n";

