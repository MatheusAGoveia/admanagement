<?php

use GlpiPlugin\Admanagement\LeitorDePlanilhas;

// O laboratório fornece apenas o autoload de bibliotecas, sem iniciar o kernel ou conectar ao banco.
$autoload = getenv('ADMANAGEMENT_AUTOLOAD_GLPI');
// O caminho é configuração do executor de testes, não um argumento recebido por HTTP.
if (is_string($autoload) && $autoload !== '' && is_file($autoload)) {
    require $autoload;
}
/** Resolve as classes próprias desta cópia quando a suíte roda fora do diretório instalado. */
spl_autoload_register(
    /** Não intercepta bibliotecas de terceiros nem classes do núcleo GLPI. */
    static function (string $classe): void {
        $prefixo = 'GlpiPlugin\\Admanagement\\';
        // Mantém o autoload limitado aos arquivos sob teste.
        if (str_starts_with($classe, $prefixo)) {
            require dirname(__DIR__) . '/src/' . substr($classe, strlen($prefixo)) . '.php';
        }
    }
);

$arquivo = tempnam(sys_get_temp_dir(), 'adm_planilha_');
$verificacoes = 0;
/** Confere uma condição observável sem depender de assert configurável no ambiente PHP. */
function conferirPlanilha(bool $condicao, string $mensagem): void {
    global $verificacoes;
    // Arquivos recusados e aceitos precisam produzir exatamente o comportamento esperado.
    if (!$condicao) { throw new RuntimeException($mensagem); }
    $verificacoes++;
}

/** Confere que dados ativos ou truncados são recusados sem avaliar seu conteúdo. */
function recusarPlanilha(callable $operacao, string $mensagem): void {
    $recusada = false;
    // O teste só considera erro de negócio esperado como recusa válida.
    try { $operacao(); }
    catch (DomainException) {
        // Exceções de infraestrutura não são transformadas em aprovação do leitor.
        $recusada = true;
    }
    conferirPlanilha($recusada, $mensagem);
}

// O arquivo isolado é removido mesmo se a verificação encontrar uma regressão.
try {
    file_put_contents($arquivo, "\xEF\xBB\xBFCPF;Nome;Objetivo;Vínculo\n000.000.001-91;Ana Luiza Campos;renovar;contratado\n");
    $linhas = LeitorDePlanilhas::ler($arquivo, 'exemplo.csv');
    conferirPlanilha(count($linhas) === 1 && $linhas[0]['cpf'] === '00000000191' && $linhas[0]['erros'] === [], 'CSV real preserva zeros e aceita BOM/cabeçalho acentuado.');
    file_put_contents($arquivo, "cpf,nome,objetivo\n11144477735,Bruno Oliveira,bloquear\n");
    conferirPlanilha(LeitorDePlanilhas::ler($arquivo, 'exemplo.csv')[0]['tipo'] === 'bloqueio', 'CSV com vírgulas não exige vínculo no bloqueio.');
    file_put_contents($arquivo, "cpf,nome,objetivo\n11144477735,=1+1,bloquear\n");
    conferirPlanilha(LeitorDePlanilhas::ler($arquivo, 'formula.csv')[0]['erros'] !== [], 'CSV com fórmula impede a linha antes da consulta.');
    $csv = \GlpiPlugin\Admanagement\ProcessadorDeLotes::exportar(['linhas' => [['linha' => 2, 'nome' => '  =1+1', 'cpf' => '00000000191', 'tipo' => 'renovacao', 'vinculo' => 'CONTRATO', 'erros' => []]]]);
    conferirPlanilha(!str_contains($csv, '00000000191') && str_contains($csv, "'  =1+1"), 'Exportação mascara CPF e neutraliza fórmula mesmo depois de espaços.');
    recusarPlanilha(
        /** O conteúdo CSV não deve ser executado por um leitor de formato com macros. */
        static function () use ($arquivo): void { LeitorDePlanilhas::ler($arquivo, 'arquivo.xlsm'); },
        'XLSM recusado.'
    );
    file_put_contents($arquivo, str_repeat('a', 2097153));
    recusarPlanilha(
        /** A verificação de tamanho ocorre antes do parser. */
        static function () use ($arquivo): void { LeitorDePlanilhas::ler($arquivo, 'excesso.csv'); },
        'Arquivo acima de 2 MiB recusado.'
    );
    // A mesma biblioteca fornecida pelo GLPI gera documentos válidos para exercitar o leitor de verdade.
    if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        $planilha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $aba = $planilha->getActiveSheet();
        $aba->fromArray([['CPF', 'Nome', 'Objetivo', 'Vínculo'], ['00000000191', 'Ana Luiza Campos', 'renovar', 'ESTAGIÁRIO']]);
        $aba->getCell('A2')->setValueExplicit('00000000191', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha))->save($arquivo);
        $linhas = LeitorDePlanilhas::ler($arquivo, 'exemplo.xlsx');
        conferirPlanilha($linhas[0]['cpf'] === '00000000191' && $linhas[0]['vinculo'] === 'ESTAGIARIO', 'XLSX real usa biblioteca do GLPI e preserva CPF textual.');
        $aba->getCell('B2')->setValue('=1+1');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha))->save($arquivo);
        recusarPlanilha(
            /** Fórmula deve ser detectada antes de montar dados executáveis do lote. */
            static function () use ($arquivo): void { LeitorDePlanilhas::ler($arquivo, 'formula.xlsx'); },
            'XLSX com fórmula recusado.'
        );
        $aba->getCell('B2')->setValue('Ana Luiza Campos');
        $planilha->createSheet();
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha))->save($arquivo);
        recusarPlanilha(
            /** Outra aba não será ignorada silenciosamente na importação. */
            static function () use ($arquivo): void { LeitorDePlanilhas::ler($arquivo, 'duas-abas.xlsx'); },
            'XLSX com mais de uma aba recusado.'
        );
        $planilha->disconnectWorksheets();
    } else {
        // O executor informa cobertura ausente em vez de alegar que XLSX foi testado.
        echo "Cobertura XLSX não executada: defina ADMANAGEMENT_AUTOLOAD_GLPI com o autoload de bibliotecas do GLPI.\n";
    }
} finally {
    // Apaga só o arquivo temporário criado por esta suíte, nunca uma pasta ou planilha do usuário.
    unlink($arquivo);
}
echo $verificacoes . " verificações de arquivos CSV/XLSX concluídas.\n";
