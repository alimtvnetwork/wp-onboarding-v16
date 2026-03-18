# Cloud Storage Providers — Enums

## CloudStorageProviderType

```php
<?php
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
```

---

## CloudStorageAccountFieldType

Field keys used in request validation and database mapping:

```php
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
```

---

## EndpointType Additions

Add these cases to `EndpointType.php`:

```php
// ── Cloud Storage ───────────────────────────────────────────────
case CloudStorageAccounts        = 'cloud-storage/accounts';
case CloudStorageAccountId       = 'cloud-storage/accounts/(?P<id>\d+)';
case CloudStorageAccountTest     = 'cloud-storage/accounts/test';
case CloudStorageSettings        = 'cloud-storage/settings';
case CloudStorageSettingsProvider = 'cloud-storage/settings/(?P<provider>[a-zA-Z]+)';
case CloudStorageUpload          = 'cloud-storage/upload';
case CloudStorageFiles           = 'cloud-storage/files';
case CloudStorageDelete          = 'cloud-storage/delete';
case CloudStorageOAuthCallback   = 'cloud-storage/oauth/callback';
case CloudStorageOAuthInitiate   = 'cloud-storage/oauth/initiate';
```

Add helper:
```php
public function isCloudStorage(): bool { return str_starts_with($this->value, 'cloud-storage/'); }
```

---

## ResponseKeyType Additions

```php
/** Cloud storage keys. */
case Accounts          = 'Accounts';
case Account           = 'Account';
case AccountId         = 'AccountId';
case AccountLabel      = 'AccountLabel';
case TokenMask         = 'TokenMask';
case ConnectionStatus  = 'ConnectionStatus';
case ProviderSettings  = 'ProviderSettings';
case UploadResult      = 'UploadResult';
case RemotePath        = 'RemotePath';
case RemoteUrl         = 'RemoteUrl';
case RotationApplied   = 'RotationApplied';
case FilesDeleted      = 'FilesDeleted';  // already exists — reuse
case OAuthUrl          = 'OAuthUrl';
case OAuthState        = 'OAuthState';
```

---

## ActionType Additions

```php
case CloudStorageUpload    = 'CloudStorageUpload';
case CloudStorageDelete    = 'CloudStorageDelete';
case CloudStorageRotation  = 'CloudStorageRotation';
case CloudStorageAccountAdd    = 'CloudStorageAccountAdd';
case CloudStorageAccountRemove = 'CloudStorageAccountRemove';
```

---

## LogCategoryType Addition

```php
case CloudStorage = 'CloudStorage';
```
