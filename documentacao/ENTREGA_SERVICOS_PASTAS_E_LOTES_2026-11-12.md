# Entrega 0.8.0 — serviços, pastas e lotes

Data: 12/11/2026, America/Sao_Paulo. Fonte: `C:/Users/matheus.damiao/Desktop/admanagement`. Implementação autorizada após revisão do desenho e confirmação das regras nesta conversa.

## O que foi implementado

- O serviço vem do campo `tipo_servico_rede` do formmanager: cadastro, renovar usuário ou bloqueio de acesso. Um POST não troca o serviço aprovado.
- `pastas_rede_json` é lido diretamente como caminho/permissão. Referências originais podem ser conferidas em uma área recolhida; a busca começa por um termo limpo e permite editar o trecho.
- Busca real de grupos `G-ADM-`, com domínio, GUID e localização; seleção validada contra o catálogo da sessão. Texto manual permanece separado e não verificado. Não existe varredura física de compartilhamentos ou aplicação automática de membros/ACL.
- Renovação contratual de 1 ano e de estágio de 6 meses; janela inclusiva de 90 dias, sem reativação. Finais de mês e anos bissextos seguem calendário equivalente à automação.
- CPF normalizado, inclusive em Escritório sem juntar matrícula. Renovação por nome único sem CPF é explicitamente identificada; CPF vazio é preenchido e confirmado antes da validade. CPF existente não é substituído.
- Bloqueio desativa a conta preservando os demais indicadores. Exige CPF correspondente; a exceção de nome único sem CPF foi aprovada para renovação.
- Mais de uma conta com o mesmo nome nos dois domínios impede a transação, inclusive contas desativadas e CPFs diferentes. Nenhum seletor permite contornar essa regra.
- Aba Lotes em PHP: XLSX/CSV, CPF/nome/objetivo obrigatórios e vínculo nas renovações. Importar, consultar, confirmar e executar são ações diferentes.
- O lote é independente de chamados. Cada POST executa uma linha confirmada, com nova consulta nos dois domínios. GET e recarregamento não executam a fila.
- Mesmo conteúdo retoma o lote original. Para uma necessidade posterior, a opção explícita de novo atendimento usa desafio consumível; não é acionada automaticamente no reenvio.
- Resultados individuais, exportação mascarada, histórico mínimo por operador/entidade e reconciliação somente de leitura. Efeito incerto permanece reservado; não há repetição automática.

O código PowerShell foi consultado como referência. Não é executado pelo plugin. Os originais de `automacao` e `formmanager` não foram alterados.

## Aproveitamento do GLPI

| Recurso | Utilização |
| --- | --- |
| `CommonDBTM` | Ancestral real de `Lote` e `ExecucaoDoDiretorio`; inclusão/leitura pelo modelo nativo. Não há CRUD público genérico para editar estados. |
| `Migration` | Criação idempotente de duas tabelas próprias no ciclo de instalação/atualização. |
| `CommonGLPI`, `Profile`, `ProfileRight` | Aba e matriz nativa de perfis; direito adicional `plugin_admanagement_lotes`. |
| `Session` e `Ticket::can()` | Sessão, perfil, entidades e acesso aos chamados preservados. |
| `Config` e `GLPIKey` | Configuração existente e assinaturas com a chave privada nativa; nenhuma senha ou chave nova em arquivo do plugin. |
| Kernel, cabeçalhos de origem e `Html` | Entradas PHP sob o kernel GLPI 12, proteção nativa de POST e apresentação integrada. |
| PhpSpreadsheet | Biblioteca 5.10.0 já fornecida pelo GLPI do laboratório, usada sem instalar dependência paralela. CSV permanece disponível se XLSX não estiver disponível. |

O produto passa a ter 45 classes próprias, 8 entradas em `front/` e 71 arquivos PHP incluindo testes. A estrutura de pastas continua simples. Regras puras de CPF e datas não herdam um modelo de banco sem necessidade.

As tabelas novas são `glpi_plugin_admanagement_lotes` e `glpi_plugin_admanagement_execucoes`. Índices únicos separam idempotência da necessidade e reserva ativa da conta; lote e chamado disputam a mesma reserva. Atualizações condicionais usam exclusivamente tabela própria e a API atual `getAffectedRows()`.

## Verificações executadas

Alvo efetivamente utilizado: **GLPI 12.0.1, PHP 8.3.36 CLI/web e MariaDB 10.11.19**, no laboratório loopback existente. A instalação foi atualizada para 0.8.0 pelo objeto nativo `Plugin` e confirmada ativa em uma nova requisição.

| Verificação | Resultado |
| --- | --- |
| Sintaxe PHP | Arquivos do produto e dos testes verificados. |
| Suítes existentes e novas | 14 suítes; cenários de serviço, CPF, homônimo, calendário, planilhas, execução parcial e idempotência. |
| Comentários | 71 PHP; funções, classes e controles cobertos pelo verificador estrutural. |
| Modelo nativo | Reserva, necessidade única, alvo único e atualização condicional exercitados no MariaDB real, sem criar adaptador LDAP. Registros transitórios desse teste removidos pelo modelo. |
| Instalação | Tabelas próprias existentes; ambos os modelos com ancestral `CommonDBTM`; zero concessões automáticas do direito de lotes. |
| Fila e importações HTTP | HTTP 200 na fila e nos chamados de teste 6, 7, 8 e 9 já existentes. |
| Lotes sem direito | HTTP 403. |
| Lotes com direito concedido para o teste | HTTP 200; CSV importado somente para revisão. Direito original restaurado ao terminar. |
| Falta do segundo domínio | HTTP 409 explícito na renovação e nos lotes, sem efeito AD. |
| POST externo | HTTP 403 pela proteção nativa. |
| Execução sem prévia | HTTP 409. |
| Upload inválido | Recusado e trabalho anterior retirado da seleção. |
| Arquivos reais | CSV com vírgula/ponto e vírgula/BOM; XLSX gerado/lido pela biblioteca nativa; fórmulas, múltiplas abas e excesso de tamanho recusados. |
| Exportação | CPF mascarado e conteúdo ativo neutralizado, inclusive após espaços. |
| Escritas no AD real | **Nenhuma.** Testes de efeitos foram feitos com adaptadores controlados em `tests/`. |

Os testes HTTP exercitam HTML e comportamento do servidor. Não foi feita homologação visual em navegador nem inspeção de todos os tamanhos de tela. Os testes isolados não certificam permissões, esquema ou escrita do AD real.

## Evidências oficiais

Pesquisa explícita com `glpi-official-docs-research`, consulta em 12/11/2026. Alvo GLPI 12.0.1, commit `7edb847cceb7ebf950171f6ab031aea8b95e45b1`. Pergunta: como reutilizar persistência, migração e confirmação de atualização nativas sem ampliar privilégios ou desligar o kernel?

| Conclusão | Tipo / fonte / símbolo | Confiança | Aplicabilidade e lacuna |
| --- | --- | --- | --- |
| Persistência reutiliza modelos, em vez de escrever tabelas internas diretamente. | Observada no [código `CommonDBTM`](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/CommonDBTM.php) e nos testes oficiais [`CommonDBTMTest::testAdd`/`testUpdate`](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/tests/functional/CommonDBTMTest.php). Código e testes da release indicada. Asserções lidas verificam inclusão, releitura e datas dos modelos; os testes oficiais não foram executados nesta pesquisa. | Alta para os contratos lidos; inclusão/reserva também verificadas no laboratório com nossos modelos. | Confirmada no laboratório para os caminhos exercitados; não abrange qualquer customização do servidor externo. |
| Instalação pode agendar estruturas próprias no ciclo nativo. | Observada em [`Migration`](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/Migration.php), símbolos `addPostQuery` e `executeMigration`. | Alta para API da release; criação efetivamente exercitada localmente. | Confirmada para as duas tabelas próprias no laboratório. Migração do servidor externo ainda precisa de backup e execução administrativa. |
| Contagem de linhas alteradas usa a API atual do GLPI 12. | Observada em [`DBmysql::getAffectedRows`](https://github.com/glpi-project/glpi/blob/7edb847cceb7ebf950171f6ab031aea8b95e45b1/src/DBmysql.php); `affectedRows` está depreciado na mesma revisão. | Alta: método e depreciação expressos, atualização própria exercitada localmente. | Confirmada na release e no caminho testado no laboratório. |
| O alvo AD usa identidade estável e a escrita compara o valor anterior. | Documentada a identidade em [Microsoft, vínculo por objectGUID](https://learn.microsoft.com/en-us/windows/win32/ad/using-objectguid-to-bind-to-an-object); API de alterações em [PHP, ldap_modify_batch](https://www.php.net/manual/en/function.ldap-modify-batch.php); atomicidade da solicitação no [RFC 4511, seção 4.6](https://www.rfc-editor.org/rfc/rfc4511#section-4.6). Fontes de protocolo/API, não prova de uma instalação específica. | Alta para os conceitos documentados; média para aplicação operacional ainda não homologada. | Não determinada para as escritas no AD real. Confirmar aceitação das modificações, GUID, schema e delegação no controlador de teste antes de uso operacional. |

A pesquisa do desenho também abriu o controlador legado e o listener/teste oficial de CSRF da mesma revisão. Não foi feita revisão exaustiva de todos os testes oficiais de persistência/migração. Documentação `latest` foi usada como localizador, sem substituir a versão exata.

## Impedimentos reais para testes de escrita

1. Configurar os **dois domínios**, com raízes distintas. A consulta confirma a raiz informada pelo servidor; Base DN limitada a uma OU não comprova inexistência de homônimos.
2. Disponibilizar as credenciais por variáveis de ambiente no PHP web. A conta/configuração ainda precisa de provisionamento; não enviar senha pelo chat. No início dos testes, o inicializador confirmou ausência de credencial.
3. Homologar atributo CPF simples e gravável, certificados e delegação de `accountExpires`, `userAccountControl` e CPF quando aplicável. A configuração atual de leitura não certifica autorização de escrita.
4. Conceder o direito de lotes ao perfil que fará esses testes. O teste HTTP usou concessão temporária, depois restaurada para zero.
5. Escolher contas de homologação explicitamente autorizadas. Resultados parciais/estado diferente do esperado exigem reconciliação; não pressionar novas tentativas para descobrir se funcionou.

Um erro depois do envio não é prova de falha sem efeito. Se a releitura não demonstrar sucesso integral, a reserva é mantida para análise. Reconciliação não completa automaticamente a parte restante nem libera repetição cega.

## Limites preservados

- Criação mantém importação/revisão, nome social, sugestões de login e OU; a matriz de escrita de criação continua pendente.
- Setor, Scripts e grupos selecionados continuam sem efeitos automáticos de associação, execução de script ou ACL.
- Ainda não foi decidido entre solucionar e fechar o chamado nem quais efeitos posteriores pertencem ao formmanager. Esta entrega não altera status de chamados após AD.
- As regras atuais de acesso à fila foram preservadas; esta ampliação não resolveu a divergência histórica entre atribuição individual e associação a grupos.
- O mapa/UML de 06/11 representa a base anterior de 31 classes; o guia de arquitetura registra as 14 classes acrescentadas.
- Não houve implantação no servidor remoto. A cópia atualizada está na pasta local do produto e no laboratório. Pacotes antigos 0.7.1 não representam esta versão.

Os documentos de desenho e plano registram as decisões. Este relatório distingue implementação e testes locais da homologação que ainda depende do diretório real.
