<?php

// Auditoria de metadados: não é histórico de chamados nem evidência automática de sucesso AD.

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\EnderecoDaFerramenta;

use GlpiPlugin\Admanagement\InterfaceDaFerramenta;

use GlpiPlugin\Admanagement\MenuDaFerramenta;

use GlpiPlugin\Admanagement\RepositorioDeOperacoes;

use GlpiPlugin\Admanagement\ConsultaDeChamados;

require_once __DIR__ . '/../../../inc/includes.php';

$contexto = AcessoGlpi::obterContextoDoTecnico();

global $DB;

$consulta_de_escopo = new ConsultaDeChamados($DB);

$leitura_por_chamado = [];

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

    AcessoGlpi::podeConfigurar()

);

echo '<section class="contas-cartao"><div class="contas-cabecalho"><h2>Auditoria da ferramenta</h2></div><div class="contas-corpo"><p>Registros minimizados no escopo do perfil ativo. Técnicos consultam suas ações; administradores acompanham os chamados dos setores autorizados.</p>';

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

    // O perfil técnico acompanha apenas suas ações; supervisores permanecem no seu escopo.

    if (

        !in_array((int) $registro['entidade_id'], $contexto['visibilidade']['entidades_ids'], true)

        || ($contexto['visibilidade']['papel'] === 'tecnico'

            && (int) $registro['ator_id'] !== $contexto['tecnico_id'])

    ) {

        continue;

    }

    $chamado_id = (int) $registro['chamado_id'];

    // Reutiliza a decisão somente dentro desta requisição, sem repetir consultas do mesmo chamado.

    if (!array_key_exists($chamado_id, $leitura_por_chamado)) {

        $leitura_por_chamado[$chamado_id] = $consulta_de_escopo->pertenceAoEscopo($chamado_id, $contexto);

    }

    // A leitura nativa isolada não basta após perda de atribuição ou mudança de setor.

    if (!$leitura_por_chamado[$chamado_id]) {

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

