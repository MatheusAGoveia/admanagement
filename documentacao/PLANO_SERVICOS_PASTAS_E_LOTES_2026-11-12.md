# Plano de implementação — serviços, pastas e lotes

**Objetivo:** integrar as regras confirmadas da automação ao ADManagement, com busca de grupos G-ADM-, renovação, bloqueio e lotes independentes.

**Arquitetura:** conservar entradas PHP e apresentação existentes. Reutilizar sessão, direitos, perfis, configuração, chave privada, migrações e modelos `CommonDBTM` do GLPI; regras puras permanecem em classes próprias. Consultas e escritas LDAP têm responsabilidades separadas e compartilham conexão TLS validada.

**Tecnologias:** GLPI 12.0.1, PHP 8.3, LDAP/LDAPS, MariaDB, PhpSpreadsheet 5.10 fornecido pelo laboratório para XLSX e leitor CSV PHP.

**Especificação:** [desenho aprovado](PROPOSTA_SERVICOS_PASTAS_E_LOTES_2026-11-12.md). Execução autorizada pelo usuário no mesmo turno; implementação nesta sessão, sem novo pedido de aprovação do plano. Skills opcionais `superpowers:*` citadas pelo protocolo de planos não estão disponíveis neste catálogo; não serão instaladas.

## Restrições globais

- Nada de dados simulados no produto ou execução dos scripts PowerShell no servidor.
- Originais de formmanager e automacao preservados; alterações existentes de rótulo Setor preservadas.
- Nome repetido nos dois domínios impede a operação, mesmo com CPF distinto; consulta incompleta não comprova unicidade.
- Upload/importação/salvamento/GET não executam AD. Confirmar ações exige revisão vigente e autorização recalculada.
- Preservar prazo de contrato +1 ano, estágio +6 meses, janela de 90 dias, CPF existente e estado da conta.
- Nenhuma conta real será alterada nos testes desta implementação sem alvo e execução especificamente autorizados.
- Funções próprias em português e documentadas, estrutura plana existente. Comentários também nos testes.

## Foco da revisão

1. Homônimo em outro domínio ou conta desativada: bloquear antes da reserva/escrita; teste na política de identidade.
2. Fim de mês/ano bissexto/FileTime: datas equivalentes ao calendário da automação; teste de política de renovação.
3. Fórmula/ZIP excessivo/CPF truncado no XLSX: rejeitar sem avaliar nem completar zeros; teste do leitor de planilhas.
4. Duas confirmações, inclusive lote versus atendimento: uma reserva por conta; teste de persistência/concorrência e HTTP.
5. Escrita sem confirmação ou CPF preenchido com validade não confirmada: resultado incerto/parte concluída durável e sem repetição automática; teste do executor com adaptador controlado.

## Tarefa 1 — importação e busca de grupos

Arquivos: `src/LeitorDoFormmanager.php`, `ImportadorDaSolicitacao.php`, `BuscaDePastas.php`, `LeitorLdap.php`, `ApresentacaoDePastas.php`, `FormularioDaSolicitacao.php`, `CoordenadorDaRevisao.php`, `front/revisao.php`.

Interfaces: `BuscaDePastas::extrairSolicitadas(mixed): array`, `BuscaDePastas::termoDoCaminho(string): string`, `LeitorLdap::buscarGrupos(string): array`.

- [x] Testar três serviços, JSON inválido, campos opcionais, termos ruidosos, prefixo, escape e resultados distintos por domínio/GUID.
- [x] Importar vínculo/pastas e manter assinatura da origem.
- [x] Acrescentar consulta explícita e seleção validada; manual separado de encontrado.
- [x] Verificar testes da importação e HTML, preservando a navegação atual.

## Tarefa 2 — identidade e políticas de ações

Arquivos: novos `src/IdentidadeDoDiretorio.php`, `PoliticaDeRenovacao.php`; adaptar leitura LDAP e formulário de operação.

Interfaces: `IdentidadeDoDiretorio::normalizarNome(string): string`, `::normalizarCpf(string): string`, `::avaliar(array $pessoa, array $contas): array`; `PoliticaDeRenovacao::propor(string $filetime, string $vinculo, DateTimeImmutable $agora): array`.

- [x] Testar homônimos entre domínios, CPF conflitante/Escritório, nome único sem CPF, conta desativada e duplicação da mesma resposta.
- [x] Testar limites de calendário e os dois sentinelas de não expiração.
- [x] Implementar consulta paginada com atributos mínimos e cobertura completa dos dois domínios.
- [x] Apresentar prévia específica por serviço sem exigir OU nova para renovação/bloqueio.

## Tarefa 3 — persistência nativa e execução protegida

Arquivos: novos `src/Lote.php`, `ExecucaoDoDiretorio.php`, `InstalacaoDaFerramenta.php`, `OperadorDoDiretorio.php`; adaptar `PerfilDaFerramenta.php`, `AcessoGlpi.php`, `hook.php`, `setup.php`.

Interfaces: modelos `Lote extends CommonDBTM` e `ExecucaoDoDiretorio extends CommonDBTM`; `OperadorDoDiretorio::preparar(array): array`, `::executar(array $pessoa, array $previa, array $origem, array $contexto): array`.

- [x] Testar prévia sem escrita, revalidação, homônimos, reserva única, pré-condições concorrentes, confirmação e resultado parcial/incerto.
- [x] Instalar somente tabelas próprias com `Migration`; direitos nativos sem concessão automática. Usar `GLPIKey` para assinaturas privadas, sem divulgar a chave.
- [x] Reservar necessidade/alvo de forma durável antes dos efeitos. Registrar intenção antes de cada escrita e confirmar o mesmo GUID por releitura.
- [x] Integrar ações explícitas de preparar/executar ao atendimento, com origem/autorização revalidadas e resultado separado da finalização pendente do chamado.

## Tarefa 4 — planilhas e aba Lotes

Arquivos: novos `src/LeitorDePlanilhas.php`, `ProcessadorDeLotes.php`, `PaginaDeLotes.php`, `front/lotes.php`; adaptar navegação.

Interfaces: `LeitorDePlanilhas::ler(string $caminho, string $nome): array`, `ProcessadorDeLotes` coordena importação/consulta/confirmação/execução por linha; `PaginaDeLotes::mostrar(array): void`.

- [x] Testar CSV/XLSX reais, cabeçalhos, CPF em texto, fórmulas, formatos não aceitos, vínculos e limites.
- [x] Criar prévia PHP, campos de seleção, resultados por linha e histórico privado do lote, com negação por perfil/autor/entidade.
- [x] Uma confirmação vigente autoriza somente as linhas escolhidas da mesma revisão. Continuar processamento requer POST e respeita reservas/resultados anteriores.
- [x] Exportar CSV minimizado e neutralizar fórmulas; nunca salvar upload em área pública.

## Tarefa 5 — integração, testes e documentação de entrega

- [x] Registrar base de comparação: onze suítes passaram em PHP 8.3 antes de alterar o produto.
- [x] Integrar novas suítes ao executor e verificar sintaxe/comentários de todos os PHP.
- [x] Executar suítes existentes e novas; corrigir expectativas apenas quando o comportamento aprovado mudou.
- [x] Atualizar cópia do laboratório por arquivos explícitos; instalar atualização pelo ciclo nativo; verificar navegação/importações e aba Lotes por HTTP real autenticado.
- [x] Testar falta de credenciais como impedimento real. Testes controlados não serão apresentados como validação de escrita no AD.
- [x] Atualizar README/documentação e relatório com arquivos, verificações e limitações concretas.

Autorrevisão do plano: requisitos distribuídos entre as cinco tarefas; interfaces usadas no fluxo conferidas; os cinco riscos acima têm tarefa/teste correspondente. Não criar uma árvore de pastas nova nem alterar os plugins de referência.

Verificação concluída no código e no laboratório local; escritas reais em AD e avaliação visual em navegador não foram homologadas. Veja os limites no relatório de entrega. Nenhum commit foi criado nesta execução; alterações pré-existentes foram preservadas.
