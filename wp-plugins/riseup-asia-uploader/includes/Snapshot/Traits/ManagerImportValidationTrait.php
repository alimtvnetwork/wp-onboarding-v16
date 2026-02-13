<?php
/**
 * ManagerImportValidationTrait — Manifest and SQLite validation for snapshot imports.
 *
 * @package RiseupAsiaUploader
 * @since   2.0.0
 */

trait ManagerImportValidationTrait {

    /**
     * Validate manifest structure and version.
     *
     * @param array $manifest Manifest data.
     * @return array Validation result.
     */
    private function validateManifest($manifest) {
        $required = array('version', 'snapshot');
        foreach ($required as $field) {
            if (!isset($manifest[$field])) {
                return array('valid' => false, 'error' => "Missing required field: {$field}");
            }
        }

        $snapshot_required = array('filename', 'tables', 'scope');
        foreach ($snapshot_required as $field) {
            if (!isset($manifest['snapshot'][$field])) {
                return array('valid' => false, 'error' => "Missing snapshot field: {$field}");
            }
        }

        $format_version = isset($manifest['format_version']) ? $manifest['format_version'] : '1.0';
        if (version_compare($format_version, '2.0', '>=')) {
            return array('valid' => false, 'error' => 'Unsupported format version: ' . $format_version);
        }

        return array('valid' => true);
    }

    /**
     * Validate SQLite database integrity.
     *
     * @param string $filepath Path to SQLite file.
     * @return array Validation result.
     */
    private function validateSqliteIntegrity($filepath) {
        try {
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $result = $pdo->query("PRAGMA integrity_check");
            $integrity = $result->fetchColumn();

            if ($integrity !== 'ok') {
                return array('valid' => false, 'error' => 'Database integrity check failed: ' . $integrity);
            }

            $meta_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='_snapshot_meta'");
            if (!$meta_check->fetch()) {
                return array('valid' => false, 'error' => 'Missing _snapshot_meta table');
            }

            $pdo = null;
            return array('valid' => true);
        } catch (Exception $e) {
            return array('valid' => false, 'error' => 'SQLite error: ' . $e->getMessage());
        }
    }

    /**
     * Delete a directory recursively.
     *
     * @param string $dir Directory path.
     * @return bool Success.
     */
    private function deleteDirectory($dir) {
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
