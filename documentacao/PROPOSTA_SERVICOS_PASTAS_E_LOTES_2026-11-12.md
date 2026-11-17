# Serviços, busca de grupos de pastas e lotes

Data: 12/11/2026, America/Sao_Paulo.

**Situação: desenho aprovado pelo usuário em 12/11/2026 para implementação.** Inclui a regra posterior de bloqueio por nomes repetidos e a exigência de aproveitar ao máximo as classes e capacidades nativas do GLPI. Este documento descreve o alvo; não afirma que as funcionalidades já existem.

## Objetivo e contexto

Ampliar o ADManagement existente, preservando sua apresentação PHP, para importar o serviço correto do formmanager, localizar grupos de acesso a pastas a partir de textos do formulário e permitir renovação e bloqueio individual ou em lotes. O código da pasta `automacao` serve como referência das regras, não como programa a executar no servidor GLPI.

Bases locais consultadas:

- Produto: `C:/Users/matheus.damiao/Desktop/admanagement`.
- Origem dos formulários, somente leitura: `C:/Users/matheus.damiao/Desktop/formmanager`.
- Automações, somente leitura: `C:/Users/matheus.damiao/Documents/ChatGPT/automacao`.
- Alvo já adotado pelo projeto: GLPI 12.0.1 e PHP 8.3. A compatibilidade das alterações ainda exigirá testes.

O produto atual importa criação e renovação, oferece revisão e consultas LDAP de leitura e impede escrita no AD. A ampliação deverá introduzir os executores necessários com confirmação própria; salvar revisão continuará sendo uma ação distinta de executar.

## Decisões confirmadas pelo usuário

| Assunto | Regra confirmada |
| --- | --- |
| Apresentação | Manter o layout atual, com organização visual limpa e nova aba **Lotes**. |
| Código | PHP, nomes próprios em português, espaçamento legível e comentários que expliquem cada função e as decisões. Manter a estrutura simples de `front/` e `src/`. Reutilizar as classes e capacidades já produzidas pelo GLPI sempre que a responsabilidade corresponder ao contrato nativo. |
| Serviço importado | Ler **Serviços** no formulário e abrir o atendimento correspondente: criação, renovação ou bloqueio. |
| Busca de pastas | Localizar **grupos do AD com prefixo `G-ADM-`**, como na automação. Não se trata de verificar a existência física de compartilhamentos. |
| Texto de origem | Usar o campo de permissões de pasta de rede, limpar ruído para a busca e sugerir apenas grupos realmente retornados pelo AD. |
| Busca manual | Aceitar trecho do nome para pesquisar e permitir digitação manual desde o início. |
| Renovação de contrato | Acrescentar 1 ano de calendário. |
| Renovação de estagiário | Acrescentar 6 meses de calendário. |
| Janela de renovação | Conta vencida ou com vencimento em até 90 dias, inclusive. Se ainda válida, partir da expiração atual; se vencida, partir do instante da consulta. |
| Restrições da renovação | Não reativar conta desativada; conta sem expiração ou a mais de 90 dias do vencimento não precisa de renovação. |
| Identificação sem CPF no AD | Aceitar nome completo único, como na automação, com indicação explícita de que o CPF informado não foi comparado no AD. |
| Preenchimento de CPF | Antes de renovar, preencher o atributo CPF vazio e confirmar por releitura. CPF já preenchido será preservado. |
| Bloquear | Desativar a conta do AD, preservando as demais propriedades. Não significa excluir a conta. |
| Lotes | Operações independentes de chamados, revisadas e confirmadas pelo técnico. Essa autorização não dispensa aprovação dos atendimentos originados por chamados. |
| Planilha | CPF, nome e objetivo obrigatórios. Objetivo: renovar ou bloquear. **Vínculo também obrigatório em cada linha de renovação.** |
| Domínios dos lotes | Consultar os dois domínios configurados. Se houver mais de uma conta com o mesmo nome, bloquear a operação, sem permitir escolher uma ocorrência para contornar o impedimento. |
| Homônimos | Verificar os dois domínios em atendimentos individuais e lotes. Mais de uma conta com o mesmo nome impede a transação, mesmo que CPF ou login distingam as contas. A confirmação posterior do usuário prevalece sobre a seleção de ocorrências descrita anteriormente. |

Essas regras complementam as decisões anteriores: operações não automáticas; elegibilidade novamente conferida; vínculo formulário/chamado por identificadores; nome social para composição de login quando indicado; todas as OUs permitidas; Setor e Scripts manuais; idempotência; resultados de AD e GLPI separados; falhas associadas ao operador.

## Evidências observadas no código local

| Origem | Evidência e alcance |
| --- | --- |
| `formmanager/front/form.php`, gravação dos campos | `pastas_rede_json` contém uma lista de objetos com `caminho` e `permissao`. Não é necessário extrair dados do HTML do chamado. |
| `formmanager/src/Form.php`, campo `tipo_servico_rede` | Valores exatos relevantes: `CADASTRO`, `RENOVAR USUÁRIO` e `BLOQUEIO DE ACESSO`. Existem outros serviços que não devem ser convertidos para essas operações. |
| `formmanager`, campo `vinculo` | Os valores incluem `CONTRATADO` e `ESTAGIÁRIO`; não são sempre iguais aos rótulos `CONTRATO`/`ESTAGIARIO` da automação. Outros vínculos não devem ser classificados silenciosamente. |
| `automacao/GruposAd.psm1` | Busca LDAP por trecho em `name` ou `sAMAccountName`, restringe `name` ao prefixo `G-ADM-`, pesquisa a subárvore do domínio e preserva ocorrências em domínios/OUs diferentes. Limites e falhas são apresentados como resultado parcial. |
| `automacao/RenovacaoAd.psm1` | Regras de validade; CPF no atributo configurado ou em Escritório; identificação alternativa por nome único sem CPF; GUID da conta; revalidação antes da escrita; preenchimento de CPF vazio e confirmação posterior. |
| `automacao/CpfAd.psm1` | Desativação com `Disable-ADAccount`. Essa implementação antiga pode identificar pelo nome sem exigir CPF; não basta copiar esse comportamento sem tratar as novas entradas do plugin. |
| `admanagement/ImportadorDaSolicitacao.php` | Atualmente só admite criação e renovação e não importa pastas nem vínculo empregatício. |
| `admanagement/RepositorioDeOperacoes.php` | Já contém reserva, posse, tentativa e resultados separados. A reserva atual exige chamado/formulário positivos e só admite criação/renovação; lotes independentes precisam de um contrato próprio. |

Nenhum script da automação foi executado contra o AD nesta investigação. Alterações pré-existentes nessa pasta e no produto serão preservadas.

## Fluxo de solicitações

1. Manter os filtros, direitos e limites de entidades atualmente implementados. Esta ampliação não redefine a política de acesso vigente.
2. Ao importar, reler o chamado e o formulário aprovado, exigir o vínculo único de rede e confirmar que o técnico pode atender esse chamado.
3. Usar o valor exato de `tipo_servico_rede` para definir a operação. Apresentar o serviço original e a operação correspondente na tela. Um POST não poderá trocar criação por bloqueio ou renovação.
4. Importar `vinculo` e os objetos de `pastas_rede_json`, quando disponíveis. JSON inválido ou campo ausente produzirá aviso e revisão manual, sem escolher dados por aproximação.
5. Criação manterá a revisão de nome social, sugestões de login, destino, Setor e Scripts. Esta ampliação não aprova uma matriz de atributos de criação que antes estava pendente.
6. Renovação ou bloqueio usará a conta informada na origem como evidência inicial e fará a identificação real no AD. O nome/login isolado não se torna prova definitiva de identidade.
7. Consultar, revisar, preparar uma prévia e executar serão ações distintas. Importar e salvar não escreverão no AD.
8. Antes da execução individual, reler aprovação, vínculo, serviço, autorização, revisão e identidade AD. Qualquer mudança invalidará a prévia.

## Busca de grupos de acesso a pastas

Manter um bloco dentro da revisão, com três áreas: **Solicitado no formulário**, **Grupos encontrados no AD** e **Adicionar manualmente**. Mostrar domínio e localização de cada ocorrência.

A normalização será usada para produzir termos de consulta, preservando o texto original e a permissão solicitada. Rótulos como ESCRITA/LEITURA, letras de unidade, separadores, referências de servidor e compartilhamento não serão tratados como nomes de grupos encontrados. O segmento final útil do caminho poderá formar o termo inicial. Não reconstruir um caminho UNC como se tivesse sido verificado.

O resultado de busca conterá somente objetos efetivamente retornados e validados no domínio consultado. Uma sugestão nunca será formada apenas concatenando `G-ADM-` ao texto do formulário. Grupos iguais em nomes, mas diferentes em GUID, domínio ou localização, permanecerão separados. Mais de uma correspondência exigirá escolha do técnico.

O técnico poderá pesquisar outro trecho, selecionar mais de um resultado, remover uma seleção ou digitar uma referência manual. Texto manual permanecerá identificado como **não verificado** até ser resolvido no AD. Ausência confirmada, limite e falha de consulta terão mensagens diferentes.

Encontrar ou selecionar um grupo não comprova existência física da pasta nem concessão de permissão. Nesta ampliação, a seleção compõe a revisão; inclusão de membros em grupos, criação de pastas e alteração de ACLs não estão autorizadas como efeitos novos. Setor e Scripts continuam campos manuais com os efeitos já delimitados no projeto.

## Identificação e execução no AD

Reutilizar TLS e configuração própria do plugin. As regras serão portadas para PHP; não haverá execução de PowerShell recebendo texto de formulários ou da planilha. Credenciais continuarão fora dos arquivos e do navegador.

A consulta deverá retornar GUID, login, domínio, controlador, nome, estado da conta, validade e evidências de CPF estritamente necessárias. A seleção terá identificador assinado e limitado à revisão atual, nunca um DN arbitrário aceito diretamente do POST.

CPF será normalizado e validado. No atributo configurado e em Escritório, números de matrícula não serão concatenados ao CPF. CPF existente divergente, inválido ou conflitante exigirá revisão e impedirá usar a alternativa pelo nome.

Antes de considerar CPF ou login como discriminadores, verificar quantas contas distintas têm o mesmo nome completo nos dois domínios. Mais de uma conta impede a operação, independentemente do CPF, login, OU ou estado ativo/desativado. Não disponibilizar seleção manual para ultrapassar esse impedimento. Repetições da mesma conta em respostas LDAP serão deduplicadas por domínio e GUID antes da contagem; contas distintas continuarão distintas.

Comparar nomes completos normalizados para caixa, acentos e espaços, preservando o nome original na apresentação. Não usar trechos de nome como prova de unicidade. A verificação não poderá considerar completa uma busca que falhou em um dos domínios.

Para renovação sem CPF nos campos consultados, exigir nome completo único no escopo dos dois domínios. Para criação, conservar também a regra já existente que impede duplicidade com uma conta da mesma pessoa; a barreira de homônimos não autoriza criar uma segunda conta quando só existe uma correspondência.

Preencher CPF vazio utilizará operação de adição condicionada ao atributo vazio e validado como valor único. Não substituir valores preenchidos, inclusive por concorrência. Escritório será fonte de leitura, não destino da escrita. Se CPF for preenchido e a renovação falhar, registrar resultado parcial e reconciliar, sem repetir cegamente as alterações.

Bloqueio utilizará a conta identificada e confirmada e alterará somente o indicador de desativação, preservando os demais indicadores da conta. Conta já desativada será informada como tal, sem nova escrita. Por escolha técnica conservadora proposta, bloqueio exigirá CPF correspondente; a alternativa de nome único foi confirmada pelo usuário especificamente para renovação.

Antes de escrever, conferir novamente identidade, configuração, domínio, controlador, login, estado e validade utilizados pela prévia. A confirmação de sucesso exigirá releitura da mesma conta. Mudança concorrente ou resultado incerto exigirá reconciliação, nunca nova tentativa automática.

Renovação e bloqueio não moverão OU, renomearão usuário nem trocarão senha. A prévia mostrará apenas os efeitos efetivamente aplicáveis à operação.

## Aba Lotes

Apresentação proposta, no estilo dos cartões e tabelas atuais:

1. **Importar planilha**: instrução curta, arquivo e exemplo de colunas.
2. **Revisar linhas**: nome, CPF mascarado, objetivo, vínculo, domínio/conta encontrada, validade atual/proposta e situação.
3. **Consultar os dois domínios**: mostrar cobertura e pendências por linha; impedir a linha quando houver mais de uma conta com o mesmo nome, sem seleção alternativa.
4. **Confirmar selecionados**: resumo com total de renovações e bloqueios, contas e efeitos. A confirmação será explícita e não ocorrerá no upload.
5. **Resultados**: estado individual, falhas, alterações parciais e ações de reconciliação; exportação minimizada e sem fórmulas executáveis.

Escolhas técnicas propostas para revisão:

- Aceitar `.xlsx` e `.csv` UTF-8; rejeitar macros, formatos antigos `.xls`, fórmulas e links externos. CPF deve ser lido como texto, preservando zeros iniciais; valor perdido pela planilha não será completado por suposição.
- Colunas obrigatórias: `cpf`, `nome`, `objetivo`. Aceitar cabeçalhos equivalentes em caixa/acentos. `vinculo` obrigatório quando objetivo for renovar. Aceitar como vínculos equivalentes somente contrato/contratado e estagiário, conforme os valores comprovados na origem; outros exigirão correção.
- Objetivos estritos: renovar e bloquear, com normalização de caixa/acentos. Não inferir ação de comentário ou nome de pessoa.
- Limites iniciais de 2 MiB, 500 linhas e 20 MiB de conteúdo descompactado. Informar limites na interface e permitir ajuste administrativo posterior, sem upload irrestrito.
- Importação inválida substituirá a seleção de trabalho anterior, evitando executar um lote antigo por engano. Linhas inválidas ficarão impedidas; linhas válidas poderão ser explicitamente selecionadas.
- Exigir os dois domínios configurados e consultados com sucesso antes de liberar ações. Falha em um domínio será cobertura incompleta, não usuário inexistente ou nome único comprovado.
- CPF repetido com objetivos conflitantes, linhas duplicadas e mais de uma linha para o mesmo GUID produzirão impedimento, sem executar duas operações na mesma conta por engano.
- Uma permissão nativa específica de lotes, administrada nos perfis GLPI e sem concessão automática, distinguirá operações independentes da autorização sobre chamados. A entidade ativa e o operador ficarão vinculados ao lote; mudar o contexto invalidará a confirmação. Essa entidade identifica a autoria, não limita sozinha os objetos do AD.
- Prévia de execução válida por até 5 minutos e no mesmo dia civil. Mudanças de conteúdo, seleção, perfil, entidade, direitos, configuração ou evidências exigirão nova consulta e confirmação. Manter a revisão local existente separada dessa prévia de escrita.
- Repositório durável privado para o lote e seus resultados, fora de `front/` e de diretórios públicos. Arquivo original será descartado depois de lido; dados de trabalho serão mantidos na sessão. Metadados duráveis não conterão CPF/nome em claro, credenciais ou respostas LDAP completas.
- Processar uma linha por requisição explícita, com controle de posse e resultado durável. A página poderá oferecer continuar as linhas restantes, sem manter uma requisição longa de centenas de escritas e sem processamento disparado por GET.

Lotes independentes não criarão chamados fictícios, aprovações fictícias ou vínculos artificiais com formulários. Não fecharão chamados apenas por correspondência de nome ou CPF.

## Idempotência e resultados

Uma operação confirmada terá identidade estável da necessidade, GUID do alvo, tipo, revisão e operador. O reenvio do mesmo lote não poderá renovar novamente a mesma revisão; renovar é especialmente sensível, pois somar o prazo outra vez alteraria o resultado pretendido.

Reserva deverá impedir concorrência sobre a mesma conta entre execução individual e lotes. Conteúdo de CPF não servirá como chave pública ou hash simples que permita enumeração. A repetição exata de um upload deverá ser reconhecida por assinatura privada e não gerar uma nova execução automaticamente. Uma futura necessidade legítima exigirá nova revisão, nova leitura do estado e uma nova confirmação.

Registrar separadamente: preparado, impedido, aguardando confirmação, reservado, em andamento, sucesso comprovado, sem alteração necessária, falha antes da escrita e resultado parcial/incerto. Expiração de posse não provará que a escrita não ocorreu. Reconciliar a releitura antes de permitir qualquer retomada.

Para solicitações com chamado, preservar resultado AD separado da finalização GLPI. Se o AD tiver sucesso e a finalização falhar, repetir apenas a finalização. A decisão anterior entre solucionar e fechar e a responsabilidade pelos efeitos posteriores do formmanager continuam pendentes; esta ampliação não as resolve por suposição nem autoriza uma transição nova.

## Responsabilidades previstas na base

Manter as pastas atuais e reutilizar as classes existentes onde a responsabilidade já existe. Estes nomes são uma proposta de divisão, a ser refinada no plano de implementação:

| Parte | Responsabilidade |
| --- | --- |
| `LeitorDoFormmanager`, `ImportadorDaSolicitacao` | Ler campos mínimos adicionais e importar o serviço correto, sem alterar a origem. |
| `FormularioDaSolicitacao`, `CoordenadorDaRevisao`, `RevisorDaOperacao` | Apresentar serviços específicos, coordenar consultas, invalidar prévias e validar campos pertinentes a cada operação. |
| `LeitorLdap` | Consultar grupos e identidades com paginação e cobertura explícitas, mantendo exclusivamente leitura. |
| `BuscaDePastas`, `ApresentacaoDePastas` | Limpar termos, resolver grupos retornados, tratar seleção/manual e apresentar o bloco de pastas. |
| `PoliticaDeRenovacao` | Datas e elegibilidade, inclusive limites de calendário equivalentes à automação. |
| `OperadorDoDiretorio` | Escritas limitadas, revalidação de pré-condições e confirmação por releitura. Compartilhar infraestrutura TLS, sem copiar configuração insegura. |
| `LeitorDePlanilhas`, `ProcessadorDeLotes`, `PaginaDeLotes` | Validar arquivo/linhas, coordenar estados e apresentar a aba PHP. |
| `Lote`, `ExecucaoDoDiretorio` | Modelos próprios herdados de `CommonDBTM`, com persistência GLPI, índices únicos para idempotência e reserva por conta, autoria e entidade. Reutilizar o histórico anterior de revisões sem inventar chamado/formulário ID zero. |
| `front/lotes.php`, navegação e aba nativa de perfil | Entrada protegida e capacidade específica, sob o kernel GLPI 12. |

Sem novo framework, servidor paralelo ou subprojeto de demonstração. A biblioteca para `.xlsx` será escolhida depois de conferir as dependências disponíveis e os limites de leitura; não presumir que o GLPI fornece uma biblioteca não comprovada.

## Critérios de aceite

| Cenário | Resultado verificável |
| --- | --- |
| Importar cada um dos três serviços | Operação correta e imutável por POST; origem não suportada é recusada. |
| Formulário sem aprovação ou vínculo ambíguo | Importação/execução individual recusada, sem escrita AD. |
| Texto ruidoso de permissão de pasta | Consulta por termo útil; somente grupos efetivamente retornados são sugeridos; original e permissão preservados. |
| Busca por trecho, homônimos de grupos e entrada manual | Domínio/localização distinguem resultados; manual não aparece como verificado antes da consulta. |
| Falha ou limite LDAP | Cobertura parcial visível; nunca concluir ausência com base em consulta incompleta. |
| Renovação contratual e estágio | Prazos confirmados; vencida usa instante da consulta; teste de fim de mês, ano bissexto e 90 dias exatos. |
| Sem expiração, conta desativada ou prazo superior a 90 dias | Nenhuma renovação enviada. |
| CPF em Escritório com matrícula e pontuação | Extrair somente CPF pertinente, validar e não juntar matrícula. |
| CPF divergente ou inválido | Impedir execução e exigir correção. |
| Mais de uma conta com o mesmo nome, com ou sem CPFs diferentes | Impedir a transação individual ou a linha afetada, mesmo com CPF/login correspondente; POST não pode forçar seleção. Contas desativadas também contam. |
| Nome único sem CPF na renovação | Método explícito; CPF adicionado somente se vazio, confirmado e preservado antes da validade. |
| Concorrência no CPF ou na validade | Pré-condição falha; não substituir dado concorrente nem anunciar sucesso completo. |
| Bloqueio | Somente desativação; demais indicadores preservados; conta já desativada sem nova escrita. |
| Planilha sem coluna, CPF inválido, objetivo inválido ou renovação sem vínculo | Linha/arquivo com erro claro e sem execução. |
| Fórmulas, macro, arquivo excessivo ou arquivo disfarçado | Recusa sem avaliar conteúdo ativo nem publicar arquivo. |
| Busca nos dois domínios e duas contas correspondentes pelo nome | Cobertura visível; bloqueio por homônimo, sem escolha pela ordem nem liberação manual da ocorrência. |
| Upload, GET ou clique em salvar | Zero escritas AD. |
| Confirmação repetida, dois navegadores ou upload repetido | Uma execução por necessidade/revisão, com conflito tratado entre lote e chamado. |
| Erro após envio ou após preencher CPF | Resultado incerto/parcial e reconciliação, sem tentativa cega de renovar outra vez. |
| Lote independente | Nenhuma criação/finalização artificial de chamado; operador, contexto e resultado registrados. |
| Controle de acesso | Acesso direto/POST indevido recusado; direitos existentes não são ampliados automaticamente. |
| Interface e código | Layout atual em telas largas/estreitas; rótulos PT-BR; funções documentadas; suítes existentes e novas passam em PHP 8.3. |

## Evidências oficiais e limites da pesquisa

Pesquisa explícita com `glpi-official-docs-research`, em 12/11/2026, America/Sao_Paulo. Pergunta: quais contratos de entrada PHP e proteção de POST devem ser preservados nesta ampliação do GLPI 12?

Alvo oficial: GLPI **12.0.1**, commit **7edb847cceb7ebf950171f6ab031aea8b95e45b1**. A documentação `latest` foi usada como localizador, sem definir compatibilidade sozinha. Testes oficiais abaixo foram **lidos, não executados**. Nenhuma pesquisa externa recebeu código privado ou dados internos.

| Conclusão | Tipo / fonte / símbolo | Confiança | Aplicabilidade / lacunas |
| --- | --- | --- | --- |
| As entradas PHP existentes podem continuar sob o controlador legado do kernel. | Observada no [código oficial `LegacyFileLoadController::__invoke`](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/Glpi/Controller/LegacyFileLoadController.php). Código da versão exata. | Alta: execução do arquivo e formação da resposta estão explícitas. | Confirmada na release oficial; nova entrada e instalação local ainda precisam de teste HTTP. |
| POST continua sujeito à proteção nativa de origem; não desabilitar o listener para upload ou execução. | Observada no [código oficial `CheckCsrfListener`](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/Glpi/Kernel/Listener/ControllerListener/CheckCsrfListener.php) e nas asserções de [testes E2E de CSRF](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/tests/e2e/specs/Security/csrf.spec.ts), ambos da mesma versão. | Alta: testes cobrem POST permitido/recusado conforme cabeçalhos. | Confirmada no código oficial; não comprova segurança ou funcionamento dos nossos endpoints futuros. |
| `accountExpires` usa intervalos de 100 ns desde 1601 UTC; dois valores representam ausência de expiração. | Documentada: [Microsoft, atributo Account-Expires](https://learn.microsoft.com/en-us/windows/win32/adschema/a-accountexpires). Símbolo `accountExpires`, documentação AD. | Alta: representação e valores especiais expressos. | Aplicável à representação do atributo; esquema, delegação e escrita da instalação ainda não homologados. |
| A desativação é um indicador acumulável da conta, distinto de expiração. | Documentada: [Microsoft, propriedades UserAccountControl](https://learn.microsoft.com/en-us/troubleshoot/windows-server/active-directory/useraccountcontrol-manipulate-account-properties). Símbolo `ACCOUNTDISABLE`. | Alta: indicador e combinação explícitos. | Sustenta preservar os demais indicadores; operação concorrente precisa de pré-condição e teste próprios. |

## Pendências que não viram aprovação por este documento

- Desenho revisado e autorizado para implementação pelo usuário em 12/11/2026; a entrega deverá registrar o que foi efetivamente testado.
- Configuração e credenciais delegadas dos dois domínios, com certificado validado. Não solicitar senhas pelo chat. A investigação atual não provisionou contas nem executou escritas reais.
- Atributo CPF configurado e comprovado no esquema; permissões de leitura/escrita necessárias. Resultado sem essa comprovação permanece impedido.
- Homologação controlada dos executores com contas explicitamente autorizadas; testes de lógica e dublês não serão apresentados como escrita real validada.
- Matriz completa de criação, efeitos de Setor/Scripts/grupos e finalização do chamado continuam nos limites das decisões anteriores. Não ampliar por associação com a busca de grupos.
- Nenhum perfil receberá a nova permissão de lotes automaticamente. O administrador deverá concedê-la na matriz nativa de perfis.

Depois da revisão, elaborar o plano de implementação na mesma base, implementar por etapas verificáveis e atualizar os documentos de estado conforme o código realmente entregue.
