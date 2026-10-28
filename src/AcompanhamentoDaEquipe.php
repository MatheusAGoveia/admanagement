<?php

namespace GlpiPlugin\Admanagement;

use DbUtils;

use DomainException;

use Group_User;

use User;

/**

 * Consulta atendimento coletivo usando o escopo do perfil e os catálogos nativos do GLPI.

 */

final class AcompanhamentoDaEquipe {

    /**

     * Compartilha a conexão já aberta pelo GLPI sem criar conexão ou gravação paralela.

     */

    public function __construct(

        private readonly object $banco

    ) {

    }

    /**

     * Obtém integrantes ativos dos setores autorizados pelo catálogo nativo de grupos e usuários.

     */

    public function listarTecnicos(

        array $grupos

    ): array {

        // Sem grupos de setor não há integrantes para consultar.

        if ($grupos === []) {

            return [];

        }

        $tecnicos = [];

        $formatador = new DbUtils();

        $condicoes = [

            User::getTable() . '.is_deleted' => 0,

            User::getTable() . '.is_active' => 1,

        ];

        // A API nativa conserva o vínculo aos grupos; nomes seguem as preferências do GLPI.

        foreach (Group_User::getGroupUsers($grupos, $condicoes) as $usuario) {

            $id = (int) $usuario['id'];

            $tecnicos[$id] = $formatador->formatUserName(

                $id,

                $usuario['name'] ?? '',

                $usuario['realname'] ?? '',

                $usuario['firstname'] ?? ''

            );

        }

        return $tecnicos;

    }

    /**

     * Apresenta fila ou histórico no mesmo escopo, com filtro opcional que apenas reduz a consulta.

     */

    public function consultar(

        array $contexto,

        array $categorias,

        ?int $tecnico,

        bool $historico,

        int $pagina = 1

    ): array {

        $escopo = $contexto['visibilidade'] ?? ['papel' => 'tecnico', 'vinculos' => []];

        $tecnicos = [];

        // O técnico não enumera colegas nem usa um grupo como concessão de acesso.

        if ($escopo['papel'] === 'tecnico') {

            $tecnicos[$contexto['tecnico_id']] = 'Meu usuário';

        } elseif ($escopo['papel'] === 'administrador_setor') {

            // O catálogo administrativo inclui somente grupos dos setores associados ao perfil.

            $grupos_autorizados = [];

            // Reúne integrantes sem misturar o catálogo com os pares de autorização da consulta.

            foreach ($escopo['vinculos'] as $vinculo) {

                $grupos_autorizados = array_merge($grupos_autorizados, $vinculo['grupos_ids']);

            }

            $tecnicos = $this->listarTecnicos(array_values(array_unique($grupos_autorizados)));

        }

        // Um técnico selecionado fora do catálogo não amplia o escopo do perfil.

        if ($tecnico !== null && $escopo['papel'] !== 'super_administrador' && !isset($tecnicos[$tecnico])) {

            throw new DomainException('O técnico selecionado não pertence ao escopo autorizado.');

        }

        $resultado = (new ConsultaDeChamados($this->banco))->listarPagina($contexto, $historico, $pagina, $tecnico);

        $resultado['tecnicos'] = $tecnicos;

        return $resultado;

    }

}


