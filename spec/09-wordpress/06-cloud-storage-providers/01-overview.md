# Cloud Storage Providers — Overview

**Spec**: `spec/17-cloud-storage-providers/`
**Plugin**: Riseup Asia Uploader
**Version**: 2.15.0+
**Migration**: v17 (CloudStorageAccounts), v18 (CloudStorageSettings)

---

## Purpose

Allow users to store WordPress site backups (snapshot ZIPs) on external cloud storage providers:

1. **GitHub** — Push backup ZIPs as commits to a dedicated repository
2. **GitLab** — Push backup ZIPs as commits to a dedicated project
3. **Google Drive** — Upload backup ZIPs via OAuth2 to user's Drive

Each provider supports **multiple accounts**. A user can add 3 GitHub accounts, 2 GitLab accounts, and 4 Google Drive accounts — all independently managed.

---

## Architecture

### Provider Abstraction

All three providers implement a common `CloudStorageProviderInterface`:

```
interface CloudStorageProviderInterface {
    upload(accountId, filePath, remotePath): Result
    listFiles(accountId, remotePath): Result
    deleteFile(accountId, remotePath): Result
    testConnection(accountId): Result
    getAccountLabel(accountId): string
}
```

### Flow: Backup → Cloud Storage

```
1. User triggers backup (manual or scheduled)
2. Backup ZIP is created locally (existing flow)
3. System checks CloudStorageSettings for enabled providers
4. For each enabled provider+account:
   a. GitHub/GitLab: Create repo if missing → commit ZIP file
   b. Google Drive: Create folder if missing → upload ZIP file
5. Rotation policy applied (delete oldest if exceeding retention count)
6. Log result to Transactions table
```

### SQLite Tables

**`CloudStorageAccounts`** (Migration v17):
- Stores credentials, tokens, and metadata for each account
- Encrypted token storage using existing SecurityConfig.EncryptionKey
- Supports multiple accounts per provider

**`CloudStorageSettings`** (Migration v18):
- Per-provider settings: enabled, rotation count, auto-backup flag
- Links to which account(s) to use for auto-backup

### Implementation Order

1. **Phase 1**: GitHub provider (this spec focuses here)
2. **Phase 2**: GitLab provider (mirrors GitHub with API differences)
3. **Phase 3**: Google Drive provider (OAuth2 web flow)

---

## Security

- **Tokens are encrypted at rest** in SQLite using `openssl_encrypt()` with `SecurityConfig.EncryptionKey`
- **Tokens never appear in API GET responses** — only masked hints (e.g., `ghp_****abc`)
- **GitHub/GitLab**: Personal Access Tokens (PATs) — user creates in provider UI, pastes into plugin
- **Google Drive**: OAuth2 flow — plugin redirects to Google, receives auth code, exchanges for access+refresh tokens
- **Refresh tokens** are stored and used to obtain new access tokens automatically
- All HTTP calls use `HttpConfigType` static factories — no inline magic arrays

---

## Coding Standards

- All new enums in `RiseupAsia\Enums` namespace with PascalCase values
- All traits in `RiseupAsia\Traits\CloudStorage\` namespace
- File cap: 500 lines; function cap: 20 lines
- `catch (Throwable $e)` with full stack trace logging
- Semantic boolean methods (e.g., `isGitHub()`, `isAccountActive()`)
- `PathHelper` for all file operations
- `HttpConfigType::authenticatedOptions()` for all API calls
- Blank line before `return`/`throw`/control structures

---

## React Dashboard Components (Future)

- `CloudStorageSettingsPage` — Provider tabs (GitHub, GitLab, Google Drive)
- `CloudStorageAccountCard` — Shows account name, status, masked token, actions
- `CloudStorageAccountDialog` — Add/edit account form with field examples
- `CloudStorageRotationSettings` — Configure retention per provider

---

## Go Backend Types (Future)

- `backend/internal/wordpress/CloudStorageTypes.go` — Request/response structs
- `backend/internal/enums/cloudstorageprovidertype/` — Provider enum variants

---

## Cross-References

- Existing backup system: see `knowledge://memory/features/plugin/remote-backups`
- Database migrations: see `knowledge://memory/coding-standards/php-database-migrations`
- PHP coding standards: see `knowledge://memory/coding-standards/php-development`
- Go coding standards: see `knowledge://memory/coding-standards/go-development`
