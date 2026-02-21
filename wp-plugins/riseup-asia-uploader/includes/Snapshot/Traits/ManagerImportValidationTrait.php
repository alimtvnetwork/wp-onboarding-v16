<?php
/**
 * ManagerImportValidationTrait — Manifest and SQLite validation for snapshot imports.
 *
 * @package RiseupAsia\Snapshot\Traits
 * @since   2.0.0
 */

namespace RiseupAsia\Snapshot\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Throwable;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\BooleanHelpers;
use RiseupAsia\Helpers\PathHelper;

trait ManagerImportValidationTrait {
    private function validateManifest(array $manifest): array {
        $required = array('version', 'snapshot');

        foreach ($required as $field) {
            $isFieldMissing = BooleanHelpers::isKeyMissing($manifest, $field);

            if ($isFieldMissing) {

                return array(
                    ResponseKeyType::Valid->value => false,
                    ResponseKeyType::Error->value => "Missing required field: {$field}",
                );
            }
        }

        $snapshotRequired = array('filename', 'tables', 'scope');

        foreach ($snapshotRequired as $field) {
            $isSnapshotFieldMissing = BooleanHelpers::isKeyMissing($manifest['snapshot'], $field);

            if ($isSnapshotFieldMissing) {

                return array(
                    ResponseKeyType::Valid->value => false,
                    ResponseKeyType::Error->value => "Missing snapshot field: {$field}",
                );
            }
        }

        $formatVersion = $manifest['format_version'] ?? '1.0';

        if (version_compare($formatVersion, '2.0', '>=')) {

            return array(
                ResponseKeyType::Valid->value => false,
                ResponseKeyType::Error->value => 'Unsupported format version: ' . $formatVersion,
            );
        }

        return array(ResponseKeyType::Valid->value => true);
    }

    private function validateSqliteIntegrity(string $filepath): array {
        try {
            $pdo = new PDO('sqlite:' . $filepath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $result = $pdo->query("PRAGMA integrity_check");
            $integrity = $result->fetchColumn();

            if ($integrity !== 'ok') {

                return array(
                    ResponseKeyType::Valid->value => false,
                    ResponseKeyType::Error->value => 'Database integrity check failed: ' . $integrity,
                );
            }

            $metaCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='_snapshot_meta'");
            $isMetaTableAbsent = ($metaCheck->fetch() === false);

            if ($isMetaTableAbsent) {

                return array(
                    ResponseKeyType::Valid->value => false,
                    ResponseKeyType::Error->value => 'Missing _snapshot_meta table',
                );
            }

            $pdo = null;

            return array(ResponseKeyType::Valid->value => true);
        } catch (Throwable $e) {

            return array(
                ResponseKeyType::Valid->value => false,
                ResponseKeyType::Error->value => 'SQLite error: ' . $e->getMessage(),
            );
        }
    }

    private function deleteDirectory(string $dir): bool {
        if (PathHelper::isDirMissing($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = PathHelper::join($dir, $file);

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                PathHelper::deleteFile($path);
            }
        }

        return PathHelper::deleteDir($dir);
    }
}
