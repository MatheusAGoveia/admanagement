# Configurar setores e permissões

O setor responsável corresponde ao **grupo em Atribuído do chamado**. O setor de lotação escrito no formulário ou a localização não define a fila do plugin. O técnico responsável também deve estar em Atribuído para receber o chamado na fila pessoal.

## Hierarquia

| Papel | Chamados visíveis | Configuração global |
| --- | --- | --- |
| Super-Admin ativo com config/UPDATE | Todos os permitidos pelo GLPI nas entidades ativas, mesmo sem atribuição | Sim |
| Administrador de setor | Somente grupos responsáveis dos setores explicitamente concedidos, nas entidades correspondentes | Não |
| Técnico | Somente atribuição individual ao próprio usuário | Não |

O GLPI conserva seus direitos nativos de leitura e entidade. Concessões no plugin não substituem direitos do perfil GLPI. Solucionados e fechados ficam no Histórico; lixeira não aparece. Pendências também oculta metadados de chamados fora do alcance atual.

## Passo a passo

1. Entre como **Super-Admin**, com direito nativo de alterar configuração.
2. Abra **ADManagement → Configurações → Setores e permissões**.
3. Cadastre um setor, por exemplo **Rede**, selecione seus grupos responsáveis reais e as entidades em que ele atua. Use os seletores GLPI; não copie IDs de outra instalação. Pode haver mais de um grupo por setor.
4. Em **Conceder permissão**, escolha **Usuário individual**, **Grupo GLPI** ou **Perfil ativo**. Preencha o destinatário correspondente à origem escolhida; os demais campos não concedem acesso.
5. Escolha o papel **Técnico** ou **Administrador dos setores selecionados**. Técnico exige setores vazios; administrador exige ao menos um setor. Selecione as entidades da concessão e mantenha a permissão ativa.
6. Salve e confira a linha exibida na tabela. Use **Editar** para alterar ou desativar a concessão. A exclusão exige confirmação; remova vínculos das regras antes de excluir um setor.
7. Configure as **categorias ITIL da operação** separadamente. Elas permitem importação, sem esconder outros chamados autorizados.

**Primeira gravação:** a política nova passa a valer ao salvar o primeiro setor ou concessão. As listas antigas são preservadas em backup, mas deixam de conceder acesso. O Super-Admin continua acessando; cadastre em seguida as concessões dos demais usuários. Antes dessa ativação, quem atende à configuração anterior entra exclusivamente como técnico. Grupos antigos nunca viram administradores automaticamente.

## Exemplos

| Conceder por | Destinatário selecionado | Papel | Setores |
| --- | --- | --- | --- |
| Perfil ativo | Técnico Rede | Técnico | Vazio |
| Usuário individual | Usuário escolhido no catálogo | Administrador de setor | Rede |
| Grupo GLPI | Grupo de gestores escolhido pelo administrador | Administrador de setor | Suporte ADM |
| Usuário individual | Outro usuário escolhido | Técnico | Vazio |

Um técnico do grupo Rede vê somente chamados individualmente atribuídos a ele. Um administrador de Rede vê os chamados atribuídos aos grupos desse setor mesmo quando outro técnico os atende. Um usuário sem grupo pode receber permissão individual, mantendo o direito nativo GLPI obrigatório.

As regras são **cumulativas**: usuário OU grupo OU perfil ativo pode conceder acesso. Havendo concessões administrativas, vale a união dos setores explícitos. Administração prevalece sobre o papel técnico e permanece limitada aos setores, inclusive quando existir uma atribuição pessoal em outro setor. Uma regra técnica não revoga uma administrativa. Não há regra de negação individual nesta versão; para retirar acesso, desative todas as concessões que alcançam a pessoa.

Perfil é avaliado somente quando está ativo na sessão. Nenhum cadastro inclui filhos automaticamente: selecione explicitamente subgrupos e entidades filhas necessários. Grupos e entidades permanecem pareados por setor para não cruzar permissões de setores diferentes.

## Quando o chamado aparece e não permite importar

A lista informa o motivo: categoria operacional ausente ou diferente, vínculo de rede ausente/ambíguo, outro papel de serviço, falta de aprovação ou serviço não previsto. Um chamado de e-mail atribuído a Rede pode ficar visível e continuar fora da operação de rede. Importação continua exigindo vínculo único, aprovação status 3, solicitação de rede e tipo CADASTRO ou RENOVAR USUÁRIO.

Fila, abertura por ID, GET/POST, filtros, visão geral, histórico e pendências revalidam o alcance no servidor. Alteração de papel, grupos, perfil, entidade ou política invalida o resumo observado. Mudança de atribuição é conferida antes de usar o chamado novamente.

## Recuperação e limites

Uma política inválida bloqueia os demais usuários. O Super-Admin recebe a opção **Recuperar política de acesso**: exige confirmação, preserva o documento inválido em backup e reinicia setores e regras vazios. Depois é necessário cadastrar novas concessões. Uma política válida não pode ser apagada por essa recuperação.

Os cadastros editam somente configurações próprias do plugin. Não criam grupos/perfis, não alteram atribuições de chamados, não gravam no AD e não fecham chamados. Os testes locais utilizam dublês; a conferência na instalação GLPI real é necessária após atualizar o plugin.
