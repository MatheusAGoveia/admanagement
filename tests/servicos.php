<?php

/** Confere políticas novas sem abrir conexões de banco ou Active Directory. */
spl_autoload_register(
    /** Carrega somente as classes próprias exigidas pelos cenários desta suíte. */
    static function (string $classe): void {
        $prefixo = 'GlpiPlugin\\Admanagement\\';
        // Não intercepta classes externas nem altera o autoload do GLPI.
        if (str_starts_with($classe, $prefixo)) {
            require dirname(__DIR__) . '/src/' . substr($classe, strlen($prefixo)) . '.php';
        }
    }
);

use GlpiPlugin\Admanagement\BuscaDePastas;
use GlpiPlugin\Admanagement\IdentidadeDoDiretorio;
use GlpiPlugin\Admanagement\PoliticaDeRenovacao;
use GlpiPlugin\Admanagement\LeitorDePlanilhas;

$verificacoes = 0;

/** Interrompe a suíte quando uma regra observável diverge do resultado esperado. */
function conferirServico(bool $condicao, string $regra): void {
    global $verificacoes;
    // Falha expressa evita confundir avisos PHP com aprovação dos cenários.
    if (!$condicao) {
        throw new RuntimeException($regra);
    }
    $verificacoes++;
}

$agora = new DateTimeImmutable('2026-11-12 10:00:00', new DateTimeZone('America/Sao_Paulo'));
$validade = PoliticaDeRenovacao::paraFiletime($agora->modify('+5 days'));
$contrato = PoliticaDeRenovacao::propor($validade, 'CONTRATADO', $agora);
conferirServico($contrato['data'] === '2027-11-17 10:00:00', 'Contrato usa um ano a partir da expiração atual.');
conferirServico(PoliticaDeRenovacao::propor($validade, 'ESTAGIÁRIO', $agora)['data'] === '2027-05-17 10:00:00', 'Estágio usa seis meses.');
conferirServico(PoliticaDeRenovacao::propor(PoliticaDeRenovacao::paraFiletime($agora->modify('-2 years')), 'CONTRATO', $agora)['data'] === '2027-11-12 10:00:00', 'Vencida usa o instante da consulta.');
conferirServico(PoliticaDeRenovacao::propor('0', 'CONTRATO', $agora)['estado'] === 'SEM_EXPIRACAO', 'Zero não recebe expiração artificial.');
conferirServico(PoliticaDeRenovacao::propor('9223372036854775807', 'CONTRATO', $agora)['estado'] === 'SEM_EXPIRACAO', 'Segundo sentinela também não expira.');
conferirServico(PoliticaDeRenovacao::propor(PoliticaDeRenovacao::paraFiletime($agora->modify('+90 days')), 'CONTRATO', $agora)['estado'] === 'PRONTO', 'Noventa dias exatos permitem renovar.');
conferirServico(PoliticaDeRenovacao::propor(PoliticaDeRenovacao::paraFiletime($agora->modify('+90 days +1 second')), 'CONTRATO', $agora)['estado'] === 'FORA_DO_PRAZO', 'Noventa dias e um segundo impedem renovar.');
$fim = new DateTimeImmutable('2028-08-31 12:30:00', $agora->getTimezone());
conferirServico(PoliticaDeRenovacao::propor(PoliticaDeRenovacao::paraFiletime($fim), 'ESTAGIÁRIO', $fim)['data'] === '2029-02-28 12:30:00', 'Fim de mês é limitado ao último dia válido.');
$bissexto = new DateTimeImmutable('2028-02-29 10:00:00', $agora->getTimezone());
conferirServico(PoliticaDeRenovacao::propor(PoliticaDeRenovacao::paraFiletime($bissexto), 'CONTRATO', $bissexto)['data'] === '2029-02-28 10:00:00', 'Um ano após 29/02 conserva um dia válido.');

conferirServico(IdentidadeDoDiretorio::normalizarCpf('000.000.001-91') === '00000000191', 'CPF normaliza pontuação e conserva zeros.');
conferirServico(IdentidadeDoDiretorio::normalizarNome('  ANA  LÚIZA ') === 'ANA LUIZA', 'Nome normaliza acentos, espaços e caixa.');
$pessoa = ['nome' => 'ANA LUIZA CAMPOS', 'cpf' => '00000000191', 'tipo' => 'renovacao'];
$conta = ['guid' => str_repeat('a', 32), 'dominio' => 1, 'nome' => $pessoa['nome'], 'nome_exibicao' => $pessoa['nome'], 'cpf' => $pessoa['cpf'], 'escritorio' => '', 'login' => 'ana.campos', 'ativa' => true, 'validade' => $validade];
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [$conta])['metodo'] === 'NOME_CPF', 'Nome e CPF correspondente identificam conta única.');
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [$conta, $conta])['estado'] === 'IDENTIFICADA', 'Resposta duplicada do mesmo GUID não é homônimo.');
$segunda = array_replace($conta, ['dominio' => 2, 'guid' => str_repeat('b', 32), 'cpf' => '11144477735']);
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [$conta, $segunda])['estado'] === 'HOMONIMO', 'Dois nomes iguais bloqueiam mesmo com CPFs diferentes.');
$segunda['ativa'] = false;
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [$conta, $segunda])['estado'] === 'HOMONIMO', 'Conta desativada também participa da verificação de homônimos.');
$sem_cpf = array_replace($conta, ['cpf' => '']);
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [$sem_cpf])['metodo'] === 'NOME_UNICO', 'Renovação permite nome completo único sem CPF.');
conferirServico(IdentidadeDoDiretorio::avaliar(array_replace($pessoa, ['tipo' => 'bloqueio']), [$sem_cpf])['estado'] === 'CPF_NAO_CONFERE', 'Bloqueio não usa a exceção de nome sem CPF.');
$escritorio = array_replace($sem_cpf, ['escritorio' => 'CPF: 000.000.001-91 mat. 12345']);
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [$escritorio])['metodo'] === 'NOME_CPF', 'Escritório não concatena matrícula ao CPF.');
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [array_replace($escritorio, ['cpf' => '11144477735'])])['estado'] === 'CPF_CONFLITANTE', 'CPF e Escritório conflitantes bloqueiam.');
conferirServico(IdentidadeDoDiretorio::avaliar($pessoa, [array_replace($sem_cpf, ['escritorio' => 'CPF: 11111111111'])])['estado'] === 'CPF_NAO_CONFERE', 'CPF inválido não permite alternativa por nome.');

$pastas = BuscaDePastas::extrairSolicitadas('[{"caminho":"gabinete$\\\\(servidor) (T:)\\\\protecaoanimal","permissao":"ESCRITA"}]');
conferirServico(count($pastas) === 1 && $pastas[0]['permissao'] === 'ESCRITA', 'Importa lista estruturada sem perder a permissão.');
conferirServico(BuscaDePastas::termoDoCaminho($pastas[0]['caminho']) === 'protecaoanimal', 'Caminho ruidoso gera termo final útil.');
conferirServico(BuscaDePastas::termoDoCaminho('ESCRITA \\\\servidor\\gabinete$\\Protecao Animal') === 'Protecao Animal', 'Não oferece rótulo ou servidor como pasta encontrada.');

$linhas = LeitorDePlanilhas::validarLinhas([['CPF', 'Nome', 'Objetivo', 'Vínculo'], ['000.000.001-91', 'Ana Luiza Campos', 'renovar', 'contratado'], ['11144477735', 'Bruno Oliveira', 'bloquear', '']]);
conferirServico($linhas[0]['tipo'] === 'renovacao' && $linhas[0]['cpf'] === '00000000191', 'Linha normaliza CPF e objetivo.');
conferirServico($linhas[1]['tipo'] === 'bloqueio' && $linhas[1]['erros'] === [], 'Bloqueio não exige vínculo.');
$invalida = LeitorDePlanilhas::validarLinhas([['cpf', 'nome', 'objetivo'], ['00000000191', 'Ana Luiza Campos', 'renovar']]);
conferirServico($invalida[0]['erros'] !== [], 'Renovação sem vínculo é impedida.');
$duplicadas = LeitorDePlanilhas::validarLinhas([['cpf', 'nome', 'objetivo', 'vinculo'], ['00000000191', 'Ana Luiza Campos', 'renovar', 'contrato'], ['00000000191', 'Ana Luiza Campos', 'bloquear', '']]);
conferirServico($duplicadas[0]['erros'] !== [] && $duplicadas[1]['erros'] !== [], 'Objetivos conflitantes para CPF impedem ambas as linhas.');

$formulario = ['id' => 8, 'status' => 3, 'entities_id' => 0, 'req_servico_rede' => 1, 'tipo_servico_rede' => 'BLOQUEIO DE ACESSO', 'tickets_id_rede' => 15, 'usuario_rede' => 'ana.campos', 'name' => 'Ana Luiza Campos', 'vinculo' => 'CONTRATADO'];
$chamado = ['id' => 15, 'entities_id' => 0, 'itilcategories_id' => 1];
$importado = \GlpiPlugin\Admanagement\ImportadorDaSolicitacao::importar($chamado, [$formulario], ['tecnico_id' => 2]);
conferirServico($importado['tipo'] === 'bloqueio' && $importado['conta_atendida'] === 'ana.campos', 'Serviço de bloqueio é importado sem converter para criação.');
$formulario['pastas_rede_json'] = 'JSON inválido';
$importado = \GlpiPlugin\Admanagement\ImportadorDaSolicitacao::importar($chamado, [$formulario], ['tecnico_id' => 2]);
conferirServico($importado['pastas_solicitadas'] === [] && $importado['avisos_estrutura'] !== [], 'Pasta inválida exige revisão manual, sem inventar grupos.');

echo $verificacoes . " verificações de serviços, identidade, datas, pastas e lotes concluídas.\n";
