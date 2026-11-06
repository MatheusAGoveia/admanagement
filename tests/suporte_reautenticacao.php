<?php

// Dublês exclusivos das suítes; não inicializam GLPI nem concedem acesso no produto.

/**
 * Representa o redirecionamento nativo quando a suíte não declarou sua própria exceção.
 */

class RedirecionamentoDeReautenticacaoDoTeste extends RuntimeException {

}

// Fluxos HTTP já possuem a exceção; as suítes de política usam somente este contrato mínimo.

if (!class_exists('Glpi\\Exception\\RedirectException', false)) {

    class_alias(RedirecionamentoDeReautenticacaoDoTeste::class, 'Glpi\\Exception\\RedirectException');

}

/**
 * Reproduz a necessidade de identidade recente sem simular a validação real de credenciais.
 */

class ReautenticacaoDoTeste {

    public static bool $validada = true;

    public static int $verificacoes = 0;

    /**
     * Entrega somente a interface consumida pelo adaptador de acesso durante os testes.
     */

    public static function getInstance(): self {

        return new self();

    }

    /**
     * Sinaliza o prompt quando falta reautenticação; a suíte verifica ordem e propagação.
     */

    public function checkReAuthenticationOrRedirect(): void {

        self::$verificacoes++;

        // Identidade pendente deve interromper a ação, sem ser convertida em erro de catálogo.

        if (!self::$validada) {

            throw new \Glpi\Exception\RedirectException('/ReAuth/Prompt', 302);

        }

    }

}

class_alias(ReautenticacaoDoTeste::class, 'Glpi\\Security\\ReAuth\\ReAuthManager');
