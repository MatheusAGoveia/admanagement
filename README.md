# ADManagement 0.6.0

Ferramenta PHP para técnicos do GLPI revisarem solicitações reais de criação e renovação de contas de rede. Esta versão incorpora as decisões do projeto **Pluguin Glpi Estagiario Ad**, com as correções posteriores que eliminaram dados simulados.

## Funcionalidades

- Fila por papel: Super-Admin, administrador de setor ou técnico; concessões por usuário, grupo ou perfil ativo. Categorias operacionais restringem importação, sem esconder chamados autorizados.
- Visão geral, histórico, pendências e ID direto respeitam o mesmo setor/atribuição; histórico inclui solucionados e fechados.
- Importação somente de chamado de rede existente, vinculado sem ambiguidade ao formulário corrente aprovado (status 3).
- Correções locais, nome social, candidatos de login na ordem aprovada e entrada manual.
- Domínio e OU selecionados manualmente em árvore real com busca; todas as OUs do domínio permitidas.
- Grupos, Scripts, pastas e validade proposta em campos de trabalho e no resumo.
- Consulta AD somente de leitura, com cobertura por domínio, disponibilidade e possíveis duplicidades por nome/login/matrícula/CPF.
- Resumo com prazo, desafio consumível e invalidação por correção, cancelamento ou mudança observada da origem, acesso e configuração.
- Auditoria mínima privada e políticas duráveis de posse, repetição e resultados independentes AD/GLPI.

**A confirmação registra a revisão de trabalho. Não cria/renova contas nem fecha chamados.** A escrita continua bloqueada: atributos, efeitos de grupos/Scripts/pastas, validade, identidade da renovação, texto padrão e adaptadores ainda dependem das definições e homologações que o próprio planejamento deixou pendentes. Não existe opção para ativar escrita por formulário ou configuração.

## Instalação e configuração

Alvos: GLPI **11.0.4 e 11.0.6**, PHP **8.2 ou superior**, JSON e mbstring. A faixa declarada é GLPI >=11.0.4 e <11.0.7. As suítes locais foram executadas no PHP 8.4; a homologação operacional nas duas versões GLPI e no PHP web continua pendente. Esta versão não declara suporte ao GLPI 10.

1. Coloque a pasta `admanagement` em `plugins/` ou `marketplace/` da instalação GLPI.
2. Instale/ative com a administração nativa. Os hooks não criam tabelas nem concedem permissões.
3. Entre com o perfil ativo `Super-Admin` e direito nativo `config/UPDATE`. Em Configurações, cadastre setores com os grupos responsáveis em **Atribuído** e entidades explícitas. Depois conceda papel de Técnico ou Administrador de setor por **usuário individual**, **grupo** ou **perfil ativo**. Concessões são cumulativas. Somente Super-Admin configura; administrador fica nos setores escolhidos; técnico vê apenas sua atribuição pessoal. A primeira gravação ativa a política nova, preserva as configurações anteriores e bloqueia os demais até receberem novas concessões. Escolha separadamente as categorias operacionais para importação. Veja o [passo a passo](documentacao/CONFIGURACAO_DE_ACESSO.md).
4. Confira a existência e os metadados de `glpi_plugin_formmanager_forms_atual`. A origem é lida pelo adaptador próprio, sem carregar código ou alterar registros do formmanager.
5. Quando a infraestrutura fornecer os parâmetros, cadastre as conexões próprias de leitura do AD: DNS, porta, Base DN, LDAPS/STARTTLS e **nomes** das variáveis de ambiente de bind/senha. Configure a CA confiável no cliente LDAP. Os atributos de CPF e matrícula precisam ser confirmados. Marque todos os domínios obrigatórios; ausência dessa lista impede cobertura conclusiva.
6. Garanta acesso privado e gravável, pelo usuário do PHP, a `GLPI_VAR_DIR/_plugins/admanagement`. A auditoria usa arquivos mínimos com lock e substituição atômica. Falha de gravação reverte o rascunho da ação; corrupção bloqueia a continuidade e preserva o arquivo para conferência. Rascunhos em sessão são temporários.

Nenhuma conexão de rede é feita ao salvar configuração. As consultas LDAP ocorrem somente por comando explícito do técnico. Senhas não são copiadas para configuração ou histórico.

## Verificação

No PowerShell, na raiz do projeto:

```powershell
./tests/run.ps1
```

As nove suítes também podem ser executadas separadamente com `php tests/acesso.php`, `php tests/carregamento.php`, `php tests/refatoracao.php`, `php tests/fluxo.php`, `php tests/regras_http.php`, `php tests/regras_vigentes.php`, `php tests/concorrencia.php` , `php tests/visibilidade.php` e `php tests/documentacao.php`. Há scripts Composer `test:<nome>` equivalentes. Os substitutos e dados de contrato ficam exclusivamente em `tests/`; não há demonstração no produto. Os testes não acessam AD nem banco real.

## Documentação da arquitetura

- [Mapa mental interativo](documentacao/MAPA_MENTAL.html) — abra no navegador e explore as responsabilidades e os arquivos.
- [Diagrama UML de classes](documentacao/UML_CLASSES.html) — atributos, métodos e dependências das 32 classes próprias.
- [Estrutura e arquitetura do código](documentacao/ESTRUTURA_E_ARQUITETURA.md) — pastas, entradas, fluxos, armazenamento e limites atuais.
- [Configuração de acesso por usuário, grupo e perfil](documentacao/CONFIGURACAO_DE_ACESSO.md).
- [Índice da documentação](documentacao/README.md).

## Comentários e manutenção

O código próprio utiliza nomes em português brasileiro. Classes e funções têm comentários de responsabilidade; cada condição, ramo alternativo e laço tem sua finalidade explicada. O PHP tem uma linha em branco entre cada linha de código, com assinaturas, listas e expressões extensas distribuídas em múltiplas linhas. Comentários permanecem agrupados, e o conteúdo literal de strings, HTML e CSS é preservado. A classe de menu é `MenuDaFerramenta`.

Nomes exigidos pelo GLPI/PHP, como `getMenuContent`, hooks e métodos dos modelos nativos, permanecem estáveis e são explicados nos comentários. O [guia da arquitetura](documentacao/ESTRUTURA_E_ARQUITETURA.md) apresenta o roteiro de leitura e manutenção. A suíte `documentacao` verifica a cobertura de comentários em todo o PHP, inclusive callbacks, templates e testes.
