# Import/Export & Global Visibility - Consistency Report

> **Version:** 1.0.0  
> **Last Updated:** 2026-01-28  
> **Status:** Complete  

---

## Overview

This document summarizes the specification changes made to support:
1. **ZIP/Markdown Import/Export** - Full project portability
2. **Global vs User-Specific Projects** - Shared visibility across users
3. **First-Login Global Selection** - Onboarding for global project discovery

---

## Specification Files Modified

### Backend Specifications

| File | Changes |
|------|---------|
| `02-database-schema.md` | Added `Visibility` enum (`user`, `global`) and field to `Project` model. Added helper methods: `IsOwner()`, `CanView()`, `CanEdit()`. |
| `03-api-endpoints.md` | Updated `GET /projects` to include visibility filtering, ownership info. Added `PUT /projects/:id/visibility` endpoint. Updated `POST /projects` to accept visibility field. |
| `29-import-export-system.md` | **NEW FILE** - Complete backend spec for ZIP/Markdown import/export, PRD parsing, metadata auto-generation. |

### Frontend Specifications

| File | Changes |
|------|---------|
| `03-project-dashboard.md` | Added visibility filter to `ProjectFilters` component (All/My Projects/Global). |
| `11-onboarding-flow.md` | Changed wizard from 3-step to 4-step. Added visibility toggle. Added `GlobalProjectSelection` component for first-login flow. |
| `30-import-export-ui.md` | **NEW FILE** - Complete frontend spec for Import/Export modals, drag & drop, hooks. |

---

## Data Model Changes

### Project Entity (Updated)

```go
// New fields added to Project
type Project struct {
    // ... existing fields ...
    
    // Visibility controls who can see this project
    // "user" = only owner, "global" = all authenticated users
    Visibility  Visibility  `gorm:"type:text;not null;default:'user';index:IX_Project_Visibility"`
}

type Visibility string

const (
    VisibilityUser   Visibility = "user"
    VisibilityGlobal Visibility = "global"
)
```

### UserPreferences Entity (New)

```go
// For storing dashboard preferences including hidden global projects
type UserPreferences struct {
    UserId               string         `gorm:"primaryKey"`
    HiddenGlobalProjects datatypes.JSON `gorm:"type:text"` // Array of project IDs
    DashboardLayout      string         `gorm:"default:'grid'"`
    DefaultCategory      *string        `gorm:"type:text"`
    UpdatedAt            time.Time
}
```

---

## API Changes Summary

### New Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/import/preview` | Preview import without executing |
| POST | `/import/execute` | Execute import with options |
| POST | `/projects/:id/export` | Start export job |
| GET | `/exports/:id/status` | Check export job status |
| GET | `/exports/:id/download` | Download completed export |
| PUT | `/projects/:id/visibility` | Change project visibility |
| PUT | `/users/me/preferences/global-projects` | Update hidden global projects |

### Modified Endpoints

| Method | Path | Changes |
|--------|------|---------|
| GET | `/projects` | Added `visibility`, `ownership` query params. Response includes `isOwner`, `canEdit`, `visibility`. |
| POST | `/projects` | Added `visibility` field to request body |

---

## UI Flow Changes

### Project Creation Wizard

**Before:** 3 steps (Info → Template → Settings)
**After:** 4 steps (Info → Template → Settings → Confirmation)

Step 1 now includes:
- Visibility radio group (User Only / Global)
- Tooltip explaining global visibility

### Dashboard Filters

**Before:** Search, Category, Sort
**After:** Search, **Visibility**, Category, Sort

Visibility options:
- All Projects (default)
- My Projects (owned)
- Global Specs (shared)

### First-Login Flow

New screen shown when:
1. User logs in for the first time
2. Global projects exist in the system

Features:
- Checkbox list of all global projects
- Pre-selected by default
- "Skip for Now" option
- Selected preferences saved to `UserPreferences`

---

## Cross-Reference Validation

### Database → API Alignment

| Database Field | API Field | ✓ |
|----------------|-----------|---|
| `Project.Visibility` | Request/Response `visibility` | ✓ |
| `Project.OwnerId` | Response `ownerId`, `isOwner` | ✓ |
| `UserPreferences.HiddenGlobalProjects` | Request `hiddenProjectIds` | ✓ |

### API → Frontend Alignment

| API Response | Frontend Usage | ✓ |
|--------------|----------------|---|
| `visibility: "global"` | Badge on ProjectCard | ✓ |
| `isOwner: false` | Disable edit menu items | ✓ |
| `canEdit: false` | Hide delete option | ✓ |

### Import/Export Format Alignment

| Export Content | Import Handling | ✓ |
|----------------|-----------------|---|
| `spec.project.json` | Parse → ProjectMetadata | ✓ |
| `export-manifest.json` | Validate checksums, skip on import | ✓ |
| Folder structure | Recreate directories | ✓ |
| File content | Write to disk + DB record | ✓ |

---

## Acceptance Criteria Checklist

### Import/Export

- [ ] ZIP export includes all project files
- [ ] Export generates valid `spec.project.json`
- [ ] Import detects file type (ZIP/MD/PRD)
- [ ] Preview shows accurate file count
- [ ] PRD files split into sections
- [ ] Missing metadata auto-generated
- [ ] Conflict handling (skip/rename/overwrite)
- [ ] Progress indicators during operations

### Global Visibility

- [ ] Projects can be marked as global during creation
- [ ] Global projects visible to all authenticated users
- [ ] Global projects are read-only for non-owners
- [ ] Dashboard filters by visibility
- [ ] First-login shows global selection
- [ ] User can hide global projects from dashboard

---

## Migration Notes

### Database Migration

1. Add `visibility` column to `Project` table with default `'user'`
2. Create `UserPreferences` table
3. Add index on `Project.Visibility`

```sql
-- Migration: 20260128_add_project_visibility
ALTER TABLE Project ADD COLUMN visibility TEXT NOT NULL DEFAULT 'user';
CREATE INDEX IX_Project_Visibility ON Project(visibility);

CREATE TABLE UserPreferences (
    user_id TEXT PRIMARY KEY,
    hidden_global_projects TEXT,
    dashboard_layout TEXT DEFAULT 'grid',
    default_category TEXT,
    updated_at DATETIME NOT NULL
);
```

### Seeding Configuration

Add to `config.seed.json`:

```json
{
  "import.maxFileSizeMb": 50,
  "import.allowedExtensions": [".zip", ".md"],
  "export.retentionHours": 24,
  "export.maxConcurrentJobs": 3
}
```

---

## Related Documentation

> **Note:** Specs migrated to consolidated `05-features/` structure.

- [Import/Export System](../05-features/03-project-management/01-import-export-system.md)
- [Import/Export UI](../05-features/03-project-management/02-import-export-ui.md)
- [Database Schema](../07-database-design/00-overview.md)
- [API Client](../05-features/15-api-client/00-overview.md)
- [Project Management](../05-features/03-project-management/00-overview.md)
