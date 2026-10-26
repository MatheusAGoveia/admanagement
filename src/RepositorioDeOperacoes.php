<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Preserva somente metadados privados, com lock exclusivo e substituição atômica.
 */

final class RepositorioDeOperacoes {

    /**
     * Recusa armazenamento público; o diretório próprio é criado apenas numa ação de gravação.
     */

    public function __construct(

        private readonly string $diretorio

    ) {

        // Exige caminho absoluto para evitar que o histórico seja criado em um diretório dependente da execução.

        if (

            !str_starts_with($diretorio, '/')

            && !preg_match('/^[a-z]:[\\\\\/]/i', $diretorio)

        ) {

            throw new DomainException('O armazenamento privado precisa de um caminho absoluto.');

        }

        // Recusa travessia de diretório e caracteres nulos no caminho privado.

        if (

            preg_match('~(?:^|[\\\\/])\.\.?(?:[\\\\/]|$)~', $diretorio)

            || str_contains($diretorio, chr(0))

        ) {

            throw new DomainException('Caminho privado inválido.');

        }

        // Confere o alvo contra a pasta pública do GLPI e a pasta carregável do plugin.

        foreach (

            [

                defined('GLPI_ROOT') ? GLPI_ROOT . '/public' : null,

                dirname(__DIR__)

            ] as $publico

        ) {

            // Recusa armazenar histórico dentro de uma das áreas públicas ou carregáveis verificadas.

            if ($publico !== null && self::estaDentro($diretorio, $publico)) {

                throw new DomainException('O histórico não pode ficar dentro da área pública ou da pasta do plugin.');

            }

        }

    }

    /**
     * Usa o diretório de dados do GLPI, sem criar tabelas ou usar a pasta pública do plugin.
     */

    public static function doGlpi(): self {

        // Bloqueia persistência quando o GLPI não informa seu diretório privado de dados.

        if (!defined('GLPI_VAR_DIR')) {

            throw new DomainException('O armazenamento privado do GLPI não está disponível.');

        }

        return new self(GLPI_VAR_DIR . '/_plugins/admanagement');

    }

    /**
     * Serializa alterações e mantém o arquivo anterior intacto até confirmar a nova gravação.
     */

    public function transacionar(

        callable $alteracao

    ): mixed {

        // Prepara o diretório privado e interrompe a ação se ele não puder ser criado.

        if (

            !is_dir($this->diretorio)

            && !mkdir($this->diretorio, 0700, true)

            && !is_dir($this->diretorio)

        ) {

            throw new DomainException('Não foi possível preparar o armazenamento privado.');

        }

        // Recusa um link no lugar do diretório destinado ao registro privado.

        if (is_link($this->diretorio)) {

            throw new DomainException('O armazenamento não pode ser um link.');

        }

        // Recusa um link no arquivo de lock para não redirecionar a posse exclusiva.

        if (is_link($this->diretorio . '/.lock')) {

            throw new DomainException('Lock privado inválido.');

        }

        $lock = fopen($this->diretorio . '/.lock', 'c+');

        // Interrompe a alteração quando não consegue abrir e adquirir o lock exclusivo.

        if ($lock === false || !flock($lock, LOCK_EX)) {

            throw new DomainException('Não foi possível obter posse exclusiva do registro.');

        }

        $temporario = null;

        try {

            $estado = $this->carregar();

            $retorno = $alteracao($estado);

            $json = json_encode($estado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            // Bloqueia crescimento além do limite até que seja definida a política institucional de retenção.

            if (strlen($json) > 10 * 1024 * 1024) {

                throw new DomainException('O histórico atingiu o limite. Defina retenção antes de continuar.');

            }

            $temporario = tempnam($this->diretorio, '.gravacao-');

            // Interrompe a gravação se não for possível criar o arquivo temporário privado.

            if ($temporario === false) {

                throw new DomainException('Não foi possível preparar a gravação.');

            }

            chmod($temporario, 0600);

            $arquivo = fopen($temporario, 'wb');

            // Recusa prosseguir quando o arquivo de substituição não pode ser aberto.

            if ($arquivo === false) {

                throw new DomainException('Não foi possível abrir a gravação privada.');

            }

            try {

                // Exige gravar e sincronizar todo o documento antes de substituir a versão anterior.

                if (

                    fwrite($arquivo, $json) !== strlen($json)

                    || !fflush($arquivo)

                    || !fsync($arquivo)

                ) {

                    throw new DomainException('A gravação não foi confirmada; o registro anterior foi preservado.');

                }

            } finally {

                // Fecha o arquivo temporário mesmo quando a escrita ou sincronização falha.

                fclose($arquivo);

            }

            // Mantém a versão anterior quando a substituição atômica não é confirmada.

            if (!rename($temporario, $this->diretorio . '/estado.json')) {

                throw new DomainException('Não foi possível confirmar a atualização atômica.');

            }

            return $retorno;

        } finally {

            // Remove sobras temporárias e libera o lock em qualquer resultado da transação.

            // Apaga apenas o arquivo temporário desta tentativa que ainda existe após a transação.

            if ($temporario !== null && is_file($temporario)) {

                unlink($temporario);

            }

            flock($lock, LOCK_UN);

            fclose($lock);

        }

    }

    /**
     * Lê apenas resultados e auditoria; a chave privada nunca integra a resposta.
     */

    public function consultar(): array {

        // Apresenta um histórico inicialmente vazio quando ainda não existe documento durável.

        if (!is_file($this->diretorio . '/estado.json')) {

            return [

                'operacoes' => [],

                'auditoria' => []

            ];

        }

        // Impede que a leitura use um arquivo de lock redirecionado por link.

        if (is_link($this->diretorio . '/.lock')) {

            throw new DomainException('Lock privado inválido.');

        }

        $lock = fopen($this->diretorio . '/.lock', 'c+');

        // Interrompe a leitura se não consegue adquirir o lock compartilhado do histórico.

        if ($lock === false || !flock($lock, LOCK_SH)) {

            throw new DomainException('O histórico está indisponível.');

        }

        try {

            $estado = $this->carregar();

            return [

                'operacoes' => $estado['operacoes'],

                'auditoria' => $estado['auditoria']

            ];

        } finally {

            // Libera o lock de leitura mesmo se o documento privado estiver inválido.

            flock($lock, LOCK_UN);

            fclose($lock);

        }

    }

    /**
     * Registra evento real da ferramenta por lista fechada, sem nomes, CPF, credenciais ou
     * snapshots.
     */

    public function registrar(

        string $evento,

        array $rascunho,

        array $contexto,

        ?array $anterior = null

    ): void {

        $this->transacionar(

            /**
             * Acrescenta um evento minimizado ao documento enquanto a transação mantém posse
             * exclusiva.
             */

            static function (

                array &$estado

            ) use ($evento, $rascunho, $contexto, $anterior): void {

                $permitidos = [

                    'IMPORTADO',

                    'REIMPORTADO',

                    'CORRIGIDO',

                    'DESCARTADO',

                    'RESUMO_PREPARADO',

                    'REVISAO_CONFIRMADA',

                    'CONSULTA_DIRETORIO',

                    'CONSULTA_OUS'

                ];

                // Recusa eventos fora da lista conhecida para não registrar estados arbitrários.

                if (!in_array($evento, $permitidos, true)) {

                    throw new DomainException('Evento de auditoria inválido.');

                }

                $campos = [];

                // Compara campos somente quando há uma revisão anterior disponível.

                if ($anterior !== null) {

                    // Verifica os campos de trabalho permitidos sem copiar seus valores pessoais para a auditoria.

                    foreach (

                        [

                            'nome_civil',

                            'nome_social',

                            'usar_nome_social',

                            'matricula',

                            'conta_atendida',

                            'login_pretendido',

                            'dominio',

                            'ou',

                            'grupos',

                            'scripts',

                            'pastas',

                            'validade_proposta'

                        ] as $campo

                    ) {

                        // Registra somente o nome do campo cujo valor mudou entre as revisões.

                        if (($anterior[$campo] ?? null) !== ($rascunho[$campo] ?? null)) {

                            $campos[] = $campo;

                        }

                    }

                }

                $estado['auditoria'][] = [

                    'campos_alterados' => $campos,

                    'evento' => $evento,

                    'chamado_id' => (int) $rascunho['chamado_id'],

                    'formulario_id' => (int) $rascunho['formulario_id'],

                    'entidade_id' => (int) $contexto['entidade_id'],

                    'tecnico_original' => (int) ($rascunho['tecnico_original'] ?? $rascunho['tecnico_id']),

                    'ator_id' => (int) $contexto['tecnico_id'],

                    'revisao' => (int) $rascunho['revisao'],

                    'instante' => gmdate(DATE_ATOM),

                ];

            }

        );

    }

    /**
     * Reserva uma necessidade independentemente do ticket e recusa repetição após início ou
     * sucesso.
     */

    public function reservarNecessidade(

        string $identificador_transitorio,

        string $necessidade_verificada,

        string $acao,

        int $chamado,

        int $ator,

        string $revisao,

        int $entidade,

        int $formulario

    ): string {

        // Exige pessoa, necessidade, alvo, autoria, revisão e tipo válidos antes de reservar uma operação.

        if (

            $identificador_transitorio === ''

            || $necessidade_verificada === ''

            || $chamado < 1

            || $ator < 1

            || $entidade < 0

            || $formulario < 1

            || $revisao === ''

            || !in_array($acao, [

                'criacao',

                'renovacao'

            ], true)

        ) {

            throw new DomainException('A identidade confiável da necessidade ainda não foi determinada.');

        }

        return $this->transacionar(

            /**
             * Reserva a necessidade sob lock, vinculando seus identificadores protegidos à operação
             * e ao chamado fixo.
             */

            static function (

                array &$estado

            ) use (

                $identificador_transitorio,

                $necessidade_verificada,

                $acao,

                $chamado,

                $ator,

                $revisao,

                $entidade,

                $formulario

            ): string {

                // HMAC com chave privada não é declaração de anonimização; continua dado protegido.

                $pessoa = hash_hmac('sha256', $identificador_transitorio, $estado['chave']);

                $chave = hash_hmac(

                    'sha256',

                    json_encode(

                        [

                            $acao,

                            $identificador_transitorio,

                            $necessidade_verificada

                        ],

                        JSON_THROW_ON_ERROR

                    ),

                    $estado['chave']

                );

                // Impede uma nova reserva da mesma necessidade, mesmo que outro chamado seja apresentado.

                if (isset($estado['operacoes'][$chave])) {

                    throw new DomainException('Esta necessidade já possui registro. Reconciliar antes de qualquer nova tentativa, mesmo com outro chamado.');

                }

                // Confere operações anteriores da pessoa antes de aceitar uma necessidade distinta.

                foreach ($estado['operacoes'] as $anterior) {

                    // Bloqueia nova necessidade com efeito anterior pendente ou nova criação após criação comprovada.

                    if (

                        ($anterior['pessoa_id'] ?? '') === $pessoa

                        && (

                            ($anterior['resultado_glpi'] ?? '') !== 'TEXTO_E_FECHAMENTO_VERIFICADOS'

                            || (

                                $acao === 'criacao'

                                && $anterior['acao'] === 'criacao'

                                && $anterior['resultado_ad'] === 'SUCESSO_COMPROVADO'

                            )

                        )

                    ) {

                        throw new DomainException('A pessoa já possui uma operação pendente ou criação comprovada. Corrigir dados ou trocar necessidade não autoriza repetir o AD.');

                    }

                }

                $id = bin2hex(random_bytes(16));

                $estado['operacoes'][$chave] = [

                    'id' => $id,

                    'pessoa_id' => $pessoa,

                    'necessidade_id' => $chave,

                    'acao' => $acao,

                    'chamado_fixo' => $chamado,

                    'entidade_id' => $entidade,

                    'formulario_id' => $formulario,

                    'tecnico_original' => $ator,

                    'ator_id' => $ator,

                    'revisao' => $revisao,

                    'resultado_ad' => 'NAO_INICIADO',

                    'resultado_glpi' => 'NAO_INICIADO',

                    'tentativas' => []

                ];

                return $id;

            }

        );

    }

    /**
     * Adquire uma única tentativa antes de qualquer efeito; não existe retomada automática do AD.
     */

    public function iniciarTentativa(

        string $id,

        int $chamado,

        int $ator

    ): string {

        // Recusa iniciar uma tentativa sem identificador positivo do ator responsável.

        if ($ator < 1) {

            throw new DomainException('Ator de tentativa inválido.');

        }

        return $this->transacionar(

            /**
             * Marca uma única tentativa em andamento dentro da transação exclusiva da operação.
             */

            static function (

                array &$estado

            ) use ($id, $chamado, $ator): string {

                $operacao =& self::localizar($estado, $id, $chamado);

                // Exige reconciliação quando a operação já iniciou uma tentativa ou possui resultado anterior.

                if ($operacao['resultado_ad'] !== 'NAO_INICIADO' || $operacao['tentativas'] !== []) {

                    throw new DomainException('Há tentativa anterior. Reconciliar sem repetir o AD.');

                }

                $tentativa = bin2hex(random_bytes(16));

                $operacao['tentativas'][] = [

                    'id' => $tentativa,

                    'ator_id' => $ator,

                    'inicio' => gmdate(DATE_ATOM),

                    'resultado' => 'EM_ANDAMENTO'

                ];

                $operacao['resultado_ad'] = 'EM_ANDAMENTO';

                return $tentativa;

            }

        );

    }

    /**
     * Recebe apenas resultado do futuro adaptador homologado; não é exposto por nenhuma rota HTTP.
     */

    public function registrarResultadoAd(

        string $id,

        int $chamado,

        string $tentativa,

        string $resultado,

        string $comprovacao = ''

    ): void {

        // Recusa resultados desconhecidos e estados conclusivos sem referência de comprovação válida.

        if (

            !in_array(

                $resultado,

                [

                    'SUCESSO_COMPROVADO',

                    'PARCIAL',

                    'DESCONHECIDO',

                    'FALHA_SEM_EFEITO_COMPROVADA'

                ],

                true

            )

            || (

                in_array($resultado, [

                    'SUCESSO_COMPROVADO',

                    'FALHA_SEM_EFEITO_COMPROVADA'

                ], true)

                && !preg_match('/^[a-f0-9]{64}$/D', $comprovacao)

            )

        ) {

            throw new DomainException('Resultado sem comprovação válida.');

        }

        $this->transacionar(

            /**
             * Registra o resultado apenas na tentativa corrente, preservando o resultado
             * independente do GLPI.
             */

            static function (

                array &$estado

            ) use ($id, $chamado, $tentativa, $resultado, $comprovacao): void {

                $operacao =& self::localizar($estado, $id, $chamado);

                $indice = array_key_last($operacao['tentativas']);

                // Impede que uma tentativa obsoleta ou concluída sobrescreva o resultado da operação.

                if (

                    $indice === null

                    || $operacao['tentativas'][$indice]['id'] !== $tentativa

                    || $operacao['resultado_ad'] !== 'EM_ANDAMENTO'

                ) {

                    throw new DomainException('Tentativa obsoleta ou já concluída; o resultado não pode ser sobrescrito.');

                }

                $operacao['resultado_ad'] = $resultado;

                $operacao['tentativas'][$indice]['resultado'] = $resultado;

                $operacao['tentativas'][$indice]['fim'] = gmdate(DATE_ATOM);

                $operacao['tentativas'][$indice]['comprovacao'] = $comprovacao;

            }

        );

    }

    /**
     * Finalização é independente do AD e permanece vinculada ao chamado original.
     */

    public function registrarFinalizacao(

        string $id,

        int $chamado,

        bool $texto_verificado,

        bool $fechamento_verificado

    ): void {

        $this->transacionar(

            /**
             * Atualiza somente a finalização do chamado original após comprovação do resultado no
             * AD.
             */

            static function (

                array &$estado

            ) use ($id, $chamado, $texto_verificado, $fechamento_verificado): void {

                $operacao =& self::localizar($estado, $id, $chamado);

                // Bloqueia finalização quando não existe sucesso comprovado no diretório.

                if ($operacao['resultado_ad'] !== 'SUCESSO_COMPROVADO') {

                    throw new DomainException('Sem sucesso comprovado no AD, não há finalização.');

                }

                $operacao['resultado_glpi'] = $texto_verificado && $fechamento_verificado ? 'TEXTO_E_FECHAMENTO_VERIFICADOS' : 'PENDENTE';

            }

        );

    }

    /**
     * Localiza a operação sob o mesmo lock e impede alterar o alvo fixado da tentativa.
     */

    private static function &localizar(

        array &$estado,

        string $id,

        int $chamado

    ): array {

        // Localiza a operação pelo identificador enquanto o chamador mantém o lock do documento.

        foreach ($estado['operacoes'] as &$operacao) {

            // Seleciona a operação que corresponde exatamente ao identificador solicitado.

            if ($operacao['id'] === $id) {

                // Recusa usar a operação encontrada para finalizar ou atualizar outro chamado.

                if ($operacao['chamado_fixo'] !== $chamado) {

                    throw new DomainException('A operação pertence a outro chamado.');

                }

                return $operacao;

            }

        }

        throw new DomainException('Operação não encontrada.');

    }

    /**
     * Carrega o documento íntegro; corrupção bloqueia operações em vez de apagar evidências.
     */

    private function carregar(): array {

        $caminho = $this->diretorio . '/estado.json';

        // Inicializa chave privada e coleções somente quando ainda não existe documento salvo.

        if (!is_file($caminho)) {

            return [

                'chave' => bin2hex(random_bytes(32)),

                'operacoes' => [],

                'auditoria' => []

            ];

        }

        // Recusa links e arquivos acima do limite para preservar o histórico que precisa de conferência.

        if (is_link($caminho) || filesize($caminho) > 10 * 1024 * 1024) {

            throw new DomainException('O histórico precisa de conferência administrativa.');

        }

        try {

            $estado = json_decode(file_get_contents($caminho), true, 64, JSON_THROW_ON_ERROR);

            // Valida a estrutura mínima do documento antes de confiar em suas operações e auditoria.

            if (

                !is_array($estado)

                || !is_string($estado['chave'] ?? null)

                || !is_array($estado['operacoes'] ?? null)

                || !is_array($estado['auditoria'] ?? null)

            ) {

                throw new DomainException('Estrutura de histórico inválida.');

            }

            return $estado;

        } catch (\Throwable $erro) {

            // Preserva o arquivo inválido e impede reiniciar silenciosamente o controle de repetição.

            throw new DomainException('O histórico privado está inválido; não é seguro reiniciar o controle de repetição.');

        }

    }

    /**
     * Compara caminhos resolvidos para também recusar referências públicas por symlink.
     */

    private static function estaDentro(

        string $alvo,

        string $base

    ): bool {

        $normalizar =

        /**
         * Resolve o ancestral existente e recompõe o restante do caminho para comparar limites de
         * diretório.
         */

        static function (

            string $p

        ): string {

            $sufixo = [];

            // Sobe até um ancestral existente, guardando os componentes ainda não criados para resolver o caminho.

            while (!file_exists($p) && dirname($p) !== $p) {

                array_unshift($sufixo, basename($p));

                $p = dirname($p);

            }

            return strtolower(

                rtrim(str_replace('\\', '/', realpath($p) ?: $p), '/')

                    . ($sufixo === [] ? '' : '/'

                    . implode('/', $sufixo))

            );

        };

        $alvo = $normalizar($alvo);

        $base = $normalizar($base);

        return $alvo === $base || str_starts_with($alvo, $base . '/');

    }

}

