<?php

// Executa os cenários HTTP de revisão em processo separado da regressão de importação.

$argv[1] = '--regras-vigentes';

require __DIR__ . '/fluxo.php';

