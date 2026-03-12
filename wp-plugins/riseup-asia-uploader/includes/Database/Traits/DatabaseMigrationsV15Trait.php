<?php
/**
 * DatabaseMigrationsV15Trait — Abbreviation casing fix for UploadSource values.
 *
 * Normalizes UploadSource enum values from all-caps abbreviations
 * to PascalCase abbreviations per spec (RestAPI → RestApi, AdminUI → AdminUi, WPCLI → WpCli).
 *
 * @package RiseupAsia\Database\Traits
 * @since   2.6.0
 */

namespace RiseupAsia\Database\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use Throwable;
use RiseupAsia\Enums\TableType;

trait DatabaseMigrationsV15Trait {

    /** Fix abbreviation casing: RestAPI → RestApi, AdminUI → AdminUi, WPCLI → WpCli */
    private const V15_FIX_UPLOAD_SOURCE_QUERY = <<<'SQL'
        UPDATE %s SET UploadSource = CASE UploadSource
            WHEN 'RestAPI' THEN 'RestApi'
            WHEN 'AdminUI' THEN 'AdminUi'
            WHEN 'WPCLI'   THEN 'WpCli'
        END
        WHERE UploadSource IN ('RestAPI', 'AdminUI', 'WPCLI')
    SQL;

    private function migrateV15UploadSourceAbbreviationFix(int $current): void {
        if ($current >= 15) {
            return;
        }

        $this->fileLogger->info('Applying migration v15: Fix UploadSource abbreviation casing (RestAPI→RestApi, AdminUI→AdminUi, WPCLI→WpCli)');

        $txn = TableType::Transactions->value;

        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec(sprintf(self::V15_FIX_UPLOAD_SOURCE_QUERY, $txn));
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->fileLogger->logCriticalException($e, 'Migration v15 failed — rolled back');
        }

        $this->recordMigration(15);
    }
}
