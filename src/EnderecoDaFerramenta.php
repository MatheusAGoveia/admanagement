<?php

namespace GlpiPlugin\Admanagement;

/**
 * Resolve a URL nativa sem depender da localização física plugins/marketplace.
 */

final class EnderecoDaFerramenta {

    /**
     * Preserva subdiretórios configurados pelo GLPI ao montar as entradas do plugin.
     */

    public static function raiz(): string {

        global $CFG_GLPI;

        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/admanagement';

    }

}

