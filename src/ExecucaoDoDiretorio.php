<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/** Registra intenção e resultado pelo modelo GLPI e reserva a conta entre lotes e chamados. */
final class ExecucaoDoDiretorio extends \CommonDBTM {

    public bool $auto_message_on_action = false;

    public static string $rightname = 'plugin_admanagement';

    public bool $no_form_page = true;

    /** Modelo interno: a leitura pública ocorre somente nas entradas que conferem autoria e contexto. */
    public static function canView(): bool { return false; }

    /** Inclusão exige o fluxo próprio, que valida upload, necessidade e autorização nativa. */
    public static function canCreate(): bool { return false; }

    /** Alterações de estado não podem ser feitas por formulários CRUD genéricos. */
    public static function canUpdate(): bool { return false; }

    /** Resultados duráveis não podem ser removidos por operações genéricas de exclusão. */
    public static function canDelete(): bool { return false; }

    /** Uma purga pública não pode apagar a prova de idempotência. */
    public static function canPurge(): bool { return false; }

    /** Fixa a tabela própria dos efeitos, separada das tabelas internas do GLPI. */
    public static function getTable($classname = null) {
        return 'glpi_plugin_admanagement_execucoes';
    }

    /** Reserva a necessidade e o alvo por índices únicos antes de qualquer envio LDAP. */
    public static function reservar(array $previa, array $origem, array $contexto): array {
        global $DB;
        $conta = $previa['conta'];
        $alvo = Lote::assinar([$conta['dominio'], $conta['guid']]);
        $necessidade = Lote::assinar([$origem, $alvo, $previa['tipo']]);
        $modelo = new self();
        // Sucesso e resultado incerto nunca são uma autorização para repetir o efeito.
        if ($modelo->getFromDBByCrit(['necessidade' => $necessidade])) {
            return $modelo->fields;
        }
        $esperada = $conta;
        $cpf_intermediario = $conta;
        // O histórico guarda assinaturas dos estados, permitindo reconciliar sem CPF/nome em claro.
        if ($previa['tipo'] === 'renovacao') {
            $esperada['validade'] = $previa['validade']['nova'];
            // Apenas CPF vazio é preenchido; formato de documento já existente fica preservado.
            if (trim($conta['cpf']) === '') {
                $esperada['cpf'] = $previa['cpf_para_preencher'];
                $cpf_intermediario['cpf'] = $previa['cpf_para_preencher'];
            }
        } else {
            // Bloqueio preserva os demais indicadores, nomes, login e validade.
            $esperada['controle'] = (string) ((int) $conta['controle'] | 2);
            $esperada['ativa'] = false;
        }
        $efeitos = [
            'validade_anterior' => $conta['validade'], 'validade_nova' => $previa['validade']['nova'] ?? '',
            'controle_anterior' => $conta['controle'], 'cpf_esperado' => $previa['assinatura_cpf'],
            'preencher_cpf' => trim($conta['cpf']) === '' && $previa['tipo'] === 'renovacao',
            'configuracao' => $previa['configuracao'], 'estado_esperado' => Lote::assinar($esperada),
            'estado_anterior' => Lote::assinar($conta), 'estado_cpf' => Lote::assinar($cpf_intermediario),
        ];
        $dados = [
            'entities_id' => $contexto['entidade_id'], 'users_id' => $contexto['tecnico_id'],
            'lotes_id' => $origem['lote_id'] ?? null, 'tickets_id' => $origem['chamado_id'] ?? null,
            'formularios_id' => $origem['formulario_id'] ?? null, 'necessidade' => $necessidade,
            'alvo' => $alvo, 'alvo_reservado' => $alvo, 'dominio_id' => $conta['dominio'], 'guid' => $conta['guid'],
            'tipo' => $previa['tipo'], 'estado' => 'RESERVADA', 'etapa' => 'SEM_ENVIO',
            'posse' => bin2hex(random_bytes(32)), 'efeitos' => json_encode($efeitos, JSON_THROW_ON_ERROR),
        ];
        // Não mantém uma transação SQL aberta durante a comunicação externa.
        try {
            $id = $modelo->add($dados, [], false);
            // Nunca executar uma operação que não tenha reserva durável confirmada.
            if (!$id) {
                throw new DomainException('Reserva não confirmada. A conta pode estar em outra operação.');
            }
            $modelo->getFromDB($id);
        } catch (\Throwable $erro) {
            // Corrida da mesma necessidade retorna seu resultado; corrida de outro alvo impede prosseguir.
            if ($modelo->getFromDBByCrit(['necessidade' => $necessidade])) {
                $existente = $modelo->fields;
                $existente['reutilizada'] = true;
                return $existente;
            }
            throw new DomainException('A conta está reservada ou o banco não confirmou a operação. Nenhuma alteração foi enviada.', 0, $erro);
        }
        $reserva = $modelo->fields;
        $reserva['nova'] = true;
        return $reserva;
    }

    /** Atualiza somente o registro próprio cuja posse ainda pertence a esta tentativa. */
    public static function registrarEtapa(array $reserva, string $etapa, string $estado = 'EM_ANDAMENTO'): void {
        global $DB;
        $dados = ['etapa' => $etapa, 'estado' => $estado, 'date_mod' => date('Y-m-d H:i:s')];
        // Reserva incerta continua retida; só efeito comprovado ou nenhum envio libera a conta.
        if (in_array($estado, ['SUCESSO_COMPROVADO', 'SEM_ENVIO'], true)) {
            $dados['alvo_reservado'] = null;
        }
        // Compare-and-swap é em tabela própria; evita sobrescrever resultado de outra posse.
        if (!$DB->update(self::getTable(), $dados, ['id' => $reserva['id'], 'posse' => $reserva['posse'], 'estado' => ['RESERVADA', 'EM_ANDAMENTO']]) || $DB->getAffectedRows() !== 1) {
            throw new DomainException('A posse da operação não foi confirmada. Reconciliar antes de continuar.');
        }
    }

    /** Lista metadados do lote sem guardar nomes ou documentos pessoais no histórico durável. */
    public static function doLote(int $id): array {
        global $DB;
        return iterator_to_array($DB->request(['FROM' => self::getTable(), 'WHERE' => ['lotes_id' => $id], 'ORDER' => ['id DESC']]));
    }

    /** Registra reconciliação somente de um resultado incerto, sem repetir nenhuma escrita AD. */
    public static function reconciliarResultado(array $registro, string $estado): void {
        global $DB;
        // O chamador só pode registrar os estados demonstrados pela releitura assinada.
        if (!in_array($estado, ['SUCESSO_COMPROVADO', 'SEM_EFEITO_OBSERVADO', 'PARCIAL', 'INCERTO'], true)) {
            throw new DomainException('Resultado de reconciliação inválido.');
        }
        $dados = ['estado' => $estado, 'etapa' => 'RELEITURA_CONFERIDA', 'date_mod' => date('Y-m-d H:i:s')];
        // Só sucesso integral demonstrado libera automaticamente o alvo; ausência atual não prova ausência passada.
        if ($estado === 'SUCESSO_COMPROVADO') {
            $dados['alvo_reservado'] = null;
        }
        // Não sobrescreve uma tentativa ou posse alterada enquanto a leitura estava em andamento.
        if (!$DB->update(self::getTable(), $dados, ['id' => $registro['id'], 'posse' => $registro['posse'], 'estado' => $registro['estado']])) {
            throw new DomainException('A reconciliação não foi registrada. Nenhuma operação AD foi repetida.');
        }
    }
}
