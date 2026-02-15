<?php
/**
 * ManagerImportValidationTrait — Manifest and SQLite validation for snapshot imports.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait ManagerImportValidationTrait {

    private function validateManifest(array $manifest): array {
        $required = array('version', 'snapshot');
        foreach ($required as $field) {
            if (!isset($manifest[$field])) {
                return array('valid' => false, 'error' => "Missing required field: {$field}");
            }
        }

        $snapshotRequired = array('filename', 'tables', 'scope');
        foreach ($snapshotRequired as $field) {
            if (!isset($manifest['snapshot'][$field])) {
                return array('valid' => false, 'error' => "Missing snapshot field: {$field}");
            }
        }

        $formatVersion = $manifest['format_version'] ?? '1.0';
        if (version_compare($formatVersion, '2.0', '>=')) {
            return array('valid' => false, 'error' => 'Unsupported format version: ' . $formatVersion);
        }

        return array('valid' => true);
    }

    private function validateSqliteIntegrity(string $filepath): array {
        try {
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $result = $pdo->query("PRAGMA integrity_check");
            $integrity = $result->fetchColumn();

            if ($integrity !== 'ok') {
                return array('valid' => false, 'error' => 'Database integrity check failed: ' . $integrity);
            }

            $metaCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='_snapshot_meta'");
            if (!$metaCheck->fetch()) {
                return array('valid' => false, 'error' => 'Missing _snapshot_meta table');
            }

            $pdo = null;
            return array('valid' => true);
        } catch (Exception $e) {
            return array('valid' => false, 'error' => 'SQLite error: ' . $e->getMessage());
        }
    }

    private function deleteDirectory(string $dir): bool {
        if (!RiseupPathUtils::dirExists($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = RiseupPathUtils::join($dir, $file);
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                RiseupPathUtils::deleteFile($path);
            }
        }

        return RiseupPathUtils::deleteDir($dir);
    }
}
