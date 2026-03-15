<?php
/**
 * CloudStorageProviderType — Cloud storage provider identifiers.
 *
 * @package RiseupAsia\Enums
 * @since   2.15.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum CloudStorageProviderType: string
{
    case GitHub      = 'GitHub';
    case GitLab      = 'GitLab';
    case GoogleDrive = 'GoogleDrive';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isGitHub(): bool      { return $this->isEqual(self::GitHub); }
    public function isGitLab(): bool      { return $this->isEqual(self::GitLab); }
    public function isGoogleDrive(): bool { return $this->isEqual(self::GoogleDrive); }

    /** Whether this provider uses OAuth2 flow (redirect-based). */
    public function isOAuth2(): bool { return $this->isGoogleDrive(); }

    /** Whether this provider uses a Personal Access Token. */
    public function isPat(): bool { return $this->isGitHub() || $this->isGitLab(); }

    /** API base URL for this provider. */
    public function apiBaseUrl(): string
    {
        return match($this) {
            self::GitHub      => 'https://api.github.com',
            self::GitLab      => 'https://gitlab.com/api/v4',
            self::GoogleDrive => 'https://www.googleapis.com/drive/v3',
        };
    }

    /** Display label for UI. */
    public function label(): string
    {
        return match($this) {
            self::GitHub      => 'GitHub',
            self::GitLab      => 'GitLab',
            self::GoogleDrive => 'Google Drive',
        };
    }
}
