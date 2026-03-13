# Multi-User Per Site — Feature Spec

> **Issue**: 24-multi-user-per-site  
> **Status**: Spec  
> **Priority**: High  
> **Date**: 2026-03-13

---

## Problem

The current architecture stores **one credential per site** (Username + PasswordEncrypted on the `Sites` table). Users need multiple WordPress application passwords per site (e.g., different accounts for testing, staging, deployment) with the ability to select a default active credential.

## Current State

```
Sites table:
  Id, Name, Url, Username, PasswordEncrypted, ConnectionStatus, ...
```

- One-to-one: each site row = one credential
- Seeding: `SeedSite` struct has a single `Username` + `ApplicationPassword`
- Connection tests use the site's single credential

## Requirements

### R1: SiteCredentials Table (Migration v10)
```sql
CREATE TABLE IF NOT EXISTS SiteCredentials (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    SiteId INTEGER NOT NULL,
    AppName TEXT NOT NULL,
    Username TEXT NOT NULL,
    PasswordEncrypted BLOB NOT NULL,
    IsDefault INTEGER DEFAULT 0,
    ConnectionStatus TEXT DEFAULT 'unknown',
    LastTestedAt TEXT,
    CreatedAt TEXT DEFAULT (datetime('now')),
    UpdatedAt TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (SiteId) REFERENCES Sites(Id) ON DELETE CASCADE,
    UNIQUE(SiteId, Username, AppName)
);
CREATE INDEX IF NOT EXISTS idx_sitecredentials_site ON SiteCredentials(SiteId);
CREATE INDEX IF NOT EXISTS idx_sitecredentials_default ON SiteCredentials(SiteId, IsDefault);
```

### R2: Data Migration
- Move existing `Sites.Username` + `Sites.PasswordEncrypted` → `SiteCredentials` rows (IsDefault = 1)
- Drop `Username` and `PasswordEncrypted` columns from `Sites` (SQLite requires table rebuild)
- OR: Keep columns on `Sites` for backward compat, mark deprecated

### R3: Seed Config — Multi-Credential Support
```json
{
  "Name": "Test V1",
  "Url": "https://testv1.developers-organism.com",
  "Category": "Testing",
  "Credentials": [
    {
      "AppName": "test-plg-v1",
      "Username": "test-plugins@pxdmail.net",
      "ApplicationPassword": "U2NaWCBmT2ExIGpxQWsgRlduViBUdUdBIEtJck4=",
      "IsDefault": true
    },
    {
      "AppName": "test-plg-v2",
      "Username": "test-plugins-v2@pxdmail.net",
      "ApplicationPassword": "bjdOTCBENG1WIDNGRFIgcUV1ZiByVE5PIE5NM1k=",
      "IsDefault": false
    }
  ]
}
```

### R4: Go Backend Changes

| File | Change |
|---|---|
| `ConfigStructs.go` | Add `SeedCredential` struct; update `SeedSite` to have `Credentials []SeedCredential` (keep `Username`/`ApplicationPassword` for backward compat) |
| `ConfigSeed.go` | Update `seedSingleSite` to iterate credentials and insert into `SiteCredentials` |
| `MigrationsData.go` | Add migration v10 |
| `DatabaseSettings.go` | Add `CreateSiteCredential`, `ListSiteCredentials`, `SetDefaultCredential`, `DeleteCredential`, `GetDefaultCredential(siteId)` |
| `site/Crud.go` | Update site queries to JOIN `SiteCredentials` for default credential |
| `site/ServiceConnection.go` | Load credential from `SiteCredentials` (default or specified) |
| New: `api/CredentialHandler.go` | REST endpoints for CRUD |

### R5: API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/sites/{id}/credentials` | List all credentials for a site |
| `POST` | `/api/sites/{id}/credentials` | Add a new credential |
| `PUT` | `/api/sites/{id}/credentials/{credId}` | Update credential |
| `DELETE` | `/api/sites/{id}/credentials/{credId}` | Remove credential |
| `PUT` | `/api/sites/{id}/credentials/{credId}/default` | Set as default |

### R6: React UI Changes

- **Site detail panel**: Show credentials list with default badge
- **Add Credential modal**: Username, AppName, Application Password fields
- **Default selector**: Radio/toggle to set active default per site
- **"View All Users" button**: Opens credentials list for the site

### R7: Connection Tests
- Use the **default** credential when testing site connection
- Allow selecting a specific credential for test (optional, phase 2)

---

## Credentials to Seed

### testv1.developers-organism.com
| AppName | Username | App Password |
|---|---|---|
| test-plg-v1 | test-plugins@pxdmail.net | ScZX fOa1 jqAk FWnV TuGA KIrN |
| test-plg-v2 | test-plugins-v2@pxdmail.net | n7NL D4mV 3FDR qEuf rTNO NM3Y |

### testv2.developers-organism.com
| AppName | Username | App Password |
|---|---|---|
| test-plg-v1 | test-plugins@pxdmail.net | ScZX fOa1 jqAk FWnV TuGA KIrN |
| test-plg-v2 | test-plugins-v2@pxdmail.net | n7NL D4mV 3FDR qEuf rTNO NM3Y |

---

## Implementation Phases

### Phase 1 ✅
- Added Test V1 and Test V2 sites to `config.json` with multi-credential format
- This spec created

### Phase 2 ✅ Backend
- DB migration v10 (SiteCredentials table + data migration from Sites)
- `SeedCredential` struct added to `ConfigStructs.go`
- Multi-credential seed logic in `ConfigSeed.go` (backward-compat with legacy single-credential)
- `DatabaseCredentials.go` — full CRUD helpers
- `CrudCredentials.go` — site service credential methods
- `CredentialHandlers.go` — REST API handlers
- Routes registered in `RouterRoutes.go`
- `AdapterSite.go` — interface + adapter methods

### Phase 3 ✅ Frontend
- `SiteCredentialsPanel.tsx` — full CRUD dialog (list, add, edit, delete, set default)
- "Users" button added to `SiteCard.tsx`
- API methods added to `methods.ts` (list, create, update, delete, setDefault)
- `SiteCredentialResponse` type added to `types.ts`
