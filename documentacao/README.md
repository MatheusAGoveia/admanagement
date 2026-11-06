# Documentação do ADManagement

Estado do código **0.7.1 em 06/11/2026**, para GLPI 12.0.1 e PHP 8.3. Esta pasta substitui a antiga `docs`.

- [Mapa mental interativo](MAPA_MENTAL.html): responsabilidades, arquivos, pontos de entrada públicos e dependências.
- [Diagrama UML de classes](UML_CLASSES.html): 31 classes próprias, atributos, visibilidade e assinaturas extraídas do código atual.
- [Estrutura e arquitetura](ESTRUTURA_E_ARQUITETURA.md): recursos nativos do GLPI, entradas HTTP, regras de acesso, revisão, armazenamento e catálogo.
- [Configurar acesso por perfil](CONFIGURACAO_DE_ACESSO.md): direitos nativos, setores, entidades, migração e exemplos.
- [Compatibilidade GLPI 12 e PHP 8.3](HOMOLOGACAO_GLPI12_PHP83.md): referências oficiais, alterações, resultados do laboratório e limites.
- [Plano da adaptação](PLANO_GLPI12_PHP83.md): escopo, preservação da base e verificação da entrega.

Mapa e UML funcionam localmente, sem internet. Os links para código são relativos à pasta do plugin. As suítes isoladas foram complementadas por instalação e requisições HTTP no GLPI 12.0.1 real do laboratório; a instalação customizada e o diretório real ainda precisam de homologação própria.
