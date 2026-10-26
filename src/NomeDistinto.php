<?php

namespace GlpiPlugin\Admanagement;

/**
 * Manipula caminhos LDAP preservando vírgulas escapadas e a identidade do ramo.
 */

final class NomeDistinto {

    /**
     * Retorna o pai separando somente vírgulas que não estão escapadas.
     */

    public static function pai(

        string $dn

    ): string {

        // Percorre o DN para localizar a primeira vírgula que realmente separa o caminho do pai.

        for ($indice = 0; $indice < strlen($dn); $indice++) {

            // Ignora caracteres que não são candidatos a separador de componentes.

            if ($dn[$indice] !== ',') {

                continue;

            }

            $barras = 0;

            // Conta as barras consecutivas anteriores à vírgula para determinar se ela está escapada.

            for (

                $anterior = $indice - 1; $anterior >= 0 && $dn[$anterior] === '\\'; $anterior--

            ) {

                $barras++;

            }

            // Aceita como separador somente uma vírgula precedida por quantidade par de barras.

            if ($barras % 2 === 0) {

                return trim(substr($dn, $indice + 1));

            }

        }

        return '';

    }

    /**
     * Confere apenas o domínio cadastrado; não implementa allowlist de OUs.
     */

    public static function pertenceAoDominio(

        string $dn,

        string $base

    ): bool {

        $atual = trim($dn);

        // Sobe pelos ancestrais do DN até encontrar a Base DN ou terminar o caminho.

        while ($atual !== '') {

            // Reconhece a Base DN sem diferenciar maiúsculas e minúsculas no texto comparado.

            if (strcasecmp($atual, trim($base)) === 0) {

                return true;

            }

            $atual = self::pai($atual);

        }

        return false;

    }

}

