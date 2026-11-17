<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Coordena ações explícitas depois da releitura obrigatória de ticket, origem e autorização.
 */

final class CoordenadorDaRevisao {

    /**
     * Separa consultas e revisão da execução explícita; somente executar_ad pode enviar efeitos ao diretório.
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

        // A confirmação de efeitos usa dados salvos, sem alterar o alvo por campos enviados no POST.

        if (in_array($acao, ['preparar_ad', 'executar_ad'], true)) {

            $pessoa = (new LeitorDoFormmanager($banco))->consultarIdentificadores($rascunho['formulario_id'], $rascunho['chamado_id']);

            $pessoa += ['nome' => $rascunho['nome_civil'], 'login' => $rascunho['conta_atendida'], 'tipo' => $rascunho['tipo'], 'vinculo' => $rascunho['vinculo'] ?? ''];

            $origem = ['chamado_id' => $rascunho['chamado_id'], 'formulario_id' => $rascunho['formulario_id'], 'assinatura' => $base['assinatura_origem']];

            $operador = new OperadorDoDiretorio();

            // Preparar consulta os dois domínios, mas não preenche CPF nem altera validade.

            if ($acao === 'preparar_ad') {

                $rascunho['previa_ad'] = $operador->preparar($pessoa, $origem, $contexto);

            } else {

                // O desafio pertence à prévia atual e não substitui a revisão da origem aprovada.

                $previa = $rascunho['previa_ad'] ?? [];

                // Só o desafio vigente e o aceite explícito podem encaminhar os efeitos revisados.

                if (($entrada['confirmar_ad'] ?? '') !== '1' || !is_string($entrada['desafio_ad'] ?? null) || !isset($previa['desafio']) || !hash_equals($previa['desafio'], $entrada['desafio_ad'])) {

                    throw new DomainException('Confirme os efeitos da prévia atual antes de executar.');

                }

                unset($rascunho['previa_ad']);

                $rascunho['resultado_ad'] = $operador->executar($pessoa, $previa, $origem, $contexto);

            }

            unset($pessoa);

            $rascunho['revisao']++;

            return $rascunho;

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

        // A busca de grupos não concede acesso e mantém referências manuais separadas dos resultados.

        if ($acao === 'buscar_pastas') {

            $consulta = BuscaDePastas::consultar(EntradaDaFerramenta::lerTexto($entrada['busca_pasta'] ?? '', 200));

            $consulta['configuracao'] = hash('sha256', json_encode(ConfiguracaoDoDiretorio::consultar(), JSON_THROW_ON_ERROR));

            $revisado['consulta_pastas'] = $consulta;

            return $revisado;

        }

        // Seleção verificada só aceita objetos presentes no catálogo vigente da sessão.

        if ($acao === 'selecionar_pastas') {

            $consulta = $rascunho['consulta_pastas'] ?? [];

            $chaves = $entrada['grupos_pastas'] ?? [];

            // Expiração ou mudança de conexão impede tratar referências antigas como verificadas.

            if (($consulta['expira'] ?? 0) <= time() || ($consulta['configuracao'] ?? '') !== hash('sha256', json_encode(ConfiguracaoDoDiretorio::consultar(), JSON_THROW_ON_ERROR)) || !is_array($chaves) || !array_is_list($chaves)) {

                throw new DomainException('O catálogo de grupos expirou ou mudou. Pesquise novamente.');

            }

            $catalogo = array_column($consulta['itens'], null, 'chave');

            $revisado['grupos_de_pastas'] = [];

            // Um identificador fora dos resultados interrompe a seleção inteira.

            foreach ($chaves as $chave) {

                // Estruturas aninhadas e valores livres não são grupos encontrados no AD.

                if (!is_string($chave) || !isset($catalogo[$chave])) {

                    throw new DomainException('Grupo fora do catálogo consultado.');

                }

                $revisado['grupos_de_pastas'][$chave] = $catalogo[$chave];

            }

            $revisado['grupos_de_pastas'] = array_values($revisado['grupos_de_pastas']);

            $nomes = [];

            // O resumo registra a seleção revisada sem copiar a resposta LDAP completa.

            foreach ($revisado['grupos_de_pastas'] as $grupo) {

                $nomes[] = $grupo['nome'] . ' (' . $grupo['dominio'] . ')';

            }

            $revisado['grupos_pastas_resumo'] = implode("\n", $nomes);


            return $revisado;

        }

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

