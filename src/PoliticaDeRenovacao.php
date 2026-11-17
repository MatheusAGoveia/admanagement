<?php

namespace GlpiPlugin\Admanagement;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/** Calcula validade de conta com as regras confirmadas, sem consultar ou alterar AD. */
final class PoliticaDeRenovacao {

    /** Traduz apenas os vínculos conhecidos, sem presumir prazo para outras categorias. */
    public static function normalizarVinculo(string $vinculo): string {
        return match (IdentidadeDoDiretorio::normalizarNome($vinculo)) {
            'CONTRATO', 'CONTRATADO' => 'CONTRATO',
            'ESTAGIARIO' => 'ESTAGIARIO',
            // Vínculos não homologados precisam de correção explícita do técnico.
            default => throw new DomainException('Informe vínculo CONTRATO ou ESTAGIÁRIO para renovar.'),
        };
    }

    /** Converte um instante em FileTime sem arredondamento por ponto flutuante. */
    public static function paraFiletime(DateTimeImmutable $instante): string {
        return (string) (($instante->getTimestamp() + 11644473600) * 10000000 + (int) $instante->format('u') * 10);
    }

    /** Reconhece os dois sentinelas AD e converte valores válidos para o fuso da operação. */
    public static function deFiletime(string $valor): ?DateTimeImmutable {
        // Ausência de expiração não será convertida em data fictícia.
        if ($valor === '0' || $valor === '9223372036854775807') {
            return null;
        }
        // O projeto exige PHP 64 bits; strings parciais e números fora do intervalo são recusados.
        if (!preg_match('/^[1-9][0-9]{0,18}$/D', $valor) || filter_var($valor, FILTER_VALIDATE_INT) === false) {
            throw new DomainException('O AD não retornou uma validade de conta válida.');
        }
        $numero = (int) $valor;
        $segundos = intdiv($numero, 10000000) - 11644473600;
        $micros = intdiv($numero % 10000000, 10);
        $data = DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%06d', $segundos, $micros));
        // Falha de conversão não deve liberar renovação usando a data atual por alternativa.
        if ($data === false) {
            throw new DomainException('A validade da conta não pôde ser convertida.');
        }
        return $data->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    }

    /** Propõe a data com limite inclusivo de noventa dias e calendário equivalente ao .NET. */
    public static function propor(string $filetime, string $vinculo, DateTimeImmutable $agora): array {
        $vinculo = self::normalizarVinculo($vinculo);
        $anterior = self::deFiletime($filetime);
        // Uma conta que nunca expira não precisa receber uma nova expiração.
        if ($anterior === null) {
            return ['estado' => 'SEM_EXPIRACAO', 'detalhe' => 'Conta sem expiração. Nenhuma renovação necessária.'];
        }
        // Preserva a janela inclusiva, sem arredondar o restante para dias inteiros.
        if ($anterior > $agora->modify('+90 days')) {
            return ['estado' => 'FORA_DO_PRAZO', 'detalhe' => 'A conta vence em mais de 90 dias.'];
        }
        $base = $anterior > $agora ? $anterior : $agora;
        $meses = $vinculo === 'CONTRATO' ? 12 : 6;
        $primeiro = $base->modify('first day of this month')->modify('+' . $meses . ' months');
        $dia = min((int) $base->format('d'), (int) $primeiro->format('t'));
        $nova = $base->setDate((int) $primeiro->format('Y'), (int) $primeiro->format('m'), $dia);
        return [
            'estado' => 'PRONTO', 'anterior' => $filetime, 'nova' => self::paraFiletime($nova),
            'data' => $nova->format('Y-m-d H:i:s'), 'vinculo' => $vinculo,
            'detalhe' => 'Validade proposta. Revise a conta antes de confirmar.',
        ];
    }
}
