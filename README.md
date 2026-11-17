# ADManagement 0.8.1

Ferramenta PHP para técnicos do GLPI revisarem solicitações reais de criação, renovação e bloqueio de contas de rede. Esta versão incorpora as decisões do projeto **Pluguin Glpi Estagiario Ad**, com as correções posteriores que eliminaram dados simulados.

## Funcionalidades

- Fila por papel: Super-Admin, administrador de setor ou técnico; direitos nativos exclusivamente pelo perfil ativo. Categorias operacionais restringem importação, sem esconder chamados autorizados.
- Visão geral, histórico, pendências e ID direto respeitam o mesmo setor/atribuição; histórico inclui solucionados e fechados.
- Importação somente de chamado de rede existente, vinculado sem ambiguidade ao formulário corrente aprovado (status 3).
- Correções locais, nome social, candidatos de login na ordem aprovada e entrada manual.
- Domínio e OU selecionados manualmente em árvore real com busca; todas as OUs do domínio permitidas.
- Setor e Scripts manuais; referências de pastas importadas, busca por trecho de grupos G-ADM- e entrada manual não verificada.
- Consultas AD de disponibilidade, OUs, grupos e identidade. Renovação e bloqueio possuem executores explícitos, prévia, reserva e confirmação por releitura.
- Aba Lotes: XLSX/CSV com CPF, nome e objetivo; vínculo obrigatório para renovar. Lotes independentes de chamados, com permissão nativa adicional.
- Busca nos dois domínios e bloqueio da transação se houver mais de uma conta com o mesmo nome, mesmo que o CPF diferencie as contas.
- Resumo com prazo, desafio consumível e invalidação por correção, cancelamento ou mudança observada da origem, acesso e configuração.
- Auditoria mínima privada e políticas duráveis de posse, repetição e resultados independentes AD/GLPI.

**Salvar revisão não executa AD.** Renovação e bloqueio exigem consultar os dois domínios, preparar a prévia e confirmar os efeitos explicitamente. Contrato recebe +1 ano; estagiário, +6 meses; conta vencida ou a até 90 dias do vencimento. Conta desativada não é reativada. CPF vazio é preenchido e confirmado antes de renovar; CPF existente é preservado.

A criação permanece em revisão: sua matriz completa de atributos e o executor não foram liberados nesta ampliação. Seleção de grupos não aplica membros nem ACLs. A transição final do chamado (solucionar ou fechar) continua pendente e não é executada automaticamente.

## Instalação e configuração

Alvo desta entrega: **GLPI 12.0.0 e 12.0.1**, com PHP **8.3 de 64 bits**, JSON, mbstring e intl. LDAP é necessário para consultas/escritas; ZIP e PhpSpreadsheet são necessários para XLSX. O requisito PHP declarado é >=8.3 e <9.0; a validação foi executada em **PHP 8.3.36 CLI e web**, com MariaDB 10.11.19. Outras releases do GLPI e sufixos de desenvolvimento são recusados até nova verificação. Esta entrega não modifica a instalação do GLPI nem o PHP global. Consulte a [correção de compatibilidade com 12.0.0](documentacao/CORRECAO_GLPI1200_2026-11-12.md), que complementa o [relatório anterior de compatibilidade](documentacao/HOMOLOGACAO_GLPI12_PHP83.md).

1. Coloque a pasta `admanagement` em `plugins/` ou `marketplace/` da instalação GLPI.
2. Instale ou atualize para 0.8.1 pela administração nativa. O hook registra os direitos nativos sem concedê-los e cria somente as tabelas próprias de lotes/execuções usando `Migration`. Os modelos herdam `CommonDBTM`. Ao substituir os arquivos, preserve a configuração no banco e os dados privados em `GLPI_VAR_DIR/_plugins/admanagement`; não coloque cópias antigas ou backups dentro de `plugins/` ou `marketplace/`.
3. O superadministrador nativo com `config/UPDATE` configura o plugin. Em **Administração → Perfis → ADManagement**, marque **Operar atribuições pessoais (READ)** para técnicos ou **Supervisionar setores vinculados (UPDATE)** para administradores de setor. Em **ADManagement → Configurações**, cadastre setores com grupos responsáveis atribuídos e entidades explícitas e vincule os perfis supervisores. Supervisão prevalece sobre operação pessoal e exige um mapa válido. Técnicos não precisam de vínculo setorial para acessar suas atribuições. Um documento antigo exige migração confirmada, com backup, sem converter concessões por usuário/grupo nem atribuir direitos nativos. Veja o [passo a passo](documentacao/CONFIGURACAO_DE_ACESSO.md).
4. Confira a existência e os metadados de `glpi_plugin_formmanager_forms_atual`. A origem é lida pelo adaptador próprio, sem carregar código ou alterar registros do formmanager.
5. Cadastre as duas conexões próprias do AD, com raízes de domínio distintas: DNS, porta, Base DN, LDAPS/STARTTLS e **nomes** das variáveis de ambiente de bind/senha. Configure a CA confiável no cliente LDAP. Os atributos de CPF e matrícula precisam ser confirmados. Para renovar/bloquear, Base DN precisa ser a raiz confirmada pelo servidor e a conta deve ter delegação para os atributos envolvidos. Credencial somente de leitura não autoriza escrita. Marque todos os domínios obrigatórios; ausência dessa lista impede cobertura conclusiva.
6. Para lotes independentes, conceda **Revisar e executar renovação/bloqueio em lotes** na aba ADManagement do perfil, além do acesso operacional existente. Nenhum perfil recebe esse direito automaticamente.
7. Garanta acesso privado e gravável, pelo usuário do PHP, a `GLPI_VAR_DIR/_plugins/admanagement`. A auditoria usa arquivos mínimos com lock e substituição atômica. Falha de gravação reverte o rascunho da ação; corrupção bloqueia a continuidade e preserva o arquivo para conferência. Rascunhos em sessão são temporários.

Nenhuma conexão de rede é feita ao salvar configuração. As consultas LDAP ocorrem somente por comando explícito do técnico. Senhas não são copiadas para configuração ou histórico.

O GLPI 12 pode pedir que o administrador confirme sua identidade ao entrar em **Configurações**. A ferramenta utiliza a reautenticação nativa antes de consultar grupos e perfis sensíveis. Isso não concede permissões nem cria outra aprovação de solicitações.

As entradas PHP são executadas pelo kernel nativo, sem incluir o carregamento legado `inc/includes.php`. Os formulários mantêm `Html::closeForm()` e utilizam a proteção CSRF do GLPI 12 por cabeçalhos de origem; não exigem o token oculto das versões anteriores.

## Verificação

No PowerShell, na raiz do projeto:

```powershell
./tests/run.ps1
```

Para escolher explicitamente o PHP 8.3, use `./tests/run.ps1 -ExecutavelPhp 'C:/caminho/php83/php.exe'`. Se houver INI particular, configure `PHPRC` no processo antes de executar as suítes, pois elas também criam subprocessos.

As quatorze suítes são `acesso`, `carregamento`, `refatoracao`, `fluxo`, `fluxo_perfis`, `regras_http`, `regras_vigentes`, `concorrencia`, `visibilidade`, `consultas_nativas` , `servicos`, `execucoes`, `planilhas` e `documentacao`. Cada uma pode ser executada com `php tests/<nome>.php` ou pelo script Composer `test:<nome>`. Os testes HTTP são particionados para limitar a quantidade de subprocessos por execução, sem reduzir cenários. Dublês e dados sintéticos ficam exclusivamente em `tests/`; essas suítes não acessam AD ou banco real. Para cobrir XLSX com a biblioteca do GLPI, defina `ADMANAGEMENT_AUTOLOAD_GLPI` para o `vendor/autoload.php` da instalação. Sem essa variável, a suíte informa a ausência de cobertura XLSX. A instalação e os testes HTTP/banco do laboratório são registrados separadamente no relatório da ampliação.

## Uso dos novos serviços

1. Importe um chamado aprovado: o serviço vem do campo `tipo_servico_rede`, sem troca livre de operação.
2. Salve correções. Use **Buscar grupos** no bloco de pastas; selecione objetos encontrados no AD ou mantenha referência manual identificada como não verificada.
3. Em renovação/bloqueio, clique **Consultar e preparar**, confira a conta e confirme os efeitos. Mais de um nome correspondente impede o envio.
4. Em **Lotes**, importe, consulte, selecione linhas prontas e confirme. **Executar próxima linha confirmada** processa uma linha por POST. Recarregar a página não executa o lote.
5. Resultados parciais/incertos oferecem conferência por leitura, sem reenviar a operação. Sucesso comprovado nunca é repetido para a mesma necessidade.

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

- [Ampliação de serviços, pastas e lotes: entrega e limites](documentacao/ENTREGA_SERVICOS_PASTAS_E_LOTES_2026-11-12.md).
