<?php

namespace GlpiPlugin\Admanagement;

/**

 * Integra permissões do ADManagement à matriz nativa de perfis do GLPI.

 */

final class PerfilDaFerramenta extends \CommonGLPI {

    // A chave nativa é o único cadastro que concede capacidades ao perfil ativo.

    public const DIREITO = 'plugin_admanagement';

    public const DIREITO_LOTES = 'plugin_admanagement_lotes';

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

            [
                'field' => self::DIREITO_LOTES,
                'label' => 'Lotes independentes de chamados',
                'rights' => [UPDATE => 'Revisar e executar renovação/bloqueio em lotes'],
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

        $novos = array_values(array_diff([self::DIREITO, self::DIREITO_LOTES], array_keys($direitos)));

        // Registrar direito não concede seu valor a nenhum perfil.

        if ($novos === []) {

            return true;

        }

        return \ProfileRight::addProfileRights($novos);

    }

}
