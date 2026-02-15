<?php
/**
 * IncrementalExportTrait — Delta row export to SQLite.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait IncrementalExportTrait {

    private function exportDeltaRows(string $incrementalDir, string $table, string $pkColumn, int $lastMaxId, int $expectedCount): array {
        $filename = $table . '.sqlite';
        $filepath = $incrementalDir . '/' . $filename;

        try {
            $sqlite = $this->createIncrementalSqliteTable($filepath, $table);
            $exported = $this->batchExportDelta($sqlite, $table, $pkColumn, $lastMaxId);
            $sqlite = null;

            return array(
                'success' => true, 'rows' => $exported,
                'file_size' => filesize($filepath), 'checksum' => md5_file($filepath),
            );
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage(), 'rows' => 0, 'file_size' => 0, 'checksum' => '');
        }
    }

    private function createIncrementalSqliteTable(string $filepath, string $table): PDO {
        $sqlite = new PDO('sqlite:' . $filepath);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec('PRAGMA journal_mode = WAL');
        $sqlite->exec('PRAGMA synchronous = OFF');

        $createResult = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        if (!$createResult) {
            throw new Exception('Failed to get CREATE TABLE for ' . $table);
        }

        $sqlite->exec(RiseupSqliteSchemaConverter::convert($createResult[1], $table));
        return $sqlite;
    }

    private function batchExportDelta(PDO $sqlite, string $table, string $pkColumn, int $lastMaxId): int {
        $prepared = $this->prepareDeltaBatchStatement($sqlite, $table);
        $sqlite->beginTransaction();

        $exported = $this->executeDeltaBatchLoop($prepared['stmt'], $table, $pkColumn, $lastMaxId);

        $sqlite->commit();
        return $exported;
    }

    private function prepareDeltaBatchStatement(PDO $sqlite, string $table): array {
        $columns = $this->wpdb->get_results("DESCRIBE `{$table}`", ARRAY_A);
        $columnNames = array_column($columns, 'Field');
        $placeholders = implode(', ', array_fill(0, count($columnNames), '?'));
        $columnList = implode(', ', array_map(function(string $c): string { return "`{$c}`"; }, $columnNames));

        $stmt = $sqlite->prepare("INSERT OR REPLACE INTO `{$table}` ({$columnList}) VALUES ({$placeholders})");
        return array('stmt' => $stmt, 'columns' => $columnNames);
    }

    private function executeDeltaBatchLoop(PDOStatement $stmt, string $table, string $pkColumn, int $lastMaxId): int {
        $offset = 0;
        $exported = 0;

        while (true) {
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE `{$pkColumn}` > %d ORDER BY `{$pkColumn}` ASC LIMIT %d OFFSET %d",
                    $lastMaxId, $this->batchSize, $offset
                ), ARRAY_N
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $stmt->execute($row);
                $exported++;
            }
            $offset += $this->batchSize;
        }

        return $exported;
    }
}
