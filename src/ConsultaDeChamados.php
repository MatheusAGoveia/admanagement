<?php

namespace GlpiPlugin\Admanagement;

use CommonITILActor;

use Glpi\Exception\Http\AccessDeniedHttpException;

use Group_Ticket;

use Ticket;

use Ticket_User;

/**
 * Consulta a fila respeitando categoria, entidade, atribuição e permissão de leitura.
 */

final class ConsultaDeChamados {

    // A tela e a consulta compartilham o mesmo limite de paginação.

    public const LIMITE_DE_PAGINAS = 100000;

    // Quantidade máxima de registros da consulta exibidos em cada página.

    private const TAMANHO_DA_PAGINA = 25;

    // readonly impede trocar a conexão depois de sua primeira definição.

    private readonly object $banco;

    /**
     * Recebe a conexão que o bootstrap do GLPI já abriu, sem conexão paralela.
     */

    public function __construct( object $banco )
     {

        $this->banco = $banco;

    }

    /**
     * Lista somente chamados abertos atribuídos ao técnico ou a seus grupos, com paginação.
     */

    public function listar(

        array $contexto,

        array $categorias,

        int $pagina = 1

    ): array {

        // Sem categorias habilitadas, a fila permanece vazia e nenhuma consulta é executada.

        if ($categorias === []) {

            return [

                'chamados' => [],

                'tem_proxima' => false

            ];

        }

        $criterios = $this->montarCriterios($contexto, $categorias);

        // Um registro extra informa se há próxima página, sem contar todo o banco.

        $criterios['LIMIT'] = self::TAMANHO_DA_PAGINA + 1;

        // Limita o número da página ao intervalo aceito e calcula o deslocamento dos registros.

        $pagina_consultada = $pagina;

        // Normaliza números inferiores à primeira página sem produzir deslocamento negativo.

        if ($pagina_consultada < 1) {

            $pagina_consultada = 1;

        }

        // Interrompe consultas que ultrapassam o limite de paginação da ferramenta.

        if ($pagina_consultada > self::LIMITE_DE_PAGINAS) {

            $pagina_consultada = self::LIMITE_DE_PAGINAS;

        }

        $criterios['START'] = ($pagina_consultada - 1) * self::TAMANHO_DA_PAGINA;

        // request devolve registros que podem ser percorridos com foreach.

        $linhas = [];

        // Coleta os registros do banco, incluindo o item extra que indica próxima página.

        foreach ($this->banco->request($criterios) as $linha) {

            $linhas[] = $linha;

        }

        // O registro extra só indica continuidade; ele não aparece nesta página.

        $tem_proxima = count($linhas) > self::TAMANHO_DA_PAGINA;

        $chamados = [];

        // Descarta o registro extra antes de verificar a leitura de cada chamado da página.

        $linhas_da_pagina = array_slice($linhas, 0, self::TAMANHO_DA_PAGINA);

        // Confere o direito nativo de leitura em cada item da página atual.

        foreach ($linhas_da_pagina as $linha) {

            $ticket = new Ticket();

            $chamado_id = (int) $linha['id'];

            // A atribuição não substitui os direitos nativos de leitura deste chamado.

            if ($ticket->can($chamado_id, READ)) {

                $chamados[] = $this->normalizarChamado($linha);

            }

        }

        return [

            'chamados' => $chamados,

            'tem_proxima' => $tem_proxima,

        ];

    }

    /**
     * Revalida o chamado também para URLs e POSTs diretos, antes de importar ou revisar.
     */

    public function consultar( int $chamado_id, array $contexto, array $categorias  ): array {

        // Procura o chamado somente quando há categorias operacionais configuradas.

        if ($categorias !== []) {

            // A correção por técnico habilitado respeita categoria/entidade/recurso, sem impor atribuição pessoal.

            $criterios = $this->montarCriterios($contexto, $categorias);

            unset($criterios['WHERE']['OR'], $criterios['LEFT JOIN']);

            $criterios['WHERE'][Ticket::getTable() . '.id'] = $chamado_id;

            $criterios['LIMIT'] = 1;

            // Examina o resultado do ID solicitado e exige leitura nativa antes de devolvê-lo.

            foreach ($this->banco->request($criterios) as $linha) {

                $ticket = new Ticket();

                // Mesmo dentro da fila, o chamado exige permissão nativa de leitura.

                if ($ticket->can($chamado_id, READ)) {

                    return $this->normalizarChamado($linha);

                }

            }

        }

        // Categorias vazias, ausência na fila ou falta de leitura resultam em acesso negado.

        RepositorioDeRascunhos::invalidarTodosDaSessao();

        throw new AccessDeniedHttpException('O chamado não está disponível no escopo autorizado ou você não tem direito de leitura.');

    }

    /**
     * Monta os critérios com o papel de atribuição, sem confundir solicitante ou observador.
     */

    private function montarCriterios( array $contexto, array $categorias ): array {

        // Obtém os nomes das tabelas pelos modelos do GLPI.

        $tabela = Ticket::getTable();

        $tabela_usuarios = Ticket_User::getTable();

        $tabela_grupos = Group_Ticket::getTable();

        // A atribuição direta ao técnico sempre participa do filtro.

        $atribuicao = [$tabela_usuarios . '.users_id' => $contexto['tecnico_id']];

        // Havendo grupos no contexto, aceita também chamados atribuídos a qualquer um deles.

        if ($contexto['grupos_ids'] !== []) {

            $atribuicao[$tabela_grupos . '.groups_id'] = $contexto['grupos_ids'];

        }

        // SELECT indica os campos devolvidos; FROM indica a tabela principal.

        $campos = [

            'id',

            'name',

            'status',

            'entities_id',

            'itilcategories_id',

            'date_mod'

        ];

        $criterios = [];

        $criterios['SELECT'] = [$tabela => $campos];

        // DISTINCT evita repetir um chamado com vários técnicos ou grupos atribuídos.

        $criterios['DISTINCT'] = true;

        $criterios['FROM'] = $tabela;

        // LEFT JOIN liga o chamado aos usuários e grupos, permitindo uma das atribuições.

        // FKEY informa quais campos fazem a ligação. ASSIGN indica o responsável.

        $criterios['LEFT JOIN'] = [

            $tabela_usuarios => [

                'FKEY' => [

                    $tabela_usuarios => 'tickets_id',

                    $tabela => 'id'

                ],

                $tabela_usuarios . '.type' => CommonITILActor::ASSIGN,

            ],

            $tabela_grupos => [

                'FKEY' => [

                    $tabela_grupos => 'tickets_id',

                    $tabela => 'id'

                ],

                $tabela_grupos . '.type' => CommonITILActor::ASSIGN,

            ],

        ];

        // WHERE reúne os filtros que o chamado precisa atender ao mesmo tempo.

        $criterios['WHERE'] = [

            $tabela . '.is_deleted' => 0,

            $tabela . '.entities_id' => $contexto['entidade_id'],

            $tabela . '.itilcategories_id' => $categorias,

            // NOT exclui solucionados e fechados; OR aceita técnico ou grupo atribuído.

            'NOT' => [

                $tabela . '.status' => [

                    Ticket::SOLVED,

                    Ticket::CLOSED

                ]

            ],

            'OR' => $atribuicao,

        ];

        // O iterador nativo recebe campo e direção juntos; apresenta os maiores IDs primeiro.

        $criterios['ORDER'] = [$tabela . '.id DESC'];

        return $criterios;

    }

    /**
     * Converte apenas IDs e estado, preservando o título real para apresentação com escape.
     */

    private function normalizarChamado( array $linha
    ): array {

        // Padroniza os campos numéricos, que podem chegar do banco como strings.

        foreach ([

            'id',

            'status',

            'entities_id',

            'itilcategories_id'

        ] as $campo) {

            $linha[$campo] = (int) $linha[$campo];

        }

        return $linha;

    }

}

