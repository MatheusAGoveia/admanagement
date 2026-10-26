<?php

namespace GlpiPlugin\Admanagement;

use Config;

use Group;

use ITILCategory;

use InvalidArgumentException;

use RuntimeException;

use Session;

/**
 * Centraliza a leitura, a validação e a gravação dos grupos e categorias usados na fila.
 */

final class ConfiguracaoDaFila {

    // O contexto separa as configurações do plugin das demais configurações do GLPI.

    private const CONTEXTO = 'admanagement';

    // As duas listas são armazenadas juntas, em um documento JSON nesta chave.

    private const CHAVE = 'grupos_e_categorias';

    /**
     * Lê as listas globais próprias; valor inválido bloqueia grupos e categorias.
     */

    public static function consultar(): array {

        // Solicita ao GLPI somente a chave usada para guardar as duas listas.

        $valores = Config::getConfigurationValues(self::CONTEXTO, [self::CHAVE]);

        // Sem configuração salva, nenhuma opção fica liberada por padrão.

        $padrao = [

            'grupos_permitidos' => [],

            'categorias_itil' => []

        ];

        // Devolve listas vazias quando a configuração própria ainda não foi salva.

        if (!array_key_exists(self::CHAVE, $valores)) {

            return $padrao;

        }

        try {

            // A leitura exige um documento JSON com as duas listas esperadas.

            $configuracao = json_decode($valores[self::CHAVE], true, 512, JSON_THROW_ON_ERROR);

            // Bloqueia grupos e categorias quando o documento salvo não contém uma estrutura de listas.

            if (!is_array($configuracao)) {

                return $padrao;

            }

            // Bloqueia o recorte quando uma das duas listas obrigatórias está ausente.

            if (!isset($configuracao['grupos_permitidos'], $configuracao['categorias_itil'])) {

                return $padrao;

            }

            // Aplica às listas salvas as mesmas regras de IDs usadas na gravação.

            return [

                'grupos_permitidos' => self::normalizarIds($configuracao['grupos_permitidos']),

                'categorias_itil' => self::normalizarIds($configuracao['categorias_itil']),

            ];

        } catch (\JsonException | InvalidArgumentException | \TypeError $erro) {

            // Trata configuração corrompida como ausência de permissões, sem liberar valores parciais.

            // Dados corrompidos ou IDs inválidos fazem a leitura retornar listas vazias.

            return $padrao;

        }

    }

    /**
     * Valida autorização e catálogos antes de persistir um único documento de configuração.
     */

    public static function salvar( array $entrada ): void {

        // Verifica a permissão de configuração antes de processar os dados recebidos.

        AcessoGlpi::exigirConfiguracao();

        $configuracao = [];

        // Valida separadamente as listas de grupos de acesso e categorias ITIL.

        foreach ([

            'grupos_permitidos',

            'categorias_itil'

        ] as $campo) {

            // Campos ausentes representam uma seleção vazia para a respectiva lista.

            $selecao = [];

            // Usa a seleção enviada somente quando o respectivo campo está presente no formulário.

            if (isset($entrada[$campo])) {

                $selecao = $entrada[$campo];

            }

            // O seletor múltiplo nativo pode enviar string vazia ao remover todas as opções.

            if ($selecao === '') {

                $selecao = [];

            }

            $configuracao[$campo] = self::normalizarIds($selecao);

        }

        // Valida cada lista no catálogo correspondente do GLPI antes de gravar.

        foreach ($configuracao['grupos_permitidos'] as $grupo_id) {

            $grupo = new Group();

            self::conferirItem($grupo, $grupo_id);

        }

        // Confere cada categoria no catálogo GLPI e no escopo nativo de entidades.

        foreach ($configuracao['categorias_itil'] as $categoria_id) {

            $categoria = new ITILCategory();

            self::conferirItem($categoria, $categoria_id);

        }

        // Só persiste o documento depois que todos os IDs passam pelas validações.

        $valor = json_encode($configuracao, JSON_THROW_ON_ERROR);

        Config::setConfigurationValues(self::CONTEXTO, [self::CHAVE => $valor]);

        // O método nativo retorna void; a releitura evita anunciar sucesso sem persistência.

        $valores_gravados = Config::getConfigurationValues(self::CONTEXTO, [self::CHAVE]);

        $valor_gravado = null;

        // Obtém o valor persistido para comparar a gravação com o documento solicitado.

        if (isset($valores_gravados[self::CHAVE])) {

            $valor_gravado = $valores_gravados[self::CHAVE];

        }

        // Recusa anunciar sucesso quando a releitura não confirma a configuração enviada.

        if ($valor_gravado !== $valor) {

            throw new RuntimeException('Não foi possível confirmar a gravação das configurações.');

        }

    }

    /**
     * Confere se o grupo ou a categoria existe e está em uma entidade acessível.
     */

    private static function conferirItem( object $item, int $identificador ): void {

        // Rejeita grupos ou categorias inexistentes no catálogo nativo.

        if (!$item->getFromDB($identificador)) {

            throw new InvalidArgumentException('Selecione somente grupos e categorias existentes no seu escopo de entidades.');

        }

        // Um item recursivo também pode estar disponível nas entidades filhas.

        $recursivo = false;

        // Considera a recursividade do item quando essa informação é fornecida pelo GLPI.

        if (isset($item->fields['is_recursive'])) {

            $recursivo = (bool) $item->fields['is_recursive'];

        }

        $entidade_id = $item->fields['entities_id'];

        // Recusa itens de entidades fora do alcance nativo do administrador.

        if (!Session::haveAccessToEntity($entidade_id, $recursivo)) {

            throw new InvalidArgumentException('Selecione somente grupos e categorias existentes no seu escopo de entidades.');

        }

    }

    /**
     * Aceita apenas listas de IDs positivos, eliminando duplicidades sem coerção permissiva.
     */

    private static function normalizarIds( mixed $valores): array {

        // Rejeita valores isolados e arrays associativos, aceitando apenas listas sequenciais.

        if (!is_array($valores)) {

            throw new InvalidArgumentException('A seleção de grupos e categorias deve ser uma lista.');

        }

        // Rejeita arrays associativos para evitar interpretar chaves arbitrárias como seleção de IDs.

        if (!array_is_list($valores)) {

            throw new InvalidArgumentException('A seleção de grupos e categorias deve ser uma lista.');

        }

        $identificadores = [];

        // Valida cada ID antes de normalizar e eliminar valores repetidos.

        foreach ($valores as $valor) {

            // Aceita inteiros positivos ou strings de dígitos sem zeros à esquerda.

            // O filtro também rejeita valores fora do intervalo de inteiros do PHP.

            if (!is_int($valor)) {

                // Rejeita valores que não sejam inteiros nem strings decimais válidas.

                if (!is_string($valor)) {

                    throw new InvalidArgumentException('A seleção contém um identificador inválido.');

                }

                // Recusa strings numéricas com formato parcial, sinal ou zeros à esquerda.

                if (!preg_match('/^[1-9][0-9]*$/D', $valor)) {

                    throw new InvalidArgumentException('A seleção contém um identificador inválido.');

                }

            }

            $opcoes = ['options' => ['min_range' => 1]];

            // Rejeita IDs não positivos ou fora da capacidade de inteiros do PHP.

            if (filter_var($valor, FILTER_VALIDATE_INT, $opcoes) === false) {

                throw new InvalidArgumentException('A seleção contém um identificador inválido.');

            }

            // Após validar, padroniza os IDs como inteiros.

            $identificadores[] = (int) $valor;

        }

        // Remove IDs repetidos e recompõe os índices para manter o formato de lista.

        $identificadores = array_unique($identificadores);

        return array_values($identificadores);

    }

}

