<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/**
 * Valida a escolha manual contra o catálogo real e sua configuração vigente.
 */

final class DestinoDaOperacao {

    /**
     * Registra apenas escolhas do domínio configurado e das OUs efetivamente consultadas.
     */

    public static function aplicar(

        array $rascunho,

        array $entrada

    ): array {

        // Preserva o destino quando a ação não enviou domínio nem OU.

        if (!array_key_exists('dominio', $entrada) && !array_key_exists('ou', $entrada)) {

            return $rascunho;

        }

        $valor = $entrada['dominio'] ?? $rascunho['dominio'];

        // Limpa domínio, OU e catálogo quando o técnico remove a seleção de domínio.

        if ($valor === '') {

            $rascunho['dominio'] = '';

            $rascunho['ou'] = '';

            unset($rascunho['catalogo_ous']);

            return $rascunho;

        }

        $id = EntradaDaFerramenta::lerIdentificador($valor);

        $dominio = ConfiguracaoDoDiretorio::obter($id);

        // Ao trocar de domínio, limpa a OU e ignora o radio antigo que pertence à árvore anterior.

        if ((string) $rascunho['dominio'] !== (string) $id) {

            $rascunho['dominio'] = (string) $id;

            $rascunho['ou'] = '';

            unset($rascunho['catalogo_ous']);

            return $rascunho;

        }

        $rascunho['dominio'] = (string) $id;

        $ou = EntradaDaFerramenta::lerTexto($entrada['ou'] ?? '', 1000);

        // Não valida uma nova OU quando o formulário não enviou uma seleção de caminho.

        if ($ou === '') {

            return $rascunho;

        }

        $catalogo = $rascunho['catalogo_ous'] ?? [];

        $assinatura = hash('sha256', json_encode($dominio, JSON_THROW_ON_ERROR));

        // Recusa OU fora do catálogo real, catálogo expirado ou catálogo de configuração diferente.

        if (

            ($catalogo['assinatura'] ?? '') !== $assinatura

            || ($catalogo['expira'] ?? 0) < time()

            || !in_array($ou, array_column($catalogo['itens'] ?? [], 'dn'), true)

        ) {

            throw new DomainException('Consulte novamente a árvore e selecione uma OU real do domínio.');

        }

        $rascunho['ou'] = $ou;

        return $rascunho;

    }

    /**
     * Monta árvore PHP expansível; a busca preserva ancestrais e caminhos completos.
     */

    public static function mostrar(

        array $rascunho

    ): void {

        $dominios = ConfiguracaoDoDiretorio::consultar();

        echo '<aside class="contas-cartao contas-destino"><div class="contas-cabecalho"><h2>Domínio e OU</h2><p>Escolha manual. Todas as OUs do domínio estão permitidas.</p></div><div class="contas-corpo">';

        echo '<label class="form-label" for="dominio">Domínio</label><select name="dominio" id="dominio" class="form-select"><option value="">Selecione um domínio configurado</option>';

        // Oferece apenas domínios cadastrados, preservando a escolha atual do técnico.

        foreach ($dominios as $id => $dominio) {

            echo '<option value="'

                . (int) $id

                . '"'

                . ((string) $id === (string) $rascunho['dominio'] ? ' selected' : '')

                . '>'

                . htmlescape($dominio['nome'])

                . '</option>';

        }

        echo '</select><button name="acao" value="consultar_ous" class="btn btn-outline-primary mt-2">Consultar árvore real</button>';

        echo '<label class="form-label mt-3" for="busca_ou">Buscar OU</label><input id="busca_ou" name="busca_ou" class="form-control" maxlength="150" value="'

            . htmlescape($rascunho['busca_ou'] ?? '')

            . '">';

        $itens = $rascunho['catalogo_ous']['itens'] ?? [];

        $busca = mb_strtolower((string) ($rascunho['busca_ou'] ?? ''), 'UTF-8');

        $selecionada = (string) ($rascunho['ou'] ?? '');

        // Exibe o DN completo da OU selecionada para distinguir ramos com o mesmo nome.

        if ($selecionada !== '') {

            echo '<p>OU selecionada: <code>' . htmlescape($selecionada) . '</code></p>';

        }

        // Informa a ausência de catálogo em vez de apresentar destinos inventados.

        if ($itens === []) {

            echo '<p class="contas-nota">Nenhum catálogo consultado. Não há destinos fictícios.</p>';

        }

        $filhos = [];

        $conhecidos = array_column($itens, null, 'dn');

        // Agrupa cada OU sob seu pai conhecido para montar a navegação hierárquica.

        foreach ($itens as $item) {

            $pai = NomeDistinto::pai($item['dn']);

            $filhos[isset($conhecidos[$pai]) ? $pai : ''][] = $item;

        }

        self::mostrarRamo($filhos, '', $selecionada, $busca, 0);

        echo '</div></aside>';

    }

    /**
     * Apresenta nós preservando hierarquia; caminhos iguais são os únicos identificadores da
     * seleção.
     */

    private static function mostrarRamo(

        array $filhos,

        string $pai,

        string $selecionada,

        string $busca,

        int $nivel

    ): void {

        // Apresenta cada filho do ramo atual e percorre recursivamente seus descendentes.

        foreach ($filhos[$pai] ?? [] as $item) {

            // Busca correspondências mais profundas quando o caminho do nó atual não contém o termo pesquisado.

            if ($busca !== '' && !str_contains(mb_strtolower($item['dn'], 'UTF-8'), $busca)) {

                // Ainda percorre filhos: a busca não esconde um ramo correspondente mais profundo.

                ob_start();

                self::mostrarRamo($filhos, $item['dn'], $selecionada, $busca, $nivel + 1);

                $ramo = ob_get_clean();

                // Oculta um ramo sem correspondência própria nem descendentes correspondentes à busca.

                if ($ramo === '') {

                    continue;

                }

            } else {

                // Mantém o ramo atual como correspondência e permite apresentar seus descendentes normalmente.

                $ramo = null;

            }

            echo '<details'

                . ($busca !== '' ? ' open' : '')

                . '><summary>'

                . htmlescape($item['nome'])

                . '</summary><div class="ms-3">';

            echo '<label><input type="radio" name="ou" value="'

                . htmlescape($item['dn'])

                . '"'

                . ($selecionada === $item['dn'] ? ' checked' : '')

                . '> <code>'

                . htmlescape($item['dn'])

                . '</code></label>';

            // Reaproveita o HTML dos descendentes que já foram examinados durante a busca.

            if ($ramo !== null) {

                echo $ramo;

            } else {

                // Percorre os filhos ainda não renderizados para completar a árvore do ramo atual.

                self::mostrarRamo($filhos, $item['dn'], $selecionada, $busca, $nivel + 1);

            }

            echo '</div></details>';

        }

    }

}

