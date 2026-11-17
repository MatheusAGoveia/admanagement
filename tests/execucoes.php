<?php

namespace {
    /** Fornece a chave sintética da suíte, sem ler segredos da instalação GLPI. */
    class GLPIKey {
        /** Produz material fixo apenas para comparar assinaturas dos cenários isolados. */
        public function get(): string { return str_repeat('x', 32); }
    }

    /** Representa somente as operações nativas de persistência usadas pelo coordenador. */
    class CommonDBTM {
        public static array $registros = [];
        public array $fields = [];
        /** Registra campos e reproduz as duas restrições únicas que protegem o efeito. */
        public function add(array $dados, array $opcoes = [], bool $historico = false): int {
            // A identidade da necessidade e a posse do alvo são independentes.
            foreach (self::$registros as $registro) {
                // O banco recusa tanto duplicata da solicitação quanto outra solicitação sobre conta reservada.
                if ($registro['necessidade'] === $dados['necessidade'] || ($registro['alvo_reservado'] !== null && $registro['alvo_reservado'] === $dados['alvo_reservado'])) {
                    throw new \RuntimeException('Índice único do cenário.');
                }
            }
            $id = count(self::$registros) + 1;
            self::$registros[$id] = ['id' => $id] + $dados;
            return $id;
        }
        /** Consulta o registro que a fronteira nativa carregaria por identificador. */
        public function getFromDB(int $id): bool { $this->fields = self::$registros[$id] ?? []; return $this->fields !== []; }
        /** Resolve a necessidade sem presumir que reserva por alvo é a mesma solicitação. */
        public function getFromDBByCrit(array $criterios): bool {
            // O contrato dessa consulta usa somente igualdade de necessidade nesta suíte.
            foreach (self::$registros as $registro) {
                // Registros distintos não são escolhidos pela posição em memória.
                if ($registro['necessidade'] === $criterios['necessidade']) { $this->fields = $registro; return true; }
            }
            return false;
        }
    }

    /** Reproduz a atualização condicional da tabela própria sem conexão SQL real. */
    class BancoDaExecucao {
        public int $afetadas = 0;
        /** Atualiza apenas a posse e os estados admitidos pelo código de produção. */
        public function update(string $tabela, array $dados, array $criterios): bool {
            $registro = CommonDBTM::$registros[$criterios['id']] ?? [];
            $this->afetadas = 0;
            // Perda de posse não pode ser revertida pela gravação do estado final.
            if (($registro['posse'] ?? '') === $criterios['posse'] && in_array($registro['estado'], (array) $criterios['estado'], true)) {
                CommonDBTM::$registros[$criterios['id']] = array_replace($registro, $dados);
                $this->afetadas = 1;
            }
            return true;
        }
        /** Mantém o retorno nativo necessário para confirmar compare-and-swap. */
        public function getAffectedRows(): int { return $this->afetadas; }
    }
}

namespace GlpiPlugin\Admanagement {
    /** Separa configuração de teste dos diretórios reais cadastrados no laboratório. */
    class ConfiguracaoDoDiretorio {
        public static array $dominios = [1 => ['nome' => 'Dominio A', 'base_dn' => 'DC=exemplo,DC=a'], 2 => ['nome' => 'Dominio B', 'base_dn' => 'DC=exemplo,DC=b']];
        /** Retorna duas configurações sintéticas, sem credenciais ou endereços de rede. */
        public static function consultar(): array { return self::$dominios; }
        /** Resolve somente um dos domínios disponíveis no cenário. */
        public static function obter(int $id): array { return self::$dominios[$id]; }
    }
}

namespace {
    use GlpiPlugin\Admanagement\ConfiguracaoDoDiretorio;
    use GlpiPlugin\Admanagement\ExecucaoDoDiretorio;
    use GlpiPlugin\Admanagement\OperadorDoDiretorio;
    use GlpiPlugin\Admanagement\PoliticaDeRenovacao;

    /** Carrega classes próprias sem inicializar o kernel, autenticação ou LDAP reais. */
    spl_autoload_register(
        /** Resolve exclusivamente o namespace do produto para arquivos desta cópia. */
        static function (string $classe): void {
            $prefixo = 'GlpiPlugin\\Admanagement\\';
            // Nenhuma biblioteca externa ou modelo nativo é carregado como substituto desses dublês.
            if (str_starts_with($classe, $prefixo)) { require dirname(__DIR__) . '/src/' . substr($classe, strlen($prefixo)) . '.php'; }
        }
    );

    /** Mantém o estado controlado do diretório e conta escritas, inclusive efeitos parciais. */
    class DiretorioDaExecucao {
        public array $contas = [];
        public int $escritas = 0;
        public bool $falhar_validade = false;
        /** Expõe o catálogo de um domínio sem eliminar contas desativadas ou homônimas. */
        public function listarContasParaOperacao(int $dominio): array {
            return array_values(array_filter($this->contas,
                /** Cada ocorrência fica no domínio ao qual seu GUID pertence. */
                static function (array $conta) use ($dominio): bool { return $conta['dominio'] === $dominio; }
            ));
        }
        /** Confirma um esquema simples no cenário sem investigar o AD da organização. */
        public function conferirAtributoCpf(): void {}
        /** Releitura usa o mesmo GUID confirmado na prévia. */
        public function lerConta(string $guid, int $dominio): array { return $this->contas[$guid]; }
        /** Reproduz adição de CPF vazio para verificar confirmação antes da validade. */
        public function preencherCpf(array $conta, string $cpf): void { $this->escritas++; $this->contas[$conta['guid']]['cpf'] = $cpf; }
        /** Altera validade ou reproduz resposta perdida depois do efeito. */
        public function renovar(array $conta, string $validade): void {
            $this->escritas++;
            $this->contas[$conta['guid']]['validade'] = $validade;
            // O cenário de falha mantém o efeito para detectar repetição indevida posterior.
            if ($this->falhar_validade) { throw new \RuntimeException('Resposta perdida do cenário.'); }
        }
        /** Simula exclusivamente o indicador de desativação, preservando os demais dados. */
        public function bloquear(array $conta): void {
            $this->escritas++;
            $this->contas[$conta['guid']]['controle'] = (string) ((int) $conta['controle'] | 2);
            $this->contas[$conta['guid']]['ativa'] = false;
        }
    }

    $DB = new BancoDaExecucao();
    $diretorio = new DiretorioDaExecucao();
    $guid = str_repeat('a', 32);
    $agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $base = ['guid' => $guid, 'dominio' => 1, 'nome' => 'ANA LUIZA CAMPOS', 'nome_exibicao' => 'ANA LUIZA CAMPOS', 'cpf' => '00000000191', 'escritorio' => '', 'login' => 'ana.campos', 'ativa' => true, 'controle' => '66048', 'validade' => PoliticaDeRenovacao::paraFiletime($agora->modify('+5 days')), 'dn' => 'CN=Conta,DC=exemplo'];
    $diretorio->contas[$guid] = $base;
    $operador = new OperadorDoDiretorio(
        /** Substitui somente o adaptador de leitura, mantendo políticas e reservas do produto. */
        static function (array $dominio) use ($diretorio): object { return $diretorio; },
        /** Todas as escritas deste teste permanecem no objeto sintético desta suíte. */
        static function (array $dominio) use ($diretorio): object { return $diretorio; }
    );
    $pessoa = ['nome' => $base['nome'], 'cpf' => $base['cpf'], 'tipo' => 'renovacao', 'vinculo' => 'CONTRATO', 'login' => 'ana.campos'];
    $contexto = ['tecnico_id' => 7, 'perfil_id' => 4, 'entidade_id' => 0];
    $origem = ['lote_id' => 10, 'linha' => 'linha-validada'];
    $verificacoes = 0;
    /** Avalia um resultado de execução sem usar assert desativável no php.ini. */
    function conferirExecucao(bool $condicao, string $mensagem): void {
        global $verificacoes;
        // Uma condição falsa encerra a suíte e não é convertida em resultado incerto do produto.
        if (!$condicao) { throw new RuntimeException($mensagem); }
        $verificacoes++;
    }
    $previa = $operador->preparar($pessoa, $origem, $contexto);
    conferirExecucao($previa['estado'] === 'PRONTO' && $diretorio->escritas === 0, 'Preparar não escreve.');
    $resultado = $operador->executar($pessoa, $previa, $origem, $contexto);
    conferirExecucao($resultado['estado'] === 'SUCESSO_COMPROVADO' && $diretorio->escritas === 1, 'Confirma validade por releitura.');
    $reserva = array_values(CommonDBTM::$registros)[0];
    conferirExecucao($reserva['alvo_reservado'] === null && !str_contains($reserva['efeitos'], $pessoa['cpf']), 'Sucesso libera alvo e não grava CPF em claro.');
    $nova = $operador->preparar(array_replace($pessoa, ['tipo' => 'bloqueio']), ['lote_id' => 11], $contexto);
    $bloqueio = $operador->executar(array_replace($pessoa, ['tipo' => 'bloqueio']), $nova, ['lote_id' => 11], $contexto);
    conferirExecucao($bloqueio['estado'] === 'SUCESSO_COMPROVADO' && $diretorio->contas[$guid]['controle'] === '66050', 'Bloqueio preserva outros bits.');
    conferirExecucao($operador->preparar($pessoa, $origem, $contexto)['estado'] === 'SEM_ALTERACAO', 'Renovação não reativa conta.');

    CommonDBTM::$registros = [];
    $diretorio->contas[$guid] = array_replace($base, ['cpf' => '']);
    $diretorio->escritas = 0;
    $diretorio->falhar_validade = true;
    $previa = $operador->preparar($pessoa, $origem, $contexto);
    $parcial = $operador->executar($pessoa, $previa, $origem, $contexto);
    conferirExecucao($parcial['estado'] === 'PARCIAL' && $diretorio->escritas === 2, 'CPF confirmado e resposta perdida na validade é parcial.');
    conferirExecucao(array_values(CommonDBTM::$registros)[0]['alvo_reservado'] !== null, 'Incerteza mantém reserva da conta.');
    $repetida = ExecucaoDoDiretorio::reservar($previa, $origem, $contexto);
    conferirExecucao(!($repetida['nova'] ?? false) && $diretorio->escritas === 2, 'Mesma necessidade não recebe outra escrita.');
    $impediu = false;
    // Outra origem, inclusive chamado, também não pode disputar a conta com resultado incerto.
    try { ExecucaoDoDiretorio::reservar($previa, ['chamado_id' => 99], $contexto); }
    catch (DomainException) {
        // O cenário espera o impedimento antes de qualquer envio.
        $impediu = true;
    }
    conferirExecucao($impediu, 'Reserva compartilhada impede lote versus chamado.');
    $conferido = $operador->reconciliar($parcial['execucao_id'], $origem, $contexto);
    conferirExecucao($conferido['estado'] === 'SUCESSO_COMPROVADO' && $diretorio->escritas === 2, 'Reconciliar resposta perdida confirma efeito sem reenviar.');
    conferirExecucao(array_values(CommonDBTM::$registros)[0]['alvo_reservado'] === null, 'Releitura integral comprovada libera a reserva.');

    CommonDBTM::$registros = [];
    $diretorio->falhar_validade = false;
    $diretorio->contas[$guid] = $base;
    $diretorio->escritas = 0;
    $previa = $operador->preparar($pessoa, $origem, $contexto);
    $outro_guid = str_repeat('b', 32);
    $diretorio->contas[$outro_guid] = array_replace($base, ['guid' => $outro_guid, 'dominio' => 2, 'cpf' => '11144477735']);
    $impediu = false;
    // Homônimo surgido depois da prévia precisa ser identificado na releitura anterior ao envio.
    try { $operador->executar($pessoa, $previa, $origem, $contexto); }
    catch (DomainException) {
        // O cenário espera o impedimento antes de qualquer envio.
        $impediu = true;
    }
    conferirExecucao($impediu && $diretorio->escritas === 0 && CommonDBTM::$registros === [], 'Homônimo concorrente bloqueia antes de reservar ou escrever.');
    echo $verificacoes . " verificações de execução, confirmação e idempotência concluídas.\n";
}
