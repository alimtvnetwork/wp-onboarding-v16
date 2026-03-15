<?php
/**
 * ZipSplitter — Splits a ZIP file into ≤ 3 MB chunks with a manifest.
 *
 * Produces numbered chunk files (backup.zip.001, .002, …) and a manifest.json
 * containing SHA-256 checksums for integrity verification during restore.
 *
 * @package RiseupAsia\CloudStorage
 * @since   2.17.0
 */

namespace RiseupAsia\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\DateHelper;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

class ZipSplitter
{
    /** Default maximum chunk size: 3 MB (3,145,728 bytes). */
    private const DEFAULT_CHUNK_SIZE = 3145728;

    /** Chunk file prefix. */
    private const CHUNK_PREFIX = 'backup.zip';

    /** Manifest filename. */
    private const MANIFEST_FILE = 'manifest.json';

    private int $chunkSize;

    /**
     * @param int $chunkSize Maximum bytes per chunk (default 3 MB).
     */
    public function __construct(int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        $this->chunkSize = $chunkSize;
    }

    /**
     * Split a ZIP file into chunks and generate a manifest.
     *
     * @param string                 $zipPath   Absolute path to the source ZIP.
     * @param string                 $outputDir Directory to write chunks + manifest into.
     * @param CloudStorageBackupType $type      Full or Incremental.
     * @param int                    $sequence  Backup sequence number.
     * @param string                 $label     Human-readable label for the manifest.
     * @return array{success: bool, chunks: array, manifestPath: string, totalSize: int}
     */
    public function split(
        string $zipPath,
        string $outputDir,
        CloudStorageBackupType $type,
        int $sequence,
        string $label,
    ): array {
        $isSourceMissing = PathHelper::isFileMissing($zipPath);

        if ($isSourceMissing) {
            return ResultHelper::error('Source ZIP not found: ' . $zipPath);
        }

        $isDirReady = $this->ensureDirectory($outputDir);

        if (!$isDirReady) {
            return ResultHelper::error('Failed to create output directory: ' . $outputDir);
        }

        $totalSize = filesize($zipPath);
        $chunks = $this->splitFileIntoChunks($zipPath, $outputDir, $totalSize);

        $isChunksFailed = empty($chunks);

        if ($isChunksFailed) {
            return ResultHelper::error('Failed to split ZIP into chunks');
        }

        $manifestPath = $this->writeManifest(
            $outputDir,
            $type,
            $sequence,
            $label,
            $totalSize,
            $chunks,
        );

        $isManifestFailed = ($manifestPath === null);

        if ($isManifestFailed) {
            return ResultHelper::error('Failed to write manifest.json');
        }

        return ResultHelper::ok(array(
            'chunks'       => $chunks,
            'manifestPath' => $manifestPath,
            'totalSize'    => $totalSize,
            'chunkCount'   => count($chunks),
        ));
    }

    /**
     * Split the source file into sequential chunks.
     *
     * @param string $zipPath   Source ZIP path.
     * @param string $outputDir Output directory.
     * @param int    $totalSize Total file size.
     * @return array<int, array{file: string, size: int, sha256: string}>
     */
    private function splitFileIntoChunks(string $zipPath, string $outputDir, int $totalSize): array
    {
        $handle = fopen($zipPath, 'rb');
        $isOpenFailed = ($handle === false);

        if ($isOpenFailed) {
            return array();
        }

        $chunks = array();
        $chunkIndex = 0;
        $bytesRemaining = $totalSize;

        while ($bytesRemaining > 0) {
            $chunkIndex++;
            $chunkResult = $this->writeOneChunk($handle, $outputDir, $chunkIndex, $bytesRemaining);

            $isChunkFailed = ($chunkResult === null);

            if ($isChunkFailed) {
                fclose($handle);

                return array();
            }

            $chunks[] = $chunkResult;
            $bytesRemaining -= $chunkResult['size'];
        }

        fclose($handle);

        return $chunks;
    }

    /**
     * Write a single chunk file from the source handle.
     *
     * @param resource $sourceHandle   Open file handle to read from.
     * @param string   $outputDir      Output directory.
     * @param int      $chunkIndex     1-based chunk number.
     * @param int      $bytesRemaining Bytes left to read.
     * @return array{file: string, size: int, sha256: string}|null
     */
    private function writeOneChunk($sourceHandle, string $outputDir, int $chunkIndex, int $bytesRemaining): ?array
    {
        $chunkFileName = self::CHUNK_PREFIX . '.' . str_pad((string) $chunkIndex, 3, '0', STR_PAD_LEFT);
        $chunkPath = $outputDir . DIRECTORY_SEPARATOR . $chunkFileName;
        $readSize = min($this->chunkSize, $bytesRemaining);

        $data = fread($sourceHandle, $readSize);
        $isReadFailed = ($data === false);

        if ($isReadFailed) {
            return null;
        }

        $written = file_put_contents($chunkPath, $data);
        $isWriteFailed = ($written === false);

        if ($isWriteFailed) {
            return null;
        }

        $sha256 = hash('sha256', $data);

        return array(
            'file'   => $chunkFileName,
            'size'   => $written,
            'sha256' => $sha256,
        );
    }

    /**
     * Write the manifest.json file.
     *
     * @param string                 $outputDir Output directory.
     * @param CloudStorageBackupType $type      Backup type.
     * @param int                    $sequence  Sequence number.
     * @param string                 $label     Folder label.
     * @param int                    $totalSize Total uncompressed size.
     * @param array                  $chunks    Chunk metadata array.
     * @return string|null Absolute path to manifest, or null on failure.
     */
    private function writeManifest(
        string $outputDir,
        CloudStorageBackupType $type,
        int $sequence,
        string $label,
        int $totalSize,
        array $chunks,
    ): ?string {
        $manifest = array(
            'type'      => strtolower($type->value),
            'sequence'  => $sequence,
            'label'     => $label,
            'createdAt' => DateHelper::nowUtc(),
            'totalSize' => $totalSize,
            'chunkSize' => $this->chunkSize,
            'chunks'    => $chunks,
        );

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $manifestPath = $outputDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;

        $written = file_put_contents($manifestPath, $json);
        $isWriteFailed = ($written === false);

        if ($isWriteFailed) {
            return null;
        }

        return $manifestPath;
    }

    /**
     * Ensure a directory exists and is writable.
     *
     * @param string $dirPath Directory to create.
     * @return bool True if directory is ready.
     */
    private function ensureDirectory(string $dirPath): bool
    {
        $isDirExists = is_dir($dirPath);

        if ($isDirExists) {
            return is_writable($dirPath);
        }

        $created = mkdir($dirPath, 0755, true);

        return $created;
    }
}
