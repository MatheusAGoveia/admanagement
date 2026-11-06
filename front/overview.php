<?php

// Acompanhamento coletivo; não utiliza a restrição da fila pessoal.

use GlpiPlugin\Admanagement\PaginaDaEquipe;

// O kernel do GLPI já inicializou o contexto antes de incluir esta entrada.

PaginaDaEquipe::mostrar(false);

