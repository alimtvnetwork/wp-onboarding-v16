# Cloud Storage Providers — Database Schema

## Migration v17: CloudStorageAccounts Table

```sql
CREATE TABLE IF NOT EXISTS CloudStorageAccounts (
    Id              INTEGER PRIMARY KEY AUTOINCREMENT,
    Provider        TEXT    NOT NULL,          -- 'GitHub', 'GitLab', 'GoogleDrive'
    AccountLabel    TEXT    NOT NULL,          -- User-chosen label, e.g. "Work GitHub"
    Username        TEXT    DEFAULT '',        -- GitHub/GitLab username
    Email           TEXT    DEFAULT '',        -- Associated email
    AccessToken     TEXT    NOT NULL,          -- Encrypted PAT or OAuth2 access token
    RefreshToken    TEXT    DEFAULT '',        -- Encrypted OAuth2 refresh token (Google Drive only)
    TokenExpiresAt  TEXT    DEFAULT '',        -- ISO 8601 UTC expiry for OAuth2 access token
    BaseUrl         TEXT    DEFAULT '',        -- GitLab self-hosted URL (blank = gitlab.com)
    RepoName        TEXT    DEFAULT '',        -- Default repo/project name for backups
    RepoOwner       TEXT    DEFAULT '',        -- GitHub org/user or GitLab namespace
    FolderId        TEXT    DEFAULT '',        -- Google Drive folder ID
    FolderName      TEXT    DEFAULT '',        -- Google Drive folder name
    IsActive        INTEGER NOT NULL DEFAULT 1,
    LastUsedAt      TEXT    DEFAULT '',
    LastError       TEXT    DEFAULT '',
    CreatedAt       TEXT    NOT NULL DEFAULT (datetime('now')),
    UpdatedAt       TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_csa_provider ON CloudStorageAccounts(Provider);
CREATE INDEX IF NOT EXISTS idx_csa_active   ON CloudStorageAccounts(IsActive);
```

### Column Details

| Column | Type | Purpose |
|---|---|---|
| `Id` | INTEGER PK | Auto-increment primary key |
| `Provider` | TEXT | Enum value: `GitHub`, `GitLab`, `GoogleDrive` |
| `AccountLabel` | TEXT | Human-readable name for this account |
| `Username` | TEXT | GitHub/GitLab username (display only) |
| `Email` | TEXT | Associated email address |
| `AccessToken` | TEXT | **Encrypted** using `openssl_encrypt(AES-256-CBC)` with `SecurityConfig.EncryptionKey`. For GitHub/GitLab: Personal Access Token. For Google Drive: OAuth2 access token. |
| `RefreshToken` | TEXT | **Encrypted** OAuth2 refresh token. Only used by Google Drive. |
| `TokenExpiresAt` | TEXT | ISO 8601 UTC timestamp. Only used by Google Drive (access tokens expire ~1 hour). |
| `BaseUrl` | TEXT | For GitLab self-hosted instances. Empty = `https://gitlab.com`. GitHub always uses `https://api.github.com`. |
| `RepoName` | TEXT | Default backup repository name (e.g., `wp-backups`). GitHub/GitLab only. |
| `RepoOwner` | TEXT | GitHub user/org or GitLab namespace path. |
| `FolderId` | TEXT | Google Drive folder ID for uploads. |
| `FolderName` | TEXT | Google Drive folder display name. |
| `IsActive` | INTEGER | 1 = active, 0 = disabled. Disabled accounts are skipped during auto-backup. |
| `LastUsedAt` | TEXT | ISO 8601 UTC timestamp of last successful operation. |
| `LastError` | TEXT | Last error message (cleared on next success). |
| `CreatedAt` | TEXT | Row creation timestamp. |
| `UpdatedAt` | TEXT | Last modification timestamp. |

### Encryption

```php
// Encrypt before storing
$encrypted = openssl_encrypt(
    $plainToken,
    'AES-256-CBC',
    $encryptionKey,
    0,
    $iv,
);

// Format: base64(iv) . '::' . base64(ciphertext)
$stored = base64_encode($iv) . '::' . $encrypted;
```

```php
// Decrypt when reading
[$ivB64, $ciphertext] = explode('::', $stored, 2);
$iv = base64_decode($ivB64);

$plain = openssl_decrypt(
    $ciphertext,
    'AES-256-CBC',
    $encryptionKey,
    0,
    $iv,
);
```

---

## Migration v18: CloudStorageSettings Table

```sql
CREATE TABLE IF NOT EXISTS CloudStorageSettings (
    Id                INTEGER PRIMARY KEY AUTOINCREMENT,
    Provider          TEXT    NOT NULL UNIQUE, -- 'GitHub', 'GitLab', 'GoogleDrive'
    IsEnabled         INTEGER NOT NULL DEFAULT 0,
    AutoBackupEnabled INTEGER NOT NULL DEFAULT 0,
    DefaultAccountId  INTEGER DEFAULT NULL,
    RetentionCount    INTEGER NOT NULL DEFAULT 10,
    RotationEnabled   INTEGER NOT NULL DEFAULT 1,
    BackupPrefix      TEXT    NOT NULL DEFAULT 'wp-backup',
    CreatedAt         TEXT    NOT NULL DEFAULT (datetime('now')),
    UpdatedAt         TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (DefaultAccountId) REFERENCES CloudStorageAccounts(Id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_css_provider ON CloudStorageSettings(Provider);
```

### Column Details

| Column | Type | Purpose |
|---|---|---|
| `Provider` | TEXT | One row per provider. UNIQUE constraint. |
| `IsEnabled` | INTEGER | Master toggle for this provider. |
| `AutoBackupEnabled` | INTEGER | Whether to auto-push backups to this provider after snapshot creation. |
| `DefaultAccountId` | INTEGER FK | Which account to use by default for auto-backup. NULL = user must select. |
| `RetentionCount` | INTEGER | Max number of backups to keep on this provider. Oldest deleted first. |
| `RotationEnabled` | INTEGER | Whether to enforce retention (delete old backups). |
| `BackupPrefix` | TEXT | Filename prefix for backup files, e.g., `wp-backup-2026-03-14.zip`. |

---

## TableType Enum Additions

```php
// Add to TableType.php
case CloudStorageAccounts  = 'CloudStorageAccounts';
case CloudStorageSettings  = 'CloudStorageSettings';
```

Add helper:
```php
public function isCloudStorage(): bool { return str_starts_with($this->value, 'CloudStorage'); }
```

---

## Migration Trait Files

- `DatabaseMigrationsV17Trait.php` — Creates `CloudStorageAccounts` table
- `DatabaseMigrationsV18Trait.php` — Creates `CloudStorageSettings` table with default rows for each provider

### Default Seed Data (v18)

After creating the table, insert default rows:

```sql
INSERT OR IGNORE INTO CloudStorageSettings (Provider, IsEnabled, RetentionCount, BackupPrefix)
VALUES
    ('GitHub', 0, 10, 'wp-backup'),
    ('GitLab', 0, 10, 'wp-backup'),
    ('GoogleDrive', 0, 10, 'wp-backup');
```
