<?php

namespace GlpiPlugin\Admanagement;

use DomainException;
use Normalizer;

/** Confere identidade e homônimos; nunca seleciona uma conta apenas pela ordem do LDAP. */
final class IdentidadeDoDiretorio {

    /** Normaliza nomes completos, preservando letras e ignorando só caixa, acentos e espaços. */
    public static function normalizarNome(string $nome): string {
        $decomposto = Normalizer::normalize($nome, Normalizer::FORM_D);
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', preg_replace('/\p{Mn}/u', '', $decomposto ?: $nome))), 'UTF-8');
    }

    /** Normaliza e confere os dígitos verificadores sem completar zeros perdidos. */
    public static function normalizarCpf(string $valor): string {
        // Somente pontuação habitual e espaços são descartados; letras não viram CPF válido.
        if (!preg_match('/^[0-9.\s\/-]+$/uD', $valor)) {
            throw new DomainException('CPF inválido. Informe os 11 dígitos, preservando zeros iniciais.');
        }
        $cpf = preg_replace('/[^0-9]/', '', $valor);
        // Sequências repetidas e números truncados não identificam uma pessoa com segurança.
        if (strlen($cpf) !== 11 || preg_match('/^([0-9])\1{10}$/D', $cpf)) {
            throw new DomainException('CPF inválido. Confira o documento de origem.');
        }
        // Os dois dígitos são calculados independentemente da apresentação recebida.
        for ($tamanho = 9; $tamanho <= 10; $tamanho++) {
            $soma = 0;
            // Cada posição usa seu peso regulamentar, incluindo o primeiro verificador no segundo cálculo.
            for ($indice = 0; $indice < $tamanho; $indice++) {
                $soma += (int) $cpf[$indice] * ($tamanho + 1 - $indice);
            }
            $digito = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);
            // Um dígito incorreto impede usar a alternativa de nome único.
            if ($digito !== (int) $cpf[$tamanho]) {
                throw new DomainException('CPF inválido. Confira os dígitos verificadores.');
            }
        }
        return $cpf;
    }

    /** Lê CPF e Escritório sem juntar os números da matrícula ao documento. */
    public static function evidenciasCpf(array $conta): array {
        $cpfs = [];
        $tem_cpf = false;
        // Os campos são fontes separadas; CPF preenchido nunca é substituído por inferência.
        foreach (['cpf', 'escritorio'] as $campo) {
            $texto = (string) ($conta[$campo] ?? '');
            $rotulado = preg_match('/(?<![\pL\pN])cpf(?!\pL)/iu', $texto) === 1;
            $padrao = $rotulado
                ? '/(?<![\pL\pN])cpf(?!\pL)[\s.:=\-]*([0-9][0-9.\s\/\-\x{2010}-\x{2015}]*)/iu'
                : '/([0-9](?:[0-9.\s\/\-\x{2010}-\x{2015}]*[0-9])?)/u';
            preg_match_all($padrao, $texto, $partes);
            // Números curtos de Escritório sem rótulo podem ser matrícula, não CPF.
            foreach ($partes[1] as $parte) {
                $digitos = preg_replace('/[^0-9]/', '', $parte);
                $tem_cpf = $tem_cpf || strlen($digitos) >= 10 || ($digitos !== '' && ($campo === 'cpf' || $rotulado));
                // Dados malformados contam como CPF informado, mas não como identidade confirmada.
                try {
                    $cpfs[] = self::normalizarCpf($digitos);
                } catch (DomainException) {
                    // A avaliação decidirá o impedimento sem converter ausência em correspondência.
                }
            }
            // Texto não numérico no atributo CPF também não é um campo vazio utilizável por alternativa.
            if ($campo === 'cpf' && trim($texto) !== '') {
                $tem_cpf = true;
            }
        }
        return ['cpfs' => array_values(array_unique($cpfs)), 'tem_cpf' => $tem_cpf];
    }

    /** Bloqueia homônimos antes de comparar CPF ou login, inclusive entre domínios. */
    public static function avaliar(array $pessoa, array $contas): array {
        $nome = self::normalizarNome((string) ($pessoa['nome'] ?? ''));
        $cpf = self::normalizarCpf((string) ($pessoa['cpf'] ?? ''));
        $encontradas = [];
        // A contagem exige nome completo, sem substring e sem escolher a primeira resposta.
        foreach ($contas as $conta) {
            // CN e nome exibido podem diferir; cada GUID continua contando uma única vez.
            if ($nome !== '' && in_array($nome, [self::normalizarNome($conta['nome'] ?? ''), self::normalizarNome($conta['nome_exibicao'] ?? '')], true)) {
                $encontradas[$conta['dominio'] . ':' . $conta['guid']] = $conta;
            }
        }
        // A regra posterior do usuário proíbe resolver homônimo escolhendo CPF, login ou domínio.
        if (count($encontradas) > 1) {
            return ['estado' => 'HOMONIMO', 'detalhe' => 'Mais de uma conta com o mesmo nome nos dois domínios. Transação bloqueada.'];
        }
        // Uma busca completa sem correspondência não autoriza fabricar uma identidade de renovação.
        if ($encontradas === []) {
            return ['estado' => 'NAO_ENCONTRADA', 'detalhe' => 'Nome completo não encontrado no escopo consultado.'];
        }
        $conta = array_values($encontradas)[0];
        $evidencia = self::evidenciasCpf($conta);
        // CPFs válidos distintos nos campos da mesma conta são conflito, não uma lista de alternativas.
        if (count($evidencia['cpfs']) > 1) {
            return ['estado' => 'CPF_CONFLITANTE', 'detalhe' => 'CPF e Escritório apresentam documentos diferentes.'];
        }
        $confere = $evidencia['cpfs'] === [$cpf];
        $nome_unico = !$evidencia['tem_cpf'] && ($pessoa['tipo'] ?? '') === 'renovacao';
        // Bloqueio exige documento correspondente; nome único sem CPF é exceção apenas da renovação.
        if (!$confere && !$nome_unico) {
            return ['estado' => 'CPF_NAO_CONFERE', 'detalhe' => 'CPF ausente, inválido ou divergente. Execução impedida.'];
        }
        // O login da solicitação, quando informado, também precisa corresponder à conta identificada.
        if (($pessoa['login'] ?? '') !== '' && strcasecmp($pessoa['login'], $conta['login']) !== 0) {
            return ['estado' => 'LOGIN_DIVERGENTE', 'detalhe' => 'O login informado não pertence à conta identificada.'];
        }
        return ['estado' => 'IDENTIFICADA', 'metodo' => $confere ? 'NOME_CPF' : 'NOME_UNICO', 'conta' => $conta];
    }
}
