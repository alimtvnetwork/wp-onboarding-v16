# Active & Future Phases

**Updated: 2026-03-15**

---

## Current Status: Cloud Storage Providers Complete ✅

All three phases of cloud storage providers (GitHub, GitLab, Google Drive) are implemented with React dashboard, publish integration, and quick publish support. Remaining work is backend pipeline integration and admin settings.

---

## Recently Completed (2026-03-15)

### Phase 7E: Cloud Storage Providers ✅

| Sub-phase | Description | Status |
|-----------|-------------|--------|
| Phase 1 | GitHub — PAT, Git Data API | ✅ Done |
| Phase 2 | GitLab — Private-Token, self-hosted | ✅ Done |
| Phase 3 | Google Drive — OAuth2, resumable uploads | ✅ Done |
| Dashboard | CloudStorageSettingsPage with tabs, cards, dialogs | ✅ Done |
| Backup Selector | CloudStorageBackupSelector in publish dialog | ✅ Done |
| Quick Publish | cloudStorageAccountIds in useQuickPublish + useBulkQuickPublish | ✅ Done |
| Progress Stage | cloud_upload stage in PublishProgressDialog | ✅ Done |

### Files Created/Modified

**PHP Traits (Cloud Storage):**
- `CloudStorageGitHubTrait.php`
- `CloudStorageGitLabTrait.php`
- `CloudStorageGoogleDriveTrait.php`
- `CloudStorageOAuthTrait.php`
- `CloudStorageTrait.php` (updated)
- `CloudStorageUploadTrait.php` (updated)
- `CloudStorageFileTrait.php` (updated)
- `CloudStorageAccountCrudTrait.php` (updated)

**React Components:**
- `src/components/cloud-storage/CloudStorageAccountCard.tsx`
- `src/components/cloud-storage/CloudStorageAccountDialog.tsx`
- `src/components/cloud-storage/CloudStorageProviderSettings.tsx`
- `src/components/cloud-storage/CloudStorageBackupSelector.tsx`
- `src/pages/CloudStorage.tsx`
- `src/types/cloudStorage.ts`
- `src/hooks/useCloudStorage.ts`

**Updated:**
- `src/lib/api/methods.ts` — 9 new API methods (8 CRUD + 1 OAuth)
- `src/hooks/useQuickPublish.ts` — reads cloudStorageAccountIds from localStorage
- `src/hooks/useBulkQuickPublish.ts` — reads cloudStorageAccountIds from localStorage
- `src/components/plugins/PublishProgressDialog.tsx` — cloud_upload stage
- `src/pages/Plugins.tsx` — CloudStorageBackupSelector integration
- `src/App.tsx` — /cloud-storage route
- `src/components/layout/Sidebar.tsx` — navigation item

---

## Previously Completed (2026-02-23)

### S-033–S-038: Code Quality Improvements ✅
### Phase 8: Plugin Identity Strings ✅
### Formatting Sweep — All Directories ✅
### ABSPATH Guard Sweep ✅
### Dead Code Cleanup ✅
### PascalCase Enum Labels — Cross-System ✅
### Template Magic String Elimination (Phase 7) ✅
### PHP Plugin SQLite PascalCase Migration (Phase 3) ✅
### PascalCase Spec Documentation Updates (Phase 4) ✅
### Phase 5: Licensing System Architecture ✅
### Go Phase 4: Positive Logic & Boolean Standards ✅
### Go Phase 5: Code Organization Standards ✅
### Go Phase 6: CI Lint Scripts & Integration ✅

---

## Pending Tasks

| # | Task | Priority | Status |
|---|------|----------|--------|
| 1 | ORM PDO Fix — Redeploy | 🔴 Critical | Blocked (deployment) |
| 2 | Cloud Storage — Go pipeline `cloud_upload` stage | 🟡 Medium | Pending |
| 3 | Google OAuth admin settings UI | 🟡 Medium | Pending |
| 4 | Conditionally show cloud_upload stage | 🟡 Medium | Pending |
| 5 | QUpload Activate → PUT | 🟡 Medium | Pending |
| 6 | QUpload Admin UI Uplift | 🟡 Medium | Pending |
| 7 | Log Rotation for both plugins | 🟡 Medium | Pending |
| 8 | Bump versions to 2.15.0 | 🟢 Low | Pending |
| 9 | Type-safety `interface{}` audit | 🟢 Low | Pending |

---

## Resolved Design Decisions ✅

| # | Question | Decision | Implementation |
|---|----------|----------|----------------|
| 1 | Remote Plugin Backups | **WP site only** | `wp-content/uploads/riseup-asia-uploader/backups/{slug}/`, 5-backup retention, pre-publish hook |
| 2 | Bulk Quick Publish | **Yes — sequential server-side** | `ServiceBulkPublish.go` with WebSocket progress events |
| 3 | True Diff Comparison | **Yes — remote MD5 hashes** | `sync-manifest` endpoint with 5-min TTL cache |
| 4 | Licensing | **Custom Go server** | `licensing/` module, SQLite (WAL), HMAC-SHA256, PHP client with 12h cache |
| 5 | Cloud Storage Providers | **GitHub + GitLab + Google Drive** | AES-256-CBC credential encryption, provider-agnostic interface, resumable uploads for large files |

---

*Master plan details in `plan.md` (repo root). Suggestions tracked in `.lovable/memory/suggestions/01-suggestions-tracker.md`.*
