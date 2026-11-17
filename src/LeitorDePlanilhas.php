<?php

namespace GlpiPlugin\Admanagement;

use DomainException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use ZipArchive;

/** Importa dados de trabalho sem avaliar fórmulas ou disparar operações no diretório. */
final class LeitorDePlanilhas {

    public const MAXIMO_BYTES = 2097152;
    public const MAXIMO_LINHAS = 500;

    /** Valida o arquivo temporário recebido e escolhe somente leitores explicitamente admitidos. */
    public static function ler(string $caminho, string $nome): array {
        // O controlador fornece o temporário do upload; nomes enviados não viram caminhos locais.
        if (!is_file($caminho) || filesize($caminho) > self::MAXIMO_BYTES || filesize($caminho) === 0) {
            throw new DomainException('Envie uma planilha não vazia, com até 2 MiB.');
        }
        $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $linhas = match ($extensao) {
            'csv' => self::lerCsv($caminho),
            'xlsx' => self::lerXlsx($caminho),
            // Formatos com macros e arquivos antigos não fazem parte da entrada aprovada.
            default => throw new DomainException('Use uma planilha .xlsx ou um arquivo .csv UTF-8.'),
        };
        return self::validarLinhas($linhas);
    }

    /** Lê CSV UTF-8, reconhecendo vírgula ou ponto e vírgula sem remover zeros de CPF. */
    private static function lerCsv(string $caminho): array {
        $texto = file_get_contents($caminho);
        // Conversão implícita de codificação poderia modificar nomes e cabeçalhos silenciosamente.
        if (!is_string($texto) || !mb_check_encoding($texto, 'UTF-8') || str_contains($texto, "\0")) {
            throw new DomainException('O CSV precisa estar em UTF-8, sem dados binários.');
        }
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
        $primeira = strtok($texto, "\r\n");
        $separador = count(str_getcsv((string) $primeira, ';', '"', '')) > count(str_getcsv((string) $primeira, ',', '"', '')) ? ';' : ',';
        $arquivo = fopen('php://temp', 'w+');
        fwrite($arquivo, $texto);
        rewind($arquivo);
        $linhas = [];
        // O temporário em memória é fechado mesmo se um limite for excedido.
        try {
            // O limite protege a sessão e impede um lote maior que o apresentado na interface.
            while (($linha = fgetcsv($arquivo, 16384, $separador, '"', '')) !== false) {
                $linhas[] = $linha;
                // Também limita linhas vazias, para que não burlem o teto de processamento.
                if (count($linhas) > self::MAXIMO_LINHAS + 1) {
                    throw new DomainException('O lote admite no máximo 500 linhas.');
                }
            }
        } finally {
            // Não preserva uma segunda cópia do documento enviado.
            fclose($arquivo);
        }
        return $linhas;
    }

    /** Inspeciona o contêiner antes de reutilizar o leitor XLSX fornecido pelo GLPI. */
    private static function lerXlsx(string $caminho): array {
        // A biblioteca é a já fornecida pelo GLPI; falta de dependência é informada sem instalar nada.
        if (!class_exists(Xlsx::class) || !class_exists(ZipArchive::class)) {
            throw new DomainException('A leitura XLSX exige PhpSpreadsheet e ZIP disponíveis no servidor GLPI. Use CSV enquanto isso.');
        }
        $zip = new ZipArchive();
        // A extensão sozinha não comprova que o arquivo seja uma planilha.
        if ($zip->open($caminho) !== true) {
            throw new DomainException('O arquivo não é uma planilha XLSX válida.');
        }
        // As inspeções não extraem arquivos no servidor nem seguem referências externas.
        try {
            $total = 0;
            // Limita entradas e tamanho descompactado antes de o leitor montar objetos em memória.
            if ($zip->numFiles > 1000) {
                throw new DomainException('A planilha contém componentes em excesso.');
            }
            // Todos os componentes são examinados, não só a primeira planilha visível.
            for ($indice = 0; $indice < $zip->numFiles; $indice++) {
                $item = $zip->statIndex($indice);
                // Entrada corrompida deve ser recusada sem usar tamanho ou nome não confirmados.
                if (!is_array($item)) {
                    throw new DomainException('O contêiner da planilha está corrompido.');
                }
                $total += $item['size'];
                // Macros, vínculos externos e volumes excessivos não são aceitos.
                if ($total > 20 * 1024 * 1024 || preg_match('~vbaProject|externalLinks|\.bin$~i', $item['name'])) {
                    throw new DomainException('Planilha com macros, vínculos externos ou conteúdo excessivo não é aceita.');
                }
                // XML é inspecionado sem expandir entidades ou avaliar fórmulas.
                if (str_ends_with($item['name'], '.xml') || str_ends_with($item['name'], '.rels')) {
                    $xml = $zip->getFromIndex($indice);
                    // Dados ativos não são removidos silenciosamente; o arquivo inteiro é recusado.
                    if (!is_string($xml) || preg_match('/<!DOCTYPE|<!ENTITY|<\s*(?:\w+:)?f(?:\s|>)|TargetMode\s*=\s*["\']External["\']/i', $xml)) {
                        throw new DomainException('Remova fórmulas e referências externas antes de enviar a planilha.');
                    }
                }
            }
            // Arquivos ZIP genéricos não devem chegar ao leitor XLSX.
            if ($zip->locateName('xl/workbook.xml') === false) {
                throw new DomainException('O arquivo não contém uma pasta de trabalho XLSX.');
            }
        } finally {
            // Libera o contêiner, inclusive quando a inspeção recusa o documento.
            $zip->close();
        }
        $leitor = new Xlsx();
        $informacoes = $leitor->listWorksheetInfo($caminho);
        // Mais de uma aba pode esconder linhas que o técnico acredita estar executando.
        if (count($informacoes) !== 1 || $informacoes[0]['totalRows'] > 501 || $informacoes[0]['totalColumns'] > 20) {
            throw new DomainException('Use uma única aba com até 500 linhas de dados e 20 colunas.');
        }
        $planilha = $leitor->load($caminho);
        $linhas = [];
        // A leitura usa valores crus; getCalculatedValue e formatação com fórmulas não são chamados.
        try {
            $aba = $planilha->getSheet(0);
            // O tamanho já foi validado antes de carregar; cada célula ainda passa pela checagem de tipo.
            for ($linha = 1; $linha <= $aba->getHighestDataRow(); $linha++) {
                $valores = [];
                // Colunas adicionais não serão tratadas como instruções de execução.
                for ($coluna = 1; $coluna <= $informacoes[0]['totalColumns']; $coluna++) {
                    $celula = $aba->getCell([$coluna, $linha]);
                    // Defesa adicional para fórmulas não identificadas na inspeção XML.
                    if ($celula->getDataType() === DataType::TYPE_FORMULA || $celula->getHyperlink()->getUrl() !== '') {
                        throw new DomainException('A planilha deve conter somente valores, sem fórmulas ou links.');
                    }
                    $valor = $celula->getValue();
                    // Objetos de conteúdo rico não são convertidos implicitamente em dados pessoais.
                    if (!is_scalar($valor) && $valor !== null) {
                        throw new DomainException('Use texto simples nas células da planilha.');
                    }
                    $valores[] = (string) ($valor ?? '');
                }
                $linhas[] = $valores;
            }
        } finally {
            // Libera células e estilos sem salvar o documento original.
            $planilha->disconnectWorksheets();
        }
        return $linhas;
    }

    /** Normaliza cabeçalhos, valida cada linha e impede documentos repetidos no mesmo lote. */
    public static function validarLinhas(array $linhas): array {
        // Arquivo sem dados não deve gerar uma confirmação de lote vazio.
        if (count($linhas) < 2 || count($linhas) > self::MAXIMO_LINHAS + 1) {
            throw new DomainException('Envie cabeçalho e entre 1 e 500 linhas de dados.');
        }
        $cabecalho = array_shift($linhas);
        $indices = [];
        // Cabeçalhos são equivalentes apenas depois da normalização explícita.
        foreach ($cabecalho as $indice => $titulo) {
            $chave = preg_replace('/[^A-Z]/', '', IdentidadeDoDiretorio::normalizarNome((string) $titulo));
            $chave = match ($chave) {
                'NOMECOMPLETO' => 'NOME', 'VINCULOEMPREGATICIO' => 'VINCULO',
                // Outros nomes não recebem interpretação de comando.
                default => $chave,
            };
            // Um cabeçalho repetido pode ocultar valores conflitantes.
            if (isset($indices[$chave])) {
                throw new DomainException('Há colunas repetidas na planilha.');
            }
            $indices[$chave] = $indice;
        }
        // A validação das colunas não depende de haver algum CPF válido nas linhas.
        foreach (['CPF', 'NOME', 'OBJETIVO'] as $obrigatorio) {
            // Os nomes obrigatórios são informados antes de qualquer consulta externa.
            if (!array_key_exists($obrigatorio, $indices)) {
                throw new DomainException('Falta a coluna obrigatória: ' . $obrigatorio . '.');
            }
        }
        $resultado = [];
        $cpfs = [];
        // Linhas inválidas permanecem visíveis com seus motivos, sem serem executáveis.
        foreach ($linhas as $numero => $valores) {
            // Ignora somente uma linha inteiramente vazia.
            if (implode('', $valores) === '') {
                continue;
            }
            $linha = ['linha' => $numero + 2, 'erros' => [], 'cpf' => '', 'nome' => '', 'tipo' => '', 'vinculo' => ''];
            // Um erro de célula impede essa linha sem apagar os demais registros de revisão.
            try {
                // CSV também aceita somente valores; marcadores de fórmula não são executados nem liberados.
                foreach ($valores as $valor) {
                    // Um conteúdo ativo invalida a linha mesmo se estiver em coluna adicional.
                    if (is_string($valor) && preg_match('/^\s*[=+@-]/u', $valor)) {
                        throw new DomainException('A linha contém fórmula. Substitua por valores simples.');
                    }
                }
                $linha['cpf'] = IdentidadeDoDiretorio::normalizarCpf((string) ($valores[$indices['CPF']] ?? ''));
                $linha['nome'] = EntradaDaFerramenta::lerTexto((string) ($valores[$indices['NOME']] ?? ''), 240);
                // Nome vazio não constitui uma pessoa para pesquisar.
                if ($linha['nome'] === '') {
                    throw new DomainException('Informe o nome completo.');
                }
                $objetivo = IdentidadeDoDiretorio::normalizarNome((string) ($valores[$indices['OBJETIVO']] ?? ''));
                $linha['tipo'] = match ($objetivo) {
                    'RENOVAR' => 'renovacao', 'BLOQUEAR' => 'bloqueio',
                    // Não aceitar sinônimos como excluir evita transformar texto livre em ação destrutiva.
                    default => throw new DomainException('Objetivo deve ser renovar ou bloquear.'),
                };
                // Bloqueio não exige vínculo; renovação precisa definir seu prazo sem suposição.
                if ($linha['tipo'] === 'renovacao') {
                    $linha['vinculo'] = PoliticaDeRenovacao::normalizarVinculo((string) ($valores[$indices['VINCULO'] ?? -1] ?? ''));
                }
            } catch (DomainException | \InvalidArgumentException $erro) {
                // Mostra a regra violada, sem incluir valores pessoais na mensagem de erro.
                $linha['erros'][] = $erro->getMessage();
            }
            $posicao = count($resultado);
            // O CPF normalizado identifica repetições dentro do lote, inclusive objetivos conflitantes.
            if ($linha['cpf'] !== '') {
                $cpfs[$linha['cpf']][] = $posicao;
            }
            $resultado[] = $linha;
        }
        // Bloqueia todas as ocorrências repetidas, não apenas a segunda.
        foreach ($cpfs as $posicoes) {
            // Duplicata de planilha não deve alterar duas vezes a mesma conta.
            if (count($posicoes) > 1) {
                // Nenhuma das linhas conflitantes será escolhida automaticamente.
                foreach ($posicoes as $posicao) {
                    $resultado[$posicao]['erros'][] = 'CPF repetido no lote. Corrija as duplicidades antes de processar.';
                }
            }
        }
        // Um arquivo apenas com cabeçalho e linhas vazias não forma um lote.
        if ($resultado === []) {
            throw new DomainException('A planilha não contém linhas de dados.');
        }
        return $resultado;
    }
}
