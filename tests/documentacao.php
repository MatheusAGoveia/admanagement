<?php

/**
 * Confere a documentação estrutural do código sem executar as classes, o GLPI ou consultas externas.
 * O tokenizer distingue código de strings, HTML e exemplos presentes nos próprios testes.
 */

/**
 * Localiza o token anterior relevante, ignorando espaços e modificadores de uma declaração.
 */

function localizarAnteriorDocumentado(

    array $tokens,

    int $indice

): ?array {

    $modificadores = [

        T_PUBLIC,

        T_PRIVATE,

        T_PROTECTED,

        T_STATIC,

        T_FINAL,

        T_ABSTRACT,

        T_NEW

    ];

    // Recua sobre espaços e modificadores até chegar ao comentário ou à instrução anterior.

    for ($anterior = $indice - 1; $anterior >= 0; $anterior--) {

        $token = $tokens[$anterior];

        // Pontuação anterior delimita a expressão e não representa documentação da declaração.

        if (!is_array($token)) {

            return null;

        }

        // Modificadores pertencem à mesma declaração e podem ficar entre o comentário e seu nome.

        if ($token[0] === T_WHITESPACE || in_array($token[0], $modificadores, true)) {

            continue;

        }

        return $token;

    }

    return null;

}

/**
 * Aceita explicação no início do ramo alternativo, logo após sua chave de abertura.
 */

function localizarExplicacaoDoRamo(

    array $tokens,

    int $indice

): bool {

    $parenteses = 0;

    // Percorre a condição ou captura até localizar a abertura do bloco pertencente ao ramo.

    for ($atual = $indice + 1; $atual < count($tokens); $atual++) {

        $token = $tokens[$atual];

        // Conta parênteses para não confundir a condição com o corpo do ramo.

        if ($token === '(') {

            $parenteses++;

        } elseif ($token === ')') {

            // Encerra um nível da condição ou da captura de exceção.

            $parenteses--;

        } elseif ($token === '{' && $parenteses === 0) {

            // Examina somente o primeiro conteúdo do corpo, sem aceitar um comentário distante.

            for ($corpo = $atual + 1; $corpo < count($tokens); $corpo++) {

                $primeiro = $tokens[$corpo];

                // O espaçamento entre a chave e a explicação não altera a cobertura.

                if (is_array($primeiro) && $primeiro[0] === T_WHITESPACE) {

                    continue;

                }

                return is_array($primeiro) && in_array($primeiro[0], [

                    T_COMMENT,

                    T_DOC_COMMENT

                ], true);

            }

            return false;

        }

    }

    return false;

}

$raiz = dirname(__DIR__);

$arquivos = new RecursiveIteratorIterator(

    new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)

);

$tipos = [

    T_CLASS => 'classe',

    T_FUNCTION => 'função',

    T_FN => 'função de expressão',

    T_IF => 'if',

    T_ELSEIF => 'elseif',

    T_ELSE => 'else',

    T_FOREACH => 'foreach',

    T_FOR => 'for',

    T_WHILE => 'while',

    T_DO => 'do',

    T_CATCH => 'catch',

    T_FINALLY => 'finally',

    T_SWITCH => 'switch',

    T_CASE => 'case',

    T_DEFAULT => 'default',

];

$ramos_alternativos = [

    T_ELSEIF,

    T_ELSE,

    T_CATCH,

    T_FINALLY

];

$faltantes = [];

$total_arquivos = 0;

$total_blocos = 0;

// Examina todos os arquivos PHP da distribuição, inclusive as entradas e esta própria suíte.

foreach ($arquivos as $arquivo) {

    // Documentação Markdown, configuração JSON e outros artefatos não contêm declarações PHP analisáveis.

    if ($arquivo->getExtension() !== 'php') {

        continue;

    }

    $total_arquivos++;

    $tokens = token_get_all(file_get_contents($arquivo->getPathname()), TOKEN_PARSE);

    // Verifica cada declaração ou controle identificado pelo parser nativo.

    foreach ($tokens as $indice => $token) {

        // Ignora pontuação e tokens que não representam os blocos exigidos pelo padrão.

        if (!is_array($token) || !isset($tipos[$token[0]])) {

            continue;

        }

        $anterior_imediato = $indice - 1;

        // Desconsidera espaços ao distinguir uma declaração de classe de uma referência Nome::class.

        while (

            $anterior_imediato >= 0

            && is_array($tokens[$anterior_imediato])

            && $tokens[$anterior_imediato][0] === T_WHITESPACE

        ) {

            $anterior_imediato--;

        }

        // A constante ::class apenas referencia um tipo e não declara uma classe nova.

        if (

            $token[0] === T_CLASS

            && $anterior_imediato >= 0

            && is_array($tokens[$anterior_imediato])

            && $tokens[$anterior_imediato][0] === T_DOUBLE_COLON

        ) {

            continue;

        }

        $total_blocos++;

        $anterior = localizarAnteriorDocumentado($tokens, $indice);

        $documentado = $anterior !== null && in_array($anterior[0], [

            T_COMMENT,

            T_DOC_COMMENT

        ], true);

        // Um else, elseif, catch ou finally pode explicar seu comportamento na primeira linha do corpo.

        if (!$documentado && in_array($token[0], $ramos_alternativos, true)) {

            $documentado = localizarExplicacaoDoRamo($tokens, $indice);

        }

        // Guarda a posição exata de cada lacuna para a manutenção corrigir antes de publicar.

        if (!$documentado) {

            $faltantes[] = $arquivo->getPathname() . ':' . $token[2] . ' — ' . $tipos[$token[0]];

        }

    }

}

// Falha a suíte quando qualquer classe, função ou controle exigido ficou sem explicação próxima.

if ($faltantes !== []) {

    fwrite(STDERR, "Blocos sem comentário:\n" . implode("\n", $faltantes) . "\n");

    exit(1);

}

echo $total_arquivos . ' arquivos PHP e ' . $total_blocos . " declarações/controles com comentários conferidos.\n";

