<?php

namespace GlpiPlugin\Admanagement;

use Config;

use InvalidArgumentException;

use RuntimeException;

/**
 * Guarda escolhas administrativas próprias sem criar tabelas nem conceder direitos.
 */

final class ConfiguracaoOperacional {

    /**
     * Lê uma chave própria e falha fechada quando o documento salvo não é válido.
     */

    public static function consultar(

        string $chave,

        array $padrao = []

    ): array {

        $valores = Config::getConfigurationValues('admanagement', [$chave]);

        // Usa o padrão informado quando a chave própria ainda não existe no GLPI.

        if (!isset($valores[$chave])) {

            return $padrao;

        }

        try {

            $dados = json_decode($valores[$chave], true, 32, JSON_THROW_ON_ERROR);

            // Interrompe a leitura quando o documento salvo não tem a estrutura esperada.

            if (!is_array($dados)) {

                throw new \DomainException('A configuração própria está inválida.');

            }

            return $dados;

        } catch (\JsonException $erro) {

            // Bloqueia configuração JSON inválida em vez de substituí-la silenciosamente por permissões padrão.

            throw new \DomainException('A configuração própria está inválida. Revise antes de continuar.');

        }

    }

    /**
     * Persiste somente escolhas validadas pelo chamador, usando o direito administrativo nativo.
     */

    public static function salvar( string $chave, array $dados ): void {

        AcessoGlpi::exigirConfiguracao();

        $json = json_encode($dados, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        Config::setConfigurationValues('admanagement', [$chave => $json]);

        // Confirma o salvamento por releitura antes de anunciar sucesso ao administrador.

        if (

            (Config::getConfigurationValues('admanagement', [$chave])[$chave] ?? null) !== $json

        ) {

            throw new RuntimeException('A gravação da configuração não foi confirmada.');

        }

    }

    /**
     * Valida os IDs de perfil e entidade sem aceitar curingas ou valores parcialmente numéricos.
     */

    public static function salvarAcesso( array $entrada ): void {

        $acesso = [];

        // Valida perfis e entidades nos respectivos modelos nativos do GLPI.

        foreach (

            [

                'perfis_permitidos' => \Profile::class,

                'entidades_permitidas' => \Entity::class

            ] as $campo => $classe

        ) {

            $valores = $entrada[$campo] ?? [];

            // Interpreta a seleção vazia do controle nativo como uma lista sem permissões.

            if ($valores === '') {

                $valores = [];

            }

            // Rejeita valores isolados ou arrays associativos no lugar das listas de acesso.

            if (!is_array($valores) || !array_is_list($valores)) {

                throw new InvalidArgumentException('Envie listas de perfis e entidades.');

            }

            $acesso[$campo] = [];

            // Confere cada perfil ou entidade selecionada antes de salvar o conjunto.

            foreach ($valores as $valor) {

                // Permite o ID zero somente para a entidade raiz explicitamente selecionada.

                if ($campo === 'entidades_permitidas' && ($valor === '0' || $valor === 0)) {

                    $id = 0;

                } else {

                    // Exige um identificador positivo para os demais perfis e entidades.

                    $id = EntradaDaFerramenta::lerIdentificador($valor);

                }

                $item = new $classe();

                // Recusa um item inexistente ou uma entidade que o administrador não pode acessar.

                if (

                    !$item->getFromDB($id)

                    || ($campo === 'entidades_permitidas'

                    && !\Session::haveAccessToEntity($id))

                ) {

                    throw new InvalidArgumentException('Há um perfil ou entidade fora do catálogo autorizado.');

                }

                $acesso[$campo][] = $id;

            }

            $acesso[$campo] = array_values(array_unique($acesso[$campo]));

        }

        self::salvar('acesso_operacional', $acesso);

    }

}

