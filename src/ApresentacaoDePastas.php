<?php

namespace GlpiPlugin\Admanagement;

/** Apresenta referências solicitadas, grupos realmente encontrados e entrada manual separadas. */
final class ApresentacaoDePastas {

    /** Renderiza o bloco dentro do formulário PHP existente, sem criar formulários aninhados. */
    public static function mostrar(array $rascunho): void {
        $solicitadas = $rascunho['pastas_solicitadas'] ?? [];
        $consulta = $rascunho['consulta_pastas'] ?? [];
        $termo = $consulta['termo'] ?? BuscaDePastas::termoDoCaminho($solicitadas[0]['caminho'] ?? '');
        echo '<section class="contas-bloco-pastas"><h3 class="contas-subtitulo"><i class="ti ti-folder-search"></i> Acesso a pastas</h3>';
        echo '<p class="contas-nota">Localize grupos G-ADM- por um trecho do nome. Os resultados não comprovam existência física da pasta nem concedem acesso.</p>';
        echo '<details class="mb-3"><summary>Conferir referências originais do formulário</summary>';
        // Os caminhos originais permanecem visíveis para conferir a limpeza dos termos.
        foreach ($solicitadas as $pasta) {
            echo '<div class="contas-origem"><span>' . htmlescape($pasta['permissao']) . '</span><span>' . htmlescape($pasta['caminho']) . '</span></div>';
        }
        echo '</details>';
        echo '<label class="form-label" for="busca_pasta">Buscar grupo por parte do nome</label>';
        echo '<div class="input-group mb-2"><input id="busca_pasta" name="busca_pasta" class="form-control" list="termos_pastas" maxlength="200" value="' . htmlescape($termo) . '" placeholder="Digite um trecho, por exemplo protecaoanimal">';
        echo '<button class="btn btn-outline-primary" name="acao" value="buscar_pastas">Buscar grupos</button></div><datalist id="termos_pastas">';
        // Cada referência da origem oferece um termo inicial, sem inventar um resultado AD.
        foreach ($solicitadas as $pasta) {
            echo '<option value="' . htmlescape(BuscaDePastas::termoDoCaminho($pasta['caminho'])) . '"></option>';
        }
        echo '</datalist>';
        // Falhas não aparecem como linhas de grupo e não são confundidas com ausência.
        foreach ($consulta['falhas'] ?? [] as $falha) {
            echo '<p class="alert alert-warning">' . htmlescape($falha) . '</p>';
        }
        // Catálogo expirado não oferece seleções como se ainda tivessem sido verificadas.
        if (($consulta['expira'] ?? 0) > time()) {
            echo '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Selecionar</th><th>Grupo encontrado</th><th>Domínio e localização</th></tr></thead><tbody>';
            $escolhidos = array_column($rascunho['grupos_de_pastas'] ?? [], 'chave');
            // O valor do checkbox é somente a chave validada contra o catálogo mantido na sessão.
            foreach ($consulta['itens'] ?? [] as $grupo) {
                echo '<tr><td><input class="form-check-input" type="checkbox" name="grupos_pastas[]" value="' . htmlescape($grupo['chave']) . '"' . (in_array($grupo['chave'], $escolhidos, true) ? ' checked' : '') . '></td>';
                echo '<td><strong>' . htmlescape($grupo['nome']) . '</strong><br><small>' . htmlescape($grupo['descricao']) . '</small></td><td>' . htmlescape($grupo['dominio']) . '<br><small>' . htmlescape($grupo['dn']) . '</small></td></tr>';
            }
            echo '</tbody></table></div>';
            // Zero resultados só é ausência confirmada quando a cobertura não contém falhas.
            if (($consulta['itens'] ?? []) === []) {
                echo '<p class="contas-nota">' . (($consulta['falhas'] ?? []) === [] ? 'Nenhum grupo correspondente encontrado.' : 'Consulta incompleta. Não foi possível confirmar ausência de grupos.') . '</p>';
            }
            echo '<button class="btn btn-outline-primary mb-2" name="acao" value="selecionar_pastas">Guardar seleção de grupos</button>';
        }
        // Seleções anteriores permanecem explícitas; este bloco não aplica membros no AD.
        foreach ($rascunho['grupos_de_pastas'] ?? [] as $grupo) {
            echo '<div class="badge bg-blue-lt me-1 mb-1">' . htmlescape($grupo['nome'] . ' · ' . $grupo['dominio']) . '</div>';
        }
        echo '<p class="contas-nota">Para informar do zero, use o campo de pastas manual abaixo. Texto digitado permanece não verificado.</p></section>';
    }
}
