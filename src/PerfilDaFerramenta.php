<?php

namespace GlpiPlugin\Admanagement;

/**

 * Integra permissões do ADManagement à matriz nativa de perfis do GLPI.

 */

final class PerfilDaFerramenta extends \CommonGLPI {

    // A chave nativa é o único cadastro que concede capacidades ao perfil ativo.

    public const DIREITO = 'plugin_admanagement';

    /**

     * Nomeia a aba somente para administradores autorizados a configurar o plugin.

     */

    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0) {

        // A aba só pertence a perfis centrais e não concede configuração a supervisores.

        if (!$item instanceof \Profile || !AcessoGlpi::podeConfigurar() || ($item->fields['interface'] ?? '') !== 'central') {

            return '';

        }

        return 'ADManagement';

    }

    /**

     * Reutiliza formulário, matriz, persistência e proteção CSRF oficiais dos perfis.

     */

    public static function displayTabContentForItem(\CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        // URLs de abas também precisam conferir perfil de destino e autorização atual.

        if (!$item instanceof \Profile || !AcessoGlpi::podeConfigurar() || !$item->can($item->getID(), UPDATE) || ($item->fields['interface'] ?? '') !== 'central') {

            return false;

        }

        echo '<form method="post" action="' . htmlescape(\Profile::getFormURL()) . '">';

        echo \Html::hidden('id', ['value' => $item->getID()]);

        echo '<p>As permissões pertencem exclusivamente ao perfil ativo. Operar permite somente chamados atribuídos pessoalmente ao técnico. Supervisionar exige o vínculo do perfil com setores nas configurações do ADManagement.</p>';

        $item->displayRightsChoiceMatrix([

            [

                'field' => self::DIREITO,

                'label' => 'ADManagement',

                'rights' => [

                    READ => 'Operar atribuições pessoais',

                    UPDATE => 'Supervisionar setores vinculados',

                ],

            ],

        ], ['title' => 'Permissões do perfil', 'canedit' => true]);

        echo '<p>Supervisão prevalece sobre operação pessoal. Um supervisor sem setores vinculados fica sem acesso. Estas permissões não concedem configuração global nem ampliam entidades ou leitura de chamados do GLPI.</p>';

        echo '<button type="submit" name="update" value="1" class="btn btn-primary">Salvar permissões do perfil</button>';

        \Html::closeForm();

        return true;

    }

    /**

     * Registra a chave sem conceder direitos nem converter permissões antigas.

     */

    public static function registrarDireito(): bool {

        $direitos = \ProfileRight::getAllPossibleRights();

        // Atualizações não devem inserir novamente uma chave já registrada.

        if (array_key_exists(self::DIREITO, $direitos)) {

            return true;

        }

        return \ProfileRight::addProfileRights([self::DIREITO]);

    }

}

