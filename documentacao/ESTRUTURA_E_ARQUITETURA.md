# Estrutura e arquitetura do ADManagement

Base: código local 0.8.1 em 12/11/2026, para GLPI 12.0.0/12.0.1 e PHP 8.3. O [mapa mental](MAPA_MENTAL.html) organiza responsabilidades e arquivos; o [UML](UML_CLASSES.html) apresenta as 31 classes anteriores à ampliação para 45; consulte também o relatório de serviços, pastas e lotes, suas assinaturas e dependências identificadas no fonte. A adaptação conserva a estrutura e as regras existentes; veja a [correção para 12.0.0](CORRECAO_GLPI1200_2026-11-12.md) e o [relatório anterior de compatibilidade](HOMOLOGACAO_GLPI12_PHP83.md).

## Organização

O plugin é PHP com páginas renderizadas no servidor. O GLPI fornece sessão, perfis, direitos, entidades, conexão de banco e componentes de apresentação. As entradas `front/` coordenam requisições; `src/` reúne políticas, adaptadores, persistência e apresentação. Classes puras de negócio não herdam um modelo GLPI sem necessidade.

```text
admanagement/
├── setup.php           Identidade, faixa de versões, menu e aba nativa de perfil
├── hook.php            Registro idempotente do direito nativo, sem concessões
├── composer.json       Requisitos, PSR-4 e atalhos de testes
├── front/              Entradas HTTP
├── src/                45 classes próprias
├── tests/              Quatorze suítes e dublês isolados
├── documentacao/       Mapa mental, UML e guias
└── README.md           Instalação e manutenção
```

Os arquivos legados `config/acesso.php`, `ControleDeAcesso` e `ConfiguracaoDeAcesso` foram retirados. O acesso não depende de listas locais, associação a grupos, concessões pessoais ou exceção de laboratório.

## Recursos nativos reutilizados

| Recurso GLPI | Uso no plugin |
| --- | --- |
| `CommonGLPI`, `Profile`, `ProfileRight` | `PerfilDaFerramenta` herda o contrato de abas e apresenta a matriz nativa no perfil; o GLPI persiste os direitos. |
| `Session::getCurrentSessionInfo`, `Session::haveRight` | Identidade e capacidade exclusivamente do perfil ativo; o bitmask é convertido para booleano na fronteira do adaptador. |
| `Profile::getSuperAdminProfilesId` e `config/UPDATE` | Administração global sem comparar o nome do perfil. |
| `Ticket`, `Ticket_User`, `Group_Ticket`, `CommonITILActor` | Estado, tabelas e atores atribuídos; leitura por recurso continua obrigatória. |
| `Group_User::getGroupUsers`, `DbUtils::formatUserName` | Catálogo e nomes dos técnicos da equipe autorizada. |
| `Ticket::getStatus`, `Ticket::getFormURLWithID`, `Html::convDateTime` | Status, links e datas SQL apresentados conforme o GLPI. |
| `Dropdown`, `Html::input`, `Html::hidden`, `Html::closeForm` | Seletores, campos, escape e fechamento do formulário. No GLPI 12, o kernel verifica CSRF por cabeçalhos de origem. `showFromArray` acrescenta colchetes automaticamente quando múltiplo; `show` preserva o nome recebido. |
| `Config::getConfigurationValues`, `Config::setConfigurationValues` | Persistência dos documentos administrativos próprios. |
| `ReAuthManager::checkReAuthenticationOrRedirect` | Confirmação nativa de identidade recente antes dos catálogos sensíveis da configuração administrativa. |

As APIs desta entrega foram conferidas no código oficial do GLPI 12.0.1 e exercitadas no laboratório com PHP 8.3.36 web/CLI. A pesquisa anterior de GLPI 11 permanece histórica e não define a matriz atual. A assinatura de abas de `CommonGLPI` continua compatível, sem alteração preventiva em `PerfilDaFerramenta`. Isso não constitui homologação da instalação customizada. LDAP, assinatura do resumo e regras de importação continuam próprios porque possuem regras que não equivalem às funções gerais do GLPI.

## Autorização e fluxo

1. `AcessoGlpi` exige plugin ativo, login, interface central e entidades nativas. READ permite atribuição pessoal; UPDATE exige mapa de setores; o superadministrador nativo configura e acompanha seu alcance global.
2. `PoliticaDeVisibilidade` resolve capacidades e pares de grupos atribuídos/entidades de cada setor. O mapa limita supervisão, sem conceder direitos.
3. `ConsultaDeChamados` reutiliza o mesmo critério na fila, histórico, ID direto e metadados. Paginação conta apenas resultados autorizados por `Ticket::can(READ)`, em lotes de memória limitada. Páginas avançadas precisam percorrer os candidatos anteriores.
4. `LeitorDoFormmanager` lê a origem corrente; `ImportadorDaSolicitacao` exige vínculo único de rede, aprovação status 3, solicitação de rede, entidade correspondente e tipo `CADASTRO`, `RENOVAR USUÁRIO` ou `BLOQUEIO DE ACESSO`. Categorias restringem apenas importação.
5. A importação explícita cria rascunho na sessão. `RevisorDaOperacao` aplica somente campos permitidos; revisões esperadas protegem abas concorrentes. Reimportação exige confirmação.
6. Comandos explícitos consultam OUs, disponibilidade e duplicidades pelo adaptador LDAP de leitura. TLS e validação de certificado são obrigatórios; falhas mantêm cobertura incompleta.
7. `RevisaoDaOperacao` assina conteúdo, origem, perfil, direitos, entidades, mapa e evidências. Resumo dura 600 segundos; catálogo e consultas LDAP duram 300 segundos. O desafio é consumido ao confirmar.
8. Auditoria registra metadados mínimos. Criação mantém a confirmação local. Renovação/bloqueio possuem prévia própria e execução explícita, com revalidação nos dois domínios, bloqueio por homônimo, reserva por conta e confirmação por releitura. O chamado não é finalizado automaticamente.

O controlador relê origem e autorização por requisição. A entidade ativa participa da assinatura de acesso; a entidade do chamado identifica a autoria e o armazenamento do rascunho. Mudanças observadas invalidam o resumo, sem restaurar um desafio ao retornar ao contexto anterior.

## Entradas HTTP

| Entrada | Responsabilidade |
| --- | --- |
| `front/index.php` | Fila autorizada e explicação dos impedimentos de importação. |
| `front/revisao.php` | Importação, correção, consultas explícitas, resumo, confirmação e descarte local. |
| `front/lotes.php` | Importação XLSX/CSV, revisão, consulta, confirmação, execução por linha e reconciliação; direito nativo adicional e autoria/entidade conferidos. |
| `front/config.php` | Categorias, orientação de direitos, mapa perfil/setor e parâmetros LDAP. |
| `front/overview.php` e `front/historico.php` | Visão geral e solucionados/fechados, com filtro autorizado de técnico. |
| `front/pending.php` | Pendências atuais e orientação sobre resultados duráveis. |
| `front/audit.php` | Até cem eventos no escopo atual; técnico vê suas ações, administradores veem chamados de seu alcance. |

## Armazenamento e segurança do estado

| Dados | Local e responsável |
| --- | --- |
| Direitos do perfil | `glpi_profilerights`, administrados pelos modelos nativos. |
| Chamados, atribuições e usuários | Banco GLPI, com conexão já aberta e modelos nativos; consultas de leitura. |
| Origem do formulário | `glpi_plugin_formmanager_forms_atual`, por `LeitorDoFormmanager`, sem alterar o outro plugin. |
| Mapa, categorias e conexões | `Config` no contexto `admanagement`. Chave antiga `grupos_e_categorias` conserva categorias; grupos antigos não autorizam. |
| Rascunhos e evidências | Sessão, separados por técnico, entidade do chamado e chamado. |
| Eventos e contratos duráveis | `GLPI_VAR_DIR/_plugins/admanagement/estado.json`, por `RepositorioDeOperacoes`, com lock e substituição atômica. |
| Lotes e efeitos AD | Modelos `Lote` e `ExecucaoDoDiretorio`, herdados de `CommonDBTM`; tabelas próprias, índices de necessidade/alvo e metadados minimizados. Sem CRUD público genérico. |
| Credenciais LDAP | Variáveis de ambiente; a configuração guarda somente seus nomes. |

CPF e respostas LDAP completas não integram o resumo ou a auditoria mínima. `comAuditoria` reverte o rascunho se a gravação durável falhar. Isso não cria uma transação distribuída entre sessão, banco e AD.

Contratos de reserva, posse, tentativa e resultado continuam separados dos efeitos externos. Resultado incerto exige reconciliação; sucesso AD comprovado permite apenas a finalização do chamado original. A renovação e o bloqueio têm regras e executores implementados. Suas escritas reais ainda precisam de homologação com credenciais delegadas e contas autorizadas. A matriz de criação, efeitos de Setor/Scripts/grupos e finalização do chamado continuam nos limites pendentes anteriores.

## Catálogo por responsabilidade

As dependências do UML representam usos encontrados no código, não um rastreamento de execução. O ancestral externo `CommonGLPI` e demais modelos nativos são explicados neste guia; os diagramas catalogam as classes próprias.

### Entrada e integração

setup.php declara a versão, os requisitos e os hooks do menu. hook.php participa da instalação e desinstalação. As páginas de front/ carregam o bootstrap do GLPI antes de usar sua sessão, conexão e classes.

| Arquivo | Responsabilidade |
| --- | --- |
| [setup.php](../setup.php) | Registra a identidade, a faixa de versões alvo e os hooks de menu/configuração do plugin. |
| [hook.php](../hook.php) | Implementa os pontos de instalação e desinstalação consultados pelo GLPI. |
| [composer.json](../composer.json) | Declara requisitos, associação PSR-4 do namespace e atalhos para as nove suítes. |
| [src/MenuDaFerramenta.php](../src/MenuDaFerramenta.php) | Define os links do plugin que o usuário atual pode visualizar no menu GLPI. |
| [src/EnderecoDaFerramenta.php](../src/EnderecoDaFerramenta.php) | Resolve a URL nativa sem depender da localização física plugins/marketplace. |

### Acesso e configuração

AcessoGlpi usa Session e a identificação administrativa nativa de Profile. PerfilDaFerramenta integra a matriz de direitos. PoliticaDeVisibilidade combina as capacidades do perfil com o mapa explícito de setores e entidades.

| Arquivo | Responsabilidade |
| --- | --- |
| [src/AcessoGlpi.php](../src/AcessoGlpi.php) | Autoriza pelo perfil ativo e preserva as fronteiras nativas de sessão e entidade. |
| [src/ConfiguracaoOperacional.php](../src/ConfiguracaoOperacional.php) | Persiste documentos próprios na configuração nativa do GLPI, sem conceder direitos. |
| [src/ConfiguracaoDaFila.php](../src/ConfiguracaoDaFila.php) | Limita a importação às categorias operacionais; autorização pertence ao perfil ativo. |
| [src/ConfiguracaoDoDiretorio.php](../src/ConfiguracaoDoDiretorio.php) | Configura conexões LDAP; credenciais continuam no ambiente e ações exigem confirmação. |
| [src/EntradaDaFerramenta.php](../src/EntradaDaFerramenta.php) | Valida os valores recebidos antes de usá-los nas consultas e nos rascunhos. |
| [front/config.php](../front/config.php) | Protege e processa as telas administrativas de acesso, fila e conexões LDAP. |
| [src/ConfiguracaoDeVisibilidade.php](../src/ConfiguracaoDeVisibilidade.php) | Persiste somente o mapa perfil-setor; direitos continuam exclusivamente no GLPI. |
| [src/PoliticaDeVisibilidade.php](../src/PoliticaDeVisibilidade.php) | Combina direitos do perfil ativo com vínculos explícitos de setor e entidade. |
| [src/PerfilDaFerramenta.php](../src/PerfilDaFerramenta.php) | Integra permissões do ADManagement à matriz nativa de perfis do GLPI. |

### Fila e origem

ConsultaDeChamados preserva atribuição pessoal ou pares setor/entidade, inclusive no ID direto. LeitorDoFormmanager lê a origem. ImportadorDaSolicitacao exige vínculo único e aprovação antes do rascunho.

| Arquivo | Responsabilidade |
| --- | --- |
| [front/index.php](../front/index.php) | Carrega o GLPI, autoriza o técnico, consulta a fila e mostra impedimentos da origem. |
| [src/ConsultaDeChamados.php](../src/ConsultaDeChamados.php) | Reúne os filtros usados na fila, histórico e abertura por ID e exige a leitura nativa. Grupos identificam os setores atribuídos; a autorização vem do perfil resolvido no contexto. |
| [src/LeitorDoFormmanager.php](../src/LeitorDoFormmanager.php) | Lê os formulários de origem sem executar ações ou gravar dados no formmanager. |
| [src/ImportadorDaSolicitacao.php](../src/ImportadorDaSolicitacao.php) | Transforma um formulário aprovado e vinculado ao chamado em dados locais de revisão. |

### Revisão do trabalho

front/revisao.php relê autorização, chamado e origem, trata as ações e salva o rascunho com auditoria. CoordenadorDaRevisao encaminha os comandos de consulta e resumo. RevisorDaOperacao aplica correções; as políticas de login, destino e vigência validam o conteúdo.

| Arquivo | Responsabilidade |
| --- | --- |
| [front/revisao.php](../front/revisao.php) | Controla importação, correção, consultas, resumo, confirmação e descarte do trabalho local. |
| [src/CoordenadorDaRevisao.php](../src/CoordenadorDaRevisao.php) | Coordena ações locais depois da releitura obrigatória de ticket, origem e autorização. |
| [src/RevisorDaOperacao.php](../src/RevisorDaOperacao.php) | Aplica correções no rascunho local e informa o que ainda precisa de revisão. |
| [src/RevisaoDaOperacao.php](../src/RevisaoDaOperacao.php) | Vincula o resumo ao conteúdo, à origem e ao perfil atuais, sem executar efeitos no AD. |
| [src/PoliticaDeLogin.php](../src/PoliticaDeLogin.php) | Compõe sugestões textuais de login; a consulta ao AD ainda não faz parte desta classe. |
| [src/DestinoDaOperacao.php](../src/DestinoDaOperacao.php) | Valida a escolha manual contra o catálogo real e sua configuração vigente. |

### Diretório AD

LeitorLdap concentra a conexão e as pesquisas LDAP. VerificadorDoDiretorio combina os resultados por domínio e distingue cobertura completa, ocorrência e falha de consulta. NomeDistinto trata caminhos LDAP e pertencimento à Base DN.

| Arquivo | Responsabilidade |
| --- | --- |
| [src/LeitorLdap.php](../src/LeitorLdap.php) | Único adaptador LDAP do produto: possui exclusivamente métodos de leitura. |
| [src/VerificadorDoDiretorio.php](../src/VerificadorDoDiretorio.php) | Separa candidatos, disponibilidade e duplicidades, com cobertura explícita de todos os domínios. |
| [src/NomeDistinto.php](../src/NomeDistinto.php) | Manipula caminhos LDAP preservando vírgulas escapadas e a identidade do ramo. |

### Estado e auditoria

RepositorioDeRascunhos usa a sessão e controla revisões. RepositorioDeOperacoes mantém metadados privados em JSON com lock e substituição atômica. PoliticaDeResultados distingue o resultado do AD do resultado da finalização no GLPI.

| Arquivo | Responsabilidade |
| --- | --- |
| [src/RepositorioDeRascunhos.php](../src/RepositorioDeRascunhos.php) | Guarda o trabalho na sessão, separado por técnico, entidade e chamado. |
| [src/RepositorioDeOperacoes.php](../src/RepositorioDeOperacoes.php) | Preserva somente metadados privados, com lock exclusivo e substituição atômica. |
| [src/PoliticaDeResultados.php](../src/PoliticaDeResultados.php) | Mantém AD e fechamento como resultados independentes e fixa o alvo de cada tentativa. |
| [front/pending.php](../front/pending.php) | Exibe rascunhos com pendências e as orientações derivadas dos estados duráveis visíveis. |
| [front/audit.php](../front/audit.php) | Lista até cem eventos visíveis, filtrados pelo ator, entidade e direito de leitura do chamado. |

### Interface e equipe

InterfaceDaFerramenta fornece a estrutura visual comum e o CSS. FormularioDaSolicitacao e Apresentacao* renderizam dados e controles. PaginaDaEquipe usa AcompanhamentoDaEquipe para apresentar visão geral e histórico com os direitos nativos por chamado.

| Arquivo | Responsabilidade |
| --- | --- |
| [src/InterfaceDaFerramenta.php](../src/InterfaceDaFerramenta.php) | Organiza a apresentação PHP sem consultar dados ou executar ações do técnico. |
| [src/FormularioDaSolicitacao.php](../src/FormularioDaSolicitacao.php) | Mostra a fila e os formulários em PHP, escapando os textos vindos dos dados. |
| [src/ApresentacaoDaConfiguracao.php](../src/ApresentacaoDaConfiguracao.php) | Apresenta parâmetros administrativos de acesso e leitura, sem oferecer escrita no AD. |
| [src/ApresentacaoDeConsultas.php](../src/ApresentacaoDeConsultas.php) | Explica disponibilidade e cobertura sem confundir ausência de resposta com ausência de conta. |
| [src/PaginaDaEquipe.php](../src/PaginaDaEquipe.php) | Apresenta a equipe autorizada usando os rótulos, datas e links nativos do GLPI. |
| [src/AcompanhamentoDaEquipe.php](../src/AcompanhamentoDaEquipe.php) | Consulta atendimento coletivo usando o escopo do perfil e os catálogos nativos do GLPI. |
| [front/overview.php](../front/overview.php) | Abre a visão geral da equipe por meio de PaginaDaEquipe::mostrar(false). |
| [front/historico.php](../front/historico.php) | Abre o histórico de solucionados e fechados com PaginaDaEquipe::mostrar(true). |
| [src/ApresentacaoDaVisibilidade.php](../src/ApresentacaoDaVisibilidade.php) | Apresenta mapas de setor e perfil com seletores nativos, sem conceder direitos. |

### Verificação

Quatorze suítes PHP cobrem perfis, visibilidade, consultas nativas, fluxo HTTP, revisão, concorrência e comentários. O runner PowerShell e o Composer oferecem entradas equivalentes.

| Arquivo | Responsabilidade |
| --- | --- |
| [tests/acesso.php](../tests/acesso.php) | Verifica direitos do perfil ativo, administração nativa e ausência de concessões por usuário, grupo ou laboratório. |
| [tests/carregamento.php](../tests/carregamento.php) | Verifica os contratos de descoberta, instalação e carregamento do plugin. |
| [tests/refatoracao.php](../tests/refatoracao.php) | Exercita os contratos de leitura, validação e apresentação cobertos pela refatoração. |
| [tests/fluxo.php](../tests/fluxo.php) | Verifica o encadeamento de importação, edição e armazenamento do trabalho local. |
| [tests/regras_http.php](../tests/regras_http.php) | Exercita a entrada HTTP de revisão com dublês do GLPI e verifica as respostas e transições. |
| [tests/regras_vigentes.php](../tests/regras_vigentes.php) | Verifica regras da revisão, destino, login, diretório e resultados com dados de contrato. |
| [tests/concorrencia.php](../tests/concorrencia.php) | Usa processos concorrentes para verificar a exclusividade da reserva e a integridade do registro. |
| [tests/documentacao.php](../tests/documentacao.php) | Usa tokens PHP para conferir comentários em classes, funções, condições, ramos e laços. |
| [tests/run.ps1](../tests/run.ps1) | Executa as quatorze suítes e propaga a primeira falha. |
| [tests/visibilidade.php](../tests/visibilidade.php) | Verifica setores e entidades pareados, atribuição pessoal, migração e seletores múltiplos nativos. |
| [tests/consultas_nativas.php](../tests/consultas_nativas.php) | Verifica filtros pareados, leitura nativa, APIs de equipe e paginação por resultados autorizados. |
| [tests/fluxo_perfis.php](../tests/fluxo_perfis.php) | Verifica HTTP por perfil, entidades filhas, auditoria, configuração e filtros inválidos. |
| [tests/suporte_perfis.php](../tests/suporte_perfis.php) | Fornece dublês isolados das APIs de perfis e apresentação nativas. |

## Verificação e manutenção

Execute `./tests/run.ps1`, com `-ExecutavelPhp` apontando ao executável PHP quando necessário. As quatorze suítes não usam AD nem banco real; a cobertura inclui perfil ativo, pareamento de setores, paginação após READ, POST direto, entidade filha, migração, escape HTML, prazo, desafio, posse e concorrência. A suíte de documentação confere comentários em classes, funções e controles PHP.

As classes, variáveis e funções próprias usam português brasileiro. Contratos exigidos por GLPI/PHP conservam os nomes oficiais. Há uma linha em branco entre linhas de código; comentários e literais HTML/CSS permanecem agrupados. A validação local em GLPI 12.0.1/PHP 8.3.36 e as pendências de homologação do servidor estão registradas no relatório específico.

## Ampliação 0.8.0

| Classe nova | Responsabilidade |
| --- | --- |
| `ConexaoLdap` | Compartilha conexão TLS entre leitura e escrita limitada. |
| `BuscaDePastas`, `ApresentacaoDePastas` | Termos da origem, grupos realmente encontrados, seleção e entrada manual. |
| `IdentidadeDoDiretorio` | CPF, Escritório e barreira de nomes repetidos nos dois domínios. |
| `PoliticaDeRenovacao` | Prazos e limites de calendário. |
| `EscritorLdap` | Modify por GUID, preservação de indicadores e comparação do valor anterior. |
| `OperadorDoDiretorio` | Prévia, revalidação, reserva, envio, confirmação e reconciliação somente de leitura. |
| `Lote`, `ExecucaoDoDiretorio` | Persistência nativa de metadados, autoria, necessidade única e reserva compartilhada por alvo. |
| `InstalacaoDaFerramenta` | Tabelas próprias pelo ciclo nativo de migração. |
| `LeitorDePlanilhas` | CSV UTF-8 e XLSX usando PhpSpreadsheet fornecido pelo GLPI. |
| `ProcessadorDeLotes`, `PaginaDeLotes` | Trabalho na sessão e apresentação da aba Lotes. |
| `ApresentacaoDaOperacao` | Confirmação explícita específica de renovação/bloqueio. |

Herança é usada quando existe responsabilidade correspondente: os modelos são `CommonDBTM`, a aba de perfis continua `CommonGLPI`, instalação usa `Migration`, configurações usam `Config`, direitos usam `ProfileRight`, sessão usa `Session` e assinaturas usam a chave `GLPIKey`. Regras puras não herdam modelos de banco artificialmente. Ver [entrega e evidências](ENTREGA_SERVICOS_PASTAS_E_LOTES_2026-11-12.md).
