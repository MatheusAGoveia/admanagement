<?php

// Acompanhamento coletivo; não utiliza a restrição da fila pessoal.

use GlpiPlugin\Admanagement\PaginaDaEquipe;

require_once __DIR__ . '/../../../inc/includes.php';

PaginaDaEquipe::mostrar(false);

