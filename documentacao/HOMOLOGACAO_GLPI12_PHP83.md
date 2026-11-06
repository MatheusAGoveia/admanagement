# ADManagement 0.7.1 — GLPI 12 e PHP 8.3

Verificação em **06/11/2026**. A adaptação do ADManagement passou pelas onze suítes próprias e por instalação, atualização e requisições HTTP no **GLPI 12.0.1 oficial com PHP 8.3.36 real**. O resultado autoriza o teste desta entrega nessa matriz; não certifica uma instalação customizada que não foi acessada.

## Base preservada e matriz

- Fonte: `Desktop/admanagement`, revisão anterior `28c5a2d`, versão anterior 0.7.0. Branch da adaptação: `codex/admanagement-glpi12-php83`.
- A cópia completa anterior foi restaurada e seus 212 arquivos foram comparados por SHA-256 antes das alterações. O backup fica fora da árvore do GLPI.
- A organização permanece com **31 classes próprias em `src/` e sete entradas PHP em `front/`**. Não foi incorporado código do PlguinOfGLPI.
- O pacote aceita a release **12.0.1**. Os metadados declaram GLPI >=12.0.1 e <12.0.2; a verificação própria também recusa sufixos de desenvolvimento. GLPI 10, 11, 12.0.0, próximas correções e outras linhas não integram esta entrega.
- Requisito declarado: PHP >=8.3 e <9.0, JSON e mbstring. A versão efetivamente exercitada foi **8.3.36, 64 bits**, tanto na CLI quanto no servidor HTTP. O intervalo declarado não significa que todas as versões PHP tenham sido homologadas.
- Banco efetivo do laboratório: **10.11.19-MariaDB**, confirmado por `SELECT VERSION()`. O banco e o servidor HTTP são exclusivos e limitados a loopback.

O arquivo oficial `glpi-12.0.1.tgz` foi conferido com SHA-256 `bf89a1be40ea76e4e7a6a5cea8fd24c9d822fab83e4e90519ff0c8d062f13291`. A tag resolve para o commit `7edb847cceb7ebf950171f6ab031aea8b95e45b1`.

O PHP particular foi extraído do pacote oficial `php-8.3.36-1-Win32-vs16-x64.zip`, SHA-256 `126dd3cc8c19df5085b9420b2bc74ed591de3d98528858c52fc42739b7bba92e`. O PHP global e seu INI não foram substituídos. O processo HTTP utiliza o mesmo executável 8.3.36 com INI particular; isso foi conferido separadamente da execução CLI. Estavam carregadas JSON, mbstring, mysqli, intl, curl, gd, fileinfo, zip, LDAP e OpenSSL.

## Alterações necessárias

1. `setup.php` passa a identificar a versão 0.7.1, declarar a matriz e conferir PHP/extensões também na instalação manual. Os seis hooks, a identidade `admanagement` e as proteções contra descoberta em pasta incorreta/redeclaração permanecem.
2. `composer.json` passa a exigir PHP >=8.3. Não houve introdução de biblioteca ou instalação de dependência do produto.
3. As sete entradas deixam de incluir `inc/includes.php`, obsoleto no 12. O kernel carrega a aplicação e seu controlador inclui a entrada. Os `global $DB` necessários foram preservados.
4. Os formulários mantêm `Html::closeForm()`. Comentários e dublês deixam de exigir o token CSRF oculto anterior; a validação por origem pertence ao kernel. Redirecionamentos e recusas continuam propagados ao GLPI, e saídas inalcançáveis após `Html::redirect()` foram retiradas.
5. `AcessoGlpi::exigirConfiguracao()` verifica a autorização e, em seguida, solicita reautenticação nativa se necessária. No ensaio real, `Group::can(READ)` recusava grupos por falta de identidade recente, mas a configuração apresentava a recusa como grupo inacessível. O prompt nativo resolve essa diferença sem relaxar direitos. `podeConfigurar()` continua uma consulta sem redirecionamento.
6. Os testes de requisitos, formulários e acesso foram adequados ao alvo. Há cobertura nova para limites de versões/extensões e para GET/POST administrativos sem reautenticação.

As assinaturas herdadas de `CommonGLPI` por `PerfilDaFerramenta` já correspondem à release investigada e **não precisaram de alteração**. A chave `plugin_admanagement`, os direitos READ/UPDATE, a matriz nativa e a persistência oficial permanecem.

## Fontes e alcance das conclusões

A pesquisa utilizou explicitamente a skill `glpi-official-docs-research`. As fontes abaixo foram abertas/lidas; as referências GLPI estão fixadas no commit da release 12.0.1. Data de consulta: **06/11/2026**. Confiança alta significa suporte direto no código ou no ensaio descrito, limitado à versão e ao caminho indicado.

| Questão e conclusão | Tipo / confiança | Fonte oficial e revisão | Aplicabilidade e lacuna |
| --- | --- | --- | --- |
| Qual release foi escolhida? 12.0.1 é a release oficial marcada como atual na consulta. | Documentada / alta | [Release 12.0.1](https://github.com/glpi-project/glpi/releases/tag/12.0.1), commit `7edb847c…`. | Define o alvo local; não identifica a versão do servidor do usuário. |
| Quem carrega as entradas? O controlador nativo inclui o arquivo dentro da aplicação inicializada. | Observada no código / alta | [LegacyFileLoadController](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/Glpi/Controller/LegacyFileLoadController.php). | Sete entradas exercitadas via kernel; acesso direto ao arquivo sem roteamento do core não foi adotado. |
| Ainda se deve incluir o carregamento legado? O arquivo apenas sinaliza obsolescência. | Observada no código / alta | [inc/includes.php](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/inc/includes.php). | Inclusões retiradas do plugin para este alvo. |
| Como os POSTs são protegidos? O listener valida `Sec-Fetch-Site` e usa `Origin`/`Host` como alternativa. | Observada no código / alta | [CheckCsrfListener](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/Glpi/Kernel/Listener/ControllerListener/CheckCsrfListener.php). | POST legítimo e origem cruzada recusada foram ensaiados no core real. Proxy/TLS do servidor não foram ensaiados. |
| Uma recusa de `can()` sempre significa falta de direito? Não: a reautenticação pode ser a condição pendente. | Observada no código / alta | [CommonDBTM::can/check](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/CommonDBTM.php) e [CommonGLPI](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/CommonGLPI.php). | Falha reproduzida na configuração administrativa; correção restrita a essa entrada. |
| Como confirmar identidade recente? O gerenciador nativo redireciona ao prompt e preserva os dados necessários à retomada. | Observada no código / alta | [ReAuthManager](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/Glpi/Security/ReAuth/ReAuthManager.php). | Retomadas GET e POST conferidas por HTTP, incluindo gravação após o POST retomado; interrupção prévia de GET/POST coberta nas suítes. Não é aprovação de solicitação. |
| O contrato de reautenticação tem teste oficial? Há asserções de validade, redirecionamento e preservação dos dados de POST. | Observada em teste / alta | [ReAuthManagerTest](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/tests/functional/Glpi/Security/ReAuth/ReAuthManagerTest.php). | Teste oficial lido, **não executado**. As execuções registradas aqui são das suítes próprias e do ensaio nativo do plugin. |
| É necessário alterar as assinaturas de abas? As assinaturas do plugin continuam compatíveis com o pai da release. | Observada no código / alta | [CommonGLPI](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/CommonGLPI.php), `getTabNameForItem` e `displayTabContentForItem`. | Classe carregada e matriz renderizada no laboratório; não se presume assinatura de branches futuras. |
| De onde veio o PHP 8.3? Pacote Windows oficial, hash conferido e versão consultada no executável. | Documentada e observada localmente / alta | [Metadados oficiais PHP Windows](https://downloads.php.net/~windows/releases/releases.json), pacote 8.3.36-1. | CLI e HTTP locais 8.3.36. Não certifica FPM/Apache do servidor. |

Não foi localizado um arquivo de teste dedicado ao `CheckCsrfListener` na árvore da revisão consultada; essa limitação não equivale à inexistência de cobertura indireta. A conclusão sobre CSRF usa o listener exato e o ensaio HTTP próprio. Nenhuma conclusão depende de um branch `master`/`develop`, de resumo automático ou da pesquisa histórica de GLPI 10.0.18/11.

## Verificação executada

As onze suítes já passavam antes da adaptação, inclusive no PHP 8.3 particular. Após a alteração, a execução final no **PHP 8.3.36** terminou com código zero em todas:

| Suíte própria | Resultado final |
| --- | --- |
| `acesso` | 33 verificações: perfil, entidade, administração, ordem da reautenticação e ausência de concessão por grupo. |
| `carregamento` | 17 cenários: descoberta, nomes de pasta, colisão de funções, releases, PHP e ausência real de mbstring no subprocesso. |
| `refatoracao` | 165 verificações; assinatura final `274ed95ff95309319e4a8589a20c3d1da217d2ef7ff88430df3eecfb70402066`. |
| `fluxo` | 34 verificações. |
| `fluxo_perfis` | 26 verificações HTTP isoladas, incluindo GET/POST sem reautenticação. |
| `regras_http` | 32 verificações de revisão. |
| `regras_vigentes` | 81 verificações. |
| `concorrencia` | Quatro processos: uma reserva, três bloqueios e documento íntegro. |
| `visibilidade` | 51 verificações. |
| `consultas_nativas` | 29 verificações de contratos nativos. |
| `documentacao` | 53 arquivos PHP; 1.109 declarações/controles com comentários. |

Os **53 arquivos PHP** também passaram em `php -l`. A assinatura HTML de referência mudou pela retirada dos tokens artificiais dos dublês; as regras operacionais e de acesso não foram substituídas. As suítes `tests/` continuam isoladas, sem conexão ao AD ou banco real.

Separadamente dessas suítes, foi instalado um **GLPI real e banco MariaDB real de laboratório**, sem alterações nos arquivos do core, para ensaiar:

- Instalação e ativação nativas do ADManagement 0.7.1. Nenhum perfil padrão recebeu concessão automática.
- As sete entradas com HTTP 200 no escopo autorizado: `overview.php`, `index.php`, `historico.php`, `pending.php`, `audit.php`, `config.php` e `revisao.php`.
- **20 verificações HTTP do fluxo**: configuração salva/relida, vínculo de rede, nome social, edição/escape, conflito de revisão antiga (409), POST de origem cruzada (403), resumo, confirmação somente do trabalho, consumo único do desafio, auditoria e descarte.
- **30 verificações HTTP por perfil na repetição final**: técnico pessoal, supervisor setorial, histórico solucionado, recusas por URL/POST direto, configuração administrativa restrita, ausência de acesso apenas por pertencer a grupo, importação de criação/renovação e perda/restauração do setor. O cadastro inicial de setor e vínculo também foi feito por formulário nativo antes da repetição.
- POST administrativo sem identidade recente: prompt nativo e retomada do POST com configuração salva/relida, em ensaio adicional ao fluxo GET.
- Renderização da matriz do plugin na classe nativa de perfil, com as assinaturas reais, sem o token oculto antigo. Os direitos de teste foram configurados por `ProfileRight`, não por escrita direta em tabela GLPI.
- Atualização nativa de um **registro controlado de versão 0.7.0** para 0.7.1 e repetição forçada do instalador. Os hashes da configuração, dos onze registros de direitos e dos dois arquivos privados foram idênticos antes/depois; versão 0.7.1 ativa ao final. O registro anterior foi preparado pela classe oficial `Plugin`, exclusivamente no banco novo.

O último cenário comprova o caminho do instalador e a preservação dos documentos existentes. **Não foi executada uma migração completa de banco/core GLPI 11 para 12**, nem restaurado um dump do servidor do usuário.

## Dados e evidências locais

O laboratório fica em `Refatoracao No Codigo Dp ADMANGEte/trabalho/glpi12-php83-20261106`, fora da fonte distribuída. Os arquivos de evidência incluem `final83-*.txt`, `resultado-web.json`, `resultado-perfis-web.json`, `resultado-retomada-post.json`, `ciclo-antes.json`, `ciclo-atualizacao.json` e `ciclo-repeticao.json`. Os JSONs de ciclo contêm hashes/contagens, sem conteúdo de configuração, senhas ou dados pessoais.

Os chamados, usuários e perfis do ensaio são sintéticos e existem somente no banco novo local. A tabela mínima de origem é identificada por comentário `FIXTURE_LOCAL_20261106_NAO_E_FORMMANAGER`: permite conferir o adaptador sem instalar/executar o formmanager original. **Isso não comprova o esquema ou o comportamento do formmanager instalado.** Não foi introduzida simulação, fallback fictício ou credencial no produto.

## Limites e próximos testes no servidor

1. Confirmar **GLPI 12.0.1** e PHP web 8.3 de 64 bits, extensões e requisitos do próprio core no servidor. A versão local não representa o servidor.
2. Comparar a tabela corrente, seus tipos e `tickets_id_rede` com o formmanager instalado. A importação continua recusando esquema divergente, ausência/ambiguidade de vínculo, aprovação insuficiente ou recurso fora do escopo.
3. Conferir usuários/perfis/setores/categorias reais, escrita privada pelo usuário do PHP e a reautenticação/CSRF atrás de HTTPS e eventual proxy. O laboratório usou Windows e o servidor HTTP do PHP, sem ensaio visual automatizado de navegador, Linux/FPM, Apache/Nginx, MySQL ou outros plugins instalados.
4. Configurar a conexão própria LDAP de leitura e homologar DNS, certificados, bind, busca de OU, atributos, paginação e cobertura entre domínios. A extensão LDAP estava carregada, mas **nenhum diretório real foi conectado** nesta adaptação.
5. Definir/homologar os adaptadores de escrita, atributos, validade e efeitos de grupos/Scripts/pastas, identidade de renovação, texto padrão e transição/efeitos do chamado antes de implementar criação/renovação ou finalização.

Nenhuma gravação no AD e nenhum fechamento de chamado pelo plugin foram habilitados. A confirmação continua registrando somente a revisão. As regras de perfil ativo, setor/entidade, atribuição pessoal, aprovação, categoria, revisão explícita e proteção contra repetição permanecem.

## Distribuição e retorno

O ZIP de instalação tem raiz única **`admanagement/setup.php`** e contém somente runtime, Composer e documentação. Não inclui `.git`, `tests/`, laboratório, dados privados, configurações de ambiente ou credenciais. Cada entrada do ZIP é conferida contra o fonte por SHA-256; o hash do arquivo final acompanha a entrega fora da árvore do plugin.

Para atualizar, preserve os documentos no banco e `GLPI_VAR_DIR/_plugins/admanagement`, substitua os arquivos na pasta única `admanagement` e execute a atualização/ativação nativa. Nunca deixe outra cópia do plugin ou backup em uma pasta descoberta pelo GLPI. O backup anterior atende à matriz antiga; restaurar somente o plugin **não** reverte uma atualização do core/banco GLPI.
