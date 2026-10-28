<?php

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\ConfiguracaoDeVisibilidade;

use GlpiPlugin\Admanagement\ConsultaDeChamados;

use GlpiPlugin\Admanagement\PoliticaDeVisibilidade;

use GlpiPlugin\Admanagement\RevisaoDaOperacao;

require __DIR__ . '/suporte_perfis.php';

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

$contexto = [

    'tecnico_id' => 9,

    'perfil_id' => 4,

    'grupos_ids' => [7],

    'entidade_id' => 2,

    'entidades_ativas_ids' => [2, 3],

    'direitos_perfil' => ['operar' => true, 'administrar_setores' => true],

];

$setores = [

    ['id' => 1, 'nome' => 'Rede', 'grupos_ids' => [6], 'entidades_ids' => [2]],

    ['id' => 2, 'nome' => 'Suporte', 'grupos_ids' => [3], 'entidades_ids' => [3]],

];

$regra = ['id' => 1, 'origem' => 'perfil', 'alvo_id' => 4, 'setores_ids' => [1], 'entidades_ids' => [2, 3], 'ativa' => true];

$configuracao = ConfiguracaoDeVisibilidade::validar(['versao' => 2, 'setores' => $setores, 'regras' => [$regra]]);

$escopo = PoliticaDeVisibilidade::resolver($contexto, $configuracao);

conferirPerfil($escopo['papel'] === 'administrador_setor' && $escopo['vinculos'][0]['grupos_ids'] === [6] && $escopo['vinculos'][0]['entidades_ids'] === [2], 'perfil com direito nativo recebe somente seu par de setor e entidade');

conferirPerfil(PoliticaDeVisibilidade::resolver(array_replace($contexto, ['perfil_id' => 5]), $configuracao)['papel'] === 'negado', 'perfil ativo diferente não herda supervisão');

conferirPerfil(PoliticaDeVisibilidade::resolver(array_replace($contexto, ['direitos_perfil' => []]), $configuracao)['papel'] === 'negado', 'mapa sem direito nativo não concede acesso');

// Grupos e usuários presentes na sessão ou no documento não substituem o perfil ativo.

foreach (['usuario' => 9, 'grupo' => 7] as $origem => $id) {

    $invalida = ['versao' => 2, 'setores' => $setores, 'regras' => [array_replace($regra, ['origem' => $origem, 'alvo_id' => $id])]];

    conferirPerfil(PoliticaDeVisibilidade::resolver($contexto, $invalida)['papel'] === 'negado', 'origem antiga não autoriza: ' . $origem);

    exigirRecusaDePerfil(

        /**

         * Um documento adulterado não deve guardar concessões por grupos ou usuários.

         */

        static function () use ($invalida): void {

            ConfiguracaoDeVisibilidade::validar($invalida);

        },

        'validação rejeita origem antiga: ' . $origem

    );

}

$duas = $configuracao;

$duas['regras'][] = array_replace($regra, ['id' => 2, 'setores_ids' => [2]]);

$uniao = PoliticaDeVisibilidade::resolver($contexto, $duas);

conferirPerfil(count($uniao['vinculos']) === 2, 'perfil pode supervisionar dois setores sem cruzar suas entidades');

$ctx_tecnico = array_replace($contexto, ['direitos_perfil' => ['operar' => true, 'administrar_setores' => false]]);

$papel_tecnico = PoliticaDeVisibilidade::resolver($ctx_tecnico, $configuracao);

$super = PoliticaDeVisibilidade::resolver(array_replace($contexto, ['super_administrador' => true]), []);

conferirPerfil($super['papel'] === 'super_administrador' && $super['entidades_ids'] === [2, 3], 'superadministrador preserva seleção de entidades');

conferirPerfil(PoliticaDeVisibilidade::resolver(array_replace($contexto, ['entidades_ativas_ids' => []]), $configuracao)['papel'] === 'negado', 'seleção de entidades vazia não vira acesso global');

/**

 * Ordena identificadores para comparar conjuntos independentemente da apresentação.

 */

function idsVisiveis(array $resultado): array {

    $ids = array_column($resultado['chamados'], 'id');

    sort($ids);

    return $ids;

}

$banco = new BancoDaVisibilidade();

$consulta = new ConsultaDeChamados($banco);

$ctx_tecnico['visibilidade'] = $papel_tecnico;

$ctx_setor = array_replace($contexto, ['visibilidade' => $escopo]);

$ctx_uniao = array_replace($contexto, ['visibilidade' => $uniao]);

$ctx_super = array_replace($contexto, ['visibilidade' => $super]);

conferirPerfil(idsVisiveis($consulta->listar($ctx_tecnico, [11])) === [1, 3, 6], 'técnico vê somente atribuição pessoal, inclusive sem grupo');

conferirPerfil(idsVisiveis($consulta->listar($ctx_setor, [11])) === [1, 2, 7], 'supervisor vê seu grupo atribuído, mesmo com outro técnico ou categoria');

conferirPerfil(idsVisiveis($consulta->listar($ctx_uniao, [])) === [1, 2, 4, 7], 'união conserva par Rede entidade 2 e Suporte entidade 3');

conferirPerfil(idsVisiveis($consulta->listar($ctx_super, [])) === [1, 2, 3, 4, 5, 6, 7, 11], 'superadministrador respeita lixeira, estados, entidades e leitura nativa');

conferirPerfil(idsVisiveis($consulta->listarPagina($ctx_setor, false, 1, 9)) === [1], 'filtro visual de técnico conserva o setor');

conferirPerfil(idsVisiveis($consulta->listarPagina($ctx_tecnico, false, 1, 10)) === [], 'filtro visual não substitui atribuição pessoal');

conferirPerfil(idsVisiveis($consulta->listarPagina($ctx_super, false, 1, null, [12])) === [2], 'filtro visual de categoria restringe o conjunto');

conferirPerfil(idsVisiveis($consulta->listarPagina($ctx_setor, true)) === [8], 'histórico usa o mesmo escopo setorial');

conferirPerfil($consulta->consultar(2, $ctx_setor, [11])['id'] === 2, 'categoria operacional não impede abrir um chamado visível');

// As mesmas recusas devem valer para URL direta, leitura nativa e escopo após reatribuição.

foreach ([[2, $ctx_tecnico], [3, $ctx_setor], [5, $ctx_uniao], [10, $ctx_super], [12, $ctx_super], [8, $ctx_super]] as [$id, $ctx]) {

    exigirRecusaDePerfil(

        /**

         * Exercita a consulta direta de um chamado fora do alcance atual.

         */

        static function () use ($consulta, $id, $ctx): void {

            $consulta->consultar($id, $ctx, []);

        },

        'consulta direta recusa chamado ' . $id

    );

}

$primeira = $banco->consultas[0];

conferirPerfil($primeira['LEFT JOIN']['glpi_tickets_users']['glpi_tickets_users.type'] === 2 && $primeira['LEFT JOIN']['glpi_groups_tickets']['glpi_groups_tickets.type'] === 2, 'somente atores atribuídos entram na consulta');

conferirPerfil(!$consulta->pertenceAoEscopo(3, $ctx_setor) && $consulta->pertenceAoEscopo(8, $ctx_setor), 'pendências conservam setor também nos chamados encerrados');

iniciarSessaoDePerfil(0, 40);

$_SESSION['glpiactiveprofile']['config'] = UPDATE;

$entrada_setor = ['tipo_cadastro' => 'setores', 'cadastro_id' => '', 'nome_setor' => 'Rede', 'grupos_ids' => ['6'], 'entidades_ids' => ['2'], 'revisao_politica' => ConfiguracaoDeVisibilidade::assinatura(null)];

ConfiguracaoDeVisibilidade::alterar($entrada_setor);

$salvo = ConfiguracaoDeVisibilidade::consultar();

conferirPerfil($salvo['setores'][0]['grupos_ids'] === [6], 'cadastro normaliza grupo atribuído sem conceder permissão');

$entrada_regra = ['tipo_cadastro' => 'regras', 'cadastro_id' => '', 'perfil_id' => '4', 'setores_ids' => ['1'], 'entidades_ids' => ['2'], 'ativa' => '1', 'revisao_politica' => ConfiguracaoDeVisibilidade::assinatura($salvo)];

ConfiguracaoDeVisibilidade::alterar($entrada_regra);

conferirPerfil(ConfiguracaoDeVisibilidade::consultar()['regras'][0]['alvo_id'] === 4, 'vínculo usa exclusivamente o perfil selecionado');

exigirRecusaDePerfil(

    /**

     * Uma versão antiga não deve sobrescrever o vínculo salvo por outra aba.

     */

    static function () use ($entrada_regra): void {

        ConfiguracaoDeVisibilidade::alterar($entrada_regra);

    },

    'revisão administrativa antiga é recusada'

);

$entrada_regra['revisao_politica'] = ConfiguracaoDeVisibilidade::assinatura(ConfiguracaoDeVisibilidade::consultar());

$antes = Config::$valores;

// Nenhum formulário parcial ou origem antiga deve produzir gravação administrativa parcial.

foreach ([['perfil_id' => 99], ['perfil_id' => '4abc'], ['setores_ids' => []], ['entidades_ids' => [4]], ['papel' => 'super_administrador'], ['origem' => 'usuario'], ['origem' => 'grupo']] as $mudanca) {

    exigirRecusaDePerfil(

        /**

         * Reproduz uma tentativa inválida de mapear um setor ao perfil.

         */

        static function () use ($entrada_regra, $mudanca): void {

            ConfiguracaoDeVisibilidade::alterar(array_replace($entrada_regra, $mudanca));

        },

        'mapa inválido não grava: ' . json_encode($mudanca)

    );

    conferirPerfil(Config::$valores === $antes, 'falha não produz gravação parcial');

}

$atualizacao = array_replace($entrada_regra, ['cadastro_id' => '1', 'ativa' => '0']);

ConfiguracaoDeVisibilidade::alterar($atualizacao);

conferirPerfil(PoliticaDeVisibilidade::resolver($contexto, ConfiguracaoDeVisibilidade::consultar())['papel'] === 'negado', 'inativar vínculo revoga supervisão sem recuar para técnico');

$remover_setor = ['tipo_cadastro' => 'setores', 'cadastro_id' => '1', 'excluir' => '1', 'revisao_politica' => ConfiguracaoDeVisibilidade::assinatura(ConfiguracaoDeVisibilidade::consultar())];

exigirRecusaDePerfil(

    /**

     * Um setor referenciado permanece íntegro inclusive quando o vínculo está inativo.

     */

    static function () use ($remover_setor): void {

        ConfiguracaoDeVisibilidade::alterar($remover_setor);

    },

    'setor com vínculo inativo não pode ser excluído'

);

ConfiguracaoDeVisibilidade::alterar(array_replace($remover_setor, ['tipo_cadastro' => 'regras']));

$remover_setor['revisao_politica'] = ConfiguracaoDeVisibilidade::assinatura(ConfiguracaoDeVisibilidade::consultar());

ConfiguracaoDeVisibilidade::alterar($remover_setor);

conferirPerfil(ConfiguracaoDeVisibilidade::consultar() === ConfiguracaoDeVisibilidade::vazia(), 'exclusão explícita mantém mapa atual vazio sem herdar concessões antigas');

iniciarSessaoDePerfil(READ | UPDATE);

exigirRecusaDePerfil(

    /**

     * Supervisores operacionais não podem configurar seus próprios setores.

     */

    static function () use ($entrada_setor): void {

        ConfiguracaoDeVisibilidade::alterar($entrada_setor);

    },

    'supervisão nativa não concede configuração global'

);

// O contrato real de showFromArray acrescenta [], enquanto show preserva o nome informado.

$_GET = [];

ob_start();

\GlpiPlugin\Admanagement\ApresentacaoDaVisibilidade::mostrar('/plugins/admanagement/front/config.php');

$formulario_de_mapa = ob_get_clean();

conferirPerfil(str_contains($formulario_de_mapa, 'name="setores_ids[]"') && !str_contains($formulario_de_mapa, 'name="setores_ids[][]"'), 'seletor nativo de setores envia lista plana, sem duplicar colchetes');

conferirPerfil(str_contains($formulario_de_mapa, 'name="grupos_ids[]"') && str_contains($formulario_de_mapa, 'name="entidades_ids[]"'), 'seletores nativos de catálogo preservam os nomes de listas');

preg_match('/<select name="(setores_ids[^\"]*)"/', $formulario_de_mapa, $campo_de_setores);

parse_str(urlencode($campo_de_setores[1]) . '=1', $post_do_seletor);

conferirPerfil(ConfiguracaoDeVisibilidade::normalizarLista($post_do_seletor['setores_ids']) === [1], 'POST do seletor múltiplo é aceito pela validação estrita do mapa');

echo $GLOBALS['verificacoes_perfil'] . " verificações de visibilidade por perfil concluídas.\n";
