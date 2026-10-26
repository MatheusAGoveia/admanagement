<?php

namespace GlpiPlugin\Admanagement;

use InvalidArgumentException;

/**
 * Valida os valores recebidos antes de usá-los nas consultas e nos rascunhos.
 */

final class EntradaDaFerramenta {

    /**
     * Aceita somente um identificador positivo, sem converter arrays, booleanos ou texto parcial.
     */

    public static function lerIdentificador(

        mixed $valor

    ): int {

        // Strings precisam ter o formato exato de um ID; inteiros seguem para o filtro.

        if (!is_int($valor)) {

            // Rejeita arrays, booleanos e outros tipos antes de validar o formato decimal do identificador.

            if (!is_string($valor)) {

                throw new InvalidArgumentException('O identificador informado é inválido.');

            }

            // A expressão aceita somente dígitos, começando de 1 a 9.

            if (!preg_match('/^[1-9][0-9]*$/D', $valor)) {

                throw new InvalidArgumentException('O identificador informado é inválido.');

            }

        }

        $opcoes = ['options' => ['min_range' => 1]];

        // Recusa identificadores não positivos ou fora do intervalo de inteiros aceito pelo PHP.

        if (filter_var($valor, FILTER_VALIDATE_INT, $opcoes) === false) {

            throw new InvalidArgumentException('O identificador informado é inválido.');

        }

        // A conversão só acontece depois de todas as verificações.

        return (int) $valor;

    }

    /**
     * Limita texto UTF-8 e rejeita estruturas ou controles que não pertencem ao formulário.
     */

    public static function lerTexto(

        mixed $valor,

        int $limite,

        bool $multilinha = false

    ): string {

        // Exige texto simples para não interpretar arrays ou objetos como conteúdo de campo.

        if (!is_string($valor)) {

            throw new InvalidArgumentException('Há um campo com formato ou tamanho inválido. Revise os dados enviados.');

        }

        // strlen mantém o limite original em bytes, antes de remover espaços nas pontas.

        if (strlen($valor) > $limite) {

            throw new InvalidArgumentException('Há um campo com formato ou tamanho inválido. Revise os dados enviados.');

        }

        // O modificador u verifica se o texto possui codificação UTF-8 válida.

        if (preg_match('//u', $valor) !== 1) {

            throw new InvalidArgumentException('Há um campo com formato ou tamanho inválido. Revise os dados enviados.');

        }

        // Rejeita caracteres de controle que não pertencem a um campo de texto.

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $valor)) {

            throw new InvalidArgumentException('Há um campo com formato ou tamanho inválido. Revise os dados enviados.');

        }

        // Campos de uma linha não podem conter quebras de linha nem tabulações.

        if (!$multilinha) {

            // Recusa quebras de linha e tabulações em campos definidos como uma única linha.

            if (preg_match('/[\r\n\t]/', $valor)) {

                throw new InvalidArgumentException('Há um campo com formato ou tamanho inválido. Revise os dados enviados.');

            }

        }

        // Remove somente os espaços das pontas após validar o conteúdo recebido.

        return trim($valor);

    }

}

