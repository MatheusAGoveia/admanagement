<?php

// Exercita posse exclusiva em processos reais sem chamar GLPI ou LDAP.

require dirname(__DIR__) . '/src/RepositorioDeOperacoes.php';

use GlpiPlugin\Admanagement\RepositorioDeOperacoes;

// Executa o papel de processo concorrente quando a suíte recebe o comando de reserva isolada.

if (($argv[1] ?? '') === '--reserva') {

    try {

        (new RepositorioDeOperacoes($argv[2]))->reservarNecessidade(

            'pessoa-de-contrato',

            'mesma-necessidade-de-contrato',

            'criacao',

            (int) $argv[3],

            9,

            'r1',

            2,

            20

        );

        echo 'RESERVADA';

    } catch (DomainException $erro) {

        // Representa a recusa esperada de uma reserva que perdeu a disputa pela mesma necessidade.

        echo 'BLOQUEADA';

    }

    exit;

}

$diretorio = sys_get_temp_dir() . '/admanagement-concorrencia-' . bin2hex(random_bytes(8));

mkdir($diretorio, 0700);

$processos = [];

try {

    // Inicia quatro processos reais para disputar simultaneamente a mesma necessidade de contrato.

    for ($i = 0; $i < 4; $i++) {

        $canais = [];

        $processo = proc_open(

            [

                PHP_BINARY,

                __FILE__,

                '--reserva',

                $diretorio,

                (string) ($i + 1)

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

        // Interrompe o cenário se um dos processos concorrentes não puder ser iniciado.

        if (!is_resource($processo)) {

            throw new RuntimeException('Falha ao iniciar concorrente.');

        }

        $processos[] = [

            $processo,

            $canais

        ];

    }

    $vencedoras = 0;

    $bloqueadas = 0;

    // Coleta saída e resultado de cada concorrente antes de conferir a unicidade da reserva.

    foreach ($processos as [$processo, $canais]) {

        $saida = stream_get_contents($canais[1]);

        $erro = stream_get_contents($canais[2]);

        fclose($canais[1]);

        fclose($canais[2]);

        // Trata erro de execução ou saída de erro como falha do teste de concorrência.

        if (proc_close($processo) !== 0 || $erro !== '') {

            throw new RuntimeException('Concorrente falhou: ' . $erro);

        }

        $vencedoras += $saida === 'RESERVADA' ? 1 : 0;

        $bloqueadas += $saida === 'BLOQUEADA' ? 1 : 0;

    }

    $operacoes = (new RepositorioDeOperacoes($diretorio))->consultar()['operacoes'];

    // Exige uma reserva vencedora, três recusas e uma única operação durável íntegra.

    if ($vencedoras !== 1 || $bloqueadas !== 3 || count($operacoes) !== 1) {

        throw new RuntimeException('A necessidade foi duplicada.');

    }

    echo "4 processos concorrentes: uma reserva, três bloqueios, documento íntegro.\n";

} finally {

    // Remove somente o diretório aleatório criado para o teste de concorrência.

    // O alvo é exatamente a pasta aleatória criada por esta execução.

    foreach (new FilesystemIterator($diretorio, FilesystemIterator::SKIP_DOTS) as $arquivo) {

        unlink($arquivo->getPathname());

    }

    rmdir($diretorio);

}

