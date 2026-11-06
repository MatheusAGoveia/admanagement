# ADManagement 0.7.1

Ferramenta PHP para técnicos do GLPI revisarem solicitações reais de criação e renovação de contas de rede. Esta versão incorpora as decisões do projeto **Pluguin Glpi Estagiario Ad**, com as correções posteriores que eliminaram dados simulados.

## Funcionalidades

- Fila por papel: Super-Admin, administrador de setor ou técnico; direitos nativos exclusivamente pelo perfil ativo. Categorias operacionais restringem importação, sem esconder chamados autorizados.
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

Alvo desta entrega: **GLPI 12.0.1**, com PHP **8.3 de 64 bits**, JSON e mbstring. O requisito PHP declarado é >=8.3 e <9.0; a validação foi executada em **PHP 8.3.36 CLI e web**, com MariaDB 10.11.19. Outras releases do GLPI são recusadas até nova verificação. Esta entrega substitui a matriz anterior do plugin; não modifica a instalação do GLPI nem o PHP global. Consulte o [relatório de compatibilidade e seus limites](documentacao/HOMOLOGACAO_GLPI12_PHP83.md).

1. Coloque a pasta `admanagement` em `plugins/` ou `marketplace/` da instalação GLPI.
2. Instale ou atualize para 0.7.1 pela administração nativa. O hook registra o direito `plugin_admanagement` com valor zero, sem criar tabelas ou conceder acesso automaticamente. Ao substituir os arquivos, preserve a configuração no banco e os dados privados em `GLPI_VAR_DIR/_plugins/admanagement`; não coloque cópias antigas ou backups dentro de `plugins/` ou `marketplace/`.
3. O superadministrador nativo com `config/UPDATE` configura o plugin. Em **Administração → Perfis → ADManagement**, marque **Operar atribuições pessoais (READ)** para técnicos ou **Supervisionar setores vinculados (UPDATE)** para administradores de setor. Em **ADManagement → Configurações**, cadastre setores com grupos responsáveis atribuídos e entidades explícitas e vincule os perfis supervisores. Supervisão prevalece sobre operação pessoal e exige um mapa válido. Técnicos não precisam de vínculo setorial para acessar suas atribuições. Um documento antigo exige migração confirmada, com backup, sem converter concessões por usuário/grupo nem atribuir direitos nativos. Veja o [passo a passo](documentacao/CONFIGURACAO_DE_ACESSO.md).
4. Confira a existência e os metadados de `glpi_plugin_formmanager_forms_atual`. A origem é lida pelo adaptador próprio, sem carregar código ou alterar registros do formmanager.
5. Quando a infraestrutura fornecer os parâmetros, cadastre as conexões próprias de leitura do AD: DNS, porta, Base DN, LDAPS/STARTTLS e **nomes** das variáveis de ambiente de bind/senha. Configure a CA confiável no cliente LDAP. Os atributos de CPF e matrícula precisam ser confirmados. Marque todos os domínios obrigatórios; ausência dessa lista impede cobertura conclusiva.
6. Garanta acesso privado e gravável, pelo usuário do PHP, a `GLPI_VAR_DIR/_plugins/admanagement`. A auditoria usa arquivos mínimos com lock e substituição atômica. Falha de gravação reverte o rascunho da ação; corrupção bloqueia a continuidade e preserva o arquivo para conferência. Rascunhos em sessão são temporários.

Nenhuma conexão de rede é feita ao salvar configuração. As consultas LDAP ocorrem somente por comando explícito do técnico. Senhas não são copiadas para configuração ou histórico.

O GLPI 12 pode pedir que o administrador confirme sua identidade ao entrar em **Configurações**. A ferramenta utiliza a reautenticação nativa antes de consultar grupos e perfis sensíveis. Isso não concede permissões nem cria outra aprovação de solicitações.

As entradas PHP são executadas pelo kernel nativo, sem incluir o carregamento legado `inc/includes.php`. Os formulários mantêm `Html::closeForm()` e utilizam a proteção CSRF do GLPI 12 por cabeçalhos de origem; não exigem o token oculto das versões anteriores.

## Verificação

No PowerShell, na raiz do projeto:

```powershell
./tests/run.ps1
```

Para escolher explicitamente o PHP 8.3, use `./tests/run.ps1 -ExecutavelPhp 'C:/caminho/php83/php.exe'`. Se houver INI particular, configure `PHPRC` no processo antes de executar as suítes, pois elas também criam subprocessos.

As onze suítes são `acesso`, `carregamento`, `refatoracao`, `fluxo`, `fluxo_perfis`, `regras_http`, `regras_vigentes`, `concorrencia`, `visibilidade`, `consultas_nativas` e `documentacao`. Cada uma pode ser executada com `php tests/<nome>.php` ou pelo script Composer `test:<nome>`. Os testes HTTP são particionados para limitar a quantidade de subprocessos por execução, sem reduzir cenários. Dublês e dados sintéticos ficam exclusivamente em `tests/`; nenhum teste acessa AD ou banco real.

## Documentação da arquitetura

- [Mapa mental interativo](documentacao/MAPA_MENTAL.html) — abra no navegador e explore as responsabilidades e os arquivos.
- [Diagrama UML de classes](documentacao/UML_CLASSES.html) — atributos, métodos e dependências das 31 classes próprias.
- [Estrutura e arquitetura do código](documentacao/ESTRUTURA_E_ARQUITETURA.md) — pastas, entradas, fluxos, armazenamento e limites atuais.
- [Configuração de acesso por perfil](documentacao/CONFIGURACAO_DE_ACESSO.md).
- [Compatibilidade GLPI 12 e PHP 8.3](documentacao/HOMOLOGACAO_GLPI12_PHP83.md) — alterações, fontes oficiais, ensaios nativos e pendências do servidor.
- [Índice da documentação](documentacao/README.md).

## Comentários e manutenção

O código próprio utiliza nomes em português brasileiro. Classes e funções têm comentários de responsabilidade; cada condição, ramo alternativo e laço tem sua finalidade explicada. O PHP tem uma linha em branco entre cada linha de código, com assinaturas, listas e expressões extensas distribuídas em múltiplas linhas. Comentários permanecem agrupados, e o conteúdo literal de strings, HTML e CSS é preservado. A classe de menu é `MenuDaFerramenta`.

Nomes exigidos pelo GLPI/PHP, como `getMenuContent`, hooks e métodos dos modelos nativos, permanecem estáveis e são explicados nos comentários. O [guia da arquitetura](documentacao/ESTRUTURA_E_ARQUITETURA.md) apresenta o roteiro de leitura e manutenção. A suíte `documentacao` verifica a cobertura de comentários em todo o PHP, inclusive callbacks, templates e testes.
