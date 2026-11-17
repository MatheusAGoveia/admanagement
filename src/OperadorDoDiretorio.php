<?php

namespace GlpiPlugin\Admanagement;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/** Coordena prévia, revalidação, reserva e efeitos limitados, sem automatizar a decisão do técnico. */
final class OperadorDoDiretorio {

    private Closure $criar_leitor;
    private Closure $criar_escritor;
    private array $leitores = [];
    private array $esquemas = [];

    /** Permite adaptadores controlados nos testes; o produto utiliza LDAP real por padrão. */
    public function __construct(?callable $leitor = null, ?callable $escritor = null) {
        $this->criar_leitor = $leitor === null
            ? /** Abre somente a conexão de leitura configurada pelo administrador. */
                static function (array $dominio): object { return new LeitorLdap($dominio); }
            : Closure::fromCallable($leitor);
        $this->criar_escritor = $escritor === null
            ? /** A conexão de escrita só é aberta depois da confirmação e reserva durável. */
                static function (array $dominio): object { return new EscritorLdap($dominio); }
            : Closure::fromCallable($escritor);
    }

    /** Exige cobertura dos dois domínios; nenhuma falha se transforma em nome único ou conta ausente. */
    public function consultarCatalogo(): array {
        $dominios = ConfiguracaoDoDiretorio::consultar();
        // A regra confirmada exige os dois domínios, não apenas aquele que estiver acessível.
        if (count($dominios) !== 2) {
            throw new DomainException('Configure os dois domínios LDAP antes de preparar renovação ou bloqueio.');
        }
        $raizes = [];
        // Dois cadastros da mesma raiz não representam cobertura dos dois domínios.
        foreach ($dominios as $dominio) {
            $raizes[] = strtolower(preg_replace('/\s*,\s*/', ',', (string) ($dominio['base_dn'] ?? '')));
        }
        // A confirmação efetiva da raiz ocorre no leitor antes de sua varredura.
        if (count(array_unique($raizes)) !== 2 || in_array('', $raizes, true)) {
            throw new DomainException('Configure raízes distintas para os dois domínios antes da consulta.');
        }
        $contas = [];
        // Catálogo existe só nesta requisição; não vira inventário durável de pessoas.
        foreach ($dominios as $id => $dominio) {
            $leitor = ($this->criar_leitor)($dominio);
            $this->leitores[$id] = $leitor;
            $contas = array_merge($contas, $leitor->listarContasParaOperacao((int) $id));
        }
        return $contas;
    }

    /** Produz uma prévia de cinco minutos sem escrever no AD, inclusive no preenchimento de CPF. */
    public function preparar(array $pessoa, array $origem, array $contexto, ?array $catalogo = null, ?DateTimeImmutable $agora = null): array {
        $agora ??= new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $catalogo ??= $this->consultarCatalogo();
        // Somente os dois serviços novos de escrita podem utilizar este executor.
        if (!in_array($pessoa['tipo'] ?? '', ['renovacao', 'bloqueio'], true)) {
            throw new DomainException('O executor atende somente renovação ou bloqueio. Criação mantém sua revisão própria.');
        }
        $identidade = IdentidadeDoDiretorio::avaliar($pessoa, $catalogo);
        // Homônimos são impedidos antes de usar CPF ou login como discriminadores.
        if ($identidade['estado'] !== 'IDENTIFICADA') {
            return $identidade;
        }
        $conta = $identidade['conta'];
        $dominios = ConfiguracaoDoDiretorio::consultar();
        $dominio = $dominios[$conta['dominio']];
        // Desativada é um resultado explícito, sem renovação ou nova escrita de bloqueio.
        if (!$conta['ativa']) {
            return ['estado' => 'SEM_ALTERACAO', 'detalhe' => $pessoa['tipo'] === 'bloqueio' ? 'A conta já está desativada.' : 'Conta desativada não pode ser renovada.'];
        }
        $validade = ['estado' => 'PRONTO'];
        // Bloqueio não precisa nem interpreta prazo de contrato ou estágio.
        if ($pessoa['tipo'] === 'renovacao') {
            $validade = PoliticaDeRenovacao::propor($conta['validade'], (string) ($pessoa['vinculo'] ?? ''), $agora);
            // Não preencher CPF em conta que não pode receber renovação.
            if ($validade['estado'] !== 'PRONTO') {
                return $validade;
            }
            // O esquema precisa comprovar o atributo antes de oferecer seu preenchimento na confirmação.
            if (trim($conta['cpf']) === '' && !isset($this->esquemas[$conta['dominio']])) {
                $this->leitores[$conta['dominio']]->conferirAtributoCpf();
                $this->esquemas[$conta['dominio']] = true;
            }
        }
        return [
            'estado' => 'PRONTO', 'tipo' => $pessoa['tipo'], 'metodo' => $identidade['metodo'], 'conta' => $conta,
            'dominio_nome' => $dominio['nome'], 'validade' => $validade,
            'instante' => $agora->format('Y-m-d\TH:i:s.uP'), 'dia' => $agora->format('Y-m-d'), 'expira' => time() + 300,
            'desafio' => bin2hex(random_bytes(32)), 'pessoa' => Lote::assinar($pessoa), 'origem' => Lote::assinar($origem),
            'contexto' => RevisaoDaOperacao::assinaturaDoContexto($contexto), 'configuracao' => Lote::assinar($dominios),
            'assinatura_conta' => Lote::assinar($conta), 'assinatura_cpf' => Lote::assinar(IdentidadeDoDiretorio::normalizarCpf($pessoa['cpf'])),
            'cpf_para_preencher' => IdentidadeDoDiretorio::normalizarCpf($pessoa['cpf']),
        ];
    }

    /** Reconsulta antes de cada tentativa e registra o estado durável antes de enviar qualquer efeito. */
    public function executar(array $pessoa, array $previa, array $origem, array $contexto): array {
        // Não existe campo POST capaz de dispensar prévia, escopo ou validade da confirmação.
        if (($previa['estado'] ?? '') !== 'PRONTO' || ($previa['expira'] ?? 0) <= time()
            || ($previa['dia'] ?? '') !== (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d')
            || !hash_equals($previa['pessoa'], Lote::assinar($pessoa)) || !hash_equals($previa['origem'], Lote::assinar($origem))
            || !hash_equals($previa['contexto'], RevisaoDaOperacao::assinaturaDoContexto($contexto))
            || !hash_equals($previa['configuracao'], Lote::assinar(ConfiguracaoDoDiretorio::consultar()))) {
            throw new DomainException('A prévia mudou ou expirou. Consulte os dois domínios e revise novamente.');
        }
        $atual = $this->preparar($pessoa, $origem, $contexto, null, new DateTimeImmutable($previa['instante']));
        // Alteração de nome, CPF, login, estado, OU ou validade invalida o alvo revisado.
        if (($atual['estado'] ?? '') !== 'PRONTO' || !hash_equals($previa['assinatura_conta'], $atual['assinatura_conta']) || $previa['validade'] !== $atual['validade']) {
            throw new DomainException('A conta ou a elegibilidade mudou. Nenhuma alteração foi enviada. Faça nova consulta.');
        }
        $reserva = ExecucaoDoDiretorio::reservar($previa, $origem, $contexto);
        // Reenvio retorna o resultado existente, sem reenviar CPF, validade ou desativação.
        if (!($reserva['nova'] ?? false)) {
            return ['estado' => $reserva['estado'], 'execucao_id' => (int) $reserva['id'], 'detalhe' => 'Necessidade já registrada. Nenhuma operação foi repetida.'];
        }
        $enviado = false;
        $cpf_confirmado = false;
        $conta = $atual['conta'];
        $dominio = ConfiguracaoDoDiretorio::obter($conta['dominio']);
        // Nenhuma exceção após envio será tratada como prova de ausência de efeito.
        try {
            $escritor = ($this->criar_escritor)($dominio);
            $leitor = $this->leitores[$conta['dominio']];
            // Preencher CPF e renovar são efeitos distintos, confirmados e registrados separadamente.
            if ($pessoa['tipo'] === 'renovacao' && trim($conta['cpf']) === '') {
                ExecucaoDoDiretorio::registrarEtapa($reserva, 'CPF_PENDENTE');
                $enviado = true;
                $escritor->preencherCpf($conta, $pessoa['cpf']);
                $nova_conta = $leitor->lerConta($conta['guid'], $conta['dominio']);
                // Confere também os campos preservados antes de prosseguir com a validade.
                if (!hash_equals($previa['assinatura_cpf'], Lote::assinar($nova_conta['cpf']))
                    || array_replace($nova_conta, ['cpf' => $conta['cpf']]) !== $conta) {
                    throw new DomainException('CPF enviado, mas os dados posteriores não confirmaram o estado esperado.');
                }
                $cpf_confirmado = true;
                ExecucaoDoDiretorio::registrarEtapa($reserva, 'CPF_CONFIRMADO');
                $conta = $nova_conta;
            }
            $etapa = $pessoa['tipo'] === 'renovacao' ? 'VALIDADE_PENDENTE' : 'DESATIVACAO_PENDENTE';
            ExecucaoDoDiretorio::registrarEtapa($reserva, $etapa);
            $enviado = true;
            // Ambos os comandos preservam indicadores concorrentes por comparação atômica no Modify.
            if ($pessoa['tipo'] === 'renovacao') {
                $escritor->renovar($conta, $previa['validade']['nova']);
            } else {
                // Bloquear é somente desativar; não remove a conta nem seus grupos.
                $escritor->bloquear($conta);
            }
            $depois = $leitor->lerConta($conta['guid'], $conta['dominio']);
            $esperada = $conta;
            // A comparação final verifica o efeito e todos os campos que deveriam ser preservados.
            if ($pessoa['tipo'] === 'renovacao') {
                $esperada['validade'] = $previa['validade']['nova'];
            } else {
                // O indicador desativado é o único valor alterado na conta bloqueada.
                $esperada['controle'] = (string) ((int) $conta['controle'] | 2);
                $esperada['ativa'] = false;
            }
            // Retorno de sucesso LDAP isolado não comprova resultado da operação.
            if ($depois !== $esperada) {
                throw new DomainException('A releitura não confirmou todos os efeitos esperados.');
            }
            ExecucaoDoDiretorio::registrarEtapa($reserva, 'CONFIRMADA', 'SUCESSO_COMPROVADO');
            return ['estado' => 'SUCESSO_COMPROVADO', 'execucao_id' => (int) $reserva['id'], 'detalhe' => $pessoa['tipo'] === 'renovacao' ? 'Validade renovada e confirmada no AD.' : 'Conta desativada e confirmada no AD.'];
        } catch (\Throwable $erro) {
            // Não devolve mensagens de infraestrutura, atributos pessoais ou credenciais ao navegador.
            $estado = !$enviado ? 'SEM_ENVIO' : ($cpf_confirmado ? 'PARCIAL' : 'INCERTO');
            ExecucaoDoDiretorio::registrarEtapa($reserva, 'RECONCILIAR', $estado);
            return ['estado' => $estado, 'execucao_id' => (int) $reserva['id'], 'detalhe' => 'Operação não confirmada. Verifique o resultado registrado antes de qualquer nova tentativa.'];
        }
    }

    /** Releitura por GUID esclarece uma tentativa anterior sem reenviar CPF, validade ou bloqueio. */
    public function reconciliar(int $id, array $origem, array $contexto): array {
        $modelo = new ExecucaoDoDiretorio();
        // Histórico não concede acesso a registros de outro operador, entidade, chamado ou lote.
        if (!$modelo->getFromDB($id) || (int) $modelo->fields['users_id'] !== $contexto['tecnico_id']
            || (int) $modelo->fields['entities_id'] !== $contexto['entidade_id']
            || (int) ($modelo->fields['lotes_id'] ?? 0) !== (int) ($origem['lote_id'] ?? 0)
            || (int) ($modelo->fields['tickets_id'] ?? 0) !== (int) ($origem['chamado_id'] ?? 0)) {
            throw new DomainException('Esta tentativa não pertence ao contexto autorizado.');
        }
        $registro = $modelo->fields;
        // Não concorre com uma tentativa ainda ativa; uma consulta não deve roubar sua posse.
        if (in_array($registro['estado'], ['RESERVADA', 'EM_ANDAMENTO'], true)
            && time() - (int) strtotime($registro['date_mod'] ?? $registro['date_creation'] ?? 'now') < 120) {
            throw new DomainException('A tentativa ainda pode estar em andamento. Aguarde antes de conferir o resultado.');
        }
        // Resultados integrais já confirmados não precisam de nova leitura nem nova alteração.
        if ($registro['estado'] === 'SUCESSO_COMPROVADO') {
            return ['estado' => $registro['estado'], 'execucao_id' => $id, 'detalhe' => 'Sucesso já comprovado. Nenhuma alteração repetida.'];
        }
        $efeitos = json_decode($registro['efeitos'], true, 16, JSON_THROW_ON_ERROR);
        // Não procurar o mesmo GUID em um destino alterado silenciosamente na configuração.
        if (($efeitos['configuracao'] ?? '') !== Lote::assinar(ConfiguracaoDoDiretorio::consultar())) {
            throw new DomainException('A configuração dos domínios mudou. Reconciliar exige o destino original.');
        }
        $dominio = ConfiguracaoDoDiretorio::obter((int) $registro['dominio_id']);
        $conta = ($this->criar_leitor)($dominio)->lerConta($registro['guid'], (int) $registro['dominio_id']);
        $assinatura = Lote::assinar($conta);
        $estado = match ($assinatura) {
            $efeitos['estado_esperado'] => 'SUCESSO_COMPROVADO',
            $efeitos['estado_anterior'] => 'SEM_EFEITO_OBSERVADO',
            $efeitos['estado_cpf'] => 'PARCIAL',
            // Um terceiro estado pode ser efeito concorrente; não deve ser revertido por suposição.
            default => 'INCERTO',
        };
        ExecucaoDoDiretorio::reconciliarResultado($registro, $estado);
        return ['estado' => $estado, 'execucao_id' => $id, 'detalhe' => 'Resultado conferido por releitura. Nenhuma escrita AD foi reenviada.'];
    }
}
