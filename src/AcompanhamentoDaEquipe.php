<?php

namespace GlpiPlugin\Admanagement;

use CommonITILActor;

use DomainException;

use Ticket;

/**
 * Consulta atendimento coletivo sem confundir histórico de chamados com efeitos no AD.
 */

final class AcompanhamentoDaEquipe {

    /**
     * Reutiliza a conexão GLPI recebida, sem conexão paralela ou gravações.
     */

    public function __construct(

        private readonly object $banco

    ) {

    }

    /**
     * Obtém os técnicos dos grupos autorizados, sem fabricar integrantes da equipe.
     */

    public function listarTecnicos(

        array $grupos

    ): array {

        // Evita consultar integrantes quando nenhum grupo de equipe foi configurado.

        if ($grupos === []) {

            return [];

        }

        $criterios = [

            'SELECT' => [

                'glpi_users.id',

                'glpi_users.name',

                'glpi_users.firstname',

                'glpi_users.realname'

            ],

            'DISTINCT' => true,

            'FROM' => 'glpi_users',

            'INNER JOIN' => [

                'glpi_groups_users' => [

                    'FKEY' => [

                        'glpi_groups_users' => 'users_id',

                        'glpi_users' => 'id'

                    ]

                ],

            ],

            'WHERE' => [

                'glpi_groups_users.groups_id' => $grupos,

                'glpi_users.is_deleted' => 0,

                'glpi_users.is_active' => 1

            ],

            'ORDER' => ['glpi_users.name ASC'],

        ];

        $tecnicos = [];

        // Monta o catálogo de técnicos ativos com nome de apresentação e ID nativo.

        foreach ($this->banco->request($criterios) as $linha) {

            $id = (int) $linha['id'];

            $nome = trim(($linha['firstname'] ?? '') . ' ' . ($linha['realname'] ?? ''));

            $tecnicos[$id] = $nome !== '' ? $nome : (string) $linha['name'];

        }

        return $tecnicos;

    }

    /**
     * Lista tickets da equipe inteira ou de um técnico, preservando entidade e direito por recurso.
     */

    public function consultar(

        array $contexto,

        array $categorias,

        array $grupos,

        ?int $tecnico,

        bool $historico,

        int $pagina = 1

    ): array {

        $escopo = $contexto['visibilidade'] ?? ['papel' => 'tecnico', 'vinculos' => []];

        $grupos_autorizados = [];

        // O catálogo de filtros administrativos vem somente dos setores concedidos.

        foreach ($escopo['vinculos'] as $vinculo) {

            $grupos_autorizados = array_merge($grupos_autorizados, $vinculo['grupos_ids']);

        }

        $tecnicos = [];

        // A visão técnica não enumera colegas; o filtro é limitado ao usuário conectado.

        if ($escopo['papel'] === 'tecnico') {

            $tecnicos[$contexto['tecnico_id']] = 'Meu usuário';

        } elseif ($escopo['papel'] === 'administrador_setor') {

            // Grupos de acesso antigos não são tratados como setores administrados.

            $tecnicos = $this->listarTecnicos(array_values(array_unique($grupos_autorizados)));

        }

        // O Super-Admin pode consultar qualquer técnico por ID; o filtro nunca amplia leitura nativa.

        if ($tecnico !== null && $escopo['papel'] !== 'super_administrador' && !isset($tecnicos[$tecnico])) {

            throw new DomainException('O técnico selecionado não pertence ao escopo autorizado.');

        }

        $resultado = (new ConsultaDeChamados($this->banco))->listarPagina($contexto, $historico, $pagina, $tecnico);

        $resultado['tecnicos'] = $tecnicos;

        return $resultado;

    }

}

