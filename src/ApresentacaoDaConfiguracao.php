<?php

namespace GlpiPlugin\Admanagement;

/**
 * Apresenta parâmetros administrativos de acesso e leitura, sem oferecer escrita no AD.
 */

final class ApresentacaoDaConfiguracao {

    /**
     * Encaminha a configuração de direitos para a aba do plugin nos perfis nativos do GLPI.
     */

    public static function mostrarAcesso(): void {

        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Permissões por perfil</h2></div><div class="contas-corpo">';

        echo '<p>Em Administração → Perfis, abra um perfil da interface central e use a aba ADManagement. O direito do perfil ativo define a operação pessoal ou a supervisão dos setores vinculados abaixo.</p>';

        echo '<p>As entidades continuam limitadas pelas autorizações nativas do GLPI. Os grupos atribuídos aos chamados identificam os setores atendidos.</p>';

        echo '<a class="btn btn-outline-primary" href="'

            . htmlescape(\Profile::getSearchURL())

            . '">Abrir perfis do GLPI</a></div></section>';

    }

    /**
     * Exibe nomes de variáveis de ambiente em vez dos segredos e mantém os atributos pendentes
     * explícitos.
     */

    public static function mostrarDiretorios(

        string $url

    ): void {

        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Conexão própria de leitura com AD</h2></div><div class="contas-corpo"><p>Cadastre somente após confirmar servidor, CA confiável e atributos com a infraestrutura. Nenhum teste de rede ocorre ao salvar.</p>';

        // Lista apenas domínios cadastrados, indicando quais são obrigatórios nas verificações.

        foreach (ConfiguracaoDoDiretorio::consultar() as $id => $dominio) {

            echo '<p>Domínio #' . (int) $id . ': ' . htmlescape($dominio['nome']) . ' — ' . (

                !empty($dominio['obrigatorio']) ? 'obrigatório nas verificações' : 'consulta por seleção'

            ) . '</p>';

        }

        echo '<form method="post" action="'

            . htmlescape($url)

            . '">';

        echo \Html::hidden('acao_configuracao', ['value' => 'salvar_diretorio']);

        $campos = [

            'dominio_id' => 'ID para atualizar (vazio para novo)',

            'nome' => 'Nome do domínio',

            'host' => 'Servidor LDAP (DNS)',

            'porta' => 'Porta',

            'base_dn' => 'Base DN do domínio',

            'bind_env' => 'Nome da variável de ambiente do bind',

            'senha_env' => 'Nome da variável de ambiente da senha',

            'atributo_cpf' => 'Atributo LDAP do CPF (pendente de homologação)',

            'atributo_matricula' => 'Atributo LDAP da matrícula (pendente de homologação)'

        ];

        // Cria os campos administrativos a partir dos rótulos e referências da conexão própria.

        foreach ($campos as $campo => $rotulo) {

            echo '<label class="form-label" for="diretorio_' . $campo . '">'

                . htmlescape($rotulo) . '</label>';

            echo \Html::input(

                $campo,

                [

                    'id' => 'diretorio_' . $campo,

                    'class' => 'form-control mb-2',

                    'value' => $campo === 'porta' ? '636' : '',

                    'maxlength' => 500

                ]

            );

        }

        echo '<label class="form-label" for="dropdown_seguranca_admanagement">Segurança</label>';

        \Dropdown::showFromArray(

            'seguranca',

            ['LDAPS' => 'LDAPS', 'STARTTLS' => 'STARTTLS'],

            [

                'value' => 'LDAPS',

                'rand' => '_admanagement',

                'width' => '100%',

                'noselect2' => true

            ]

        );

        echo '<br><label><input type="checkbox" name="obrigatorio" value="1" checked> Domínio obrigatório para disponibilidade e duplicidades</label><p>Não há allowlist de OUs, conta modelo ou opção de escrita. A Base DN identifica o domínio consultado.</p><button class="btn btn-primary">Salvar conexão de leitura</button>';

        \Html::closeForm();

        echo '</div></section>';

    }

}

