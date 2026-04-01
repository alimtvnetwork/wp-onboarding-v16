<?php
/**
 * LogDedupRegistryTrait — Remote view and clear of the persistent dedup registry.
 *
 * @package QUpload\Traits\Log
 * @since   2.32.0
 */

namespace QUpload\Traits\Log;

if (!defined('ABSPATH')) {
    exit;
}

use WP_REST_Request;
use WP_REST_Response;

use QUpload\Enums\HttpMethodType;
use QUpload\Enums\HttpStatusType;
use QUpload\Enums\ResponseKeyType;
use QUpload\Enums\PhpNativeType;

trait LogDedupRegistryTrait
{
    /**
     * Handle GET|DELETE /logs/dedup-registry — view or clear the persistent dedup registry.
     */
    public function handleLogsDedupRegistry(WP_REST_Request $request): WP_REST_Response {
        $method = $request->get_method();
        $isDelete = ($method === HttpMethodType::Delete->value);

        if ($isDelete) {
            return $this->handleLogsDedupRegistryClear();
        }

        return $this->handleLogsDedupRegistryGet();
    }

    /** Return the dedup registry contents and metadata. */
    private function handleLogsDedupRegistryGet(): WP_REST_Response {
        $this->fileLogger->info('Dedup registry status endpoint called');

        $logsDir = $this->fileLogger->getLogsDir();
        $registryPath = $logsDir . '/dedup-registry.json';
        $isFileExists = file_exists($registryPath);

        if ($isFileExists === false) {
            return new WP_REST_Response(
                [
                    ResponseKeyType::Success->value => true,
                    'DedupRegistry' => [
                        ResponseKeyType::Exists->value => false,
                        ResponseKeyType::Version->value => null,
                        'EntryCount'    => 0,
                        'FileSizeBytes' => 0,
                        'Entries'       => [],
                        'InfoCount'     => 0,
                        'DebugCount'    => 0,
                        'InfoEntries'   => [],
                        'DebugEntries'  => [],
                    ],
                ],
                HttpStatusType::Ok->value,
            );
        }

        $contents = @file_get_contents($registryPath);
        $data = ($contents !== false) ? json_decode($contents, true) : null;
        $isValidData = gettype($data) === PhpNativeType::PhpArray->value;

        $version = $isValidData ? ($data['version'] ?? null) : null;
        $hashes = ($isValidData && isset($data['hashes']) && gettype($data['hashes']) === PhpNativeType::PhpArray->value) ? $data['hashes'] : [];
        $entryCount = count($hashes);
        $fileSize = @filesize($registryPath);

        $infoCount = 0;
        $debugCount = 0;
        $infoEntries = [];
        $debugEntries = [];

        foreach ($hashes as $hash => $level) {
            if ($level === 'info') {
                $infoCount++;
                $infoEntries[] = $hash;
            } elseif ($level === 'debug') {
                $debugCount++;
                $debugEntries[] = $hash;
            }
        }

        return new WP_REST_Response(
            [
                ResponseKeyType::Success->value => true,
                'DedupRegistry' => [
                    ResponseKeyType::Exists->value => true,
                    ResponseKeyType::Version->value => $version,
                    'EntryCount'    => $entryCount,
                    'FileSizeBytes' => ($fileSize !== false) ? $fileSize : 0,
                    'Entries'       => array_keys($hashes),
                    'InfoCount'     => $infoCount,
                    'DebugCount'    => $debugCount,
                    'InfoEntries'   => $infoEntries,
                    'DebugEntries'  => $debugEntries,
                ],
            ],
            HttpStatusType::Ok->value,
        );
    }

    /** Clear the dedup registry and return the previous entry count. */
    private function handleLogsDedupRegistryClear(): WP_REST_Response {
        $this->fileLogger->info('Dedup registry clear endpoint called');

        $logsDir = $this->fileLogger->getLogsDir();
        $registryPath = $logsDir . '/dedup-registry.json';
        $previousCount = 0;

        $isFileExists = file_exists($registryPath);

        if ($isFileExists) {
            $contents = @file_get_contents($registryPath);
            $data = ($contents !== false) ? json_decode($contents, true) : null;
            $hasHashes = gettype($data) === PhpNativeType::PhpArray->value && isset($data['hashes']) && gettype($data['hashes']) === PhpNativeType::PhpArray->value;

            if ($hasHashes) {
                $previousCount = count($data['hashes']);
            }
        }

        $this->fileLogger->clearPersistentDedupRegistry();

        return new WP_REST_Response(
            [
                ResponseKeyType::Success->value => true,
                ResponseKeyType::Message->value => 'Dedup registry cleared',
                'PreviousEntryCount'            => $previousCount,
            ],
            HttpStatusType::Ok->value,
        );
    }
}
