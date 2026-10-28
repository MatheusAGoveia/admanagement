<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

use Html;

/**
 * Vincula o resumo ao conteúdo, à origem e ao perfil atuais, sem executar efeitos no AD.
 */

final class RevisaoDaOperacao {

    // Uma única definição mantém os campos assinados e seus rótulos sempre correspondentes.

    private const CAMPOS = [

        'chamado_id' => 'Chamado de rede',

        'formulario_id' => 'Formulário de origem',

        'tecnico_id' => 'Técnico desta revisão',

        'tecnico_original' => 'Técnico original',

        'entidade_id' => 'Entidade',

        'tipo' => 'Operação',

        'nome_civil' => 'Nome civil',

        'nome_social' => 'Nome social',

        'usar_nome_social' => 'Usar nome social',

        'matricula' => 'Matrícula',

        'conta_atendida' => 'Conta atendida',

        'login_pretendido' => 'Login pretendido',

        'dominio' => 'Domínio',

        'ou' => 'OU selecionada',

        'grupos' => 'Grupos manuais',

        'scripts' => 'Scripts manuais',

        'pastas' => 'Pastas propostas',

        'validade_proposta' => 'Validade proposta',

    ];

    // O resumo exige uma nova conferência após dez minutos.

    private const VALIDADE_EM_SEGUNDOS = 600;

    /**
     * Captura somente os campos revisáveis e cria um desafio de confirmação consumível.
     */

    public static function preparar(

        array $rascunho,

        array $contexto,

        ?int $agora = null

    ): array {

        $instante = $agora ?? time();

        $dados = self::extrairDados($rascunho);

        $rascunho['resumo'] = [

            'dados' => $dados,

            'conteudo' => self::assinar($dados),

            'revisao' => $rascunho['revisao'],

            'origem' => $rascunho['assinatura_origem'],

            'contexto' => self::assinaturaDoContexto($contexto),

            'vigencia' => self::assinaturaDaVigencia($rascunho, $instante),

            'expira' => $instante + self::VALIDADE_EM_SEGUNDOS,

            'desafio' => bin2hex(random_bytes(24)),

        ];

        unset($rascunho['confirmacao']);

        return $rascunho;

    }

    /**
     * Confirma a mesma revisão após releitura da origem e consome o desafio para impedir repetição.
     */

    public static function confirmar(

        array $rascunho,

        array $base_atual,

        array $contexto,

        array $entrada,

        ?int $agora = null

    ): array {

        $instante = $agora ?? time();

        $resumo = $rascunho['resumo'] ?? [];

        // A confirmação deve ser explícita e apresentar o desafio da revisão ainda não consumida.

        if (

            ($entrada['confirmar_revisao'] ?? '') !== '1'

            || !is_string($entrada['desafio'] ?? null)

            || !is_string($resumo['desafio'] ?? null)

            || !hash_equals($resumo['desafio'], $entrada['desafio'])

        ) {

            throw new DomainException('Confirme explicitamente o resumo atual.');

        }

        // Revisão, origem, autorização e evidências precisam continuar válidas no mesmo instante.

        if (!self::resumoEstaValido($rascunho, $base_atual, $contexto, $instante)) {

            throw new DomainException('A revisão perdeu validade. Gere um novo resumo após conferir os dados e o acesso.');

        }

        // A assinatura protege inclusive alterações que não tenham atualizado o contador de revisão.

        if (!hash_equals($resumo['conteudo'], self::assinar(self::extrairDados($rascunho)))) {

            throw new DomainException('O conteúdo mudou após a preparação do resumo.');

        }

        $rascunho['confirmacao'] = [

            'revisao' => $rascunho['revisao'],

            'conteudo' => $resumo['conteudo'],

            'ator_id' => $contexto['tecnico_id'],

            'instante' => gmdate(DATE_ATOM),

        ];

        unset($rascunho['resumo']['desafio']);

        return $rascunho;

    }

    /**
     * Detecta perda de validade também em leitura; o controlador inutiliza o resumo na sessão.
     */

    public static function continuaValida(

        array $rascunho,

        array $base,

        array $contexto

    ): bool {

        // Trabalho sem resumo ainda não possui confirmação que precise ser invalidada.

        if (!isset($rascunho['resumo'])) {

            return true;

        }

        try {

            return self::resumoEstaValido($rascunho, $base, $contexto, time());

        } catch (DomainException | \InvalidArgumentException $erro) {

            // Falha de configuração ou de catálogo invalida a evidência já preparada.

            return false;

        }

    }

    /**
     * Centraliza a conferência temporal usada pela leitura e pela confirmação do mesmo resumo.
     */

    private static function resumoEstaValido(

        array $rascunho,

        array $base,

        array $contexto,

        int $agora

    ): bool {

        $resumo = $rascunho['resumo'] ?? [];

        return ($resumo['revisao'] ?? 0) === $rascunho['revisao']

            && ($resumo['expira'] ?? 0) > $agora

            && ($resumo['origem'] ?? '') === $base['assinatura_origem']

            && ($resumo['contexto'] ?? '') === self::assinaturaDoContexto($contexto)

            && ($resumo['vigencia'] ?? '') === self::assinaturaDaVigencia($rascunho, $agora);

    }

    /**
     * Usa a mesma seleção de campos para preparar, confirmar e apresentar o conteúdo revisado.
     */

    private static function extrairDados(

        array $rascunho

    ): array {

        $dados = [];

        // Campos de transporte, respostas LDAP e dados externos não integram o resumo persistido.

        foreach (array_keys(self::CAMPOS) as $campo) {

            $dados[$campo] = $rascunho[$campo] ?? '';

        }

        return $dados;

    }

    /**
     * Confere domínio, catálogo real e prazo das consultas antes de assinar as evidências.
     */

    private static function assinaturaDaVigencia(

        array $rascunho,

        int $agora

    ): string {

        $dominios = ConfiguracaoDoDiretorio::consultar();

        // Uma OU só permanece revisada enquanto estiver no catálogo vigente do mesmo domínio.

        if (($rascunho['ou'] ?? '') !== '') {

            $id = EntradaDaFerramenta::lerIdentificador($rascunho['dominio']);

            $dominio = ConfiguracaoDoDiretorio::obter($id);

            $catalogo = $rascunho['catalogo_ous'] ?? [];

            // Não reutiliza catálogo expirado, de outra conexão ou sem o DN selecionado.

            if (

                ($catalogo['expira'] ?? 0) <= $agora

                || ($catalogo['assinatura'] ?? '') !== self::assinar($dominio)

                || !in_array($rascunho['ou'], array_column($catalogo['itens'] ?? [], 'dn'), true)

                || !NomeDistinto::pertenceAoDominio($rascunho['ou'], $dominio['base_dn'])

            ) {

                throw new DomainException('O destino perdeu validade. Consulte a árvore real e revise novamente.');

            }

        }

        $consultas = array_values($rascunho['consultas_logins'] ?? []);

        // A evidência de duplicidade usa o mesmo prazo das consultas de disponibilidade.

        if (isset($rascunho['duplicidades'])) {

            $consultas[] = $rascunho['duplicidades'];

        }

        // Nenhuma resposta expirada pode continuar apresentada como verificação atual.

        foreach ($consultas as $consulta) {

            // Mesmo uma consulta incompleta deixa de representar a tentativa atual após expirar.

            if (($consulta['expira'] ?? 0) <= $agora) {

                throw new DomainException('As consultas ao diretório expiraram. Consulte e revise novamente.');

            }

        }

        return self::assinar([

            $dominios,

            ConfiguracaoDaFila::consultar(),

            $rascunho['catalogo_ous'] ?? [],

            $rascunho['consultas_logins'] ?? [],

            $rascunho['duplicidades'] ?? [],

        ]);

    }

    /**
     * Assina apenas dados de controle; esta função não anonimiza identificadores pessoais.
     */

    private static function assinar(

        array $dados

    ): string {

        return hash('sha256', json_encode($dados, JSON_THROW_ON_ERROR));

    }

    /**
     * Mostra a revisão e sua confirmação usando os campos ocultos e CSRF nativos do GLPI.
     */

    public static function mostrar(

        array $rascunho,

        string $url

    ): void {

        echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>Resumo e confirmação</h2></div><div class="contas-corpo">';

        echo '<form method="post" action="' . htmlescape($url) . '">';

        echo Html::hidden('chamado_id', ['value' => $rascunho['chamado_id']]);

        echo Html::hidden('revisao', ['value' => $rascunho['revisao']]);

        // O resumo existe somente depois do comando explícito de preparação.

        if (isset($rascunho['resumo'])) {

            self::mostrarDados($rascunho['resumo']['dados']);

            // Um desafio consumido não oferece uma segunda confirmação do mesmo conteúdo.

            if (isset($rascunho['resumo']['desafio'])) {

                echo Html::hidden('desafio', ['value' => $rascunho['resumo']['desafio']]);

                echo '<label><input type="checkbox" name="confirmar_revisao" value="1" required> Revisei este conteúdo. Esta confirmação registra somente meu trabalho, sem executar AD ou fechar chamados.</label>';

                echo '<br><button name="acao" value="confirmar_revisao" class="btn btn-primary mt-2">Confirmar revisão de trabalho</button>';

            }

        }

        echo '<button name="acao" value="preparar_resumo" class="btn btn-outline-primary mt-2" formnovalidate>Preparar novo resumo salvo</button>';

        Html::closeForm();

        // A mensagem informa apenas o registro local, sem sugerir que houve execução no diretório.

        if (isset($rascunho['confirmacao'])) {

            echo '<p class="alert alert-info">Revisão registrada. A operação no AD não foi executada.</p>';

        }

        echo '<p>Execução real bloqueada até definição e homologação dos efeitos de grupos, Scripts, atributos e validade. Não há botão para habilitar escrita.</p></div></section>';

    }

    /**
     * Apresenta somente campos conhecidos e mantém os valores escapados para HTML.
     */

    private static function mostrarDados(

        array $dados

    ): void {

        echo '<dl>';

        // Rótulos e valores usam a definição única dos campos incluídos na assinatura.

        foreach ($dados as $campo => $valor) {

            // Booleanos são apresentados como indicação textual de uso do nome.

            if (is_bool($valor)) {

                $valor = $valor ? 'sim' : 'não';

            }

            // O identificador técnico de domínio é traduzido pelo catálogo próprio vigente.

            if ($campo === 'dominio' && $valor !== '') {

                $dominio = ConfiguracaoDoDiretorio::obter((int) $valor);

                $valor = $dominio['nome'] . ' — ' . $dominio['base_dn'];

            }

            echo '<dt>' . htmlescape(self::CAMPOS[$campo] ?? $campo) . '</dt>';

            echo '<dd class="text-break">' . htmlescape((string) $valor) . '</dd>';

        }

        echo '</dl>';

    }

    /**
     * Detecta troca de perfil, entidades, direitos ou mapa de setores sem depender de grupos pessoais.
     */

    public static function assinaturaDoContexto(

        array $contexto

    ): string {

        $entidades = $contexto['entidades_ativas_ids'] ?? [$contexto['entidade_id']];

        sort($entidades, SORT_NUMERIC);

        return self::assinar([

            'tecnico_id' => $contexto['tecnico_id'],

            'perfil_id' => $contexto['perfil_id'],

            // A autoria usa a entidade do chamado, mas a autorização usa a entidade ativa do GLPI.

            'entidade_id' => $contexto['entidade_ativa_id'] ?? $contexto['entidade_id'],

            'entidades_ativas_ids' => $entidades,

            'direitos_perfil' => $contexto['direitos_perfil'] ?? [],

            'visibilidade' => $contexto['visibilidade'] ?? [],

            'assinatura_visibilidade' => $contexto['assinatura_visibilidade'] ?? '',

        ]);

    }

}
