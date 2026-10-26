<?php

// Configuração legada: perfil, grupo e entidade são exigidos até ativar a política nova pela tela.

// A política nova permite concessões por usuário OU grupo OU perfil e define setores explicitamente.

// Super-Admin ativo com config/UPDATE dispensa listas próprias; direitos nativos permanecem obrigatórios.

// Entidade zero significa somente a raiz; nenhuma lista vazia representa acesso global.

return [

    // Somente true ativa o laboratório legado; a política nova substitui esta exceção local.

    'acesso_temporario_para_testes' => false,

    // Perfis da configuração anterior, preservados para a transição sem promoção administrativa.

    'perfis_permitidos' => [],

    // Entidades da configuração anterior; seleções novas ficam na política de visibilidade.

    'entidades_permitidas' => [],

];

