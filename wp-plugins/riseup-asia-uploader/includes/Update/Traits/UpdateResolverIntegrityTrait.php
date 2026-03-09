<?php
/**
 * UpdateResolverIntegrityTrait — SHA-256 checksum verification for downloaded packages.
 *
 * @package RiseupAsia\Update\Traits
 * @since   2.2.0
 */

namespace RiseupAsia\Update\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;

use RiseupAsia\Enums\WpErrorCodeType;

trait UpdateResolverIntegrityTrait {

    /**
     * Verify a downloaded file against an expected SHA-256 hash.
     *
     * @param string $filePath      Absolute path to the downloaded file.
     * @param string $expectedHash  Hex-encoded SHA-256 hash from the update server.
     *
     * @return true|WP_Error True on match, WP_Error on mismatch or missing file.
     */
    public function verifyChecksum(
        string $filePath,
        string $expectedHash,
    ): true|WP_Error {
        $isFileMissing = !file_exists($filePath);

        if ($isFileMissing) {
            $this->fileLogger->error('Checksum verification failed — file not found', array('file' => $filePath));

            return new WP_Error(
                WpErrorCodeType::FileNotFound->value,
                'Downloaded package file not found',
            );
        }

        $actualHash = hash_file('sha256', $filePath);
        $isHashMismatch = !hash_equals(strtolower($expectedHash), strtolower($actualHash));

        if ($isHashMismatch) {
            $this->fileLogger->error('Checksum mismatch', array(
                'expected' => $expectedHash,
                'actual' => $actualHash,
                'file' => $filePath,
            ));

            return new WP_Error(
                WpErrorCodeType::ChecksumMismatch->value,
                'SHA-256 checksum verification failed — package may be corrupted or tampered with',
                array('expected' => $expectedHash, 'actual' => $actualHash),
            );
        }

        $this->fileLogger->info('Checksum verified', array('hash' => $actualHash));

        return true;
    }

    /**
     * Download a package and verify its integrity before returning the local path.
     *
     * @param string      $packageUrl   URL to the ZIP package.
     * @param string|null $expectedHash Expected SHA-256 hash (null skips verification).
     *
     * @return string|WP_Error Local file path on success, WP_Error on failure.
     */
    public function downloadAndVerify(
        string $packageUrl,
        ?string $expectedHash = null,
    ): string|WP_Error {
        $this->fileLogger->info('Downloading update package', array('url' => $packageUrl));

        $tmpFile = download_url($packageUrl, 300);
        if (is_wp_error($tmpFile)) {
            $this->fileLogger->error('Package download failed', array('error' => $tmpFile->get_error_message()));

            return $tmpFile;
        }

        $isHashProvided = ($expectedHash !== null && $expectedHash !== '');

        if ($isHashProvided) {
            $verification = $this->verifyChecksum($tmpFile, $expectedHash);
            if (is_wp_error($verification)) {
                @unlink($tmpFile);

                return $verification;
            }
        } else {
            $this->fileLogger->warn('No SHA-256 hash provided — skipping integrity verification');
        }

        return $tmpFile;
    }
}
