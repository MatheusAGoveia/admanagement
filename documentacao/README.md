# Documentação do ADManagement

Estado do código **0.8.1 em 12/11/2026**, para GLPI 12.0.0/12.0.1 e PHP 8.3. Esta pasta substitui a antiga `docs`.

- [Correção para GLPI 12.0.0](CORRECAO_GLPI1200_2026-11-12.md): causa da recusa, pesquisa oficial, validação local nas duas releases e instrução para substituir os pacotes.

- [Mapa mental interativo](MAPA_MENTAL.html): responsabilidades, arquivos, pontos de entrada públicos e dependências.
- [Diagrama UML de classes](UML_CLASSES.html): 31 classes da base de 06/11; a ampliação para 45 classes está descrita no relatório de serviços, pastas e lotes.
- [Estrutura e arquitetura](ESTRUTURA_E_ARQUITETURA.md): recursos nativos do GLPI, entradas HTTP, regras de acesso, revisão, armazenamento e catálogo.
- [Configurar acesso por perfil](CONFIGURACAO_DE_ACESSO.md): direitos nativos, setores, entidades, migração e exemplos.
- [Compatibilidade GLPI 12 e PHP 8.3](HOMOLOGACAO_GLPI12_PHP83.md): referências oficiais, alterações, resultados do laboratório e limites.
- [Plano da adaptação](PLANO_GLPI12_PHP83.md): escopo, preservação da base e verificação da entrega.

Mapa e UML funcionam localmente, sem internet. Os links para código são relativos à pasta do plugin. As suítes isoladas foram complementadas por instalação e requisições HTTP nos laboratórios GLPI 12.0.0 e 12.0.1; a instalação customizada e o diretório real ainda precisam de homologação própria.

- [Entrega de serviços, pastas e lotes](ENTREGA_SERVICOS_PASTAS_E_LOTES_2026-11-12.md): comportamento implementado, modelos nativos, verificações e impedimentos reais.
- [Desenho aprovado](PROPOSTA_SERVICOS_PASTAS_E_LOTES_2026-11-12.md) e [plano de implementação](PLANO_SERVICOS_PASTAS_E_LOTES_2026-11-12.md).

Mapa e UML anteriores retratam a base de 06/11 e não devem ser usados como inventário completo da versão 0.8.0.
