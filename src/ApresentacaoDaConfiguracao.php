<?php

namespace GlpiPlugin\Admanagement;

/**
 * Apresenta parâmetros administrativos de acesso e leitura, sem oferecer escrita no AD.
 */

final class ApresentacaoDaConfiguracao {

    /**
     * Mostra controles nativos de perfil e entidade protegidos pela entrada administrativa.
     */

    public static function mostrarAcesso( string $url ): void {

        $acesso = ConfiguracaoDeAcesso::consultar();

        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Perfis e entidades autorizados</h2></div><div class="contas-corpo"><form method="post" action="'

            . htmlescape($url)

            . '"><input type="hidden" name="acao_configuracao" value="salvar_acesso">';

        \Dropdown::show(

            \Profile::class,

            [

                'name' => 'perfis_permitidos[]',

                'value' => $acesso['perfis_permitidos'] ?? [],

                'multiple' => true,

                'addicon' => false,

                'width' => '100%'

            ]

        );

        \Dropdown::show(

            \Entity::class,

            [

                'name' => 'entidades_permitidas[]',

                'value' => $acesso['entidades_permitidas'] ?? [],

                'multiple' => true,

                'addicon' => false,

                'width' => '100%'

            ]

        );

        echo '<p>Perfil E grupo E entidade são obrigatórios. Listas vazias bloqueiam a operação. Nenhum direito é concedido automaticamente.</p><button class="btn btn-primary">Salvar acesso</button>';

        \Html::closeForm();

        echo '</div></section>';

    }

    /**
     * Exibe nomes de variáveis de ambiente em vez dos segredos e mantém os atributos pendentes
     * explícitos.
     */

    public static function mostrarDiretorios(string $url ): void {

        echo '<section class="contas-cartao mt-3"><div class="contas-cabecalho"><h2>Conexão própria de leitura com AD</h2></div><div class="contas-corpo"><p>Cadastre somente após confirmar servidor, CA confiável e atributos com a infraestrutura. Nenhum teste de rede ocorre ao salvar.</p>';

        // Lista apenas domínios cadastrados, indicando quais são obrigatórios nas verificações.

        foreach (ConfiguracaoDoDiretorio::consultar() as $id => $dominio) {

            echo '<p>Domínio #' . (int) $id . ': ' . htmlescape($dominio['nome']) . ' — ' . (

                !empty($dominio['obrigatorio']) ? 'obrigatório nas verificações' : 'consulta por seleção'

            ) . '</p>';

        }

        echo '<form method="post" action="'

            . htmlescape($url)

            . '"><input type="hidden" name="acao_configuracao" value="salvar_diretorio">';

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

            echo '<label class="form-label" for="diretorio_'

                . $campo

                . '">'

                . htmlescape($rotulo)

                . '</label><input class="form-control mb-2" id="diretorio_'

                . $campo

                . '" name="'

                . $campo

                . '" value="'

                . ($campo === 'porta' ? '636' : '')

                . '" maxlength="500">';

        }

        echo '<label>Segurança <select name="seguranca" class="form-select"><option value="LDAPS">LDAPS</option><option value="STARTTLS">STARTTLS</option></select></label><br><label><input type="checkbox" name="obrigatorio" value="1" checked> Domínio obrigatório para disponibilidade e duplicidades</label><p>Não há allowlist de OUs, conta modelo ou opção de escrita. A Base DN identifica o domínio consultado.</p><button class="btn btn-primary">Salvar conexão de leitura</button>';

        \Html::closeForm();

        echo '</div></section>';

    }

}

