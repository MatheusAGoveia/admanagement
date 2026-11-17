# Correção de compatibilidade com a instalação GLPI 12.0.0

**Objetivo:** retirar o impedimento indevido para a versão efetivamente informada pelo usuário, preservando os controles de versão e as regras do produto.

**Arquitetura:** manter o código e as heranças existentes. Comparar as APIs oficiais de GLPI 12.0.0 e 12.0.1 e validar o carregamento/instalação no núcleo 12.0.0 antes de ampliar a faixa declarada.

**Tecnologias:** PHP 8.3, GLPI 12.0.0/12.0.1, MariaDB local e pacotes PHP existentes.

**Especificação:** o servidor informado possui o marcador `version/12.0.0`. ADManagement 0.8.0 e Form Manager 1.0.0-lab12 tinham mínimo 12.0.1. A correção é continuidade do diagnóstico e adaptação já autorizados; não modifica o servidor remoto.

## Restrições

- Aceitar somente 12.0.0 e 12.0.1; manter a recusa de releases posteriores e de desenvolvimento.
- Não trocar o núcleo/banco do laboratório 12.0.1 ou o código original do formmanager.
- Nenhuma operação real em AD, aprovação ou finalização de chamado será executada.
- Preservar as alterações existentes e atualizar as cópias/ZIPs de Downloads utilizados pelo usuário.

## Foco da revisão

1. Metadados e pré-requisito PHP não podem divergir sobre a versão GLPI aceita.
2. Mudanças em ProfileRight/ReAuth/CommonDBTM exigem verificar os caminhos usados.
3. A lista de arquivos do comparador GitHub é limitada; ausência na lista não prova igualdade.
4. Atualização não pode conceder direitos nativos automaticamente ou apagar tabelas existentes.
5. Compatibilidade local não é homologação completa dos fluxos do formmanager ou do AD real.

## Etapas

- [x] Comparar código oficial nas tags resolvidas para commits e registrar limites.
- [x] Acrescentar o teste de aceitação 12.0.0 e manter recusa de 12.0.2, GLPI 11/13 e sufixos de desenvolvimento.
- [x] Ajustar `setup.php` do ADManagement e da cópia adaptada do formmanager; incrementar versões dos plugins.
- [x] Executar as 14 suítes e validar os dois plugins em laboratório isolado com núcleo oficial 12.0.0.
- [x] Conferir ausência de concessões automáticas, assinatura dos arquivos e preservação da referência original.
- [x] Atualizar pastas e ZIPs em Downloads, com relatório e instrução de transferência.

Concluído em 12/11/2026. Pastas extraídas e ZIPs comparados por SHA-256, com raiz técnica admanagement/formmanager e sem inclusão dos arquivos privados do laboratório. Resultados e limites em [CORRECAO_GLPI1200_2026-11-12.md](CORRECAO_GLPI1200_2026-11-12.md).

Autorrevisão: as etapas cobrem a causa observada e os cinco riscos acima. Não haverá mudança de regras de negócio ou liberação genérica de versões GLPI.
