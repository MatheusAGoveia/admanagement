<?php

use GlpiPlugin\Admanagement\AcessoGlpi;

use GlpiPlugin\Admanagement\ApresentacaoDaVisibilidade;

use GlpiPlugin\Admanagement\ConfiguracaoDeVisibilidade;

use GlpiPlugin\Admanagement\PerfilDaFerramenta;

require __DIR__ . '/suporte_perfis.php';

iniciarSessaoDePerfil();

conferirPerfil(!AcessoGlpi::podeAcessar(), 'nenhum direito nativo nega acesso');

$_SESSION['acesso_temporario_para_testes'] = true;

$_SESSION['glpiprofiles'] = [40 => ['plugin_admanagement' => READ | UPDATE]];

$_GET = ['perfil_id' => 40, 'super_administrador' => 1];

conferirPerfil(!AcessoGlpi::podeAcessar(), 'grupo, perfil inativo e parâmetros HTTP não concedem acesso');

iniciarSessaoDePerfil(READ);

conferirPerfil(AcessoGlpi::podeAcessar(), 'READ nativo permite fila pessoal sem mapa ou grupo');

$_SESSION['glpigroups'] = [];

conferirPerfil(AcessoGlpi::podeAcessar(), 'nenhuma associação a grupo é exigida');

$tecnico = AcessoGlpi::obterContextoDoTecnico();

conferirPerfil($tecnico['visibilidade']['papel'] === 'tecnico' && $tecnico['perfil_id'] === 4, 'contexto usa perfil e usuário ativos');

conferirPerfil($tecnico['direitos_perfil'] === ['operar' => true, 'administrar_setores' => false], 'adaptador converte bitmask nativo em capacidades booleanas');

$_SESSION['glpiactiveprofile'][PerfilDaFerramenta::DIREITO] = 0;

conferirPerfil(!AcessoGlpi::podeAcessar(), 'revogação no perfil ativo impede a próxima requisição');

iniciarSessaoDePerfil(READ | UPDATE);

conferirPerfil(!AcessoGlpi::podeAcessar(), 'supervisor sem mapa não recua para técnico');

iniciarSessaoDePerfil(READ);

Config::$valores['visibilidade'] = '{invalido';

conferirPerfil(!AcessoGlpi::podeAcessar(), 'mapa corrompido bloqueia operação');

Config::$valores['visibilidade'] = json_encode(['versao' => 1, 'setores' => [], 'regras' => []]);

conferirPerfil(!AcessoGlpi::podeAcessar(), 'mapa antigo aguarda migração explícita');

iniciarSessaoDePerfil(0, 40);

$_SESSION['glpiactiveprofile']['config'] = UPDATE;

Config::$valores['visibilidade'] = '{invalido';

conferirPerfil(AcessoGlpi::podeConfigurar() && AcessoGlpi::podeAcessar(), 'superadministrador nativo renomeado mantém recuperação');

$_SESSION['glpiactiveprofile']['name'] = 'Outro nome';

conferirPerfil(AcessoGlpi::podeConfigurar(), 'rótulo do perfil não define sua capacidade administrativa');

$_SESSION['glpiactiveprofile']['config'] = 0;

conferirPerfil(!AcessoGlpi::podeConfigurar(), 'administrar perfis sem config UPDATE não configura o plugin');

iniciarSessaoDePerfil(UPDATE);

$_SESSION['glpiactiveprofile']['name'] = 'Super-Admin';

$_SESSION['glpiactiveprofile']['config'] = UPDATE;

conferirPerfil(!AcessoGlpi::podeConfigurar(), 'nome Super-Admin e direito operacional não concedem configuração');

iniciarSessaoDePerfil(READ);

$_SESSION['glpiactiveprofile']['interface'] = 'helpdesk';

conferirPerfil(!AcessoGlpi::podeAcessar(), 'autoatendimento não opera o plugin');

iniciarSessaoDePerfil(READ);

unset($_SESSION['glpiactive_entity']);

conferirPerfil(!AcessoGlpi::podeAcessar(), 'entidade ausente não vira entidade raiz');

iniciarSessaoDePerfil(READ);

$_SESSION['glpiactiveentities'] = [3];

conferirPerfil(!AcessoGlpi::podeAcessar(), 'entidade ativa sem direito nativo não autoriza');

iniciarSessaoDePerfil(READ);

$_SESSION['plugin_ativo'] = false;

conferirPerfil(!AcessoGlpi::podeAcessar(), 'plugin desativado bloqueia entrada');

iniciarSessaoDePerfil();

conferirPerfil(PerfilDaFerramenta::registrarDireito() && ProfileRight::$chaves[PerfilDaFerramenta::DIREITO] === 0, 'instalação registra chave sem conceder direitos');

PerfilDaFerramenta::registrarDireito();

conferirPerfil(ProfileRight::$inclusoes === 1, 'registro repetido não duplica a chave');

iniciarSessaoDePerfil(0, 40);

$_SESSION['glpiactiveprofile']['config'] = UPDATE;

$perfil = new Profile();

$perfil->getFromDB(4);

ob_start();

$mostrou = PerfilDaFerramenta::displayTabContentForItem($perfil);

$html = ob_get_clean();

conferirPerfil($mostrou && str_contains($html, '/front/profile.form.php') && str_contains($html, '_glpi_csrf_token'), 'aba usa destino e fechamento nativos');

conferirPerfil(Profile::$matriz[0]['field'] === PerfilDaFerramenta::DIREITO && array_keys(Profile::$matriz[0]['rights']) === [READ, UPDATE], 'matriz declara exclusivamente capacidades nativas');

iniciarSessaoDePerfil(UPDATE);

conferirPerfil((new PerfilDaFerramenta())->getTabNameForItem($perfil) === '', 'supervisor não configura direitos de perfil');

iniciarSessaoDePerfil(0, 40);

$_SESSION['glpiactiveprofile']['config'] = UPDATE;

$legado = ['versao' => 1, 'setores' => [['id' => 1, 'nome' => 'Rede', 'grupos_ids' => [6], 'entidades_ids' => [2]]], 'regras' => [

    ['id' => 1, 'origem' => 'perfil', 'alvo_id' => 4, 'papel' => 'administrador_setor', 'setores_ids' => [1], 'entidades_ids' => [2], 'ativa' => true],

    ['id' => 2, 'origem' => 'usuario', 'alvo_id' => 9, 'papel' => 'administrador_setor', 'setores_ids' => [1], 'entidades_ids' => [2], 'ativa' => true],

    ['id' => 3, 'origem' => 'grupo', 'alvo_id' => 7, 'papel' => 'administrador_setor', 'setores_ids' => [1], 'entidades_ids' => [2], 'ativa' => true],

]];

Config::$valores['visibilidade'] = json_encode($legado);

$antes = Config::$valores;

$previa = ConfiguracaoDeVisibilidade::consultarMigracao();

conferirPerfil(Config::$valores === $antes && count($previa['setores']) === 1 && count($previa['regras']) === 1, 'prévia preserva setores e ignora usuário e grupo sem gravar');

$revisao = ConfiguracaoDeVisibilidade::assinaturaDoDocumento();

exigirRecusaDePerfil(

    /**

     * Migração depende de confirmação explícita mesmo quando a revisão está correta.

     */

    static function () use ($revisao): void {

        ConfiguracaoDeVisibilidade::restaurar(['revisao_documento' => $revisao]);

    },

    'migração sem confirmação é recusada'

);

ConfiguracaoDeVisibilidade::restaurar(['confirmar_restauracao' => '1', 'revisao_documento' => $revisao]);

$migrada = ConfiguracaoDeVisibilidade::consultar();

conferirPerfil($migrada === $previa && json_decode(Config::$valores['backup_visibilidade_anterior'], true)['documento'] === $antes['visibilidade'], 'migração preserva documento original e mapa recuperável');

iniciarSessaoDePerfil(0);

Config::$valores['visibilidade'] = json_encode($migrada);

conferirPerfil(!AcessoGlpi::podeAcessar(), 'mapa migrado não concede direito nativo');

iniciarSessaoDePerfil(UPDATE);

Config::$valores['visibilidade'] = json_encode($migrada);

conferirPerfil(AcessoGlpi::obterContextoDoTecnico()['visibilidade']['papel'] === 'administrador_setor', 'UPDATE com mapa válido permite somente supervisão setorial');

$_SESSION['glpiactiveprofile']['id'] = 5;

conferirPerfil(!AcessoGlpi::podeAcessar(), 'outro perfil ativo não herda mapa do perfil anterior');

iniciarSessaoDePerfil(0, 40);

$_SESSION['glpiactiveprofile']['config'] = UPDATE;

ob_start();

ApresentacaoDaVisibilidade::mostrar('/plugins/admanagement/front/config.php');

$formulario = ob_get_clean();

conferirPerfil(str_contains($formulario, 'name="perfil_id"') && !str_contains($formulario, 'name="usuario_id"') && !str_contains($formulario, 'name="grupo_id"') && !str_contains($formulario, 'name="papel"'), 'formulário possui somente vínculos de perfil');

echo $GLOBALS['verificacoes_perfil'] . " verificações de acesso por perfil concluídas.\n";
