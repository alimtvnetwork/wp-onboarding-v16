# Active Development Plan

Updated: 2026-03-15

## Current Focus Areas

- **Cloud Storage Integration**: Frontend complete (3 providers + dashboard + publish integration). Backend Go pipeline integration pending.
- **Issue Resolution**: ORM PDO fix needs redeployment (critical). QUpload PUT migration and UI uplift pending.
- **Code Quality & Standards**: Ongoing enforcement of formatting rules and code consistency.
- **Issue Documentation**: Every fix requires a write-up under `/spec/02-app-issues/` per the post-fix workflow.

## Recently Completed (2026-03-15)

### Phase 7E: Cloud Storage Providers ✅

- **CS-001**: Cloud Storage Settings Page — provider tabs, account cards, account dialog with dynamic fields
- **CS-002**: Google Drive OAuth2 — `CloudStorageGoogleDriveTrait.php`, `CloudStorageOAuthTrait.php`, resumable uploads
- **CS-003**: CloudStorageBackupSelector — collapsible selector in publish dialog with localStorage persistence
- **CS-004**: `cloudStorageAccountIds` passed through `useQuickPublish` hook
- **CS-005**: `cloudStorageAccountIds` passed through `useBulkQuickPublish` hook
- **CS-006**: `cloud_upload` stage added to `PublishProgressDialog` (between backup and package)

### Previously Completed (2026-02-23)

- **S-033–S-040**: Code quality improvements (DateHelper, camelCase, ResponseKeyType, etc.)
- **Phase 8**: Plugin identity strings replaced with PluginConfigType enum
- **Go Phases 4–6**: Boolean standards, code organization, CI lint integration
- **All compliance sweeps**: Formatting, ABSPATH guards, dead code, PascalCase enums

## Pending Tasks

| # | Task | Priority | Blocked? |
|---|------|----------|----------|
| 1 | ORM PDO Fix — Redeploy | 🔴 Critical | Yes (deployment) |
| 2 | Cloud Storage Go pipeline `cloud_upload` stage (S-044) | 🟡 Medium | No |
| 3 | Google OAuth admin settings page (S-042) | 🟡 Medium | No |
| 4 | Conditionally show cloud_upload stage in UI (S-041) | 🟡 Medium | Depends on #2 |
| 5 | QUpload Activate → PUT (all layers) | 🟡 Medium | No |
| 6 | QUpload Admin UI Uplift | 🟡 Medium | No |
| 7 | Log Rotation for both plugins | 🟡 Medium | No |
| 8 | Bump versions to 2.15.0 (S-043) | 🟢 Low | After #2 |
| 9 | Type-safety `interface{}` audit | 🟢 Low | No |
| 10 | Licensing admin dashboard (S-045) | 🟢 Low | No |
| 11 | Publish analytics dashboard (S-046) | 🟢 Low | No |
