<?php

namespace GlpiPlugin\Admanagement;

/**
 * Apresenta setores e permissões por usuário, grupo ou perfil com os seletores oficiais do GLPI.
 */

final class ApresentacaoDaVisibilidade {

    /**
     * Mostra concessões existentes e formulários de edição sem gravar durante consultas GET.
     */

    public static function mostrar(

        string $url

    ): void {

        try {

            $politica = ConfiguracaoDeVisibilidade::consultar();

        } catch (\DomainException | \InvalidArgumentException $erro) {

            // O Super-Admin pode reparar documento corrompido sem liberar usuários durante a falha.

            echo '<section class="contas-cartao mt-3"><div class="contas-corpo"><h2>Recuperar configuração de acesso</h2><p class="alert alert-danger">A política de visibilidade está inválida. A operação dos demais usuários permanece bloqueada.</p><form method="post" action="' . htmlescape($url) . '"><input type="hidden" name="acao_configuracao" value="restaurar_visibilidade"><input type="hidden" name="revisao_documento" value="' . ConfiguracaoDeVisibilidade::assinaturaDoDocumento() . '"><label><input type="checkbox" name="confirmar_restauracao" value="1" required> Preservar o documento em backup e reiniciar setores e permissões vazios. Somente o Super-Admin terá acesso até novos cadastros.</label><button class="btn btn-outline-danger">Recuperar política de acesso</button>';

            \Html::closeForm();

            echo '</div></section>';

            return;

        }

        $dados = $politica ?? ['versao' => 1, 'setores' => [], 'regras' => []];

        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Setores e permissões</h2></div><div class="contas-corpo">';

        echo '<p>Super-Admin vê todos os chamados permitidos pelo GLPI e configura o plugin. Administrador de setor vê somente grupos responsáveis dos setores concedidos. Técnico vê somente chamados atribuídos individualmente a ele.</p><p>Permissões por usuário, grupo ou perfil ativo são cumulativas. A concessão administrativa prevalece e fica restrita aos setores escolhidos. Nenhuma regra concede direitos nativos que o GLPI não permita.</p>';

        // A transição não transforma grupos antigos em administradores e só é ativada por salvamento explícito.

        if ($politica === null) {

            echo '<div class="alert alert-warning">Política nova ainda não ativada. O acesso anterior continua como técnico. Ao salvar o primeiro setor ou regra, somente as novas concessões valerão; o Super-Admin mantém acesso. Os parâmetros anteriores serão preservados em backup.</div>';

        }

        echo '<h3>Setores cadastrados</h3><div class="table-responsive"><table class="table"><thead><tr><th>Setor</th><th>Grupos responsáveis</th><th>Entidades</th><th>Ações</th></tr></thead><tbody>';

        // Os grupos responsáveis são atores atribuídos; não são o setor de lotação descrito no formulário.

        foreach ($dados['setores'] as $setor) {

            echo '<tr><td>' . htmlescape($setor['nome']) . '</td><td>' . htmlescape(self::nomes('grupo', $setor['grupos_ids'])) . '</td><td>' . htmlescape(self::nomes('entidade', $setor['entidades_ids'])) . '</td><td><a href="' . htmlescape($url . '?editar_setor=' . $setor['id']) . '">Editar</a>';

            self::mostrarExclusao($url, $politica, 'setores', $setor['id']);

            echo '</td></tr>';

        }

        echo '</tbody></table></div><h3>Concessões de acesso</h3><div class="table-responsive"><table class="table"><thead><tr><th>Origem e destinatário</th><th>Papel</th><th>Setores</th><th>Entidades</th><th>Estado</th><th>Ações</th></tr></thead><tbody>';

        // Apresenta concessões individuais lado a lado com grupos e perfis, incluindo regras inativas.

        foreach ($dados['regras'] as $regra) {

            $nomes_setores = [];

            // Traduz os IDs setoriais sem buscar dados de chamados ou rascunhos.

            foreach ($dados['setores'] as $setor) {

                // Mostra somente setores efetivamente concedidos pela regra desta linha.

                if (in_array($setor['id'], $regra['setores_ids'], true)) {

                    $nomes_setores[] = $setor['nome'];

                }

            }

            echo '<tr><td>' . htmlescape(ucfirst($regra['origem']) . ': ' . self::nomes($regra['origem'], [$regra['alvo_id']])) . '</td><td>' . ($regra['papel'] === 'tecnico' ? 'Técnico' : 'Administrador de setor') . '</td><td>' . htmlescape(implode(', ', $nomes_setores)) . '</td><td>' . htmlescape(self::nomes('entidade', $regra['entidades_ids'])) . '</td><td>' . ($regra['ativa'] ? 'Ativa' : 'Inativa') . '</td><td><a href="' . htmlescape($url . '?editar_regra=' . $regra['id']) . '">Editar</a>';

            self::mostrarExclusao($url, $politica, 'regras', $regra['id']);

            echo '</td></tr>';

        }

        echo '</tbody></table></div>';

        $setor = self::obterEdicao($dados['setores'], $_GET['editar_setor'] ?? null);

        $regra = self::obterEdicao($dados['regras'], $_GET['editar_regra'] ?? null);

        echo '<h3>' . ($setor === [] ? 'Cadastrar setor' : 'Editar setor #' . $setor['id']) . '</h3>';

        self::abrirFormulario($url, $politica, 'setores', $setor['id'] ?? null);

        echo '<label class="form-label">Nome do setor<input class="form-control" name="nome_setor" maxlength="200" required value="' . htmlescape($setor['nome'] ?? '') . '"></label>';

        self::mostrarSeletor('Grupos responsáveis pelos chamados', \Group::class, 'grupos_ids[]', $setor['grupos_ids'] ?? []);

        self::mostrarSeletor('Entidades do setor', \Entity::class, 'entidades_ids[]', $setor['entidades_ids'] ?? []);

        echo '<p>Seleção exata: não inclui subgrupos nem entidades filhas automaticamente. Use o grupo em Atribuído do chamado.</p><button class="btn btn-primary">Salvar setor</button>';

        \Html::closeForm();

        echo '<hr><h3>' . ($regra === [] ? 'Conceder permissão' : 'Editar permissão #' . $regra['id']) . '</h3>';

        self::abrirFormulario($url, $politica, 'regras', $regra['id'] ?? null);

        echo '<label class="form-label">Conceder por<select class="form-select" name="origem">';

        // A origem determina qual dos três seletores será considerado no servidor.

        foreach (['usuario' => 'Usuário individual', 'grupo' => 'Grupo GLPI', 'perfil' => 'Perfil ativo'] as $valor => $rotulo) {

            echo '<option value="' . $valor . '"' . (($regra['origem'] ?? 'usuario') === $valor ? ' selected' : '') . '>' . $rotulo . '</option>';

        }

        echo '</select></label><p>Preencha o destinatário correspondente à origem. Os outros seletores não concedem acesso.</p><label class="form-label">Usuário individual</label>';

        \User::dropdown(['name' => 'usuario_id', 'value' => ($regra['origem'] ?? '') === 'usuario' ? $regra['alvo_id'] : 0, 'right' => 'all', 'entity' => \Session::getActiveEntities(), 'entity_sons' => false, 'width' => '100%', 'ldap_import' => false]);

        // Grupos e perfis usam catálogos nativos sem criar novos registros.

        foreach (['grupo' => \Group::class, 'perfil' => \Profile::class] as $origem => $classe) {

            echo '<label class="form-label">' . ucfirst($origem) . '</label>';

            \Dropdown::show($classe, ['name' => $origem . '_id', 'value' => ($regra['origem'] ?? '') === $origem ? $regra['alvo_id'] : 0, 'addicon' => false, 'width' => '100%']);

        }

        echo '<label class="form-label">Papel<select class="form-select" name="papel"><option value="tecnico">Técnico — somente atribuição pessoal</option><option value="administrador_setor"' . (($regra['papel'] ?? '') === 'administrador_setor' ? ' selected' : '') . '>Administrador dos setores selecionados</option></select></label><label class="form-label">Setores administrados<select class="form-select" name="setores_ids[]" multiple>';

        // A escolha setorial é obrigatória somente para o administrador; técnicos não recebem setores.

        foreach ($dados['setores'] as $item) {

            echo '<option value="' . $item['id'] . '"' . (in_array($item['id'], $regra['setores_ids'] ?? [], true) ? ' selected' : '') . '>' . htmlescape($item['nome']) . '</option>';

        }

        echo '</select></label><p>Para Técnico, deixe os setores vazios. Para Administrador de setor, escolha ao menos um.</p>';

        self::mostrarSeletor('Entidades em que esta concessão vale', \Entity::class, 'entidades_ids[]', $regra['entidades_ids'] ?? []);

        echo '<label class="form-check"><input class="form-check-input" type="checkbox" name="ativa" value="1"' . (($regra['ativa'] ?? true) ? ' checked' : '') . '> Permissão ativa</label><button class="btn btn-primary">Salvar permissão</button>';

        \Html::closeForm();

        echo '<p class="mt-3">Exemplo: usuário escolhido → Administrador de setor → Rede. Esse usuário verá os chamados atribuídos aos grupos do setor Rede, mesmo quando outro técnico estiver responsável. Usuário escolhido → Técnico verá somente suas atribuições individuais. O perfil Super-Admin dispensa estes cadastros.</p></div></section>';

    }

    /**
     * Recupera o cadastro de edição; ID desconhecido não cria um cadastro implícito.
     */

    private static function obterEdicao(

        array $lista,

        mixed $id

    ): array {

        // Ausência do parâmetro representa cadastro novo e não uma edição.

        if ($id === null) {

            return [];

        }

        $identificador = EntradaDaFerramenta::lerIdentificador($id);

        // Procura somente no documento administrativo já validado.

        foreach ($lista as $item) {

            // Devolve a linha correspondente sem aceitar dados de formulário por GET.

            if ($item['id'] === $identificador) {

                return $item;

            }

        }

        throw new \InvalidArgumentException('Cadastro de edição não encontrado.');

    }

    /**
     * Abre um formulário independente, incluindo a versão que evita sobrescrita de aba antiga.
     */

    private static function abrirFormulario(

        string $url,

        ?array $politica,

        string $tipo,

        ?int $id

    ): void {

        echo '<form method="post" action="' . htmlescape($url) . '"><input type="hidden" name="acao_configuracao" value="salvar_visibilidade"><input type="hidden" name="tipo_cadastro" value="' . $tipo . '"><input type="hidden" name="cadastro_id" value="' . ($id ?? '') . '"><input type="hidden" name="revisao_politica" value="' . ConfiguracaoDeVisibilidade::assinatura($politica) . '">';

    }

    /**
     * Exige confirmação explícita para excluir somente o cadastro administrativo da linha.
     */

    private static function mostrarExclusao(

        string $url,

        ?array $politica,

        string $tipo,

        int $id

    ): void {

        self::abrirFormulario($url, $politica, $tipo, $id);

        echo '<label><input type="checkbox" name="excluir" value="1" required> Confirmar exclusão</label> <button class="btn btn-outline-danger btn-sm">Excluir</button>';

        \Html::closeForm();

    }

    /**
     * Compartilha opções seguras de seletores múltiplos sem duplicar a marcação dos catálogos.
     */

    private static function mostrarSeletor(

        string $rotulo,

        string $classe,

        string $nome,

        array $valores

    ): void {

        echo '<label class="form-label">' . htmlescape($rotulo) . '</label>';

        \Dropdown::show($classe, ['name' => $nome, 'value' => $valores, 'multiple' => true, 'addicon' => false, 'width' => '100%']);

    }

    /**
     * Traduz os IDs com modelos nativos e leitura por recurso, escapando os nomes na saída.
     */

    private static function nomes(

        string $origem,

        array $ids

    ): string {

        $classe = match ($origem) {'usuario' => \User::class, 'grupo' => \Group::class, 'perfil' => \Profile::class, 'entidade' => \Entity::class};

        $nomes = [];

        // Um item removido permanece identificável sem expor conteúdo de recurso negado.

        foreach ($ids as $id) {

            $item = new $classe();

            $nomes[] = $item->can($id, READ) ? $item->getName() . ' (#' . $id . ')' : '#' . $id . ' (indisponível)';

        }

        return implode(', ', $nomes);

    }

}

