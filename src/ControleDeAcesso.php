<?php

namespace GlpiPlugin\Admanagement;

/**
 * Aplica as listas próprias, com exceção do Super-Admin validado e do laboratório explícito.
 */

final class ControleDeAcesso {

    /**
     * Valida o contexto e aplica as listas, dispensadas pelo Super-Admin validado ou pelo laboratório.
     */

    public static function avaliarPerfilGrupoEEntidade(

        array $contexto,

        array $configuracao

    ): bool {

        // Todos os dados precisam estar presentes antes de conferir as permissões.

        if (

            !isset(

                $contexto['perfil_atual'],

                $contexto['entidade_atual'],

                $contexto['grupos_atuais']

            )

        ) {

            return false;

        }

        // Perfil zero não representa um perfil autorizado, mesmo se estiver na lista.

        $perfil_atual = self::normalizarId($contexto['perfil_atual']);

        // Recusa perfil ausente, inválido ou zero antes de avaliar permissões.

        if ($perfil_atual === null || $perfil_atual === 0) {

            return false;

        }

        // A entidade zero pode representar a raiz; somente null significa valor inválido.

        $entidade_atual = self::normalizarId($contexto['entidade_atual']);

        // Recusa entidade inválida sem convertê-la automaticamente na entidade raiz.

        if ($entidade_atual === null) {

            return false;

        }

        // As listas são verificadas por inteiro para não aceitar dados parcialmente válidos.

        $grupos_atuais = self::normalizarLista($contexto['grupos_atuais']);

        // Nega acesso quando os grupos nativos não formam uma lista válida de IDs.

        if ($grupos_atuais === null) {

            return false;

        }

        // O adaptador da sessão identifica o Super-Admin; somente o booleano true dispensa as listas próprias.

        if (($contexto['super_administrador'] ?? false) === true) {

            return true;

        }

        // Nega acesso quando alguma lista obrigatória de perfil, grupo ou entidade não foi configurada.

        if (

            !isset(

                $configuracao['perfis_permitidos'],

                $configuracao['grupos_permitidos'],

                $configuracao['entidades_permitidas']

            )

        ) {

            return false;

        }

        // Exceção local autorizada para testes: pula somente as listas próprias do plugin.

        // A sessão precisa continuar com perfil, entidade e grupos estruturalmente válidos.

        if (

            isset($configuracao['acesso_temporario_para_testes'])

            && $configuracao['acesso_temporario_para_testes'] === true

        ) {

            return true;

        }

        $perfis_permitidos = self::normalizarLista($configuracao['perfis_permitidos']);

        // Nega acesso quando a lista de perfis autorizados contém valores inválidos.

        if ($perfis_permitidos === null) {

            return false;

        }

        $grupos_permitidos = self::normalizarLista($configuracao['grupos_permitidos']);

        // Nega acesso quando a lista de grupos autorizados contém valores inválidos.

        if ($grupos_permitidos === null) {

            return false;

        }

        $entidades_permitidas = self::normalizarLista($configuracao['entidades_permitidas']);

        // Nega acesso quando a lista de entidades autorizadas contém valores inválidos.

        if ($entidades_permitidas === null) {

            return false;

        }

        // O terceiro argumento true exige que valor e tipo sejam iguais.

        if (!in_array($perfil_atual, $perfis_permitidos, true)) {

            return false;

        }

        // Exige que a entidade ativa esteja explicitamente na lista permitida.

        if (!in_array($entidade_atual, $entidades_permitidas, true)) {

            return false;

        }

        // Basta um dos grupos do técnico estar na lista permitida.

        foreach ($grupos_atuais as $grupo_id) {

            // Autoriza somente quando um dos grupos atuais também está na lista de grupos permitidos.

            if (in_array($grupo_id, $grupos_permitidos, true)) {

                return true;

            }

        }

        // Chegar até aqui significa que nenhum grupo do técnico estava permitido.

        return false;

    }

    /**
     * Converte um inteiro não negativo ou texto decimal válido; retorna null nos demais casos.
     */

    private static function normalizarId(

        mixed $valor

    ): ?int {

        // Submete valores não inteiros à validação estrita do formato decimal.

        if (!is_int($valor)) {

            // Recusa tipos que não representam um ID inteiro ou uma string decimal.

            if (!is_string($valor)) {

                return null;

            }

            // Aceita "0" ou dígitos sem zeros à esquerda. Não aceita espaços ou sinais.

            if (!preg_match('/^(0|[1-9][0-9]*)$/D', $valor)) {

                return null;

            }

        }

        // O filtro também rejeita números maiores do que o PHP consegue representar.

        $opcoes = ['options' => ['min_range' => 0]];

        $identificador = filter_var($valor, FILTER_VALIDATE_INT, $opcoes);

        // Devolve ausência de ID quando o filtro nativo rejeita o valor ou seu intervalo.

        if ($identificador === false) {

            return null;

        }

        return $identificador;

    }

    /**
     * Valida todos os IDs da lista, rejeitando a lista inteira se houver valor inválido.
     */

    private static function normalizarLista(

        mixed $valores

    ): ?array {

        // Recusa permissões que não foram fornecidas como uma lista.

        if (!is_array($valores)) {

            return null;

        }

        // Uma lista usa índices 0, 1, 2...; um array com nomes de campos não serve aqui.

        if (!array_is_list($valores)) {

            return null;

        }

        $identificadores = [];

        // Normaliza todos os IDs da lista, sem aceitar apenas sua parte válida.

        foreach ($valores as $valor) {

            $identificador = self::normalizarId($valor);

            // Invalida a lista inteira quando um dos seus identificadores não passa na validação.

            if ($identificador === null) {

                return null;

            }

            $identificadores[] = $identificador;

        }

        return $identificadores;

    }

}

