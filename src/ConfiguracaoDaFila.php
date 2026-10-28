<?php

namespace GlpiPlugin\Admanagement;

use DomainException;

use InvalidArgumentException;

use ITILCategory;

use Session;

/**
 * Limita a importação às categorias operacionais; autorização pertence ao perfil ativo.
 */

final class ConfiguracaoDaFila {

    // Mantém a chave instalada para aproveitar categorias sem reutilizar grupos de acesso.

    private const CHAVE = 'grupos_e_categorias';

    /**
     * Configuração ausente ou inválida bloqueia importação, sem esconder chamados autorizados.
     */

    public static function consultar(): array {

        try {

            $configuracao = ConfiguracaoOperacional::consultar(self::CHAVE);

            return [

                'categorias_itil' => EntradaDaFerramenta::lerListaDeIdentificadores(

                    $configuracao['categorias_itil'] ?? []

                ),

            ];

        } catch (DomainException | InvalidArgumentException $erro) {

            // Categorias inválidas nunca produzem uma autorização parcial de importação.

            return ['categorias_itil' => []];

        }

    }

    /**
     * Confere categorias e entidades no catálogo nativo antes de salvar o documento próprio.
     */

    public static function salvar(

        array $entrada

    ): void {

        AcessoGlpi::exigirConfiguracao();

        $categorias = EntradaDaFerramenta::lerListaDeIdentificadores($entrada['categorias_itil'] ?? []);

        // Cada categoria precisa existir e estar disponível nas entidades do administrador.

        foreach ($categorias as $categoria_id) {

            $categoria = new ITILCategory();

            // Recusa itens inexistentes antes de consultar seus dados de entidade.

            if (!$categoria->getFromDB($categoria_id)) {

                throw new InvalidArgumentException('Selecione somente categorias existentes no seu escopo de entidades.');

            }

            $entidade_id = $categoria->fields['entities_id'];

            $recursiva = (bool) ($categoria->fields['is_recursive'] ?? false);

            // Respeita a recursividade nativa sem ampliar o alcance do administrador.

            if (!Session::haveAccessToEntity($entidade_id, $recursiva)) {

                throw new InvalidArgumentException('Selecione somente categorias existentes no seu escopo de entidades.');

            }

        }

        ConfiguracaoOperacional::salvar(self::CHAVE, ['categorias_itil' => $categorias]);

    }

}
