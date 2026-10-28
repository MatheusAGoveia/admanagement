<?php

namespace GlpiPlugin\Admanagement;

/**

 * Apresenta mapas de setor e perfil com seletores nativos, sem conceder direitos.

 */

final class ApresentacaoDaVisibilidade {

    /**

     * Mostra o mapa atual ou uma recuperação explícita que preserva o documento anterior.

     */

    public static function mostrar(string $url): void {

        try {

            $politica = ConfiguracaoDeVisibilidade::consultar();

        } catch (\DomainException | \InvalidArgumentException $erro) {

            // Documentos antigos não podem ativar grupos ou usuários enquanto aguardam migração.

            self::mostrarRecuperacao($url);

            return;

        }

        $dados = $politica ?? ConfiguracaoDeVisibilidade::vazia();

        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Setores e vínculos de perfil</h2></div><div class="contas-corpo">';

        echo '<p>Os direitos são definidos em Administração → Perfis → ADManagement. Este mapa apenas limita quais setores um perfil com supervisão pode atender. Grupos identificam o responsável atribuído ao chamado e não concedem acesso aos integrantes.</p>';

        echo '<p>O superadministrador nativo vê todos os chamados permitidos nas entidades selecionadas e configura o plugin. O supervisor vê somente os setores vinculados. O técnico com permissão de operação vê somente suas atribuições individuais.</p>';

        self::mostrarSetores($url, $politica, $dados);

        self::mostrarVinculos($url, $politica, $dados);

        self::mostrarFormularioSetor($url, $politica, self::obterEdicao($dados['setores'], $_GET['editar_setor'] ?? null));

        self::mostrarFormularioPerfil($url, $politica, $dados, self::obterEdicao($dados['regras'], $_GET['editar_regra'] ?? null));

        echo '</div></section>';

    }

    /**

     * Distingue migração recuperável de reinicialização de um documento corrompido.

     */

    private static function mostrarRecuperacao(string $url): void {

        $migracao = null;

        try {

            $migracao = ConfiguracaoDeVisibilidade::consultarMigracao();

        } catch (\DomainException | \InvalidArgumentException $erro) {

            // Um documento sem estrutura recuperável exige reinicialização explícita do mapa.

            $migracao = null;

        }

        echo '<section class="contas-cartao mt-3"><div class="contas-corpo"><h2>Atualizar controle por perfil</h2>';

        // A prévia informa precisamente quais dados sobreviverão à migração confirmada.

        if ($migracao !== null) {

            echo '<p class="alert alert-warning">Há uma configuração anterior. A operação permanece bloqueada até a confirmação. Serão preservados ' . count($migracao['setores']) . ' setores e ' . count($migracao['regras']) . ' vínculos administrativos de perfil. Concessões por usuário ou grupo e papéis técnicos antigos não serão convertidos.</p>';

            echo '<p>Setores preservados: ' . htmlescape(implode(', ', array_column($migracao['setores'], 'nome'))) . '.</p>';

            $confirmacao = 'Confirmo preservar o documento anterior em backup e migrar somente os setores e vínculos de perfil apresentados. Nenhuma permissão nativa será concedida automaticamente.';

        } else {

            // A recuperação mantém o original em backup, sem usar permissões parciais.

            echo '<p class="alert alert-danger">O mapa está inválido e não pode ser migrado com segurança. Confirme para preservar o documento original em backup e iniciar um mapa vazio. Direitos nativos de operação continuarão sujeitos às entidades do GLPI; supervisores precisam de novos vínculos.</p>';

            $confirmacao = 'Confirmo preservar o documento anterior em backup e iniciar um mapa de setores vazio.';

        }

        echo '<form method="post" action="' . htmlescape($url) . '">';

        echo \Html::hidden('acao_configuracao', ['value' => 'restaurar_visibilidade']);

        echo \Html::hidden('revisao_documento', ['value' => ConfiguracaoDeVisibilidade::assinaturaDoDocumento()]);

        echo '<label><input type="checkbox" name="confirmar_restauracao" value="1" required> ' . htmlescape($confirmacao) . '</label><button class="btn btn-primary">Confirmar atualização do mapa</button>';

        \Html::closeForm();

        echo '</div></section>';

    }

    /**

     * Lista setores responsáveis sem tratar associação de grupo como permissão.

     */

    private static function mostrarSetores(string $url, ?array $politica, array $dados): void {

        echo '<h3>Setores cadastrados</h3><div class="table-responsive"><table class="table"><thead><tr><th>Setor</th><th>Grupos atribuídos</th><th>Entidades</th><th>Ações</th></tr></thead><tbody>';

        // Os grupos permanecem pareados às entidades de cada setor cadastrado.

        foreach ($dados['setores'] as $setor) {

            echo '<tr><td>' . htmlescape($setor['nome']) . '</td><td>' . htmlescape(self::nomes(\Group::class, $setor['grupos_ids'])) . '</td><td>' . htmlescape(self::nomes(\Entity::class, $setor['entidades_ids'])) . '</td><td><a href="' . htmlescape($url . '?editar_setor=' . $setor['id']) . '">Editar</a>';

            self::mostrarExclusao($url, $politica, 'setores', $setor['id']);

            echo '</td></tr>';

        }

        echo '</tbody></table></div>';

    }

    /**

     * Lista somente vínculos de perfil, mantendo os direitos na matriz oficial.

     */

    private static function mostrarVinculos(string $url, ?array $politica, array $dados): void {

        $nomes = array_column($dados['setores'], 'nome', 'id');

        echo '<h3>Perfis e setores supervisionados</h3><div class="table-responsive"><table class="table"><thead><tr><th>Perfil ativo</th><th>Setores</th><th>Entidades</th><th>Estado</th><th>Ações</th></tr></thead><tbody>';

        // Um mapa existente não habilita o perfil sem seu direito nativo de supervisão.

        foreach ($dados['regras'] as $regra) {

            $selecionados = array_intersect_key($nomes, array_flip($regra['setores_ids']));

            echo '<tr><td>' . htmlescape(self::nomes(\Profile::class, [$regra['alvo_id']])) . '</td><td>' . htmlescape(implode(', ', $selecionados)) . '</td><td>' . htmlescape(self::nomes(\Entity::class, $regra['entidades_ids'])) . '</td><td>' . ($regra['ativa'] ? 'Ativo' : 'Inativo') . '</td><td><a href="' . htmlescape($url . '?editar_regra=' . $regra['id']) . '">Editar</a>';

            self::mostrarExclusao($url, $politica, 'regras', $regra['id']);

            echo '</td></tr>';

        }

        echo '</tbody></table></div>';

    }

    /**

     * Edita a identificação dos responsáveis pelos chamados, sem expandir grupos ou entidades.

     */

    private static function mostrarFormularioSetor(string $url, ?array $politica, array $setor): void {

        echo '<h3>' . ($setor === [] ? 'Cadastrar setor' : 'Editar setor #' . $setor['id']) . '</h3>';

        self::abrirFormulario($url, $politica, 'setores', $setor['id'] ?? null);

        echo '<label class="form-label">Nome do setor<input class="form-control" name="nome_setor" maxlength="200" required value="' . htmlescape($setor['nome'] ?? '') . '"></label>';

        self::mostrarSeletor('Grupos atribuídos aos chamados', \Group::class, 'grupos_ids[]', $setor['grupos_ids'] ?? []);

        self::mostrarSeletor('Entidades do setor', \Entity::class, 'entidades_ids[]', $setor['entidades_ids'] ?? []);

        echo '<p>Seleção exata: subgrupos e entidades filhas precisam ser selecionados explicitamente. Use o grupo apresentado em Atribuído no chamado.</p><button class="btn btn-primary">Salvar setor</button>';

        \Html::closeForm();

    }

    /**

     * Edita o alcance setorial de um perfil central sem duplicar sua concessão de direitos.

     */

    private static function mostrarFormularioPerfil(string $url, ?array $politica, array $dados, array $regra): void {

        echo '<hr><h3>' . ($regra === [] ? 'Vincular perfil aos setores' : 'Editar vínculo #' . $regra['id']) . '</h3>';

        self::abrirFormulario($url, $politica, 'regras', $regra['id'] ?? null);

        echo '<label class="form-label">Perfil supervisor</label>';

        \Dropdown::show(\Profile::class, ['name' => 'perfil_id', 'value' => $regra['alvo_id'] ?? 0, 'condition' => ['interface' => 'central'], 'addicon' => false, 'width' => '100%']);

        echo '<label class="form-label">Setores supervisionados</label>';

        // Diferente de Dropdown::show, showFromArray acrescenta os colchetes da seleção múltipla.

        \Dropdown::showFromArray('setores_ids', array_column($dados['setores'], 'nome', 'id'), ['values' => $regra['setores_ids'] ?? [], 'multiple' => true, 'width' => '100%']);

        self::mostrarSeletor('Entidades em que o vínculo vale', \Entity::class, 'entidades_ids[]', $regra['entidades_ids'] ?? []);

        echo '<label class="form-check"><input class="form-check-input" type="checkbox" name="ativa" value="1"' . (($regra['ativa'] ?? true) ? ' checked' : '') . '> Vínculo ativo</label><button class="btn btn-primary">Salvar vínculo de perfil</button>';

        echo '<p>Exemplo: perfil Supervisor de Rede, com supervisão habilitada no GLPI, vinculado ao setor Rede. Um perfil com apenas operação pessoal não utiliza estes vínculos.</p>';

        \Html::closeForm();

    }

    /**

     * Resolve uma edição somente por ID já presente no documento validado.

     */

    private static function obterEdicao(array $lista, mixed $id): array {

        // Ausência do parâmetro representa um cadastro novo.

        if ($id === null) {

            return [];

        }

        $identificador = EntradaDaFerramenta::lerIdentificador($id);

        // A página não recebe dados administrativos diretamente dos parâmetros GET.

        foreach ($lista as $item) {

            // Somente um cadastro existente pode ser editado.

            if ($item['id'] === $identificador) {

                return $item;

            }

        }

        throw new \InvalidArgumentException('Cadastro de edição não encontrado.');

    }

    /**

     * Abre um formulário com revisão explícita para impedir sobrescritas de abas antigas.

     */

    private static function abrirFormulario(string $url, ?array $politica, string $tipo, ?int $id): void {

        echo '<form method="post" action="' . htmlescape($url) . '">';

        echo \Html::hidden('acao_configuracao', ['value' => 'salvar_visibilidade']);

        echo \Html::hidden('tipo_cadastro', ['value' => $tipo]);

        echo \Html::hidden('cadastro_id', ['value' => $id ?? '']);

        echo \Html::hidden('revisao_politica', ['value' => ConfiguracaoDeVisibilidade::assinatura($politica)]);

    }

    /**

     * Exige confirmação explícita para excluir somente o cadastro apresentado.

     */

    private static function mostrarExclusao(string $url, ?array $politica, string $tipo, int $id): void {

        self::abrirFormulario($url, $politica, $tipo, $id);

        echo '<label><input type="checkbox" name="excluir" value="1" required> Confirmar exclusão</label> <button class="btn btn-outline-danger btn-sm">Excluir</button>';

        \Html::closeForm();

    }

    /**

     * Reutiliza os seletores múltiplos nativos sem criar catálogos paralelos.

     */

    private static function mostrarSeletor(string $rotulo, string $classe, string $nome, array $valores): void {

        echo '<label class="form-label">' . htmlescape($rotulo) . '</label>';

        \Dropdown::show($classe, ['name' => $nome, 'value' => $valores, 'multiple' => true, 'addicon' => false, 'width' => '100%']);

    }

    /**

     * Resolve rótulos pelos modelos do GLPI e mantém recursos indisponíveis identificáveis.

     */

    private static function nomes(string $classe, array $ids): string {

        $nomes = [];

        // A leitura por recurso também se aplica aos nomes dos catálogos.

        foreach ($ids as $id) {

            $item = new $classe();

            $nomes[] = $item->can($id, READ) ? $item->getName() . ' (#' . $id . ')' : '#' . $id . ' (indisponível)';

        }

        return implode(', ', $nomes);

    }

}
