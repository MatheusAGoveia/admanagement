<?php

namespace GlpiPlugin\Admanagement;

/**
 * Compõe sugestões textuais de login; a consulta ao AD ainda não faz parte desta classe.
 */

final class PoliticaDeLogin {

    /**
     * Gera apenas composições textuais na ordem aprovada; não consulta nem afirma disponibilidade.
     */

    public static function gerarCandidatos(

        string $nome

    ): array {

        // Normalização explícita dos caracteres portugueses, sem dependência adicional.

        $nome = strtr(

            $nome,

            [

                'Á' => 'a',

                'À' => 'a',

                'Â' => 'a',

                'Ã' => 'a',

                'Ä' => 'a',

                'á' => 'a',

                'à' => 'a',

                'â' => 'a',

                'ã' => 'a',

                'ä' => 'a',

                'É' => 'e',

                'È' => 'e',

                'Ê' => 'e',

                'Ë' => 'e',

                'é' => 'e',

                'è' => 'e',

                'ê' => 'e',

                'ë' => 'e',

                'Í' => 'i',

                'Ì' => 'i',

                'Î' => 'i',

                'Ï' => 'i',

                'í' => 'i',

                'ì' => 'i',

                'î' => 'i',

                'ï' => 'i',

                'Ó' => 'o',

                'Ò' => 'o',

                'Ô' => 'o',

                'Õ' => 'o',

                'Ö' => 'o',

                'ó' => 'o',

                'ò' => 'o',

                'ô' => 'o',

                'õ' => 'o',

                'ö' => 'o',

                'Ú' => 'u',

                'Ù' => 'u',

                'Û' => 'u',

                'Ü' => 'u',

                'ú' => 'u',

                'ù' => 'u',

                'û' => 'u',

                'ü' => 'u',

                'Ç' => 'c',

                'ç' => 'c',

            ]

        );

        $nome = trim($nome);

        $nome = strtolower($nome);

        // Separa o nome por espaços e ignora partes vazias.

        $nomes = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY);

        // Encerra sem sugestões quando o nome não contém partes utilizáveis.

        if (!$nomes) {

            return [];

        }

        // Confere todas as partes do nome antes de permitir sua composição automática.

        foreach ($nomes as $parte) {

            // Nomes que exigem outra política não recebem transliteração por suposição.

            if (!preg_match('/^[a-z]+$/D', $parte)) {

                return [];

            }

        }

        $primeiro_nome = $nomes[0];

        $candidatos = [];

        // Começa pelo último sobrenome e termina no segundo nome completo.

        $ultimo_indice = count($nomes) - 1;

        // Combina o primeiro nome com os demais nomes completos, do último para o segundo.

        for ($indice = $ultimo_indice; $indice >= 1; $indice--) {

            $candidato = $primeiro_nome . '.' . $nomes[$indice];

            // Inclui a composição somente se ela passar na barreira inicial de formato e proteção linguística.

            if (self::passaNaVerificacaoInicial($candidato)) {

                $candidatos[] = $candidato;

            }

        }

        // O primeiro nome sozinho é sempre o último candidato a ser avaliado.

        if (self::passaNaVerificacaoInicial($primeiro_nome)) {

            $candidatos[] = $primeiro_nome;

        }

        // Nomes repetidos não devem gerar a mesma sugestão duas vezes.

        $candidatos = array_unique($candidatos);

        return array_values($candidatos);

    }

    /**
     * Aplica somente a barreira inicial conhecida; não representa a política linguística completa.
     */

    public static function passaNaVerificacaoInicial(

        string $login

    ): bool {

        // Mantém o tamanho máximo aceito pela verificação inicial.

        if (strlen($login) > 64) {

            return false;

        }

        // Aceita letras minúsculas, com pontos separando partes não vazias.

        if (!preg_match('/^[a-z]+(?:\.[a-z]+)*$/D', $login)) {

            return false;

        }

        // Impede a composição constrangedora expressamente citada pelo usuário.

        $partes_do_login = explode('.', $login);

        // Recusa o componente constrangedor explicitamente proibido na política aprovada.

        if (in_array('anal', $partes_do_login, true)) {

            return false;

        }

        return true;

    }

}

