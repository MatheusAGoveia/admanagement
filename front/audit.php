<?php

// Auditoria de metadados: não é histórico de chamados nem evidência automática de sucesso AD.

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\EnderecoDaFerramenta;

use GlpiPlugin\Admanagement\InterfaceDaFerramenta;

use GlpiPlugin\Admanagement\MenuDaFerramenta;

use GlpiPlugin\Admanagement\RepositorioDeOperacoes;

require_once __DIR__ . '/../../../inc/includes.php';

$contexto = AcessoGlpi::obterContextoDoTecnico();

$registros = [];

$erro = null;

try {

    $registros = RepositorioDeOperacoes::doGlpi()->consultar()['auditoria'];

} catch (DomainException $falha) {

    // Apresenta a indisponibilidade do registro privado sem anunciar uma auditoria vazia como resultado válido.

    $erro = $falha->getMessage();

}

Html::header(

    'Auditoria do ADManagement',

    $_SERVER['PHP_SELF'],

    'plugins',

    MenuDaFerramenta::class

);

InterfaceDaFerramenta::mostrarInicio(

    EnderecoDaFerramenta::raiz(),

    'auditoria',

    AcessoGlpi::podeConfigurar(),

    AcessoGlpi::estaComAcessoTemporarioParaTestes()

);

echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>Auditoria da ferramenta</h2></div><div class="contas-corpo"><p>Registros minimizados das suas ações. Acesso ampliado e retenção institucional ainda dependem de definição.</p>';

// Mostra o motivo que impediu a leitura da auditoria.

if ($erro !== null) {

    echo '<p class="alert alert-danger">' . htmlescape($erro) . '</p>';

}

echo '<table class="table"><thead><tr><th>Instante UTC</th><th>Chamado</th><th>Evento</th><th>Revisão</th><th>Ator</th></tr></thead><tbody>';

$exibidos = 0;

// Percorre os eventos do mais recente ao mais antigo, antes de aplicar o escopo de leitura.

foreach (array_reverse($registros) as $registro) {

    // Interrompe a apresentação ao atingir o limite de cem eventos visíveis.

    if ($exibidos >= 100) {

        break;

    }

    // Regra restritiva enquanto os grupos de consulta de auditoria não forem definidos.

    if (

        (int) $registro['entidade_id'] !== $contexto['entidade_id']

        || (int) $registro['ator_id'] !== $contexto['tecnico_id']

    ) {

        continue;

    }

    $ticket = new Ticket();

    // Oculta o evento quando o técnico não pode ler o chamado correspondente no GLPI.

    if (!$ticket->can((int) $registro['chamado_id'], READ)) {

        continue;

    }

    echo '<tr><td>'

        . htmlescape($registro['instante'])

        . '</td><td>#'

        . (int) $registro['chamado_id']

        . '</td><td>'

        . htmlescape($registro['evento'])

        . '</td><td>'

        . (int) $registro['revisao']

        . '</td><td>#'

        . (int) $registro['ator_id']

        . '</td></tr>';

    $exibidos++;

}

// Informa que nenhum evento ficou visível após os filtros de autorização.

if ($exibidos === 0) {

    echo '<tr><td colspan="5">Nenhum evento visível. A ausência de registro não comprova efeito externo.</td></tr>';

}

echo '</tbody></table></div></section>';

InterfaceDaFerramenta::mostrarFim();

Html::footer();

