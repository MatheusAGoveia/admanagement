<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

/** Prepara referências de pastas sem declarar existência física nem inventar grupos AD. */
final class BuscaDePastas {

    /** Decodifica os pares caminho/permissão do formmanager e preserva seu texto original. */
    public static function extrairSolicitadas(mixed $valor): array {
        // A origem pode não ter solicitado nenhuma pasta de rede.
        if ($valor === null || $valor === '') {
            return [];
        }
        // JSON é limitado antes de decodificar para não carregar um inventário arbitrário na sessão.
        if (!is_string($valor) || strlen($valor) > 20000) {
            throw new DomainException('O campo de pastas da origem precisa de revisão manual.');
        }
        // JSON malformado deve aparecer como impedimento, nunca como lista vazia confirmada.
        try {
            $lista = json_decode($valor, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // JSON inválido preserva o impedimento em vez de fingir ausência de pasta solicitada.
            throw new DomainException('O JSON de permissões de pastas da origem está inválido.');
        }
        // A estrutura da origem é uma lista de objetos, não um texto HTML.
        if (!is_array($lista) || !array_is_list($lista) || count($lista) > 50) {
            throw new DomainException('A lista de pastas da origem precisa de revisão manual.');
        }
        $pastas = [];
        // Cada permissão permanece associada ao caminho específico recebido.
        foreach ($lista as $item) {
            // Rejeita estrutura divergente sem converter arrays em texto.
            if (!is_array($item) || !is_string($item['caminho'] ?? null) || !is_string($item['permissao'] ?? null)) {
                throw new DomainException('Há uma referência de pasta inválida na origem.');
            }
            $pastas[] = [
                'caminho' => EntradaDaFerramenta::lerTexto($item['caminho'], 1000),
                'permissao' => EntradaDaFerramenta::lerTexto($item['permissao'], 40),
            ];
        }
        return $pastas;
    }

    /** Extrai um termo útil, sem anunciar que o texto já corresponde a um grupo encontrado. */
    public static function termoDoCaminho(string $caminho): string {
        $texto = html_entity_decode(strip_tags($caminho), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/^\s*(ESCRITA|LEITURA)(?:\s*\/\s*ESCRITA)?\s+/iu', '', $texto);
        $texto = preg_replace('/\([^)]*\)/u', '', $texto);
        $partes = preg_split('~[\\\\/]+~u', trim($texto, " \\/\t\r\n"), -1, PREG_SPLIT_NO_EMPTY);
        $termo = trim((string) end($partes));
        return mb_substr($termo, 0, 150, 'UTF-8');
    }

    /** Consulta os domínios configurados, preservando resultados parciais e grupos homônimos. */
    public static function consultar(string $termo): array {
        $grupos = [];
        $falhas = [];
        // Cada domínio é consultado separadamente; erro não será interpretado como ausência.
        foreach (ConfiguracaoDoDiretorio::consultar() as $id => $dominio) {
            // A apresentação guarda somente objetos reais e os metadados necessários à seleção.
            try {
                $leitura = (new LeitorLdap($dominio))->buscarGrupos($termo);
                // Identidade de grupo é domínio e GUID; nome sozinho não elimina ocorrências.
                foreach ($leitura as $grupo) {
                    $grupo['dominio_id'] = (int) $id;
                    $grupo['dominio'] = $dominio['nome'];
                    $grupo['chave'] = $id . ':' . $grupo['guid'];
                    $grupos[] = $grupo;
                }
            } catch (DomainException $erro) {
                // Informa a falha de domínio sem inventar uma linha de grupo.
                $falhas[] = $dominio['nome'] . ': ' . $erro->getMessage();
            }
        }
        // Nenhum domínio configurado é situação diferente de uma busca completa sem resultados.
        if (ConfiguracaoDoDiretorio::consultar() === []) {
            $falhas[] = 'Nenhum domínio LDAP configurado.';
        }
        return ['itens' => $grupos, 'falhas' => $falhas, 'termo' => $termo, 'expira' => time() + 300];
    }
}
