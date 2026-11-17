<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/** Modelo nativo para autoria e idempotência do lote, sem armazenar a planilha ou CPF em claro. */
final class Lote extends \CommonDBTM {

    public bool $auto_message_on_action = false;

    public static string $rightname = 'plugin_admanagement_lotes';

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

    /** Fixa o nome da tabela própria sem depender de pluralização de nomes portugueses. */
    public static function getTable($classname = null) {
        return 'glpi_plugin_admanagement_lotes';
    }

    /** Assina conteúdo com a chave privada já administrada pelo GLPI, sem criar segredo paralelo. */
    public static function assinar(mixed $dados): string {
        $chave = (new \GLPIKey())->get();
        // Falta de chave nativa impede gerar identificadores enumeráveis a partir de CPF.
        if (!is_string($chave) || $chave === '') {
            throw new DomainException('A chave de segurança do GLPI precisa estar disponível.');
        }
        return hash_hmac('sha256', json_encode($dados, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $chave);
    }

    /** Reutiliza o mesmo lote sem duplicar o processamento quando o arquivo é reenviado. */
    public static function importar(array $linhas, array $contexto, ?string $nova_necessidade = null): self {
        $itens = [];
        // A assinatura é semântica: reordenar o CSV ou converter para XLSX não cria outra necessidade.
        foreach ($linhas as $linha) {
            $itens[] = [$linha['cpf'], IdentidadeDoDiretorio::normalizarNome($linha['nome']), $linha['tipo'], $linha['vinculo']];
        }
        sort($itens);
        $assinatura = self::assinar(['lote-v1', $itens]);
        // Um atendimento futuro pode reutilizar a planilha, mas exige decisão explícita e desafio novo.
        if ($nova_necessidade !== null) {
            // Somente um identificador transitório gerado no servidor distingue a nova necessidade.
            if (!preg_match('/^[a-f0-9]{64}$/D', $nova_necessidade)) {
                throw new DomainException('A confirmação de novo atendimento é inválida.');
            }
            $assinatura = self::assinar(['lote-v1', $itens, $nova_necessidade]);
        }
        $lote = new self();
        // A consulta usa o modelo GLPI, mantendo o acesso ao registro separado da busca de duplicata.
        if (!$lote->getFromDBByCrit(['assinatura' => $assinatura])) {
            // Uma corrida de importação termina no índice único e é recuperada pela mesma assinatura.
            try {
                $id = $lote->add(['assinatura' => $assinatura, 'entities_id' => $contexto['entidade_id'], 'users_id' => $contexto['tecnico_id'], 'total' => count($linhas)], [], false);
                // Falha da gravação não deve deixar uma sessão executável sem autoria durável.
                if (!$id) {
                    throw new DomainException('Não foi possível registrar o lote. Atualize o plugin e tente novamente.');
                }
                $lote->getFromDB($id);
            } catch (\Throwable $erro) {
                // Apenas uma duplicata confirmada pode ser aproveitada; outros erros permanecem falhas.
                if (!$lote->getFromDBByCrit(['assinatura' => $assinatura])) {
                    throw new DomainException('Não foi possível registrar o lote no armazenamento GLPI.', 0, $erro);
                }
            }
        }
        $lote->conferirAutoria($contexto);
        return $lote;
    }

    /** Mantém lotes independentes restritos ao operador, entidade e direito atualmente ativos. */
    public function conferirAutoria(array $contexto): void {
        // A entidade é contexto de autoria; não é utilizada como filtro artificial de usuários AD.
        if (!AcessoGlpi::podeOperarLotes() || (int) $this->fields['users_id'] !== $contexto['tecnico_id'] || (int) $this->fields['entities_id'] !== $contexto['entidade_id']) {
            throw new DomainException('Este lote pertence a outro operador ou contexto autorizado.');
        }
    }

    /** Reutiliza o contrato nativo para negar leitura de registro de outro operador ou entidade. */
    public function canViewItem(): bool {
        return AcessoGlpi::podeOperarLotes() && (int) ($this->fields['users_id'] ?? 0) === (int) \Session::getLoginUserID()
            && (int) ($this->fields['entities_id'] ?? -1) === (int) \Session::getActiveEntity();
    }

    /** Lista somente metadados dos lotes do operador na entidade ativa, inclusive após encerrar a sessão. */
    public static function listarDoOperador(array $contexto): array {
        global $DB;
        return iterator_to_array($DB->request([
            'SELECT' => ['id', 'total', 'date_creation'], 'FROM' => self::getTable(),
            'WHERE' => ['users_id' => $contexto['tecnico_id'], 'entities_id' => $contexto['entidade_id']],
            'ORDER' => ['id DESC'], 'LIMIT' => 20,
        ]));
    }
}
