<?php

namespace GlpiPlugin\Admanagement;

use CommonITILActor;

use DomainException;

use Glpi\Exception\Http\AccessDeniedHttpException;

use Group_Ticket;

use Ticket;

use Ticket_User;

/**

 * Reúne os filtros usados na fila, histórico e abertura por ID e exige a leitura nativa.

 * Grupos identificam os setores atribuídos; a autorização vem do perfil resolvido no contexto.

 */

final class ConsultaDeChamados {

    // Limite compartilhado com os controles de navegação das telas.

    public const LIMITE_DE_PAGINAS = 100000;

    // A paginação considera chamados que também passaram pela leitura nativa do GLPI.

    private const TAMANHO_DA_PAGINA = 25;

    // Lotes limitam a memória sem encerrar páginas por causa de registros ilegíveis.

    private const TAMANHO_DO_LOTE = 100;

    /**

     * Reutiliza a conexão já aberta pelo GLPI, sem conexão ou gravação paralela.

     */

    public function __construct(

        private readonly object $banco

    ) {

    }

    /**

     * Mostra a fila aberta; categorias operacionais limitam a importação em sua própria camada.

     */

    public function listar(

        array $contexto,

        array $categorias,

        int $pagina = 1

    ): array {

        return $this->listarPagina($contexto, false, $pagina);

    }

    /**

     * Restringe o escopo pelos filtros opcionais e pagina somente chamados legíveis pelo GLPI.

     */

    public function listarPagina(

        array $contexto,

        bool $historico,

        int $pagina = 1,

        ?int $tecnico = null,

        array $categorias_visuais = []

    ): array {

        // Recusa páginas inválidas antes de consultar ou calcular deslocamentos.

        if ($pagina < 1 || $pagina > self::LIMITE_DE_PAGINAS) {

            throw new DomainException('Página fora do intervalo permitido.');

        }

        $criterios = $this->montarCriterios($contexto, $historico);

        // O filtro selecionado restringe a atribuição, preservando os setores e entidades.

        if ($tecnico !== null) {

            $criterios['WHERE']['AND'][] = [Ticket_User::getTable() . '.users_id' => $tecnico];

        }

        // A seleção visual pode reduzir a lista sem ampliar sua autorização.

        if ($categorias_visuais !== []) {

            $criterios['WHERE'][Ticket::getTable() . '.itilcategories_id'] = $categorias_visuais;

        }

        $deslocamento_visivel = ($pagina - 1) * self::TAMANHO_DA_PAGINA;

        $quantidade_visivel = 0;

        $inicio_do_lote = 0;

        $chamados = [];

        // Percorre candidatos em lotes até completar a página e encontrar um próximo item legível.

        while (true) {

            $criterios['START'] = $inicio_do_lote;

            $criterios['LIMIT'] = self::TAMANHO_DO_LOTE;

            $quantidade_do_lote = 0;

            // A leitura por recurso ocorre antes de contar posições da paginação visível.

            foreach ($this->banco->request($criterios) as $linha) {

                $quantidade_do_lote++;

                // Um registro ilegível não ocupa posição nem revela se há próxima página.

                if (!(new Ticket())->can((int) $linha['id'], READ)) {

                    continue;

                }

                $quantidade_visivel++;

                // Ignora somente chamados legíveis pertencentes às páginas anteriores.

                if ($quantidade_visivel <= $deslocamento_visivel) {

                    continue;

                }

                // O item legível além do limite comprova a existência da próxima página.

                if (count($chamados) === self::TAMANHO_DA_PAGINA) {

                    return ['chamados' => $chamados, 'tem_proxima' => true];

                }

                $chamados[] = $this->normalizarChamado($linha);

            }

            $inicio_do_lote += $quantidade_do_lote;

            // Um lote incompleto encerra os candidatos, inclusive quando nenhum é legível.

            if ($quantidade_do_lote < self::TAMANHO_DO_LOTE) {

                break;

            }

        }

        return ['chamados' => $chamados, 'tem_proxima' => false];

    }

    /**

     * Revalida URLs e POSTs na fila aberta, preservando setor, atribuição e leitura nativa.

     */

    public function consultar(

        int $chamado_id,

        array $contexto,

        array $categorias

    ): array {

        $criterios = $this->montarCriterios($contexto, false);

        $criterios['WHERE']['AND'][] = [Ticket::getTable() . '.id' => $chamado_id];

        $criterios['LIMIT'] = 1;

        // Mantém o conjunto completo de filtros também na abertura direta por ID.

        foreach ($this->banco->request($criterios) as $linha) {

            // A leitura nativa continua obrigatória após o filtro de setor ou atribuição.

            if ((new Ticket())->can($chamado_id, READ)) {

                return $this->normalizarChamado($linha);

            }

        }

        RepositorioDeRascunhos::invalidarTodosDaSessao();

        throw new AccessDeniedHttpException('O chamado não está disponível no escopo autorizado ou você não tem direito de leitura.');

    }

    /**

     * Confere pendências no mesmo escopo, inclusive após o encerramento do chamado.

     */

    public function pertenceAoEscopo(

        int $chamado_id,

        array $contexto

    ): bool {

        $criterios = $this->montarCriterios($contexto, null);

        $criterios['WHERE']['AND'][] = [Ticket::getTable() . '.id' => $chamado_id];

        $criterios['LIMIT'] = 1;

        // Confere somente a leitura dos metadados, sem autorizar nova execução.

        foreach ($this->banco->request($criterios) as $linha) {

            return (new Ticket())->can($chamado_id, READ);

        }

        return false;

    }

    /**

     * Mantém cada setor com suas entidades e usa somente atores responsáveis.

     * Histórico nulo permite conferir metadados sem exigir estado aberto ou encerrado.

     */

    private function montarCriterios(

        array $contexto,

        ?bool $historico

    ): array {

        $tabela = Ticket::getTable();

        $usuarios = Ticket_User::getTable();

        $grupos = Group_Ticket::getTable();

        $entidades_ativas = $contexto['entidades_ativas_ids'] ?? [$contexto['entidade_id']];

        $escopo = $contexto['visibilidade'] ?? [

            'papel' => 'tecnico',

            'entidades_ids' => [$contexto['entidade_id']],

            'vinculos' => [],

        ];

        $entidades = array_values(array_intersect($entidades_ativas, $escopo['entidades_ids'] ?? []));

        $condicoes = [

            $tabela . '.is_deleted' => 0,

            $tabela . '.entities_id' => $entidades ?: [-1],

        ];

        // O histórico reúne somente solucionados e fechados no mesmo alcance autorizado.

        if ($historico === true) {

            $condicoes[$tabela . '.status'] = [Ticket::SOLVED, Ticket::CLOSED];

        } elseif ($historico === false) {

            // A fila de importação e revisão exclui os estados já encerrados.

            $condicoes['NOT'] = [$tabela . '.status' => [Ticket::SOLVED, Ticket::CLOSED]];

        }

        // O técnico recebe exclusivamente chamados atribuídos ao seu usuário.

        if ($escopo['papel'] === 'tecnico') {

            $condicoes[$usuarios . '.users_id'] = $contexto['tecnico_id'];

        } elseif ($escopo['papel'] === 'administrador_setor') {

            // Cada setor cria um ramo para não cruzar grupos e entidades de setores diferentes.

            $setores = [];

            // A união aceita qualquer par autorizado, sem conceder acesso por associação ao grupo.

            foreach ($escopo['vinculos'] as $vinculo) {

                $setores[] = [

                    $grupos . '.groups_id' => $vinculo['grupos_ids'] ?: [-1],

                    $tabela . '.entities_id' => $vinculo['entidades_ids'] ?: [-1],

                ];

            }

            // Um administrador sem setores não recebe consulta global por omissão.

            if ($setores === []) {

                $condicoes[$tabela . '.id'] = -1;

            } else {

                // O OR preserva os ramos AND de grupo e entidades do mesmo setor.

                $condicoes['OR'] = $setores;

            }

        } elseif ($escopo['papel'] !== 'super_administrador') {

            // Um papel desconhecido não pode aproveitar o alcance administrativo.

            $condicoes[$tabela . '.id'] = -1;

        }

        return [

            'SELECT' => [$tabela => ['id', 'name', 'status', 'entities_id', 'itilcategories_id', 'date_mod']],

            'DISTINCT' => true,

            'FROM' => $tabela,

            'LEFT JOIN' => [

                $usuarios => [

                    'FKEY' => [$usuarios => 'tickets_id', $tabela => 'id'],

                    $usuarios . '.type' => CommonITILActor::ASSIGN,

                ],

                $grupos => [

                    'FKEY' => [$grupos => 'tickets_id', $tabela => 'id'],

                    $grupos . '.type' => CommonITILActor::ASSIGN,

                ],

            ],

            'WHERE' => $condicoes,

            'ORDER' => [$tabela . '.id DESC'],

        ];

    }

    /**

     * Converte campos numéricos e preserva o título original para escape na apresentação.

     */

    private function normalizarChamado(

        array $linha

    ): array {

        // O banco pode fornecer identificadores como texto; o negócio recebe inteiros.

        foreach (['id', 'status', 'entities_id', 'itilcategories_id'] as $campo) {

            $linha[$campo] = (int) $linha[$campo];

        }

        return $linha;

    }

}


