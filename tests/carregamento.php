<?php

// Confere a descoberta das nossas funções em processos separados, sem executar GLPI ou banco.

/**
 * Compara o resultado do processo com o contrato necessário à descoberta do plugin.
 */

function conferirCarregamento(

    bool $condicao,

    string $cenario

): void {

    // Interrompe o teste com o nome da expectativa de carregamento que falhou.

    if (!$condicao) {

        throw new RuntimeException('Falhou: ' . $cenario);

    }

    echo 'OK: ' . $cenario . PHP_EOL;

}

// Cada processo filho começa sem funções do plugin já registradas por outro cenário.

if (($argv[1] ?? '') === '--cenario') {

    $entrada = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);

    // Reproduz o contexto GLPI somente nos cenários que devem disponibilizar os hooks do plugin.

    if ($entrada['com_contexto']) {

        define('GLPI_ROOT', $entrada['raiz_glpi']);

        define('GLPI_VERSION', $entrada['versao']);

    }

    // A descoberta inclui cada setup antes de procurar a função que corresponde à pasta.

    foreach ($entrada['pastas'] as $pasta) {

        require $pasta . '/setup.php';

        require $pasta . '/hook.php';

    }

    $funcoes = [

        'plugin_init_admanagement',

        'plugin_version_admanagement',

        'plugin_admanagement_check_prerequisites',

        'plugin_admanagement_check_config',

        'plugin_admanagement_install',

        'plugin_admanagement_uninstall',

    ];

    $registradas = [];

    // Conta as funções globais conhecidas para detectar duplicação ou carregamento indevido.

    foreach ($funcoes as $funcao) {

        // Acumula somente funções que realmente foram declaradas no cenário isolado.

        if (function_exists($funcao)) {

            $registradas[] = $funcao;

        }

    }

    $metadados = null;

    $requisitos = null;

    // Consulta metadados e requisitos apenas quando a função de identificação do plugin foi carregada.

    if (function_exists('plugin_version_admanagement')) {

        $metadados = plugin_version_admanagement();

        $requisitos = plugin_admanagement_check_prerequisites();

    }

    // O marcador comprova que incluir um arquivo não encerrou a requisição do chamador.

    echo json_encode(

        [

            'continuou' => true,

            'funcoes' => $registradas,

            'metadados' => $metadados,

            'requisitos' => $requisitos,

        ],

        JSON_THROW_ON_ERROR

    );

    exit;

}

// Somente as cópias aleatórias no temporário serão criadas e removidas por este teste.

$raiz = dirname(__DIR__);

$temporario = sys_get_temp_dir() . '/glpi-carregamento-' . bin2hex(random_bytes(6));

mkdir($temporario, 0700, true);

$pastas = [

    'correta' => $temporario . '/plugins/admanagement',

    'antiga' => $temporario . '/plugins/PluguinGlpi',

    'backup' => $temporario . '/plugins/admanagementbackup',

    'segunda' => $temporario . '/marketplace/admanagement',

];

try {

    // Prepara as pastas que representam cópia válida, nome antigo e backup nos cenários.

    foreach ($pastas as $pasta) {

        mkdir($pasta, 0700, true);

        copy($raiz . '/setup.php', $pasta . '/setup.php');

        copy($raiz . '/hook.php', $pasta . '/hook.php');

    }

    $cenarios = [

        [

            'antiga e correta',

            true,

            '12.0.1',

            [

                'antiga',

                'correta'

            ],

            6,

            true

        ],

        [

            'correta e antiga',

            true,

            '12.0.1',

            [

                'correta',

                'antiga'

            ],

            6,

            true

        ],

        [

            'backup e correta',

            true,

            '12.0.1',

            [

                'backup',

                'correta'

            ],

            6,

            true

        ],

        [

            'somente pasta errada',

            true,

            '12.0.1',

            ['antiga'],

            0,

            null

        ],

        [

            'duas inclusões do mesmo arquivo',

            true,

            '12.0.1',

            [

                'correta',

                'correta'

            ],

            6,

            true

        ],

        [

            'duas cópias com chave correta',

            true,

            '12.0.1',

            [

                'correta',

                'segunda'

            ],

            6,

            true

        ],

        [

            'sem contexto GLPI',

            false,

            '12.0.1',

            ['correta'],

            0,

            null

        ],

        [

            'release mínima',

            true,

            '12.0.1',

            ['correta'],

            6,

            true

        ],

        [

            'versão incompatível mantém metadados',

            true,

            '12.0.2',

            ['correta'],

            6,

            false

        ],

    ];

    // Acrescenta os limites do alvo sem aceitar releases ou runtimes não homologados.

    $cenarios = array_merge($cenarios, [

        ['recusa GLPI 11 anterior', true, '11.0.6', ['correta'], 6, false],

        ['aceita GLPI 12.0.0 instalado no servidor', true, '12.0.0', ['correta'], 6, true],

        ['recusa GLPI 12.0.0 de desenvolvimento', true, '12.0.0-dev', ['correta'], 6, false],

        ['recusa GLPI 12.0.0 RC', true, '12.0.0-rc1', ['correta'], 6, false],

        ['recusa GLPI de desenvolvimento', true, '12.0.1-dev', ['correta'], 6, false],

        ['recusa GLPI 13 não homologado', true, '13.0.0', ['correta'], 6, false],

        ['recusa PHP abaixo de 8.3', true, '12.0.1', ['correta'], 6, false, '8.2.99'],

        ['aceita política mínima PHP 8.3', true, '12.0.1', ['correta'], 6, true, '8.3.0'],

        ['recusa PHP 9 não homologado', true, '12.0.1', ['correta'], 6, false, '9.0.0'],

        ['recusa ausência de mbstring', true, '12.0.1', ['correta'], 6, false, PHP_VERSION, true],

    ]);

    // Executa cada combinação de nomes, ordem de inclusão e versão GLPI em processo independente.

    foreach (

        $cenarios as $cenario

    ) {

        [$nome, $com_contexto, $versao, $chaves, $total_funcoes, $requisitos] = $cenario;

        // Substitui somente a constante na cópia temporária para conferir os limites da política.

        $versao_php = $cenario[6] ?? PHP_VERSION;

        $sem_mbstring = $cenario[7] ?? false;

        $codigo_setup = str_replace('PHP_VERSION', var_export($versao_php, true), file_get_contents($raiz . '/setup.php'));

        // Cada processo recebe uma cópia independente; o código do produto não é reescrito.

        foreach ($pastas as $pasta) {

            file_put_contents($pasta . '/setup.php', $codigo_setup);

        }

        $entrada = [

            'com_contexto' => $com_contexto,

            'raiz_glpi' => $temporario,

            'versao' => $versao,

            'pastas' => [],

        ];

        // Seleciona as cópias de setup e hook que devem participar daquele cenário de inclusão.

        foreach ($chaves as $chave) {

            $entrada['pastas'][] = $pastas[$chave];

        }

        $arquivo_entrada = $temporario . '/cenario.json';

        file_put_contents($arquivo_entrada, json_encode($entrada, JSON_THROW_ON_ERROR));

        $comando_php = [PHP_BINARY];

        // O parâmetro -n testa ausência real da extensão, sem carregar o INI do laboratório.

        if ($sem_mbstring) {

            $comando_php[] = '-n';

        }

        $comando_php = array_merge($comando_php, [__FILE__, '--cenario', $arquivo_entrada]);

        $processo = proc_open(

            $comando_php,

            [

                1 => [

                    'pipe',

                    'w'

                ],

                2 => [

                    'pipe',

                    'w'

                ]

            ],

            $canais

        );

        // Recusa continuar quando o processo isolado do cenário não pode ser iniciado.

        if (!is_resource($processo)) {

            throw new RuntimeException('Não foi possível iniciar o cenário: ' . $nome);

        }

        $saida = stream_get_contents($canais[1]);

        $erros = stream_get_contents($canais[2]);

        fclose($canais[1]);

        fclose($canais[2]);

        $codigo = proc_close($processo);

        conferirCarregamento($codigo === 0 && $erros === '', $nome . ': sem falha fatal');

        $resultado = json_decode($saida, true, 512, JSON_THROW_ON_ERROR);

        conferirCarregamento($resultado['continuou'] === true, $nome . ': preserva o chamador');

        conferirCarregamento(count($resultado['funcoes']) === $total_funcoes, $nome . ': funções esperadas');

        conferirCarregamento($resultado['requisitos'] === $requisitos, $nome . ': requisitos isolados');

        // Confere a identidade e os requisitos somente nos cenários que deveriam carregar o plugin.

        if ($total_funcoes > 0) {

            conferirCarregamento(

                $resultado['metadados']['name'] === 'ADManagement',

                $nome . ': identidade preservada'

            );

            $contrato = $resultado['metadados']['requirements'];

            $metadados_aceitam = version_compare($versao, $contrato['glpi']['min'], '>=')

                && version_compare($versao, $contrato['glpi']['max'], '<')

                && version_compare($versao_php, $contrato['php']['min'], '>=')

                && version_compare($versao_php, $contrato['php']['max'], '<')

                && !$sem_mbstring;

            // O intervalo nativo admite números entre releases; a política própria também recusa sufixos de desenvolvimento.

            $release_estavel = preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $versao) === 1;

            conferirCarregamento(($metadados_aceitam && $release_estavel) === $requisitos, $nome . ': faixa e política de release concordam');

            conferirCarregamento($contrato['php']['exts']['mbstring']['required'] === true, $nome . ': exige mbstring no GLPI');

        }

    }

    echo count($cenarios) . ' cenários de carregamento concluídos.' . PHP_EOL;

} finally {

    // Remove os arquivos e pastas temporários usados para os cenários de carregamento.

    // Os nomes são conhecidos e foram criados acima; nenhuma pasta do projeto é removida.

    foreach ($pastas as $pasta) {

        unlink($pasta . '/setup.php');

        unlink($pasta . '/hook.php');

        rmdir($pasta);

    }

    // Apaga o arquivo de entrada do processo somente quando ele foi criado pelo teste.

    if (is_file($temporario . '/cenario.json')) {

        unlink($temporario . '/cenario.json');

    }

    rmdir($temporario . '/plugins');

    rmdir($temporario . '/marketplace');

    rmdir($temporario);

}

