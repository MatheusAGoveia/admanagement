<?php

namespace GlpiPlugin\Admanagement;

/** Instala somente estruturas próprias utilizando o ciclo e a migração nativos do GLPI. */
final class InstalacaoDaFerramenta {

    /** Cria tabelas com unicidade no banco, sem inserir ou atualizar tabelas internas diretamente. */
    public static function instalar(): bool {
        global $DB;
        $migracao = new \Migration('0.8.1');
        $tabelas = [
            Lote::getTable() => "
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` bigint unsigned NOT NULL,
                `users_id` bigint unsigned NOT NULL,
                `assinatura` char(64) NOT NULL,
                `total` int unsigned NOT NULL,
                `date_creation` datetime DEFAULT NULL,
                `date_mod` datetime DEFAULT NULL,
                PRIMARY KEY (`id`), UNIQUE KEY `assinatura` (`assinatura`)",
            ExecucaoDoDiretorio::getTable() => "
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` bigint unsigned NOT NULL,
                `users_id` bigint unsigned NOT NULL,
                `lotes_id` bigint unsigned DEFAULT NULL,
                `tickets_id` bigint unsigned DEFAULT NULL,
                `formularios_id` bigint unsigned DEFAULT NULL,
                `necessidade` char(64) NOT NULL,
                `alvo_reservado` char(64) DEFAULT NULL,
                `alvo` char(64) NOT NULL,
                `dominio_id` int unsigned NOT NULL,
                `guid` char(32) NOT NULL,
                `tipo` varchar(20) NOT NULL,
                `estado` varchar(40) NOT NULL,
                `etapa` varchar(40) NOT NULL,
                `posse` char(64) NOT NULL,
                `efeitos` text NOT NULL,
                `date_creation` datetime DEFAULT NULL,
                `date_mod` datetime DEFAULT NULL,
                PRIMARY KEY (`id`), UNIQUE KEY `necessidade` (`necessidade`),
                UNIQUE KEY `alvo_reservado` (`alvo_reservado`),
                KEY `lote` (`lotes_id`), KEY `chamado` (`tickets_id`)",
        ];
        // Migração reexecutada não apaga resultados nem concede permissões automaticamente.
        foreach ($tabelas as $tabela => $colunas) {
            // Nomes são constantes dos modelos, nunca recebidos da requisição.
            if (!$DB->tableExists($tabela)) {
                $migracao->addPostQuery('CREATE TABLE `' . $tabela . '` (' . $colunas . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            }
        }
        $migracao->executeMigration();
        return $DB->tableExists(Lote::getTable()) && $DB->tableExists(ExecucaoDoDiretorio::getTable());
    }
}
