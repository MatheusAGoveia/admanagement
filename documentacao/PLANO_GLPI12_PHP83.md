# GLPI 12 e PHP 8.3 — Plano da adaptação

**Objetivo:** adaptar o ADManagement existente, sem alterar as regras operacionais ou habilitar escrita no AD.

**Fonte:** `C:/Users/matheus.damiao/Desktop/admanagement`, revisão anterior `28c5a2d`, branch de trabalho `codex/admanagement-glpi12-php83`.

**Arquitetura:** conservar as 31 classes em `src/`, as sete entradas PHP em `front/`, o armazenamento privado e as permissões nativas por perfil. A migração muda os contratos com o GLPI, os requisitos e os testes correspondentes; não incorpora código do PlguinOfGLPI.

**Alvos desta entrega:** GLPI 12.0.1 oficial e PHP 8.3.36 real, de 64 bits. GLPI 12.0.1 é a release estável oficial encontrada em 06/11/2026. Não ampliar a declaração a outras releases sem verificação. A base anterior permanece na cópia de segurança para uso com a matriz anterior.

## Restrições

- Manter escrita, comentários, nomes próprios em português e espaçamento do mantenedor.
- Preservar a autoridade do perfil ativo, a supervisão por setores, os limites de entidades e a atribuição pessoal do técnico. Não transplantar as regras de acesso do outro plugin.
- Preservar consultas LDAP somente de leitura, cobertura por domínio, revisão explícita, desafio consumível, auditoria privada, bloqueios de escrita e ausência de simulação no produto.
- Não modificar GLPI, formmanager, servidor ou Active Directory; o laboratório usa dados próprios e não recebe credenciais de diretório.
- Não substituir o PHP global nem suas configurações; o PHP 8.3 terá executável e INI particulares.

## Tarefas

- [x] Proteger a base: cópia completa e restauração dos 212 arquivos conferidas por SHA-256; estado Git limpo antes da edição.
- [x] Registrar a referência anterior: onze suítes passaram em PHP 8.4 antes das alterações.
- [x] Obter PHP 8.3 oficial, conferir SHA-256 e habilitar extensões somente no INI privado.
- [x] Acrescentar testes para GLPI 12.0.1/PHP 8.3, versões recusadas e coerência entre `setup.php`, Composer e verificação própria. A recusa do novo alvo foi observada antes da correção; os 17 cenários finais passaram.
- [x] Atualizar `setup.php` e `composer.json`, mantendo identidade `admanagement` e elevando a versão do plugin para 0.7.1. Informar GLPI >=12.0.1 e <12.0.2; PHP >=8.3 e <9.0, JSON e mbstring. LDAP continua necessário somente para as consultas reais.
- [x] Adequar as sete entradas: retirar `inc/includes.php`, conservar os globais exigidos pelo controlador legado, respeitar autorização, CSRF e redirecionamentos do kernel. Não remover `Html::closeForm()`.
- [x] Atualizar comentários e substitutos de teste que impõem tokens antigos; não introduzir validação manual nem flexibilizar os testes de autorização.
- [x] Conferir `PerfilDaFerramenta` contra `CommonGLPI` e a persistência nativa dos direitos no 12. Assinaturas compatíveis e matriz renderizada; não foi necessária alteração preventiva de herança.
- [x] Corrigir a diferença reproduzida na configuração real: exigir a reautenticação administrativa nativa antes de consultar grupos/perfis sensíveis, após verificar a autorização. Acrescentar cobertura de ordem, GET e POST; não acrescentar aprovação de solicitações.
- [x] Executar as onze suítes e lint com o executável PHP 8.3 real. Subprocessos herdaram o mesmo INI por `PHPRC`; 53 arquivos PHP válidos.
- [x] Instalar GLPI 12.0.1 em laboratório exclusivo com MariaDB 10.11.19, PHP web 8.3.36 e rede em loopback. Plugin instalado/ativado sem conceder direitos automaticamente.
- [x] Exercitar as sete telas, perfis, configuração, fila, histórico/visão geral/pendências/auditoria, importação, revisão e resumo. POSTs, CSRF, revogação e manutenção do bloqueio de escrita conferidos: 20 verificações do fluxo e 30 por perfil na repetição final.
- [x] Comparar instalação nova, atualização nativa de registro controlado 0.7.0 e repetição do instalador: hashes de direitos, configuração e armazenamento privado preservados. Não executar instalador da origem para preparar dados. O ensaio não representa migração completa do core/banco GLPI 11 para 12.
- [x] Registrar evidências e limitações em `documentacao/HOMOLOGACAO_GLPI12_PHP83.md`, atualizar README, índice e diagramas documentais e gerar pacote limpo com hashes conferidos. Pacote e manifesto ficam fora da árvore do plugin, em `Refatoracao No Codigo Dp ADMANGEte/entregas/`.

## Arquivos e interfaces

- `setup.php`: preservar os seis hooks globais; alterar somente a versão, os requisitos e as verificações associadas.
- `composer.json`: alinhar o requisito PHP e extensões com o instalador.
- `front/*.php`: manter ações, campos, destinos e classes delegadas; o kernel inicializa a aplicação.
- `src/EnderecoDaFerramenta.php`: já utiliza a URL nativa; manter `raiz(): string` e atualizar somente a explicação de versão.
- `src/PerfilDaFerramenta.php`: manter a chave `plugin_admanagement`, READ/UPDATE e o uso da matriz oficial; verificar com o core antes de modificar.
- `src/AcessoGlpi.php`: integrar `ReAuthManager` somente à entrada de configuração administrativa; manter os critérios e as consultas de autorização existentes.
- `tests/carregamento.php`, `tests/acesso.php`, `tests/suporte_perfis.php`, `tests/fluxo.php`, `tests/refatoracao.php`: adequar contratos e cobertura do alvo, preservando as regressões de negócio.
- Demais classes: não alterar sem falha reproduzida que exija a correção.

## Aceite e retorno

Cada conjunto de alterações deve passar pelas suítes pertinentes antes da etapa seguinte. Falha nativa precisa ser distinguida de falha do preparador ou do cliente de testes. Tokens, senhas, respostas LDAP completas e dados pessoais reais não entram nos relatórios.

O pacote final usa a raiz `admanagement/setup.php`, sem laboratório, testes, dumps, `.git` ou credenciais. A implantação remota não faz parte desta execução. Preservar a configuração própria e o armazenamento privado ao atualizar uma instalação existente.

Uma falha de compatibilidade permite retornar à revisão e à cópia anteriores dentro da matriz antiga; restaurar apenas o plugin não reverte uma atualização do GLPI inteiro.

## Revisão do plano

Cobertura conferida: requisitos, entrada HTTP, CSRF, direitos, contexto de sessão, consultas, armazenamento, testes no runtime real e distribuição têm verificação explícita. Estrutura, interface e regras não serão redesenhadas. LDAP real, criação/renovação de contas e finalização de chamados ficam para a próxima solicitação específica.
