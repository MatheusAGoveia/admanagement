# Configurar acesso por perfil

ADManagement 0.7.0 — documentação do código em 28/10/2026. A autorização vem exclusivamente do **perfil ativo**. Pertencer a um grupo ou constar como usuário em uma configuração antiga não concede acesso.

## Direitos e hierarquia

| Perfil/capacidade | Chamados visíveis | Configuração global |
| --- | --- | --- |
| Superadministrador nativo com `config/UPDATE` | Todos os permitidos pelo GLPI nas entidades ativas | Sim |
| Perfil com `plugin_admanagement/UPDATE` | Somente setores vinculados ao perfil, nas entidades correspondentes | Não |
| Perfil com `plugin_admanagement/READ` | Somente chamados atribuídos individualmente ao próprio técnico | Não |
| Perfil sem essas capacidades | Sem acesso | Não |

A identificação do superadministrador usa o critério nativo de perfil central que pode editar outros perfis; não depende do nome “Super-Admin”. O direito `config/UPDATE` também é obrigatório. Supervisão (UPDATE) prevalece sobre operação pessoal (READ): um supervisor sem setores vinculados fica sem acesso, inclusive quando READ está marcado.

As entidades e a leitura de cada chamado continuam sujeitas ao GLPI. Ter direito do plugin não substitui `Ticket::can(READ)`. O técnico precisa estar em **Atribuído** como usuário; requerente e observador não correspondem a essa atribuição. Para supervisão, o setor corresponde aos grupos responsáveis em **Atribuído**, sem usar lotação do formulário ou localização como autorização.

## Instalação ou atualização

1. Atualize os arquivos para 0.7.0 e execute a instalação/atualização pela administração de plugins do GLPI.
2. O hook registra a chave nativa `plugin_admanagement` sem conceder direitos. A instalação é idempotente e não promove o perfil instalador.
3. Entre com o perfil administrativo nativo autorizado e abra **Administração → Perfis → perfil da interface central → ADManagement**.
4. Para técnicos, marque **Operar atribuições pessoais**. Para administradores de setor, marque **Supervisionar setores vinculados**. Salve o formulário nativo de perfil.
5. Ative o perfil correspondente na sessão para conferir seu resultado. Direitos de outros perfis vinculados ao usuário não são somados.

## Setores e vínculos de supervisão

1. Abra **ADManagement → Configurações → Setores e vínculos de perfil**.
2. Cadastre um setor, selecione seus grupos responsáveis reais e as entidades em que atua. Um setor pode ter mais de um grupo.
3. No formulário de vínculo, selecione o perfil central, os setores atendidos e as entidades explícitas; mantenha o vínculo ativo e salve.
4. Confira o cadastro na tabela. Edição e exclusão conferem a revisão atual do documento para impedir sobrescrita por uma aba antiga. A exclusão exige confirmação; retire o setor dos vínculos antes de excluí-lo.
5. Configure as **categorias ITIL da operação** separadamente. Elas restringem importação, sem ocultar outros chamados do escopo autorizado.

O mapa não concede READ ou UPDATE: ele apenas limita o alcance de um perfil que já tenha supervisão nativa. Grupos e entidades permanecem pareados por setor para não cruzar permissões. Selecione explicitamente subgrupos e entidades filhas necessários. A seleção nativa de entidades também precisa incluí-los.

## Exemplos

| Capacidade do perfil ativo | Mapa | Resultado |
| --- | --- | --- |
| Técnico Rede: READ | Nenhum vínculo necessário | Somente chamados atribuídos ao técnico conectado |
| Gestor Rede: UPDATE | Setor Rede nas entidades selecionadas | Chamados dos grupos responsáveis desse setor, mesmo com outro técnico atribuído |
| Gestor Saúde: UPDATE | Setor Suporte Saúde nas entidades selecionadas | Somente os chamados desse setor no alcance nativo |
| Perfil sem direito | Participação em qualquer grupo | Sem acesso ao plugin |

Dois usuários com o mesmo perfil ativo compartilham suas capacidades e o mapa desse perfil. Para administrar setores diferentes, use perfis distintos ou contextos de entidade que os distingam. A atribuição individual continua separando a fila dos técnicos.

## Migração de uma configuração anterior

Documentos de visibilidade versão 1 não são interpretados como permissões atuais. O superadministrador vê uma prévia e confirma a atualização. A migração preserva o documento anterior em backup, todos os setores válidos e somente os vínculos administrativos cuja origem já era perfil. Concessões por usuário, grupo e regras técnicas antigas não se transformam em direitos de perfil.

Depois da confirmação, configure os direitos na matriz nativa. Se o documento não tiver estrutura recuperável, a recuperação explícita preserva o original e reinicia o mapa vazio. A recuperação não apaga uma política atual válida.

## Revisão e limites

Fila, URL direta, GET/POST, visão geral, histórico, pendências e auditoria revalidam o mesmo alcance no servidor. Histórico inclui solucionados e fechados; lixeira não aparece. Categoria diferente, vínculo de rede ausente/ambíguo ou origem sem aprovação impedem importação e apresentam o motivo.

Troca de perfil, direitos, entidades ou mapa invalida o resumo observado. A entidade ativa é distinta da entidade do chamado: abrir um chamado de entidade filha autorizada não invalida seu resumo por si só. Uma troca real de contexto observada torna o desafio anterior inutilizável.

O cadastro não cria perfis/grupos nem altera atribuições. A confirmação registra somente revisão local; não grava no AD e não fecha chamados. As suítes locais usam dublês. Homologação no GLPI real continua necessária.
