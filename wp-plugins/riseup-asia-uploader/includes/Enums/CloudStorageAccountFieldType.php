<?php
/**
 * CloudStorageAccountFieldType — Field keys for request validation and database mapping.
 *
 * @package RiseupAsia\Enums
 * @since   2.15.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum CloudStorageAccountFieldType: string
{
    case Provider     = 'Provider';
    case AccountLabel = 'AccountLabel';
    case Username     = 'Username';
    case Email        = 'Email';
    case AccessToken  = 'AccessToken';
    case RefreshToken = 'RefreshToken';
    case BaseUrl      = 'BaseUrl';
    case RepoName     = 'RepoName';
    case RepoOwner    = 'RepoOwner';
    case FolderId     = 'FolderId';
    case FolderName   = 'FolderName';
    case IsActive     = 'IsActive';

    public function isEqual(self $other): bool { return $this === $other; }

    /** Fields required for GitHub/GitLab account creation. */
    public static function gitRequiredFields(): array
    {
        return array(
            self::Provider,
            self::AccountLabel,
            self::AccessToken,
        );
    }

    /** Fields required for Google Drive account creation. */
    public static function googleDriveRequiredFields(): array
    {
        return array(
            self::Provider,
            self::AccountLabel,
            self::AccessToken,
            self::RefreshToken,
        );
    }
}
