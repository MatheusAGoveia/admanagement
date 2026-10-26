<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Guarda o trabalho na sessão, separado por técnico, entidade e chamado.
 */

final class RepositorioDeRascunhos {

    // Nome reservado para os rascunhos dentro da sessão do GLPI.

    private const CHAVE = 'admanagement_rascunhos';

    // A última revisão permanece mesmo após o descarte, para recusar abas antigas.

    private const CHAVE_REVISOES = 'admanagement_revisoes';

    // Quantidade máxima de rascunhos por técnico e entidade.

    private const LIMITE = 20;

    /**
     * Obtém o trabalho somente do técnico e da entidade que estão autorizados nesta requisição.
     */

    public static function consultar(

        int $chamado_id,

        array $contexto

    ): ?array {

        $tecnico_id = $contexto['tecnico_id'];

        $entidade_id = $contexto['entidade_id'];

        // Ausência de um rascunho nesse caminho devolve null, sem criar trabalho novo.

        if (!isset($_SESSION[self::CHAVE][$tecnico_id][$entidade_id][$chamado_id])) {

            return null;

        }

        return $_SESSION[self::CHAVE][$tecnico_id][$entidade_id][$chamado_id];

    }

    /**
     * Guarda a primeira importação sem apagar trabalho anterior; reimportação exige confirmação
     * externa.
     */

    public static function guardarInicial(

        array $rascunho,

        bool $substituir = false,

        ?int $revisao_esperada = null

    ): array {

        // Usa os dados de autoria do rascunho para localizar o trabalho já existente.

        $contexto = [

            'tecnico_id' => $rascunho['tecnico_id'],

            'entidade_id' => $rascunho['entidade_id'],

        ];

        $atual = self::consultar($rascunho['chamado_id'], $contexto);

        // Importar novamente sem pedir substituição continua o trabalho anterior.

        if ($atual !== null && !$substituir) {

            return $atual;

        }

        // Uma substituição só prossegue se a aba estiver na revisão esperada.

        if ($atual !== null) {

            self::conferirRevisao($atual, $revisao_esperada);

            $rascunho['revisao'] = $atual['revisao'] + 1;

        }

        $tecnico_id = $contexto['tecnico_id'];

        $entidade_id = $contexto['entidade_id'];

        $rascunhos = [];

        // Recupera os rascunhos existentes do mesmo técnico e entidade antes de conferir o limite.

        if (isset($_SESSION[self::CHAVE][$tecnico_id][$entidade_id])) {

            $rascunhos = $_SESSION[self::CHAVE][$tecnico_id][$entidade_id];

        }

        // Um trabalho reimportado após descarte não pode reutilizar a revisão de uma aba antiga.

        if ($atual === null) {

            $ultima_revisao = 0;

            // Reaproveita o contador preservado após descarte para não aceitar uma aba com revisão antiga.

            if (

                isset(

                    $_SESSION[self::CHAVE_REVISOES][$tecnico_id][$entidade_id][$rascunho['chamado_id']]

                )

            ) {

                $ultima_revisao = $_SESSION[self::CHAVE_REVISOES][$tecnico_id][$entidade_id][$rascunho['chamado_id']];

            }

            $rascunho['revisao'] = $ultima_revisao + 1;

        }

        // O limite impede novas importações, mas permite atualizar trabalhos existentes.

        if ($atual === null && count($rascunhos) >= self::LIMITE) {

            throw new DomainException('Há 20 rascunhos nesta entidade. Descarte um trabalho antes de importar outro.');

        }

        self::gravar($rascunho);

        return $rascunho;

    }

    /**
     * Salva uma revisão somente se outra aba não tiver atualizado o mesmo rascunho.
     */

    public static function salvar(

        array $rascunho,

        int $revisao_esperada

    ): void {

        // Confere a versão atual antes de substituir os dados guardados na sessão.

        $contexto = [

            'tecnico_id' => $rascunho['tecnico_id'],

            'entidade_id' => $rascunho['entidade_id'],

        ];

        $atual = self::consultar($rascunho['chamado_id'], $contexto);

        self::conferirRevisao($atual, $revisao_esperada);

        self::gravar($rascunho);

    }

    /**
     * Descarta somente o trabalho local selecionado, sem alterar o formulário ou chamado.
     */

    public static function descartar(

        int $chamado_id,

        array $contexto,

        int $revisao_esperada

    ): void {

        $atual = self::consultar($chamado_id, $contexto);

        self::conferirRevisao($atual, $revisao_esperada);

        $tecnico_id = $contexto['tecnico_id'];

        $entidade_id = $contexto['entidade_id'];

        // Preserva também a revisão de rascunhos criados antes deste controle de descarte.

        self::registrarUltimaRevisao($atual);

        // unset remove apenas o rascunho selecionado, sem apagar os outros trabalhos.

        unset($_SESSION[self::CHAVE][$tecnico_id][$entidade_id][$chamado_id]);

    }

    /**
     * Interrompe gravação de uma aba antiga em vez de sobrescrever correções silenciosamente.
     */

    private static function conferirRevisao(

        ?array $atual,

        ?int $revisao_esperada

    ): void {

        // Um rascunho ausente ou já alterado não pode ser sobrescrito por uma aba antiga.

        if ($atual === null || $atual['revisao'] !== $revisao_esperada) {

            throw new DomainException('O rascunho mudou em outra aba. Recarregue a página antes de continuar.');

        }

    }

    /**
     * Centraliza a chave de sessão sem criar arquivos ou tabelas próprias para os rascunhos.
     */

    private static function gravar(

        array $rascunho

    ): void {

        // Os três níveis impedem misturar técnicos, entidades ou chamados diferentes.

        $tecnico_id = $rascunho['tecnico_id'];

        $entidade_id = $rascunho['entidade_id'];

        $chamado_id = $rascunho['chamado_id'];

        $_SESSION[self::CHAVE][$tecnico_id][$entidade_id][$chamado_id] = $rascunho;

        self::registrarUltimaRevisao($rascunho);

    }

    /**
     * Mantém somente o número da última revisão por técnico, entidade e chamado na sessão.
     */

    private static function registrarUltimaRevisao(

        array $rascunho

    ): void {

        // O contador não armazena os dados pessoais do trabalho descartado.

        $tecnico_id = $rascunho['tecnico_id'];

        $entidade_id = $rascunho['entidade_id'];

        $chamado_id = $rascunho['chamado_id'];

        $_SESSION[self::CHAVE_REVISOES][$tecnico_id][$entidade_id][$chamado_id] = $rascunho['revisao'];

    }

    /**
     * Reverte somente o rascunho se a auditoria durável da mesma ação não puder ser gravada.
     */

    public static function comAuditoria(

        callable $acao

    ): mixed {

        $anteriores = [];

        // Captura apenas as duas chaves próprias que precisam ser revertidas se a auditoria falhar.

        foreach ([

            self::CHAVE,

            self::CHAVE_REVISOES

        ] as $chave) {

            $anteriores[$chave] = $_SESSION[$chave] ?? null;

        }

        try {

            return $acao();

        } catch (\Throwable $erro) {

            // Reverte o trabalho local quando a mesma ação não consegue concluir sua auditoria durável.

            // Restaura somente as chaves do plugin, preservando tokens e direitos atuais da sessão GLPI.

            foreach ($anteriores as $chave => $anterior) {

                // Remove a chave que não existia antes da ação malsucedida.

                if ($anterior === null) {

                    unset($_SESSION[$chave]);

                } else {

                    // Restaura o conteúdo anterior da chave que já existia na sessão.

                    $_SESSION[$chave] = $anterior;

                }

            }

            throw $erro;

        }

    }

    /**
     * Remove definitivamente confirmação obsoleta, preservando os dados corrigidos e o contador.
     */

    public static function invalidarConfirmacao(

        int $chamado,

        array $contexto

    ): void {

        $atual = self::consultar($chamado, $contexto);

        // Invalida somente um trabalho existente que ainda contenha resumo ou confirmação aproveitável.

        if ($atual !== null && (isset($atual['resumo']) || isset($atual['confirmacao']))) {

            unset($atual['resumo'], $atual['confirmacao']);

            $atual['revisao']++;

            self::gravar($atual);

        }

    }

    /**
     * Uma recusa observada não pode recuperar validade ao restaurar posteriormente o acesso.
     */

    public static function invalidarTodosDaSessao(): void {

        // Percorre os técnicos com trabalhos guardados na própria sessão para invalidar seus resumos.

        foreach ($_SESSION[self::CHAVE] ?? [] as $tecnico => $entidades) {

            // Examina as entidades dos trabalhos da sessão sem consultar recursos externos negados.

            foreach ($entidades as $entidade => $rascunhos) {

                // Inutiliza cada resumo existente e preserva seus dados de trabalho e contador de revisão.

                foreach ($rascunhos as $chamado => $rascunho) {

                    self::invalidarConfirmacao((int) $chamado, [

                        'tecnico_id' => $tecnico,

                        'entidade_id' => $entidade

                    ]);

                }

            }

        }

    }

    /**
     * Invalida também a mudança permitida de perfil/entidade observada em outra página da
     * ferramenta.
     */

    public static function conferirContexto(

        array $contexto

    ): void {

        // Percorre os trabalhos da sessão para detectar contexto observado diferente do revisado.

        foreach ($_SESSION[self::CHAVE] ?? [] as $tecnico => $entidades) {

            // Confere os rascunhos de cada entidade guardada nesta sessão.

            foreach ($entidades as $entidade => $rascunhos) {

                // Compara a autorização revisada de cada trabalho com o contexto nativo atual.

                foreach ($rascunhos as $chamado => $rascunho) {

                    // Descarta definitivamente o resumo que pertence a outro perfil, entidade ou conjunto de grupos observado.

                    if (

                        isset($rascunho['resumo'])

                        && ($rascunho['resumo']['contexto'] ?? '') !== RevisaoDaOperacao::assinaturaDoContexto($contexto)

                    ) {

                        self::invalidarConfirmacao((int) $chamado, [

                            'tecnico_id' => $tecnico,

                            'entidade_id' => $entidade

                        ]);

                    }

                }

            }

        }

    }

    /**
     * Lista somente os trabalhos da sessão do técnico na entidade atual.
     */

    public static function listar(

        array $contexto

    ): array {

        $entidades = $contexto['visibilidade']['entidades_ids'] ?? [$contexto['entidade_id']];

        $rascunhos = [];

        // Reúne somente trabalhos do usuário conectado nas entidades efetivamente autorizadas.

        foreach ($entidades as $entidade) {

            $rascunhos = array_merge($rascunhos, array_values($_SESSION[self::CHAVE][$contexto['tecnico_id']][$entidade] ?? []));

        }

        return $rascunhos;

    }

}

