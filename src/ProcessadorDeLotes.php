<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/** Mantém o trabalho do lote na sessão e o resultado durável nos modelos nativos. */
final class ProcessadorDeLotes {

    private const CHAVE = 'admanagement_lote_em_revisao';

    /** Descarta somente a entrada ativa antes de tentar importar outro arquivo. */
    public static function invalidar(): void {
        unset($_SESSION[self::CHAVE]);
    }

    /** Registra a autoria sem enviar nenhuma alteração LDAP. */
    public static function importar(array $linhas, array $contexto, ?string $nova_necessidade = null): void {
        $lote = Lote::importar($linhas, $contexto, $nova_necessidade);
        $_SESSION[self::CHAVE] = [
            'id' => (int) $lote->getID(), 'tecnico_id' => $contexto['tecnico_id'], 'entidade_id' => $contexto['entidade_id'],
            'contexto' => RevisaoDaOperacao::assinaturaDoContexto($contexto), 'revisao' => 1, 'linhas' => $linhas,
        ];
    }

    /** Recusa usar trabalho de outro operador ou entidade e invalida confirmações após mudança de perfil. */
    public static function obter(array $contexto): ?array {
        $estado = $_SESSION[self::CHAVE] ?? null;
        // A ausência de lote ativo é normal na primeira abertura da página.
        if ($estado === null) {
            return null;
        }
        // Troca de contexto não deve revelar nomes ou documentos da sessão anterior.
        if ($estado['tecnico_id'] !== $contexto['tecnico_id'] || $estado['entidade_id'] !== $contexto['entidade_id']) {
            return null;
        }
        $modelo = new Lote();
        // Excluir ou alterar a autoria no banco também invalida o trabalho em sessão.
        if (!$modelo->getFromDB($estado['id'])) {
            throw new DomainException('O lote não está disponível no armazenamento GLPI.');
        }
        $modelo->conferirAutoria($contexto);
        $assinatura = RevisaoDaOperacao::assinaturaDoContexto($contexto);
        // Perfil, direitos, entidades e visibilidade fazem parte da vigência da confirmação.
        if ($estado['contexto'] !== $assinatura) {
            unset($_SESSION[self::CHAVE]['confirmacao']);
            // Uma revisão antiga pode ser lida, mas não reaproveita consultas como autorização de escrita.
            foreach ($_SESSION[self::CHAVE]['linhas'] as &$linha) {
                unset($linha['previa']);
            }
            unset($linha);
            $_SESSION[self::CHAVE]['contexto'] = $assinatura;
            $_SESSION[self::CHAVE]['revisao']++;
        }
        return $_SESSION[self::CHAVE];
    }

    /** Usa um catálogo transitório comum para avaliar as linhas sem repetir a varredura por pessoa. */
    public static function consultar(array $contexto): void {
        $estado = self::obter($contexto);
        // Sem arquivo não há pessoa ou objetivo autorizados para consulta.
        if ($estado === null) {
            throw new DomainException('Importe uma planilha antes de consultar os domínios.');
        }
        unset($_SESSION[self::CHAVE]['confirmacao']);
        // Evidências anteriores deixam de valer mesmo se a nova conexão falhar.
        foreach ($_SESSION[self::CHAVE]['linhas'] as &$linha) {
            unset($linha['previa']);
        }
        unset($linha);
        $operador = new OperadorDoDiretorio();
        $catalogo = $operador->consultarCatalogo();
        // Dados pessoais do catálogo não são copiados para arquivo ou histórico.
        foreach ($_SESSION[self::CHAVE]['linhas'] as &$linha) {
            // Erro estrutural precisa de correção na planilha antes da consulta dessa linha.
            if ($linha['erros'] !== []) {
                continue;
            }
            // Falha específica de uma pessoa é apresentada sem liberar a operação afetada.
            try {
                $linha['previa'] = $operador->preparar(self::pessoa($linha), self::origem($estado['id'], $linha), $contexto, $catalogo);
            } catch (DomainException $erro) {
                // Mensagens de negócio são controladas; não incluem resposta bruta LDAP.
                $linha['previa'] = ['estado' => 'IMPEDIDA', 'detalhe' => $erro->getMessage()];
            }
        }
        unset($linha, $catalogo);
        $_SESSION[self::CHAVE]['revisao']++;
    }

    /** Fixa somente as linhas prontas selecionadas e não envia operação ao AD. */
    public static function confirmar(array $entrada, array $contexto): void {
        $estado = self::obter($contexto);
        $selecionadas = $entrada['linhas'] ?? [];
        // A confirmação está vinculada à revisão atual e exige aceite expresso do técnico.
        if ($estado === null || ($entrada['confirmar_lote'] ?? '') !== '1' || (string) ($entrada['revisao'] ?? '') !== (string) $estado['revisao']
            || !is_array($selecionadas) || !array_is_list($selecionadas) || $selecionadas === []) {
            throw new DomainException('Selecione linhas prontas e confirme os efeitos da revisão atual.');
        }
        $fila = [];
        // Cada índice é validado contra a sessão; os valores da pessoa não são recebidos no POST.
        foreach ($selecionadas as $indice) {
            // Índice decimal estrito impede estruturas, números negativos e conversão parcial.
            if (!is_string($indice) || !preg_match('/^(0|[1-9][0-9]*)$/D', $indice) || !isset($estado['linhas'][(int) $indice])) {
                throw new DomainException('Linha fora do lote em revisão.');
            }
            $linha = $estado['linhas'][(int) $indice];
            // Homônimos, falhas de CPF e evidências expiradas não têm opção de liberação manual.
            if ($linha['erros'] !== [] || ($linha['previa']['estado'] ?? '') !== 'PRONTO' || ($linha['previa']['expira'] ?? 0) <= time() || isset($linha['resultado'])) {
                throw new DomainException('Uma das linhas selecionadas está impedida ou já possui resultado. Consulte novamente.');
            }
            $fila[] = (int) $indice;
        }
        $_SESSION[self::CHAVE]['confirmacao'] = [
            'fila' => array_values(array_unique($fila)), 'revisao' => $estado['revisao'], 'expira' => time() + 300,
            'contexto' => $estado['contexto'], 'configuracao' => Lote::assinar(ConfiguracaoDoDiretorio::consultar()), 'desafio' => bin2hex(random_bytes(32)),
        ];
    }

    /** Processa uma única linha por POST, com revalidação completa e reserva compartilhada com chamados. */
    public static function executarProxima(array $entrada, array $contexto): void {
        $estado = self::obter($contexto);
        $confirmacao = $estado['confirmacao'] ?? [];
        // GET, desafio reaproveitado e troca de configuração não autorizam continuar um lote.
        if ($estado === null || ($confirmacao['fila'] ?? []) === [] || ($confirmacao['expira'] ?? 0) <= time()
            || !is_string($entrada['desafio'] ?? null) || !hash_equals($confirmacao['desafio'], $entrada['desafio'])
            || $confirmacao['revisao'] !== $estado['revisao'] || $confirmacao['contexto'] !== RevisaoDaOperacao::assinaturaDoContexto($contexto)
            || $confirmacao['configuracao'] !== Lote::assinar(ConfiguracaoDoDiretorio::consultar())) {
            throw new DomainException('A confirmação do lote mudou ou expirou. Revise e confirme novamente.');
        }
        $indice = array_shift($_SESSION[self::CHAVE]['confirmacao']['fila']);
        $_SESSION[self::CHAVE]['confirmacao']['desafio'] = bin2hex(random_bytes(32));
        $linha = $estado['linhas'][$indice];
        // A rotação do desafio e o índice único durável impedem repetir efeitos por reenvio de POST.
        try {
            $resultado = (new OperadorDoDiretorio())->executar(self::pessoa($linha), $linha['previa'], self::origem($estado['id'], $linha), $contexto);
        } catch (DomainException $erro) {
            // Falha de preparação não libera outra tentativa automática dessa linha.
            $resultado = ['estado' => 'IMPEDIDA', 'detalhe' => $erro->getMessage()];
        }
        $_SESSION[self::CHAVE]['linhas'][$indice]['resultado'] = $resultado;
        unset($_SESSION[self::CHAVE]['linhas'][$indice]['previa']);
    }

    /** Consulta somente o efeito já enviado; não reabre a fila ou reenviar a operação. */
    public static function reconciliar(array $entrada, array $contexto): void {
        $estado = self::obter($contexto);
        $id = EntradaDaFerramenta::lerIdentificador($entrada['execucao_id'] ?? null);
        // A autoria durável é conferida mesmo quando a sessão original da planilha já terminou.
        $lote_id = EntradaDaFerramenta::lerIdentificador($entrada['lote_id'] ?? null);
        $lote = new Lote();
        // O ID recebido não pode revelar metadados de lote alheio.
        if (!$lote->getFromDB($lote_id)) {
            throw new DomainException('Lote não encontrado.');
        }
        $lote->conferirAutoria($contexto);
        $resultado = (new OperadorDoDiretorio())->reconciliar($id, ['lote_id' => $lote_id], $contexto);
        // Quando a planilha ainda está na sessão, a linha também recebe o resultado da releitura.
        if ($estado !== null && $estado['id'] === $lote_id) {
            // O vínculo usa o ID da execução persistida, sem associar registros por nome ou CPF.
            foreach ($_SESSION[self::CHAVE]['linhas'] as &$linha) {
                // Não substitui o resultado de outras tentativas do lote.
                if (($linha['resultado']['execucao_id'] ?? 0) === $id) {
                    $linha['resultado'] = $resultado;
                }
            }
            unset($linha);
        }
    }

    /** Forma somente os dados necessários à identificação e à regra de validade. */
    public static function pessoa(array $linha): array {
        return ['nome' => $linha['nome'], 'cpf' => $linha['cpf'], 'tipo' => $linha['tipo'], 'vinculo' => $linha['vinculo'], 'login' => ''];
    }

    /** Identifica a linha pelo conteúdo normalizado, sem depender da posição da planilha. */
    private static function origem(int $id, array $linha): array {
        $pessoa = self::pessoa($linha);
        $pessoa['nome'] = IdentidadeDoDiretorio::normalizarNome($pessoa['nome']);
        return ['lote_id' => $id, 'linha' => Lote::assinar($pessoa)];
    }

    /** Exporta o que está em revisão com CPF mascarado e neutralização de fórmulas CSV. */
    public static function exportar(array $estado): string {
        $arquivo = fopen('php://temp', 'w+');
        fputcsv($arquivo, ['Linha', 'Nome', 'CPF mascarado', 'Objetivo', 'Vinculo', 'Situacao'], ';', '"', '');
        // A exportação não consulta nem altera contas e não contém CPF completo.
        foreach ($estado['linhas'] as $linha) {
            $valores = [(string) $linha['linha'], $linha['nome'], '***.***.***-' . substr($linha['cpf'], -2), $linha['tipo'], $linha['vinculo'], $linha['resultado']['estado'] ?? $linha['previa']['estado'] ?? implode(' ', $linha['erros'])];
            // Aspas CSV não impedem fórmula em planilhas; prefixar dados ativos é necessário.
            foreach ($valores as &$valor) {
                // Considera também espaços antes do marcador, para impedir interpretação pelo Excel.
                if (preg_match('/^[\s]*[=+@-]/u', $valor)) {
                    $valor = "'" . $valor;
                }
            }
            unset($valor);
            fputcsv($arquivo, $valores, ';', '"', '');
        }
        rewind($arquivo);
        $csv = stream_get_contents($arquivo);
        fclose($arquivo);
        return "\xEF\xBB\xBF" . $csv;
    }
}
