<?php

// Pendências locais da revisão; resultado desconhecido não permite repetição automática.

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\EnderecoDaFerramenta;

use GlpiPlugin\Admanagement\InterfaceDaFerramenta;

use GlpiPlugin\Admanagement\MenuDaFerramenta;

use GlpiPlugin\Admanagement\RepositorioDeRascunhos;

use GlpiPlugin\Admanagement\RevisorDaOperacao;

use GlpiPlugin\Admanagement\RepositorioDeOperacoes;

use GlpiPlugin\Admanagement\PoliticaDeResultados;

use GlpiPlugin\Admanagement\ConsultaDeChamados;

require_once __DIR__ . '/../../../inc/includes.php';

$contexto = AcessoGlpi::obterContextoDoTecnico();

global $DB;

$consulta_de_escopo = new ConsultaDeChamados($DB);

Html::header(

    'Pendências do ADManagement',

    $_SERVER['PHP_SELF'],

    'plugins',

    MenuDaFerramenta::class

);

InterfaceDaFerramenta::mostrarInicio(

    EnderecoDaFerramenta::raiz(),

    'pendencias',

    AcessoGlpi::podeConfigurar(),

    AcessoGlpi::estaComAcessoTemporarioParaTestes()

);

echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>Revisões com pendências</h2></div><div class="contas-corpo">';

// Apresenta os rascunhos temporários do técnico na entidade atual.

foreach (RepositorioDeRascunhos::listar($contexto) as $rascunho) {

    // A perda de atribuição ou setor também oculta os metadados e invalida o resumo anterior.

    if (!$consulta_de_escopo->pertenceAoEscopo((int) $rascunho['chamado_id'], $contexto)) {

        RepositorioDeRascunhos::invalidarConfirmacao((int) $rascunho['chamado_id'], ['tecnico_id' => $contexto['tecnico_id'], 'entidade_id' => $rascunho['entidade_id']]);

        continue;

    }

    echo '<p><a href="' . htmlescape(

        EnderecoDaFerramenta::raiz() . '/front/revisao.php?id=' . $rascunho['chamado_id']

    )

        . '">Chamado #'

        . (int) $rascunho['chamado_id']

        . '</a> — '

        . count(RevisorDaOperacao::listarPendencias($rascunho))

        . ' pendência(s). A origem e a autorização serão relidas ao abrir.</p>';

}

try {

    // Consulta os estados duráveis para indicar quais operações ainda exigem acompanhamento.

    foreach (RepositorioDeOperacoes::doGlpi()->consultar()['operacoes'] as $operacao) {

        // Oculta operações de outra entidade, outro ator ou chamado sem leitura autorizada.

        if (

            !in_array($operacao['entidade_id'], $contexto['visibilidade']['entidades_ids'], true)

            || $operacao['ator_id'] !== $contexto['tecnico_id']

            || !$consulta_de_escopo->pertenceAoEscopo((int) $operacao['chamado_fixo'], $contexto)

        ) {

            continue;

        }

        echo '<p>Chamado #'

            . (int) $operacao['chamado_fixo']

            . ' — '

            . htmlescape(PoliticaDeResultados::proximaEtapa($operacao, $operacao['chamado_fixo']))

            . '</p>';

    }

} catch (\Throwable $erro) {

    // Bloqueia a apresentação de recuperação quando o registro privado não pode ser conferido.

    echo '<p class="alert alert-warning">O registro privado precisa de conferência administrativa. Recuperação bloqueada.</p>';

}

echo '<p>Não existe recuperação automática de AD. Resultado parcial, desconhecido ou com sucesso comprovado exige reconciliação antes de qualquer nova tentativa; sucesso no AD permite apenas finalizar o chamado original.</p></div></section>';

InterfaceDaFerramenta::mostrarFim();

Html::footer();

