<?php

namespace GlpiPlugin\Admanagement;

use InvalidArgumentException;

/**
 * Valida os valores recebidos antes de usá-los nas consultas e nos rascunhos.
 */

final class EntradaDaFerramenta {

    /**
     * Reúne a orientação apresentada quando um texto viola o contrato dos formulários.
     */

    private const MENSAGEM_TEXTO_INVALIDO = 'Há um campo com formato ou tamanho inválido. Revise os dados enviados.';

    /**
     * Aceita somente um identificador positivo, sem converter arrays, booleanos ou texto parcial.
     */

    public static function lerIdentificador(

        mixed $valor

    ): int {

        // Aceita inteiros ou texto decimal estrito; o curto-circuito impede testar arrays como texto.

        if (!is_int($valor) && (!is_string($valor) || !preg_match('/^[1-9][0-9]*$/D', $valor))) {

            throw new InvalidArgumentException('O identificador informado é inválido.');

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
     * Normaliza uma lista de identificadores positivos e admite a entidade raiz quando solicitado.
     */

    public static function lerListaDeIdentificadores(

        mixed $valores,

        bool $permitir_raiz = false

    ): array {

        // Os controles múltiplos nativos enviam texto vazio quando nenhuma opção foi selecionada.

        if ($valores === '') {

            return [];

        }

        // Rejeita valores escalares e mapas associativos antes de percorrer os identificadores.

        if (!is_array($valores) || !array_is_list($valores)) {

            throw new InvalidArgumentException('A lista de identificadores informada é inválida.');

        }

        $identificadores = [];

        // Cada item segue a mesma validação estrita de identificador utilizada nas demais entradas.

        foreach ($valores as $valor) {

            // A entidade raiz usa zero, permitido somente quando o chamador solicita esse alcance.

            if ($permitir_raiz && ($valor === 0 || $valor === '0')) {

                $identificadores[] = 0;

                continue;

            }

            $identificadores[] = self::lerIdentificador($valor);

        }

        return array_values(array_unique($identificadores));

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

            throw new InvalidArgumentException(self::MENSAGEM_TEXTO_INVALIDO);

        }

        // strlen mantém o limite original em bytes, antes de remover espaços nas pontas.

        if (strlen($valor) > $limite) {

            throw new InvalidArgumentException(self::MENSAGEM_TEXTO_INVALIDO);

        }

        // O modificador u verifica se o texto possui codificação UTF-8 válida.

        if (preg_match('//u', $valor) !== 1) {

            throw new InvalidArgumentException(self::MENSAGEM_TEXTO_INVALIDO);

        }

        // Rejeita caracteres de controle que não pertencem a um campo de texto.

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $valor)) {

            throw new InvalidArgumentException(self::MENSAGEM_TEXTO_INVALIDO);

        }

        // Recusa quebras de linha e tabulações somente nos campos definidos como uma única linha.

        if (!$multilinha && preg_match('/[\r\n\t]/', $valor)) {

            throw new InvalidArgumentException(self::MENSAGEM_TEXTO_INVALIDO);

        }

        // Remove somente os espaços das pontas após validar o conteúdo recebido.

        return trim($valor);

    }

}

