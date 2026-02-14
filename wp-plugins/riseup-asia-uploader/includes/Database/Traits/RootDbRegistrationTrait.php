<?php
/**
 * RootDb Registration Trait — table registration, stats, incrementals, plugins, metadata reading.
 *
 * @package RiseupAsiaUploader
 * @since   1.12.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\LogLevelType;

trait RootDbRegistrationTrait {

    /**
     * Register a table export in a-root.db.
     */
    public function registerTable($pdo, $table_name, $row_count, $sqlite_file, $file_size = 0, $checksum = '') {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO snapshot_tables
            (table_name, row_count, sqlite_file, file_size_bytes, checksum_md5, exported_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array($table_name, $row_count, $sqlite_file, $file_size, $checksum, gmdate('c')));
    }

    /**
     * Update final stats in snapshot_meta.
     */
    public function updateStats($pdo, $table_count, $total_rows) {
        $stmt = $pdo->prepare("UPDATE snapshot_meta SET table_count = ?, total_rows = ? WHERE id = 1");
        $stmt->execute(array($table_count, $total_rows));
    }

    /**
     * Register an incremental backup in a-root.db.
     */
    public function registerIncremental($pdo, $info) {
        $stmt = $pdo->prepare("INSERT INTO incremental_backups
            (sequence_num, folder_name, created_at, tables_changed, total_new_rows, relative_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $info['sequence_num'], $info['folder_name'], gmdate('c'),
            $info['tables_changed'] ?? 0, $info['total_new_rows'] ?? 0, $info['relative_path'],
        ));
    }

    /**
     * Register a plugin snapshot in a-root.db.
     */
    public function registerPluginSnapshot($pdo, $info) {
        $stmt = $pdo->prepare("INSERT INTO plugin_snapshots
            (plugin_slug, plugin_name, plugin_version, zip_file, file_size_bytes, checksum_md5) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(
            $info['plugin_slug'], $info['plugin_name'] ?? '', $info['plugin_version'] ?? '',
            $info['zip_file'], $info['file_size_bytes'] ?? 0, $info['checksum_md5'] ?? '',
        ));
    }

    /**
     * Read metadata from an existing a-root.db.
     *
     * @param string $filepath Path to a-root.db file.
     * @return array|null Metadata or null if invalid.
     */
    public function readMetadata($filepath) {
        if (!RiseupPathUtils::file_exists($filepath)) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $meta = $pdo->query("SELECT * FROM snapshot_meta WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            $tables = $pdo->query("SELECT * FROM snapshot_tables ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
            $deps = $pdo->query("SELECT * FROM table_dependencies ORDER BY parent_table, child_table")->fetchAll(PDO::FETCH_ASSOC);
            $incrementals = $pdo->query("SELECT * FROM incremental_backups ORDER BY sequence_num")->fetchAll(PDO::FETCH_ASSOC);
            $plugins = $pdo->query("SELECT * FROM plugin_snapshots ORDER BY plugin_slug")->fetchAll(PDO::FETCH_ASSOC);
            $pdo = null;

            return array(
                'meta' => $meta, 'tables' => $tables, 'dependencies' => $deps,
                'incrementals' => $incrementals, 'plugins' => $plugins,
            );
        } catch (Throwable $e) {
            $this->log(LogLevelType::Error->value, 'Failed to read a-root.db', array('path' => $filepath, 'error' => $e->getMessage()));
            return null;
        }
    }
}
