<?php
/**
 * ZipReassembler — Reassembles split ZIP chunks into the original archive.
 *
 * Reads a manifest.json, downloads/locates chunks, verifies SHA-256 checksums,
 * and concatenates them into a single ZIP file.
 *
 * @package RiseupAsia\CloudStorage
 * @since   2.17.0
 */

namespace RiseupAsia\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\ResponseKeyType;
use RiseupAsia\Helpers\PathHelper;
use RiseupAsia\Helpers\ResultHelper;

class ZipReassembler
{
    /** Manifest filename. */
    private const MANIFEST_FILE = 'manifest.json';

    /**
     * Reassemble chunks from a directory into a single ZIP file.
     *
     * @param string $chunksDir  Directory containing manifest.json and chunk files.
     * @param string $outputPath Absolute path for the reassembled ZIP.
     * @return array{success: bool, outputPath: string, totalSize: int}
     */
    public function reassemble(string $chunksDir, string $outputPath): array
    {
        $manifest = $this->loadManifest($chunksDir);
        $isManifestMissing = ($manifest === null);

        if ($isManifestMissing) {
            return ResultHelper::error('manifest.json not found or invalid in: ' . $chunksDir);
        }

        $hasChunks = !empty($manifest['chunks']);

        if (!$hasChunks) {
            return ResultHelper::error('Manifest contains no chunks');
        }

        $verifyResult = $this->verifyChecksums($chunksDir, $manifest['chunks']);
        $isVerifyFailed = !$verifyResult[ResponseKeyType::Success->value];

        if ($isVerifyFailed) {
            return $verifyResult;
        }

        $joinResult = $this->joinChunks($chunksDir, $manifest['chunks'], $outputPath);

        return $joinResult;
    }

    /**
     * Load and parse the manifest.json file.
     *
     * @param string $chunksDir Directory containing the manifest.
     * @return array|null Parsed manifest or null on failure.
     */
    private function loadManifest(string $chunksDir): ?array
    {
        $manifestPath = $chunksDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        $isFileMissing = PathHelper::isFileMissing($manifestPath);

        if ($isFileMissing) {
            return null;
        }

        $contents = file_get_contents($manifestPath);
        $isReadFailed = ($contents === false);

        if ($isReadFailed) {
            return null;
        }

        $decoded = json_decode($contents, true);
        $isDecodeFailed = ($decoded === null);

        if ($isDecodeFailed) {
            return null;
        }

        return $decoded;
    }

    /**
     * Verify SHA-256 checksums for all chunks.
     *
     * @param string $chunksDir Directory containing chunk files.
     * @param array  $chunks    Chunk metadata from manifest.
     * @return array{success: bool, error?: string}
     */
    private function verifyChecksums(string $chunksDir, array $chunks): array
    {
        foreach ($chunks as $chunk) {
            $chunkPath = $chunksDir . DIRECTORY_SEPARATOR . $chunk['file'];
            $isChunkMissing = PathHelper::isFileMissing($chunkPath);

            if ($isChunkMissing) {
                return ResultHelper::error('Chunk file missing: ' . $chunk['file']);
            }

            $contents = file_get_contents($chunkPath);
            $isReadFailed = ($contents === false);

            if ($isReadFailed) {
                return ResultHelper::error('Failed to read chunk: ' . $chunk['file']);
            }

            $actualHash = hash('sha256', $contents);
            $isHashMismatch = ($actualHash !== $chunk['sha256']);

            if ($isHashMismatch) {
                return ResultHelper::error(
                    'Checksum mismatch for ' . $chunk['file']
                    . ': expected ' . $chunk['sha256']
                    . ', got ' . $actualHash
                );
            }
        }

        return ResultHelper::ok();
    }

    /**
     * Concatenate chunk files into a single output ZIP.
     *
     * @param string $chunksDir  Directory containing chunk files.
     * @param array  $chunks     Chunk metadata from manifest (ordered).
     * @param string $outputPath Target path for the reassembled ZIP.
     * @return array{success: bool, outputPath: string, totalSize: int}
     */
    private function joinChunks(string $chunksDir, array $chunks, string $outputPath): array
    {
        $outputHandle = fopen($outputPath, 'wb');
        $isOpenFailed = ($outputHandle === false);

        if ($isOpenFailed) {
            return ResultHelper::error('Failed to open output file: ' . $outputPath);
        }

        $totalWritten = 0;

        foreach ($chunks as $chunk) {
            $chunkPath = $chunksDir . DIRECTORY_SEPARATOR . $chunk['file'];
            $chunkHandle = fopen($chunkPath, 'rb');
            $isChunkOpenFailed = ($chunkHandle === false);

            if ($isChunkOpenFailed) {
                fclose($outputHandle);

                return ResultHelper::error('Failed to open chunk: ' . $chunk['file']);
            }

            $copied = stream_copy_to_stream($chunkHandle, $outputHandle);
            fclose($chunkHandle);

            $isCopyFailed = ($copied === false);

            if ($isCopyFailed) {
                fclose($outputHandle);

                return ResultHelper::error('Failed to copy chunk: ' . $chunk['file']);
            }

            $totalWritten += $copied;
        }

        fclose($outputHandle);

        return ResultHelper::ok([
            'outputPath' => $outputPath,
            'totalSize'  => $totalWritten,
            'chunkCount' => count($chunks),
        ]);
    }
}
