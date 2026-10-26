<?php

namespace GlpiPlugin\Admanagement;

/**
 * Reúne as permissões do arquivo local e os grupos salvos na configuração da fila.
 */

final class ConfiguracaoDeAcesso {

    /**
     * Combina perfil/entidade locais com os grupos configurados na tela administrativa.
     */

    public static function consultar(): array {

        // A classe fica diretamente em src/; o diretório pai é a raiz do plugin.

        $arquivo_configuracao = dirname(__DIR__) . '/config/acesso.php';

        // Arquivo ausente mantém o acesso sem permissões configuradas.

        if (!is_file($arquivo_configuracao)) {

            return [];

        }

        // O arquivo PHP devolve um array com perfis e entidades permitidos.

        $configuracao = require $arquivo_configuracao;

        // Mantém a política sem permissões quando o arquivo local não devolve um array válido.

        if (!is_array($configuracao)) {

            return [];

        }

        $acesso_salvo = ConfiguracaoOperacional::consultar('acesso_operacional');

        // Combina as duas listas administrativas de perfil e entidade com a configuração local.

        foreach ([

            'perfis_permitidos',

            'entidades_permitidas'

        ] as $campo) {

            // Substitui o valor local somente quando a respectiva lista foi salva administrativamente.

            if (array_key_exists($campo, $acesso_salvo)) {

                $configuracao[$campo] = $acesso_salvo[$campo];

            }

        }

        // Os grupos vêm da tela administrativa e completam a configuração local.

        $configuracao_da_fila = ConfiguracaoDaFila::consultar();

        $configuracao['grupos_permitidos'] = $configuracao_da_fila['grupos_permitidos'];

        return $configuracao;

    }

}

