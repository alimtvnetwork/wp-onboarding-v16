<?php
/**
 * BackupFolderResolver — Generates folder names and resolves parent/child
 * relationships for the Git-based backup hierarchy.
 *
 * Folder structure (all hyphens, no underscores):
 *   full-backup/{seq}-W{week}-{DD-MMM-YYYY}[-{label}]/
 *   incremental-backup/{parent-folder}/{inc-seq}/
 *
 * @package RiseupAsia\CloudStorage
 * @since   2.17.0
 */

namespace RiseupAsia\CloudStorage;

if (!defined('ABSPATH')) {
    exit;
}

use RiseupAsia\Enums\CloudStorageBackupType;
use RiseupAsia\Helpers\DateHelper;

class BackupFolderResolver
{
    /** Root folder for full backups. */
    public const FULL_ROOT = 'full-backup';

    /** Root folder for incremental backups. */
    public const INCREMENTAL_ROOT = 'incremental-backup';

    /** Date format for folder names: DD-MMM-YYYY (e.g., 15-Mar-2026). */
    private const FOLDER_DATE_FORMAT = 'd-M-Y';

    /**
     * Build the full-backup folder name.
     *
     * Format: {seq}-W{week}-{DD-MMM-YYYY}[-{label}]
     *
     * @param int         $sequence  Backup sequence number.
     * @param int|null    $timestamp Unix timestamp (null = now).
     * @param string|null $label     Optional user-provided label (manual backups).
     * @return string Folder name (e.g., "001-W11-15-Mar-2026" or "001-W11-15-Mar-2026-pre-deployment").
     */
    public function buildFullFolderName(int $sequence, ?int $timestamp = null, ?string $label = null): string
    {
        $seq = $this->padSequence($sequence);
        $date = $this->formatDate($timestamp);
        $week = $this->formatWeekNumber($timestamp);
        $base = $seq . '-W' . $week . '-' . $date;

        $hasLabel = !empty($label);

        if ($hasLabel) {
            $sanitized = $this->sanitizeLabel($label);

            return $base . '-' . $sanitized;
        }

        return $base;
    }

    /**
     * Build the full path for a full-backup folder.
     *
     * @param int         $sequence  Backup sequence number.
     * @param int|null    $timestamp Unix timestamp (null = now).
     * @param string|null $label     Optional label.
     * @return string Path like "full-backup/001-W11-15-Mar-2026".
     */
    public function buildFullPath(int $sequence, ?int $timestamp = null, ?string $label = null): string
    {
        $folderName = $this->buildFullFolderName($sequence, $timestamp, $label);

        return self::FULL_ROOT . '/' . $folderName;
    }

    /**
     * Build the incremental sub-folder path.
     *
     * @param string $parentFolderName The parent full-backup folder name.
     * @param int    $incrementalSeq   Incremental sequence within this parent.
     * @return string Path like "incremental-backup/001-W11-15-Mar-2026/003".
     */
    public function buildIncrementalPath(string $parentFolderName, int $incrementalSeq): string
    {
        $seq = $this->padSequence($incrementalSeq);

        return self::INCREMENTAL_ROOT . '/' . $parentFolderName . '/' . $seq;
    }

    /**
     * Parse a full-backup folder name into its components.
     *
     * @param string $folderName e.g., "001-W11-15-Mar-2026-pre-deployment"
     * @return array{sequence: int, week: int, date: string, label: string|null}|null
     */
    public function parseFullFolderName(string $folderName): ?array
    {
        $pattern = '/^(\d{3})-W(\d{1,2})-(\d{2}-[A-Za-z]{3}-\d{4})(?:-(.+))?$/';
        $isMatch = preg_match($pattern, $folderName, $matches);

        if (!$isMatch) {
            return null;
        }

        $hasLabel = !empty($matches[4]);

        return array(
            'sequence' => (int) $matches[1],
            'week'     => (int) $matches[2],
            'date'     => $matches[3],
            'label'    => $hasLabel ? $matches[4] : null,
        );
    }

    /**
     * Parse an incremental path to extract parent and incremental sequence.
     *
     * @param string $path e.g., "incremental-backup/001-W11-15-Mar-2026/003"
     * @return array{parentFolder: string, incrementalSequence: int}|null
     */
    public function parseIncrementalPath(string $path): ?array
    {
        $prefix = self::INCREMENTAL_ROOT . '/';
        $isValidPrefix = (strpos($path, $prefix) === 0);

        if (!$isValidPrefix) {
            return null;
        }

        $relativePath = substr($path, strlen($prefix));
        $parts = explode('/', $relativePath);
        $hasEnoughParts = (count($parts) >= 2);

        if (!$hasEnoughParts) {
            return null;
        }

        $parentFolder = $parts[0];
        $incSeq = $parts[1];
        $isNumericSeq = is_numeric($incSeq);

        if (!$isNumericSeq) {
            return null;
        }

        return array(
            'parentFolder'        => $parentFolder,
            'incrementalSequence' => (int) $incSeq,
        );
    }

    /**
     * Determine the next full-backup sequence from existing folder names.
     *
     * @param array $existingFolderNames Array of existing full-backup folder names.
     * @return int Next sequence number.
     */
    public function resolveNextFullSequence(array $existingFolderNames): int
    {
        $maxSequence = 0;

        foreach ($existingFolderNames as $folderName) {
            $parsed = $this->parseFullFolderName($folderName);
            $isParsed = ($parsed !== null);

            if ($isParsed) {
                $isHigher = ($parsed['sequence'] > $maxSequence);

                if ($isHigher) {
                    $maxSequence = $parsed['sequence'];
                }
            }
        }

        return $maxSequence + 1;
    }

    /**
     * Determine the next incremental sequence within a parent folder.
     *
     * @param array $existingSubFolders Array of existing sub-folder names (e.g., ["001", "002"]).
     * @return int Next incremental sequence number.
     */
    public function resolveNextIncrementalSequence(array $existingSubFolders): int
    {
        $maxSequence = 0;

        foreach ($existingSubFolders as $subFolder) {
            $isNumeric = is_numeric($subFolder);

            if ($isNumeric) {
                $seq = (int) $subFolder;
                $isHigher = ($seq > $maxSequence);

                if ($isHigher) {
                    $maxSequence = $seq;
                }
            }
        }

        return $maxSequence + 1;
    }

    /**
     * Get the corresponding incremental root for a full-backup folder.
     *
     * @param string $fullFolderName Full-backup folder name.
     * @return string Path like "incremental-backup/001-W11-15-Mar-2026".
     */
    public function getIncrementalRootForFull(string $fullFolderName): string
    {
        return self::INCREMENTAL_ROOT . '/' . $fullFolderName;
    }

    /**
     * Build a Git commit message for a backup operation.
     *
     * @param CloudStorageBackupType $type            Backup type.
     * @param int                    $sequence        Full backup sequence.
     * @param int|null               $incrementalSeq  Incremental sequence (null for full).
     * @param int|null               $timestamp       Unix timestamp (null = now).
     * @param string|null            $label           Optional label.
     * @return string Commit message.
     */
    public function buildCommitMessage(
        CloudStorageBackupType $type,
        int $sequence,
        ?int $incrementalSeq = null,
        ?int $timestamp = null,
        ?string $label = null,
    ): string {
        $seq = $this->padSequence($sequence);
        $date = $this->formatDate($timestamp);
        $week = $this->formatWeekNumber($timestamp);

        $isFull = $type->isFull();

        if ($isFull) {
            $message = 'backup: full #' . $seq . ' W' . $week . ' — ' . $date;
            $hasLabel = !empty($label);

            if ($hasLabel) {
                $message .= ' — ' . $label;
            }

            return $message;
        }

        $incSeq = $this->padSequence($incrementalSeq ?? 0);

        return 'backup: incremental #' . $seq . '/' . $incSeq . ' W' . $week . ' — ' . $date;
    }

    /**
     * Build a cleanup commit message for rotation deletions.
     *
     * @param int $sequence Full backup sequence being removed.
     * @return string Commit message.
     */
    public function buildCleanupCommitMessage(int $sequence): string
    {
        $seq = $this->padSequence($sequence);

        return 'cleanup: remove full #' . $seq . ' + incrementals';
    }

    /**
     * Zero-pad a sequence number to 3 digits.
     *
     * @param int $sequence Sequence number.
     * @return string Zero-padded string (e.g., "001").
     */
    private function padSequence(int $sequence): string
    {
        return str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Format a timestamp for folder naming.
     *
     * @param int|null $timestamp Unix timestamp (null = now).
     * @return string Formatted date (e.g., "15-Mar-2026").
     */
    private function formatDate(?int $timestamp = null): string
    {
        $hasTimestamp = ($timestamp !== null);

        if ($hasTimestamp) {
            return DateHelper::format($timestamp, self::FOLDER_DATE_FORMAT);
        }

        return gmdate(self::FOLDER_DATE_FORMAT);
    }

    /**
     * Get the ISO week number for a timestamp.
     *
     * @param int|null $timestamp Unix timestamp (null = now).
     * @return string Week number (e.g., "11").
     */
    private function formatWeekNumber(?int $timestamp = null): string
    {
        $hasTimestamp = ($timestamp !== null);

        if ($hasTimestamp) {
            return DateHelper::format($timestamp, 'W');
        }

        return gmdate('W');
    }

    /**
     * Sanitize a user-provided label for use in folder names.
     *
     * Converts spaces to hyphens, removes non-alphanumeric characters,
     * lowercases, and trims to 50 characters.
     *
     * @param string $label Raw label.
     * @return string Sanitized label.
     */
    private function sanitizeLabel(string $label): string
    {
        $lowered = strtolower(trim($label));
        $hyphenated = str_replace(' ', '-', $lowered);
        $cleaned = preg_replace('/[^a-z0-9\-]/', '', $hyphenated);
        $deduped = preg_replace('/-{2,}/', '-', $cleaned);
        $trimmed = trim($deduped, '-');

        $isTooLong = (strlen($trimmed) > 50);

        if ($isTooLong) {
            return substr($trimmed, 0, 50);
        }

        return $trimmed;
    }
}
