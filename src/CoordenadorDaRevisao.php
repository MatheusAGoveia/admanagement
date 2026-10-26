<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Coordena ações locais depois da releitura obrigatória de ticket, origem e autorização.
 */

final class CoordenadorDaRevisao {

    /**
     * Trata apenas comandos explícitos da revisão; nenhum deles executa escrita no AD.
     */

    public static function processar(

        string $acao,

        array $rascunho,

        array $base,

        array $contexto,

        array $entrada,

        object $banco

    ): array {

        $esperada = EntradaDaFerramenta::lerIdentificador($entrada['revisao'] ?? null);

        // Interrompe comandos enviados por uma aba antiga ou baseados em outra versão da origem.

        if (

            $rascunho['revisao'] !== $esperada

            || $rascunho['assinatura_origem'] !== $base['assinatura_origem']

        ) {

            throw new DomainException('A revisão ou a origem mudou. Recarregue e revise novamente.');

        }

        // Prepara somente o resumo do trabalho, sem executar AD ou finalizar chamado.

        if ($acao === 'preparar_resumo') {

            return RevisaoDaOperacao::preparar($rascunho, $contexto);

        }

        // Confere o desafio e a vigência do resumo antes de registrar a revisão explícita.

        if ($acao === 'confirmar_revisao') {

            return RevisaoDaOperacao::confirmar($rascunho, $base, $contexto, $entrada);

        }

        // Descarta o radio antigo de OU para permitir atualizar um catálogo expirado ou trocar de domínio.

        if ($acao === 'consultar_ous') {

            // Atualizar catálogo não valida o radio antigo, que pode ter expirado ou pertencer a outro domínio.

            $entrada['ou'] = '';

            $rascunho['ou'] = '';

        }

        $revisado = RevisorDaOperacao::aplicar($rascunho, $entrada);

        // Consulta a árvore real do domínio selecionado e guarda um catálogo temporário para esta revisão.

        if ($acao === 'consultar_ous') {

            $id = EntradaDaFerramenta::lerIdentificador($revisado['dominio']);

            $dominio = ConfiguracaoDoDiretorio::obter($id);

            $itens = (new LeitorLdap($dominio))->listarOus();

            // O catálogo serve só à sessão/revisão atual e expira quando a conexão mudar.

            if (strlen(json_encode($itens, JSON_THROW_ON_ERROR)) > 1024 * 1024) {

                throw new DomainException('O catálogo excede o tamanho de revisão. A consulta não será apresentada como completa.');

            }

            $revisado['catalogo_ous'] = [

                'itens' => $itens,

                'assinatura' => hash('sha256', json_encode($dominio, JSON_THROW_ON_ERROR)),

                'expira' => time() + 300

            ];

            $revisado['busca_ou'] = EntradaDaFerramenta::lerTexto($entrada['busca_ou'] ?? '', 150);

        } elseif ($acao === 'consultar_diretorio') {

            // Consulta disponibilidade e duplicidades somente após o comando explícito do técnico.

            $id = EntradaDaFerramenta::lerIdentificador($revisado['dominio']);

            $dominios = ConfiguracaoDoDiretorio::consultar();

            ConfiguracaoDoDiretorio::obter($id);

            $verificador = new VerificadorDoDiretorio();

            $candidatos = $revisado['tipo'] === 'criacao'

                ? PoliticaDeLogin::gerarCandidatos(RevisorDaOperacao::obterNomeParaLogin($revisado))

                : [];

            $login = $revisado['tipo'] === 'criacao' ? $revisado['login_pretendido'] : $revisado['conta_atendida'];

            // Inclui a entrada manual de login nas consultas, além dos candidatos da política.

            if ($login !== '') {

                $candidatos[] = $login;

            }

            $revisado['consultas_logins'] = [];

            // Consulta cada candidato distinto em todos os domínios exigidos para a operação.

            foreach (array_values(array_unique($candidatos)) as $candidato) {

                $revisado['consultas_logins'][$candidato] = $verificador->consultarDisponibilidade($candidato, $dominios, $id);

            }

            $pessoa = (new LeitorDoFormmanager($banco))->consultarIdentificadores($revisado['formulario_id'], $revisado['chamado_id']);

            $pessoa['nome'] = $revisado['nome_civil'];

            $pessoa['matricula'] = $revisado['matricula'];

            $pessoa['login'] = $login;

            $revisado['duplicidades'] = $verificador->consultarDuplicidades($pessoa, $dominios, $id);

            // CPF e respostas LDAP deixam de existir ao terminar esta chamada.

            unset($pessoa);

        } else {

            // Recusa comandos desconhecidos para não criar caminhos de operação implícitos.

            throw new DomainException('Comando de revisão desconhecido.');

        }

        return $revisado;

    }

}

