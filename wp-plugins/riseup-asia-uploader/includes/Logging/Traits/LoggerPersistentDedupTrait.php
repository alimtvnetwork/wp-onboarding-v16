<?php
/**
 * Logger Persistent Dedup Trait — JSON-backed cross-request deduplication for Info logs.
 *
 * Stores MD5 hashes of previously logged Info messages in a JSON registry file.
 * Resets automatically when the plugin version changes (i.e., on deployment).
 *
 * @package RiseupAsia\Logging\Traits
 * @since   2.32.0
 */

namespace RiseupAsia\Logging\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\PluginConfigType;

trait LoggerPersistentDedupTrait {
    private const DEDUP_REGISTRY_FILENAME = 'dedup-registry.json';
    private const DEDUP_MAX_ENTRIES = 500;

    /** @var array<string, bool> */
    private array $persistentDedupHashes = array();
    private bool $persistentDedupLoaded = false;

    /**
     * Check if an Info-level log message was already logged in a previous request.
     *
     * Only call this for Info-level messages.
     */
    private function isPersistentDuplicate(string $message, string $file, int $line): bool {
        $this->loadPersistentDedupRegistry();

        $hash = md5($message . '|' . basename($file) . '|' . $line);
        $isAlreadyLogged = isset($this->persistentDedupHashes[$hash]);

        if ($isAlreadyLogged) {
            return true;
        }

        $this->persistentDedupHashes[$hash] = true;
        $this->savePersistentDedupRegistry();

        return false;
    }

    /** Lazy-load the persistent dedup registry from disk. */
    private function loadPersistentDedupRegistry(): void {
        if ($this->persistentDedupLoaded) {
            return;
        }

        $this->persistentDedupLoaded = true;
        $registryPath = $this->getPersistentDedupPath();
        $isPathMissing = ($registryPath === null);

        if ($isPathMissing) {
            return;
        }

        $isFileExists = file_exists($registryPath);

        if ($isFileExists === false) {
            return;
        }

        $contents = @file_get_contents($registryPath);
        $isReadFailed = ($contents === false);

        if ($isReadFailed) {
            return;
        }

        $data = json_decode($contents, true);
        $isDecodeFailed = (!is_array($data));

        if ($isDecodeFailed) {
            return;
        }

        $currentVersion = PluginConfigType::Version->value;
        $storedVersion = $data['version'] ?? '';
        $isVersionMismatch = ($storedVersion !== $currentVersion);

        if ($isVersionMismatch) {
            $this->persistentDedupHashes = array();
            @unlink($registryPath);

            return;
        }

        $hasHashes = isset($data['hashes']) && is_array($data['hashes']);
        $this->persistentDedupHashes = $hasHashes ? $data['hashes'] : array();
    }

    /** Save the persistent dedup registry to disk with LOCK_EX. */
    private function savePersistentDedupRegistry(): void {
        $registryPath = $this->getPersistentDedupPath();
        $isPathMissing = ($registryPath === null);

        if ($isPathMissing) {
            return;
        }

        $this->pruneRegistryIfNeeded();

        $data = array(
            'version' => PluginConfigType::Version->value,
            'hashes'  => $this->persistentDedupHashes,
        );

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @file_put_contents($registryPath, $json, LOCK_EX);
    }

    /** Prune the registry if it exceeds the max entries cap. */
    private function pruneRegistryIfNeeded(): void {
        $entryCount = count($this->persistentDedupHashes);
        $isWithinLimit = ($entryCount <= self::DEDUP_MAX_ENTRIES);

        if ($isWithinLimit) {
            return;
        }

        $keepCount = (int) (self::DEDUP_MAX_ENTRIES / 2);
        $this->persistentDedupHashes = array_slice($this->persistentDedupHashes, -$keepCount, null, true);
    }

    /** Delete the persistent dedup registry file. */
    public function clearPersistentDedupRegistry(): void {
        $this->persistentDedupHashes = array();
        $this->persistentDedupLoaded = false;

        $registryPath = $this->getPersistentDedupPath();
        $isPathMissing = ($registryPath === null);

        if ($isPathMissing) {
            return;
        }

        $isFileExists = file_exists($registryPath);

        if ($isFileExists) {
            @unlink($registryPath);
        }
    }

    /** Resolve the full path to the dedup registry JSON file. */
    private function getPersistentDedupPath(): ?string {
        $isUninitialized = ($this->isInitialized === false);
        $isInitFailed = $isUninitialized && ($this->initializePaths() === false);

        if ($isInitFailed) {
            return null;
        }

        return $this->logsDir . '/' . self::DEDUP_REGISTRY_FILENAME;
    }
}
