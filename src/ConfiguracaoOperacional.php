<?php

namespace GlpiPlugin\Admanagement;

use Config;

use DomainException;

use JsonException;

use RuntimeException;

/**
 * Persiste documentos próprios na configuração nativa do GLPI, sem conceder direitos.
 */

final class ConfiguracaoOperacional {

    /**
     * Lê uma chave do plugin e recusa documentos inválidos sem liberar valores parciais.
     */

    public static function consultar(

        string $chave,

        array $padrao = []

    ): array {

        $valores = Config::getConfigurationValues('admanagement', [$chave]);

        // Somente a ausência da chave usa o padrão escolhido pelo chamador.

        if (!array_key_exists($chave, $valores)) {

            return $padrao;

        }

        try {

            // Tipos diferentes de texto não representam um documento JSON persistido.

            if (!is_string($valores[$chave])) {

                throw new DomainException('A configuração própria está inválida.');

            }

            $dados = json_decode($valores[$chave], true, 32, JSON_THROW_ON_ERROR);

            // A configuração sempre precisa representar uma lista ou um objeto de trabalho.

            if (!is_array($dados)) {

                throw new DomainException('A configuração própria está inválida.');

            }

            return $dados;

        } catch (JsonException $erro) {

            // Preserva a falha do documento em vez de substituí-lo por permissões padrão.

            throw new DomainException('A configuração própria está inválida. Revise antes de continuar.', 0, $erro);

        }

    }

    /**
     * Exige administração nativa e confirma a persistência antes de anunciar sucesso.
     */

    public static function salvar(

        string $chave,

        array $dados

    ): void {

        AcessoGlpi::exigirConfiguracao();

        $json = json_encode($dados, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        Config::setConfigurationValues('admanagement', [$chave => $json]);

        $persistido = Config::getConfigurationValues('admanagement', [$chave]);

        // O método nativo retorna void; a releitura identifica eventual falha silenciosa.

        if (($persistido[$chave] ?? null) !== $json) {

            throw new RuntimeException('A gravação da configuração não foi confirmada.');

        }

    }

}
