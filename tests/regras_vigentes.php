<?php

// Contratos das regras de 22/10, isolados: não há servidor LDAP, GLPI ou dados reais nos testes.

use GlpiPlugin\Admanagement\{

    AcompanhamentoDaEquipe,

    ConfiguracaoOperacional,

    ConfiguracaoDaFila,

    DestinoDaOperacao,

    NomeDistinto,

    PoliticaDeLogin,

    PoliticaDeResultados,

    RepositorioDeOperacoes,

    RepositorioDeRascunhos,

    RevisorDaOperacao,

    RevisaoDaOperacao,

    VerificadorDoDiretorio

};

define('READ', 1);

/**
 * Configuração de contrato em memória, exclusiva desta suíte.
 */

class Config {

    public static array $valores = [];

    /**
     * Lê somente os documentos explicitamente montados pelo cenário.
     */

    public static function getConfigurationValues(

        $contexto,

        $chaves

    ): array {

        return self::$valores;

    }

}

/**
 * Mantém a diferença entre leitura por recurso e filtro de consulta.
 */

class Ticket {

    public const SOLVED = 5, CLOSED = 6;

    /**
     * Fornece a tabela usada pela consulta compartilhada nas telas.
     */

    public static function getTable(): string {

        return 'glpi_tickets';

    }

    /**
     * Recusa um recurso apesar de a consulta tê-lo retornado.
     */

    public function can(

        $id,

        $direito

    ): bool {

        return $id !== 2;

    }

}

/**
 * Papel de atribuição documentado pelo contrato GLPI.
 */

class CommonITILActor {

    public const ASSIGN = 2;

}

/**
 * Fornece o contrato nativo de atores individuais para os testes da consulta compartilhada.
 */

class Ticket_User {

    /**
     * Devolve a tabela de técnicos atribuídos.
     */

    public static function getTable(): string {

        return 'glpi_tickets_users';

    }

}

/**
 * Fornece o contrato nativo de grupos responsáveis para os testes da consulta compartilhada.
 */

class Group_Ticket {

    /**
     * Devolve a tabela de grupos atribuídos.
     */

    public static function getTable(): string {

        return 'glpi_groups_tickets';

    }

}

/**

 * Fornece o nome da tabela nativa usada na seleção de usuários ativos.

 */

class User {

    /**

     * Identifica a tabela de usuários sem abrir conexão ou consultar catálogos reais.

     */

    public static function getTable(): string {

        return 'glpi_users';

    }

}

/**

 * Reproduz o catálogo nativo de integrantes somente com técnicos sintéticos.

 */

class Group_User {

    public static array $consultas = [];

    /**

     * Captura os grupos dos setores e os filtros complementares encaminhados à API.

     */

    public static function getGroupUsers(array $grupos, array $condicoes = []): array {

        self::$consultas[] = [$grupos, $condicoes];

        return [

            ['id' => 9, 'name' => 'tecnico1', 'firstname' => 'Primeiro', 'realname' => 'Técnico'],

            ['id' => 10, 'name' => 'tecnico2', 'firstname' => 'Segundo', 'realname' => 'Técnico'],

        ];

    }

}

/**

 * Distingue o nome formatado pela API nativa da concatenação manual anterior.

 */

class DbUtils {

    /**

     * Fornece uma apresentação sintética reconhecível sem reproduzir as preferências do GLPI.

     */

    public function formatUserName($id, $login, $sobrenome, $nome): string {

        return 'Nome nativo ' . $id;

    }

}

spl_autoload_register(

    /**
     * Carrega somente classes próprias do plugin para executar os contratos sem instalar o GLPI.
     */

    static function (

        string $classe

    ): void {

        // Carrega apenas classes do plugin, sem tentar instalar outro autoloader.

        $prefixo = 'GlpiPlugin\\Admanagement\\';

        // Restringe o carregamento ao namespace do projeto em teste.

        if (str_starts_with($classe, $prefixo)) {

            require dirname(__DIR__) . '/src/' . substr($classe, strlen($prefixo)) . '.php';

        }

    }

);

$verificacoes = 0;

/**
 * Uma falha interrompe o processo para que a integração não reporte sucesso parcial.
 */

function conferirRegra(

    bool $ok,

    string $cenario

): void {

    global $verificacoes;

    $verificacoes++;

    // Interrompe a suíte com o nome da regra cujo resultado esperado não foi atendido.

    if (!$ok) {

        throw new RuntimeException($cenario);

    }

}

/**
 * Garante recusa sem depender do texto incidental de erro.
 */

function recusarRegra(

    callable $acao,

    string $cenario

): void {

    try {

        $acao();

    } catch (DomainException | InvalidArgumentException $erro) {

        // Conta como sucesso do cenário a recusa esperada da ação inválida.

        conferirRegra(true, $cenario);

        return;

    }

    conferirRegra(false, $cenario);

}

/**
 * Apaga somente o diretório temporário explicitamente criado por esta suíte.
 */

function limparRegra(

    string $diretorio

): void {

    // Percorre apenas o diretório temporário explicitamente criado por esta suíte.

    foreach (new FilesystemIterator($diretorio, FilesystemIterator::SKIP_DOTS) as $item) {

        // Desce em subdiretório real sem seguir links durante a limpeza do laboratório.

        if ($item->isDir() && !$item->isLink()) {

            limparRegra($item->getPathname());

        } else {

            // Remove o arquivo ou link do próprio laboratório sem percorrer seu destino.

            unlink($item->getPathname());

        }

    }

    rmdir($diretorio);

}

$temporario = sys_get_temp_dir() . '/admanagement-regras-' . bin2hex(random_bytes(8));

mkdir($temporario, 0700);

try {

    $dominio = [

        'nome' => 'Contrato',

        'host' => 'ad.example.invalid',

        'base_dn' => 'DC=example,DC=invalid',

        'obrigatorio' => true

    ];

    Config::$valores['diretorios'] = json_encode(

        [

            1 => $dominio,

            2 => array_replace(

                $dominio,

                [

                    'nome' => 'Outro',

                    'base_dn' => 'DC=outro,DC=invalid',

                    'obrigatorio' => false

                ]

            )

        ]

    );

    Config::$valores['grupos_e_categorias'] = json_encode(['categorias_itil' => [11, 12]]);

    conferirRegra(

        ConfiguracaoDaFila::consultar() === ['categorias_itil' => [11, 12]],

        'a chave instalada preserva somente categorias operacionais, sem conceder acesso por grupo'

    );

    $contexto = [

        'tecnico_id' => 9,

        'perfil_id' => 4,

        'entidade_id' => 2,

        'grupos_ids' => [7],

        'entidades_ativas_ids' => [2],

        'direitos_perfil' => ['operar' => true, 'administrar_setores' => false],

        'visibilidade' => ['papel' => 'tecnico', 'entidades_ids' => [2], 'vinculos' => []],

        'assinatura_visibilidade' => 'mapa-inicial',

    ];

    $rascunho = [

        'chamado_id' => 10,

        'formulario_id' => 20,

        'tecnico_id' => 9,

        'tecnico_original' => 9,

        'entidade_id' => 2,

        'tipo' => 'criacao',

        'revisao' => 1,

        'assinatura_origem' => 'origem',

        'nome_civil' => 'Ana Luiza Campos',

        'nome_social' => '',

        'usar_nome_social' => false,

        'uso_nome_na_origem' => false,

        'matricula' => 'M1',

        'conta_atendida' => '',

        'login_pretendido' => 'ana.campos',

        'dominio' => '1',

        'ou' => 'OU=Equipe,DC=example,DC=invalid',

        'grupos' => 'Grupo digitado',

        'scripts' => 'Texto digitado',

        'pastas' => 'Pendente',

        'validade_proposta' => 'Pendente',

        'avisos_estrutura' => []

    ];

    $rascunho['catalogo_ous'] = [

        'assinatura' => hash('sha256', json_encode($dominio, JSON_THROW_ON_ERROR)),

        'expira' => time() + 300,

        'itens' => [

            [

                'nome' => 'Equipe',

                'dn' => $rascunho['ou']

            ],

            [

                'nome' => 'Outra',

                'dn' => 'OU=Outra,DC=example,DC=invalid'

            ]

        ]

    ];

    conferirRegra(

        PoliticaDeLogin::gerarCandidatos('Ana Luiza Campos') === ['ana.campos', 'ana.luiza', 'ana'],

        'sequência Ana'

    );

    conferirRegra(

        PoliticaDeLogin::gerarCandidatos('Matheus Augusto Alves Goveia Damiao') === [

            'matheus.damiao',

            'matheus.goveia',

            'matheus.alves',

            'matheus.augusto',

            'matheus'

        ],

        'sequência Matheus'

    );

    // Confere que composições ofensivas ou formatos não previstos não passam na barreira inicial.

    foreach ([

        'anal.santos',

        'ana1',

        'a.c1',

        'Ana',

        'ana_',

        'ana..campos'

    ] as $login) {

        conferirRegra(

            !PoliticaDeLogin::passaNaVerificacaoInicial($login),

            'composição inadequada ' . $login

        );

    }

    conferirRegra(

        RevisorDaOperacao::obterNomeParaLogin(array_replace($rascunho, ['usar_nome_social' => true])) === '',

        'nome social ausente não usa civil'

    );

    conferirRegra(

        NomeDistinto::pai('OU=Setor\, Sala,DC=example,DC=invalid') === 'DC=example,DC=invalid',

        'vírgula escapada'

    );

    conferirRegra(

        NomeDistinto::pertenceAoDominio($rascunho['ou'], $dominio['base_dn']),

        'domínio validado'

    );

    conferirRegra(

        !NomeDistinto::pertenceAoDominio('OU=Equipe,DC=example,DC=invalid.evil', $dominio['base_dn']),

        'não aceita sufixo semelhante'

    );

    $trocado = DestinoDaOperacao::aplicar($rascunho, [

        'dominio' => '2',

        'ou' => $rascunho['ou']

    ]);

    conferirRegra(

        $trocado['dominio'] === '2' && $trocado['ou'] === '' && !isset($trocado['catalogo_ous']),

        'trocar domínio limpa radio antigo'

    );

    $expirado = $rascunho;

    $expirado['catalogo_ous']['expira'] = time() - 1;

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: não salva catálogo expirado.
         */

        fn() => DestinoDaOperacao::aplicar($expirado, ['ou' => $expirado['ou']]),

        'não salva catálogo expirado'

    );

    conferirRegra(

        DestinoDaOperacao::aplicar($expirado, ['ou' => ''])['ou'] === $rascunho['ou'],

        'ação de atualização pode ignorar radio antigo'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: recusa OU inventada.
         */

        fn() => DestinoDaOperacao::aplicar($rascunho, ['ou' => 'OU=Inventada,DC=example,DC=invalid']),

        'recusa OU inventada'

    );

    conferirRegra(

        DestinoDaOperacao::aplicar($rascunho, ['ou' => 'OU=Outra,DC=example,DC=invalid'])['ou'] === 'OU=Outra,DC=example,DC=invalid',

        'todas OUs consultadas permitidas'

    );

    $fabrica =

    /**
     * Fornece um leitor de contrato por domínio para testar cobertura sem rede ou credenciais
     * reais.
     */

    static function (

        array $d

    ): object {

        // Adaptador de contrato, definido no teste; o produto sempre constrói o LeitorLdap real.

        return

        /**
         * Dublê local de leitura que fornece dados de contrato e falhas controladas, sem acessar
         * GLPI ou AD.
         */

        new class($d) {

            /**
             * Define o domínio do cenário sem rede.
             */

            public function __construct(

                private array $d

            ) {

            }

            /**
             * Simula indisponibilidade, ocupação ou ausência para validar a cobertura.
             */

            public function loginOcupado(

                string $login

            ): bool {

                // Simula indisponibilidade do domínio na consulta de login para exercitar cobertura incompleta.

                if (!empty($this->d['falhar'])) {

                    throw new DomainException('Contrato incompleto');

                }

                return !empty($this->d['ocupado']);

            }

            /**
             * Retorna somente identificadores do cenário de teste.
             */

            public function listarIdentidades(): array {

                // Simula indisponibilidade da leitura de identidades sem produzir uma falsa ausência de duplicidade.

                if (!empty($this->d['falhar'])) {

                    throw new DomainException('Contrato incompleto');

                }

                return $this->d['identidades'] ?? [];

            }

        };

    };

    $verificador = new VerificadorDoDiretorio($fabrica);

    $dominios = [

        1 => $dominio,

        2 => array_replace($dominio, ['obrigatorio' => false])

    ];

    $pessoa = [

        'nome' => 'Ana Luiza Campos',

        'login' => 'ana.campos',

        'matricula' => 'M1',

        'cpf' => '123.456.789-01'

    ];

    conferirRegra(

        $verificador->consultarDisponibilidade('ana.campos', $dominios, 2)['estado'] === 'disponivel',

        'união alvo opcional e obrigatório'

    );

    $dominios[1]['falhar'] = true;

    conferirRegra(

        $verificador->consultarDisponibilidade('ana.campos', $dominios, 2)['estado'] === 'incompleto',

        'domínio obrigatório fora bloqueia'

    );

    unset($dominios[1]['falhar']);

    $dominios[2]['identidades'] = [

        array_replace($pessoa, ['cpf' => '12345678901'])

    ];

    $duplicidades = $verificador->consultarDuplicidades($pessoa, $dominios, 2);

    conferirRegra(

        $duplicidades['estado'] === 'possivel_duplicidade' && $duplicidades['contagens']['cpf'] === 1 && count($duplicidades['cobertura']) === 2,

        'duplicidade no alvo opcional, CPF normalizado'

    );

    conferirRegra(

        !str_contains(json_encode($duplicidades), '12345678901'),

        'resposta minimizada'

    );

    conferirRegra(

        $verificador->consultarDuplicidades([], $dominios, 2)['estado'] === 'incompleto',

        'identificadores ausentes bloqueiam'

    );

    conferirRegra(

        $verificador->consultarDuplicidades($pessoa, [1 => $dominio], 2)['estado'] === 'nao_consultado',

        'alvo ausente não é ausência de duplicidade'

    );

    conferirRegra(

        $verificador->consultarDisponibilidade('ana.campos', [], 1)['estado'] === 'nao_consultado',

        'nenhum domínio não é disponibilidade'

    );

    conferirRegra(

        $verificador->consultarDisponibilidade('ana.campos', [2 => ['obrigatorio' => false]], 2)['estado'] === 'incompleto',

        'sem lista obrigatória não comprova disponibilidade'

    );

    conferirRegra(

        $verificador->consultarDuplicidades($pessoa, [2 => ['obrigatorio' => false]], 2)['estado'] === 'incompleto',

        'sem lista obrigatória não comprova duplicidades'

    );

    $preparado = RevisaoDaOperacao::preparar($rascunho, $contexto);

    $entrada = [

        'confirmar_revisao' => '1',

        'desafio' => $preparado['resumo']['desafio']

    ];

    $confirmado = RevisaoDaOperacao::confirmar($preparado, $rascunho, $contexto, $entrada);

    conferirRegra(

        isset($confirmado['confirmacao']) && !isset($confirmado['resumo']['desafio']),

        'confirmação explícita consome desafio'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: POST repetido.
         */

        fn() => RevisaoDaOperacao::confirmar($confirmado, $rascunho, $contexto, $entrada),

        'POST repetido'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: confirmação não implícita.
         */

        fn() => RevisaoDaOperacao::confirmar($preparado, $rascunho, $contexto, []),

        'confirmação não implícita'

    );

    $outro_perfil = array_replace($contexto, ['perfil_id' => 8]);

    conferirRegra(

        !RevisaoDaOperacao::continuaValida($preparado, $rascunho, $outro_perfil),

        'mudança de perfil invalida'

    );

    $direito_revogado = array_replace($contexto, ['direitos_perfil' => ['operar' => false, 'administrar_setores' => false]]);

    conferirRegra(

        !RevisaoDaOperacao::continuaValida($preparado, $rascunho, $direito_revogado),

        'revogação dos direitos do perfil invalida o resumo mesmo sem trocar o identificador do perfil'

    );

    recusarRegra(

        /**

         * O POST não pode reaproveitar um desafio preparado antes da revogação do perfil.

         */

        fn() => RevisaoDaOperacao::confirmar($preparado, $rascunho, $direito_revogado, $entrada),

        'direitos revogados bloqueiam confirmação direta'

    );

    $mapa_alterado = array_replace($contexto, ['assinatura_visibilidade' => 'mapa-alterado']);

    conferirRegra(

        !RevisaoDaOperacao::continuaValida($preparado, $rascunho, $mapa_alterado),

        'alterar a revisão do mapa de visibilidade invalida o resumo'

    );

    recusarRegra(

        /**

         * Uma edição de configuração invalida a confirmação mesmo com o perfil ainda ativo.

         */

        fn() => RevisaoDaOperacao::confirmar($preparado, $rascunho, $mapa_alterado, $entrada),

        'mapa alterado bloqueia confirmação direta'

    );

    $grupos_informativos = array_replace($contexto, ['grupos_ids' => [99]]);

    conferirRegra(

        RevisaoDaOperacao::continuaValida($preparado, $rascunho, $grupos_informativos),

        'mudança isolada dos grupos pessoais não altera a autorização por perfil nem invalida o resumo'

    );

    $_SESSION = [];

    RepositorioDeRascunhos::guardarInicial($preparado);

    RepositorioDeRascunhos::conferirContexto($grupos_informativos);

    conferirRegra(

        isset(RepositorioDeRascunhos::consultar(10, $contexto)['resumo']),

        'a conferência persistida mantém o resumo quando somente os grupos informativos mudam'

    );

    RepositorioDeRascunhos::invalidarConfirmacao(10, $contexto);

    $invalidado = RepositorioDeRascunhos::consultar(10, $contexto);

    conferirRegra(

        !isset($invalidado['resumo']) && $invalidado['revisao'] === 2,

        'invalidação é persistida monotonicamente'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: voltar perfil não revive desafio.
         */

        fn() => RevisaoDaOperacao::confirmar($invalidado, $rascunho, $contexto, $entrada),

        'voltar perfil não revive desafio'

    );

    $origem_nova = array_replace($rascunho, ['assinatura_origem' => 'alterada']);

    conferirRegra(

        !RevisaoDaOperacao::continuaValida($preparado, $origem_nova, $contexto),

        'mudança de origem'

    );

    $mudado = RevisorDaOperacao::aplicar($confirmado, ['scripts' => 'Outro script']);

    conferirRegra(

        !isset($mudado['resumo']) && !isset($mudado['confirmacao']),

        'alteração de efeitos descarta confirmação'

    );

    $diferente = $rascunho;

    $diferente['catalogo_ous']['expira'] = time() - 1;

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: resumo não chancela catálogo
         * expirado.
         */

        fn() => RevisaoDaOperacao::preparar($diferente, $contexto),

        'resumo não chancela catálogo expirado'

    );

    $dominio_novo = $dominio;

    $dominio_novo['host'] = 'novo.example.invalid';

    Config::$valores['diretorios'] = json_encode([1 => $dominio_novo]);

    conferirRegra(

        !RevisaoDaOperacao::continuaValida($preparado, $rascunho, $contexto),

        'nova configuração não mantém confirmação'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: configuração alterada bloqueia
         * POST.
         */

        fn() => RevisaoDaOperacao::confirmar($preparado, $rascunho, $contexto, $entrada),

        'configuração alterada bloqueia POST'

    );

    Config::$valores['diretorios'] = json_encode([1 => $dominio]);

    $resumo_expirado = $preparado;

    $resumo_expirado['resumo']['expira'] = time() - 1;

    conferirRegra(

        !RevisaoDaOperacao::continuaValida($resumo_expirado, $rascunho, $contexto),

        'resumo expirado'

    );

    $_SESSION['csrf'] = 'antes';

    try {

        RepositorioDeRascunhos::comAuditoria(

            /**
             * Modifica o rascunho e consome um token de contrato antes de simular falha da
             * auditoria.
             */

            static function () use ($invalidado): void {

                RepositorioDeRascunhos::salvar(array_replace($invalidado, ['revisao' => 3]), 2);

                $_SESSION['csrf'] = 'consumido';

                throw new DomainException('Auditoria indisponível');

            }

        );

    } catch (DomainException $erro) {

        // Aceita a falha simulada para conferir em seguida o rollback limitado às chaves próprias do rascunho.

    }

    conferirRegra(

        RepositorioDeRascunhos::consultar(10, $contexto)['revisao'] === 2 && $_SESSION['csrf'] === 'consumido',

        'falha durável reverte só rascunho sem ressuscitar CSRF'

    );

    RepositorioDeRascunhos::descartar(10, $contexto, 2);

    $novo = RepositorioDeRascunhos::guardarInicial($rascunho);

    conferirRegra($novo['revisao'] === 3, 'cancelamento não reutiliza revisão de aba antiga');

    $repositorio = new RepositorioDeOperacoes($temporario . '/privado');

    $repositorio->registrar(

        'IMPORTADO',

        array_replace($rascunho, [

            'cpf' => '12345678901',

            'senha' => 'SEGREDO'

        ]),

        $contexto

    );

    $id = $repositorio->reservarNecessidade(

        'identidade-transitoria-protegida',

        'necessidade-1',

        'criacao',

        10,

        9,

        'r1',

        2,

        20

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: novo ticket não contorna
         * necessidade existente.
         */

        fn() => $repositorio->reservarNecessidade(

            'identidade-transitoria-protegida',

            'necessidade-1',

            'criacao',

            11,

            9,

            'r2',

            2,

            20

        ),

        'novo ticket não contorna necessidade existente'

    );

    $tentativa = $repositorio->iniciarTentativa($id, 10, 9);

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: sem segunda tentativa concorrente.
         */

        fn() => $repositorio->iniciarTentativa($id, 10, 9),

        'sem segunda tentativa concorrente'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: não finaliza sem sucesso AD.
         */

        fn() => $repositorio->registrarFinalizacao($id, 10, true, true),

        'não finaliza sem sucesso AD'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: sucesso precisa evidência.
         */

        fn() => $repositorio->registrarResultadoAd($id, 10, $tentativa, 'SUCESSO_COMPROVADO'),

        'sucesso precisa evidência'

    );

    $repositorio->registrarResultadoAd($id, 10, $tentativa, 'DESCONHECIDO');

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: timeout não permite repetir.
         */

        fn() => $repositorio->iniciarTentativa($id, 10, 9),

        'timeout não permite repetir'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: nova necessidade não contorna
         * resultado desconhecido.
         */

        fn() => $repositorio->reservarNecessidade(

            'identidade-transitoria-protegida',

            'necessidade-trocada',

            'criacao',

            13,

            9,

            'r2',

            2,

            20

        ),

        'nova necessidade não contorna resultado desconhecido'

    );

    $operacao = array_values($repositorio->consultar()['operacoes'])[0];

    conferirRegra(

        PoliticaDeResultados::proximaEtapa($operacao, 10) === 'RECONCILIAR_SEM_REPETIR_AD',

        'recuperação desconhecida'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: alvo fixo de recuperação.
         */

        fn() => PoliticaDeResultados::proximaEtapa($operacao, 11),

        'alvo fixo de recuperação'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: ator obsoleto não sobrescreve
         * resultado.
         */

        fn() => $repositorio->registrarResultadoAd($id, 10, $tentativa, 'SUCESSO_COMPROVADO', str_repeat('a', 64)),

        'ator obsoleto não sobrescreve resultado'

    );

    $id2 = $repositorio->reservarNecessidade(

        'outra-identidade-transitoria',

        'necessidade-2',

        'renovacao',

        12,

        9,

        'r1',

        2,

        22

    );

    $tentativa2 = $repositorio->iniciarTentativa($id2, 12, 9);

    $repositorio->registrarResultadoAd($id2, 12, $tentativa2, 'SUCESSO_COMPROVADO', str_repeat('b', 64));

    $operacao2 = array_values($repositorio->consultar()['operacoes'])[1];

    conferirRegra(

        PoliticaDeResultados::proximaEtapa($operacao2, 12) === 'SOMENTE_FINALIZAR_CHAMADO',

        'AD comprovado permite apenas fechamento'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: não troca chamado da finalização.
         */

        fn() => $repositorio->registrarFinalizacao($id2, 10, true, true),

        'não troca chamado da finalização'

    );

    $repositorio->registrarFinalizacao($id2, 12, true, false);

    conferirRegra(

        array_values($repositorio->consultar()['operacoes'])[1]['resultado_glpi'] === 'PENDENTE',

        'texto não prova fechamento'

    );

    $repositorio->registrarFinalizacao($id2, 12, true, true);

    conferirRegra(

        PoliticaDeResultados::proximaEtapa(array_values($repositorio->consultar()['operacoes'])[1], 12) === 'CONCLUIDA',

        'duas etapas verificadas'

    );

    $posterior = $repositorio->reservarNecessidade(

        'outra-identidade-transitoria',

        'necessidade-real-posterior',

        'renovacao',

        14,

        9,

        'r2',

        2,

        24

    );

    conferirRegra($posterior !== $id2, 'necessidade posterior distinta exige operação nova após conclusão');

    $json = file_get_contents($temporario . '/privado/estado.json');

    // Confere que o documento durável não contém CPF, segredo, identidade transitória ou valores pessoais.

    foreach (

        [

            '12345678901',

            'SEGREDO',

            'identidade-transitoria-protegida',

            'Ana Luiza Campos',

            'Grupo digitado'

        ] as $proibido

    ) {

        conferirRegra(!str_contains($json, $proibido), 'metadados não expõem ' . $proibido);

    }

    conferirRegra(

        !isset($repositorio->consultar()['chave']),

        'chave privada não sai do repositório'

    );

    file_put_contents($temporario . '/privado/estado.json', '{corrompido');

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: corrupção não reinicia proteção.
         */

        fn() => $repositorio->registrar('IMPORTADO', $rascunho, $contexto),

        'corrupção não reinicia proteção'

    );

    conferirRegra(

        file_get_contents($temporario . '/privado/estado.json') === '{corrompido',

        'evidência corrompida preservada'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: não persiste dentro plugin.
         */

        fn() => new RepositorioDeOperacoes(dirname(__DIR__) . '/public/historico'),

        'não persiste dentro plugin'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: não aceita travessia de diretório.
         */

        fn() => new RepositorioDeOperacoes($temporario . '/../public'),

        'não aceita travessia de diretório'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: sem botão para habilitar execução.
         */

        fn() => PoliticaDeResultados::exigirExecucaoReal([]),

        'sem botão para habilitar execução'

    );

    Config::$valores['diretorios'] = '{invalido';

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: configuração corrompida não libera
         * cobertura.
         */

        fn() => ConfiguracaoOperacional::consultar('diretorios'),

        'configuração corrompida não libera cobertura'

    );

    $banco =

    /**
     * Dublê local de leitura que fornece dados de contrato e falhas controladas, sem acessar GLPI
     * ou AD.
     */

    new class {

        public array $consultas = [];

        /**
         * Captura as condições efetivamente encaminhadas ao GLPI.
         */

        public function request(

            array $criterios

        ): array {

            $this->consultas[] = $criterios;

            return [

                ['id' => 1, 'status' => 5, 'entities_id' => 2, 'itilcategories_id' => 11],

                ['id' => 2, 'status' => 5, 'entities_id' => 2, 'itilcategories_id' => 11],

                ['id' => 3, 'status' => 5, 'entities_id' => 2, 'itilcategories_id' => 11]

            ];

        }

    };

    $equipe = new AcompanhamentoDaEquipe($banco);

    $resultado = $equipe->consultar($contexto, [

        11,

        12

    ], null, true);

    $criterios = $banco->consultas[0];

    conferirRegra(count($resultado['chamados']) === 2, 'direito individual remove recurso negado');

    conferirRegra(

        $criterios['WHERE']['glpi_tickets_users.users_id'] === $contexto['tecnico_id'],

        'técnico permanece na atribuição pessoal'

    );

    conferirRegra(

        $criterios['WHERE']['glpi_tickets.status'] === [5, 6],

        'histórico solucionado e fechado'

    );

    conferirRegra(

        $criterios['WHERE']['glpi_tickets.entities_id'] === [2] && !isset($criterios['WHERE']['glpi_tickets.itilcategories_id']),

        'escopo não ampliado'

    );

    $equipe->consultar($contexto, [11], $contexto['tecnico_id'], false);

    conferirRegra(

        $banco->consultas[1]['WHERE']['AND'][0] === ['glpi_tickets_users.users_id' => $contexto['tecnico_id']],

        'filtro técnico não substitui a atribuição pessoal'

    );

    recusarRegra(

        /**
         * Executa a tentativa que deve ser recusada no cenário: filtro não concede equipe externa.
         */

        fn() => $equipe->consultar($contexto, [11], 99, false),

        'filtro não concede equipe externa'

    );

    $supervisor = array_replace($contexto, [

        'direitos_perfil' => ['operar' => false, 'administrar_setores' => true],

        'visibilidade' => [

            'papel' => 'administrador_setor',

            'entidades_ids' => [2],

            'vinculos' => [['grupos_ids' => [6], 'entidades_ids' => [2]]],

        ],

    ]);

    $equipe_do_setor = $equipe->consultar($supervisor, [], 10, true);

    conferirRegra(

        $equipe_do_setor['tecnicos'] === [9 => 'Nome nativo 9', 10 => 'Nome nativo 10'],

        'a equipe usa integrantes e nomes apresentados pelas APIs nativas'

    );

    conferirRegra(

        Group_User::$consultas[0] === [[6], ['glpi_users.is_deleted' => 0, 'glpi_users.is_active' => 1]],

        'o catálogo usa os grupos atribuídos aos setores do perfil e exclui técnicos inativos ou removidos'

    );

    echo $verificacoes . " verificações das regras vigentes concluídas.\n";

} finally {

    // Remove o laboratório desta execução, preservando qualquer arquivo fora da pasta temporária criada.

    limparRegra($temporario);

}

