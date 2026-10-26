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

            '11.0.6',

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

            '11.0.6',

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

            '11.0.6',

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

            '11.0.6',

            ['antiga'],

            0,

            null

        ],

        [

            'duas inclusões do mesmo arquivo',

            true,

            '11.0.6',

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

            '11.0.6',

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

            '11.0.6',

            ['correta'],

            0,

            null

        ],

        [

            'release mínima',

            true,

            '11.0.4',

            ['correta'],

            6,

            true

        ],

        [

            'versão incompatível mantém metadados',

            true,

            '11.0.7',

            ['correta'],

            6,

            false

        ],

    ];

    // Executa cada combinação de nomes, ordem de inclusão e versão GLPI em processo independente.

    foreach (

        $cenarios as [$nome, $com_contexto, $versao, $chaves, $total_funcoes, $requisitos]

    ) {

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

        $processo = proc_open(

            [

                PHP_BINARY,

                __FILE__,

                '--cenario',

                $arquivo_entrada

            ],

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

        }

    }

    echo '9 cenários de carregamento concluídos.' . PHP_EOL;

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

