<?php
namespace GlpiPlugin\Admanagement;

use DomainException;

/** Associa o resumo ao conteúdo, à origem e à autorização atuais, sem executar efeitos no AD. */
final class RevisaoDaOperacao {
    /** Captura os efeitos declarados para revisão, com conta atendida e login pretendido separados. */
    public static function preparar(array $rascunho, array $contexto, ?int $agora = null): array {
        $dados = [];
        foreach (['chamado_id', 'formulario_id', 'tecnico_id', 'tecnico_original', 'entidade_id', 'tipo', 'nome_civil', 'nome_social', 'usar_nome_social', 'matricula', 'conta_atendida', 'login_pretendido', 'dominio', 'ou', 'grupos', 'scripts', 'pastas', 'validade_proposta'] as $campo) {
            $dados[$campo] = $rascunho[$campo] ?? '';
        }
        $rascunho['resumo'] = [
            'dados' => $dados,
            'conteudo' => hash('sha256', json_encode($dados, JSON_THROW_ON_ERROR)),
            'revisao' => $rascunho['revisao'],
            'origem' => $rascunho['assinatura_origem'],
            'contexto' => self::assinaturaDoContexto($contexto),
            'vigencia' => self::assinaturaDaVigencia($rascunho, $agora ?? time()),
            'expira' => ($agora ?? time()) + 600,
            'desafio' => bin2hex(random_bytes(24)),
        ];
        unset($rascunho['confirmacao']);
        return $rascunho;
    }

    /** Exige confirmação explícita da mesma revisão e da mesma origem, após revalidação pelo controlador. */
    public static function confirmar(array $rascunho, array $base_atual, array $contexto, array $entrada, ?int $agora = null): array {
        $resumo = $rascunho['resumo'] ?? [];
        if (($entrada['confirmar_revisao'] ?? '') !== '1' || !is_string($entrada['desafio'] ?? null) || !isset($resumo['desafio']) || !hash_equals($resumo['desafio'], $entrada['desafio'])) {
            throw new DomainException('Confirme explicitamente o resumo atual.');
        }
        if (($resumo['revisao'] ?? 0) !== $rascunho['revisao'] || ($resumo['expira'] ?? 0) <= ($agora ?? time()) ||
            ($resumo['origem'] ?? '') !== $base_atual['assinatura_origem'] ||
            ($resumo['contexto'] ?? '') !== self::assinaturaDoContexto($contexto) ||
            ($resumo['vigencia'] ?? '') !== self::assinaturaDaVigencia($rascunho, $agora ?? time())) {
            throw new DomainException('A revisão perdeu validade. Gere um novo resumo após conferir os dados e o acesso.');
        }
        $atual = self::preparar($rascunho, $contexto, $agora);
        if (!hash_equals($resumo['conteudo'], $atual['resumo']['conteudo'])) {
            throw new DomainException('O conteúdo mudou após a preparação do resumo.');
        }
        $rascunho['confirmacao'] = ['revisao' => $rascunho['revisao'], 'conteudo' => $resumo['conteudo'], 'ator_id' => $contexto['tecnico_id'], 'instante' => gmdate(DATE_ATOM)];
        // Consome o desafio: repetir o POST não confirma uma segunda vez.
        unset($rascunho['resumo']['desafio']);
        return $rascunho;
    }

    /** Detecta mudanças observadas inclusive no GET; o controlador inutiliza o desafio na sessão. */
    public static function continuaValida(array $rascunho, array $base, array $contexto): bool {
        $resumo = $rascunho['resumo'] ?? null;
        if ($resumo === null) { return true; }
        try {
            return ($resumo['revisao'] ?? 0) === $rascunho['revisao'] && ($resumo['expira'] ?? 0) > time() &&
                ($resumo['origem'] ?? '') === $base['assinatura_origem'] &&
                ($resumo['contexto'] ?? '') === self::assinaturaDoContexto($contexto) &&
                ($resumo['vigencia'] ?? '') === self::assinaturaDaVigencia($rascunho, time());
        } catch (\DomainException | \InvalidArgumentException $erro) { return false; }
    }

    /** Valida o catálogo real, configuração e prazo de qualquer evidência antes de chamar algo de revisado. */
    private static function assinaturaDaVigencia(array $rascunho, int $agora): string {
        $dominios = ConfiguracaoDoDiretorio::consultar();
        if (($rascunho['ou'] ?? '') !== '') {
            $id = EntradaDaFerramenta::lerIdentificador($rascunho['dominio']);
            $dominio = ConfiguracaoDoDiretorio::obter($id);
            $catalogo = $rascunho['catalogo_ous'] ?? [];
            if (($catalogo['expira'] ?? 0) <= $agora || ($catalogo['assinatura'] ?? '') !== hash('sha256', json_encode($dominio, JSON_THROW_ON_ERROR)) ||
                !in_array($rascunho['ou'], array_column($catalogo['itens'] ?? [], 'dn'), true) || !NomeDistinto::pertenceAoDominio($rascunho['ou'], $dominio['base_dn'])) {
                throw new DomainException('O destino perdeu validade. Consulte a árvore real e revise novamente.');
            }
        }
        foreach (array_merge(array_values($rascunho['consultas_logins'] ?? []), isset($rascunho['duplicidades']) ? [$rascunho['duplicidades']] : []) as $consulta) {
            if (($consulta['expira'] ?? 0) <= $agora) { throw new DomainException('As consultas ao diretório expiraram. Consulte e revise novamente.'); }
        }
        return hash('sha256', json_encode([$dominios, ConfiguracaoOperacional::consultar('acesso_operacional'), ConfiguracaoDaFila::consultar(), $rascunho['catalogo_ous'] ?? [], $rascunho['consultas_logins'] ?? [], $rascunho['duplicidades'] ?? []], JSON_THROW_ON_ERROR));
    }

    /** Apresenta todos os campos revisados e distingue confirmação de trabalho de execução ainda bloqueada. */
    public static function mostrar(array $rascunho, string $url): void {
        echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>Resumo e confirmação</h2></div><div class="contas-corpo">';
        echo '<form method="post" action="' . htmlescape($url) . '"><input type="hidden" name="chamado_id" value="' . (int) $rascunho['chamado_id'] . '"><input type="hidden" name="revisao" value="' . (int) $rascunho['revisao'] . '">';
        if (isset($rascunho['resumo'])) {
            $rotulos = ['chamado_id' => 'Chamado de rede', 'formulario_id' => 'Formulário de origem', 'tecnico_id' => 'Técnico desta revisão', 'tecnico_original' => 'Técnico original', 'entidade_id' => 'Entidade', 'tipo' => 'Operação', 'nome_civil' => 'Nome civil', 'nome_social' => 'Nome social', 'usar_nome_social' => 'Usar nome social', 'matricula' => 'Matrícula', 'conta_atendida' => 'Conta atendida', 'login_pretendido' => 'Login pretendido', 'dominio' => 'Domínio', 'ou' => 'OU selecionada', 'grupos' => 'Grupos manuais', 'scripts' => 'Scripts manuais', 'pastas' => 'Pastas propostas', 'validade_proposta' => 'Validade proposta'];
            echo '<dl>';
            foreach ($rascunho['resumo']['dados'] as $campo => $valor) {
                if (is_bool($valor)) { $valor = $valor ? 'sim' : 'não'; }
                if ($campo === 'dominio' && $valor !== '') { $dominio = ConfiguracaoDoDiretorio::obter((int) $valor); $valor = $dominio['nome'] . ' — ' . $dominio['base_dn']; }
                echo '<dt>' . htmlescape($rotulos[$campo] ?? $campo) . '</dt><dd class="text-break">' . htmlescape((string) $valor) . '</dd>';
            }
            echo '</dl>';
            if (isset($rascunho['resumo']['desafio'])) {
                echo '<input type="hidden" name="desafio" value="' . htmlescape($rascunho['resumo']['desafio']) . '"><label><input type="checkbox" name="confirmar_revisao" value="1" required> 
                Revisei este conteúdo. Esta confirmação registra somente meu trabalho, sem executar AD ou fechar chamados.</label><br><button name="acao" value="confirmar_revisao" 
                class="btn btn-primary mt-2">Confirmar revisão de trabalho</button>';
            }
        }
        echo '<button name="acao" value="preparar_resumo" class="btn btn-outline-primary mt-2" formnovalidate>Preparar novo resumo salvo</button>';
        \Html::closeForm();
        if (isset($rascunho['confirmacao'])) { echo '<p class="alert alert-info">Revisão registrada. A operação no AD não foi executada.</p>'; }
        echo '<p>Execução real bloqueada até definição e homologação dos efeitos de grupos, Scripts, atributos e validade. Não há botão para habilitar escrita.</p></div></section>';
    }

    /** Ordena grupos para que a autorização seja estável sem ignorar revogação de grupo/perfil/entidade. */
    public static function assinaturaDoContexto(array $contexto): string {
        $grupos = $contexto['grupos_ids']; sort($grupos);
        return hash('sha256', json_encode([$contexto['tecnico_id'], $contexto['perfil_id'], $contexto['entidade_id'], $grupos], JSON_THROW_ON_ERROR));
    }
}
