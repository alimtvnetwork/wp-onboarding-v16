# 31 — Sync Service Implementation

> **Location:** `spec/wp-plugin-publish/03-implementation/31-sync-service-impl.md`  
> **Updated:** 2026-02-01  
> **Status:** Implementation Spec

---

## Overview

Complete Go implementation for the Sync Service. This service compares local plugin files with remote WordPress installations and manages file change detection.

---

## File Structure

```
backend/internal/services/sync/
├── service.go      # Main service interface and constructor
├── check.go        # Sync checking operations
├── compare.go      # Local vs remote file comparison
├── changes.go      # File change management
└── types.go        # Input/output types
```

---

## Implementation: types.go

```go
package sync

import "time"

// SyncResult represents the result of a sync check
type SyncResult struct {
	PluginID      int64        `json:"pluginId"`
	SiteID        int64        `json:"siteId"`
	PluginName    string       `json:"pluginName"`
	SiteName      string       `json:"siteName"`
	Status        string       `json:"status"` // synced, pending, error
	TotalFiles    int          `json:"totalFiles"`
	ChangedFiles  int          `json:"changedFiles"`
	AddedFiles    int          `json:"addedFiles"`
	ModifiedFiles int          `json:"modifiedFiles"`
	DeletedFiles  int          `json:"deletedFiles"`
	Changes       []FileChange `json:"changes"`
	CheckedAt     time.Time    `json:"checkedAt"`
	Error         string       `json:"error,omitempty"`
}

// FileChange represents a detected file difference
type FileChange struct {
	Path        string    `json:"path"`
	ChangeType  string    `json:"type"` // added, modified, deleted
	LocalHash   string    `json:"localHash,omitempty"`
	RemoteHash  string    `json:"remoteHash,omitempty"`
	LocalSize   int64     `json:"localSize,omitempty"`
	RemoteSize  int64     `json:"remoteSize,omitempty"`
	LocalMTime  time.Time `json:"localMTime,omitempty"`
	RemoteMTime time.Time `json:"remoteMTime,omitempty"`
}

// SyncOptions configures sync behavior
type SyncOptions struct {
	IncludeUntracked bool `json:"includeUntracked"`
	ForceFullCheck   bool `json:"forceFullCheck"`
}

// BatchSyncResult holds results for multiple sites
type BatchSyncResult struct {
	PluginID int64        `json:"pluginId"`
	Results  []SyncResult `json:"results"`
	Summary  SyncSummary  `json:"summary"`
}

// SyncSummary aggregates sync status across sites
type SyncSummary struct {
	TotalSites    int `json:"totalSites"`
	SyncedSites   int `json:"syncedSites"`
	PendingSites  int `json:"pendingSites"`
	ErrorSites    int `json:"errorSites"`
	TotalChanges  int `json:"totalChanges"`
}
```

---

## Implementation: service.go

```go
package sync

import (
	"context"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

// Service interface for sync operations
type Service interface {
	// Sync checking
	CheckSync(ctx context.Context, pluginID, siteID int64) (*SyncResult, error)
	CheckAllSites(ctx context.Context, pluginID int64) (*BatchSyncResult, error)
	CheckAllPlugins(ctx context.Context) ([]SyncResult, error)

	// File change management
	GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error)
	RecordFileChange(ctx context.Context, change *models.FileChange) error
	MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error
	ClearChanges(ctx context.Context, pluginID int64) error
}

// Config holds sync service configuration
type Config struct {
	DB              *database.DB
	Logger          *logger.Logger
	PluginService   plugin.Service
	WPClientFactory func(url, user, pass string) *wordpress.Client
	WSHub           *ws.Hub
}

type serviceImpl struct {
	db              *database.DB
	log             *logger.Logger
	pluginService   plugin.Service
	wpClientFactory func(url, user, pass string) *wordpress.Client
	wsHub           *ws.Hub
}

// New creates a new sync service
func New(cfg Config) Service {
	return &serviceImpl{
		db:              cfg.DB,
		log:             cfg.Logger,
		pluginService:   cfg.PluginService,
		wpClientFactory: cfg.WPClientFactory,
		wsHub:           cfg.WSHub,
	}
}
```

---

## Implementation: check.go

```go
package sync

import (
	"context"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) CheckSync(ctx context.Context, pluginID, siteID int64) (*SyncResult, error) {
	s.log.Info("Checking sync status", "pluginId", pluginID, "siteId", siteID)

	// Broadcast sync started event
	s.wsHub.Broadcast(ws.EventSyncStarted, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
	})

	result := &SyncResult{
		PluginID:  pluginID,
		SiteID:    siteID,
		CheckedAt: time.Now(),
		Changes:   []FileChange{},
	}

	// Get plugin details
	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		result.Status = "error"
		result.Error = err.Error()
		return result, err
	}
	result.PluginName = plugin.Name

	// Get site details
	var site models.Site
	err = s.db.QueryRowContext(ctx, `
		SELECT Id, Name, Url, Username, PasswordEncrypted
		FROM Sites WHERE Id = ?
	`, siteID).Scan(&site.ID, &site.Name, &site.URL, &site.Username, &site.PasswordEncrypted)
	if err != nil {
		result.Status = "error"
		result.Error = "site not found"
		return result, apperror.New(apperror.ErrNotFound, "site not found")
	}
	result.SiteName = site.Name

	// Get mapping to find remote slug
	var remoteSlug string
	err = s.db.QueryRowContext(ctx, `
		SELECT RemoteSlug FROM PluginMappings
		WHERE PluginId = ? AND SiteId = ?
	`, pluginID, siteID).Scan(&remoteSlug)
	if err != nil {
		result.Status = "error"
		result.Error = "plugin not mapped to site"
		return result, apperror.New(apperror.ErrNotFound, "mapping not found")
	}

	// Scan local plugin directory
	localScan, err := s.pluginService.ScanDirectory(ctx, plugin.Path)
	if err != nil {
		result.Status = "error"
		result.Error = err.Error()
		return result, err
	}
	result.TotalFiles = localScan.FileCount

	// Create WordPress client and get remote files
	wpClient := s.wpClientFactory(site.URL, site.Username, string(site.PasswordEncrypted))
	remoteFiles, err := wpClient.GetPluginFiles(ctx, remoteSlug)
	if err != nil {
		// If remote plugin doesn't exist, all files are "added"
		s.log.Warn("Could not fetch remote files", "error", err)
		for _, f := range localScan.Files {
			if !f.IsDirectory {
				result.Changes = append(result.Changes, FileChange{
					Path:       f.Path,
					ChangeType: "added",
					LocalHash:  f.Hash,
					LocalSize:  f.Size,
					LocalMTime: f.ModifiedAt,
				})
				result.AddedFiles++
			}
		}
		result.ChangedFiles = result.AddedFiles
		result.Status = "pending"
	} else {
		// Compare local and remote files
		result.Changes = s.compareFiles(localScan.Files, remoteFiles)
		for _, c := range result.Changes {
			switch c.ChangeType {
			case "added":
				result.AddedFiles++
			case "modified":
				result.ModifiedFiles++
			case "deleted":
				result.DeletedFiles++
			}
		}
		result.ChangedFiles = len(result.Changes)

		if result.ChangedFiles == 0 {
			result.Status = "synced"
		} else {
			result.Status = "pending"
		}
	}

	// Update mapping sync status
	s.db.ExecContext(ctx, `
		UPDATE PluginMappings 
		SET SyncStatus = ?, UpdatedAt = datetime('now')
		WHERE PluginId = ? AND SiteId = ?
	`, result.Status, pluginID, siteID)

	// Broadcast sync complete event
	s.wsHub.Broadcast(ws.EventSyncComplete, map[string]interface{}{
		"pluginId":     pluginID,
		"siteId":       siteID,
		"status":       result.Status,
		"changedFiles": result.ChangedFiles,
	})

	return result, nil
}

func (s *serviceImpl) CheckAllSites(ctx context.Context, pluginID int64) (*BatchSyncResult, error) {
	s.log.Info("Checking sync for all sites", "pluginId", pluginID)

	// Get all mappings for this plugin
	mappings, err := s.pluginService.GetMappings(ctx, pluginID)
	if err != nil {
		return nil, err
	}

	batch := &BatchSyncResult{
		PluginID: pluginID,
		Results:  make([]SyncResult, 0, len(mappings)),
		Summary:  SyncSummary{TotalSites: len(mappings)},
	}

	for _, m := range mappings {
		result, err := s.CheckSync(ctx, pluginID, m.SiteID)
		if err != nil {
			batch.Summary.ErrorSites++
		} else {
			switch result.Status {
			case "synced":
				batch.Summary.SyncedSites++
			case "pending":
				batch.Summary.PendingSites++
			default:
				batch.Summary.ErrorSites++
			}
			batch.Summary.TotalChanges += result.ChangedFiles
		}
		batch.Results = append(batch.Results, *result)
	}

	return batch, nil
}

func (s *serviceImpl) CheckAllPlugins(ctx context.Context) ([]SyncResult, error) {
	s.log.Info("Checking sync for all plugins")

	// Get all mappings
	rows, err := s.db.QueryContext(ctx, `
		SELECT PluginId, SiteId FROM PluginMappings
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var results []SyncResult
	for rows.Next() {
		var pluginID, siteID int64
		rows.Scan(&pluginID, &siteID)

		result, _ := s.CheckSync(ctx, pluginID, siteID)
		if result != nil {
			results = append(results, *result)
		}
	}

	return results, nil
}
```

---

## Implementation: compare.go

```go
package sync

import (
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
)

// compareFiles compares local files with remote files and returns differences
func (s *serviceImpl) compareFiles(local []plugin.FileInfo, remote []wordpress.RemoteFile) []FileChange {
	var changes []FileChange

	// Build map of remote files by path
	remoteMap := make(map[string]wordpress.RemoteFile)
	for _, f := range remote {
		remoteMap[f.Path] = f
	}

	// Check local files against remote
	localPaths := make(map[string]bool)
	for _, lf := range local {
		if lf.IsDirectory {
			continue
		}
		localPaths[lf.Path] = true

		if rf, exists := remoteMap[lf.Path]; exists {
			// File exists on both - check if modified
			if lf.Hash != rf.Hash {
				changes = append(changes, FileChange{
					Path:        lf.Path,
					ChangeType:  "modified",
					LocalHash:   lf.Hash,
					RemoteHash:  rf.Hash,
					LocalSize:   lf.Size,
					RemoteSize:  rf.Size,
					LocalMTime:  lf.ModifiedAt,
					RemoteMTime: rf.ModifiedAt,
				})
			}
		} else {
			// File only exists locally - needs to be added
			changes = append(changes, FileChange{
				Path:       lf.Path,
				ChangeType: "added",
				LocalHash:  lf.Hash,
				LocalSize:  lf.Size,
				LocalMTime: lf.ModifiedAt,
			})
		}
	}

	// Check for deleted files (exist on remote but not local)
	for _, rf := range remote {
		if !localPaths[rf.Path] {
			changes = append(changes, FileChange{
				Path:        rf.Path,
				ChangeType:  "deleted",
				RemoteHash:  rf.Hash,
				RemoteSize:  rf.Size,
				RemoteMTime: rf.ModifiedAt,
			})
		}
	}

	return changes
}
```

---

## Implementation: changes.go

```go
package sync

import (
	"context"
	"database/sql"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
)

func (s *serviceImpl) GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT Id, PluginId, FilePath, ChangeType, LocalHash, RemoteHash,
		       LocalModifiedAt, DetectedAt, SyncedAt
		FROM FileChanges
		WHERE PluginId = ? AND SyncedAt IS NULL
		ORDER BY DetectedAt DESC
	`, pluginID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get file changes")
	}
	defer rows.Close()

	var changes []models.FileChange
	for rows.Next() {
		var c models.FileChange
		var localModAt, syncedAt sql.NullString

		err := rows.Scan(
			&c.ID, &c.PluginID, &c.FilePath, &c.ChangeType,
			&c.LocalHash, &c.RemoteHash, &localModAt, &c.DetectedAt, &syncedAt,
		)
		if err != nil {
			continue
		}

		if localModAt.Valid {
			t, _ := time.Parse(time.RFC3339, localModAt.String)
			c.LocalModifiedAt = &t
		}
		if syncedAt.Valid {
			t, _ := time.Parse(time.RFC3339, syncedAt.String)
			c.SyncedAt = &t
		}

		changes = append(changes, c)
	}

	return changes, nil
}

func (s *serviceImpl) RecordFileChange(ctx context.Context, change *models.FileChange) error {
	// Check if change already exists for this file
	var existingID int64
	err := s.db.QueryRowContext(ctx, `
		SELECT Id FROM FileChanges
		WHERE PluginId = ? AND FilePath = ? AND SyncedAt IS NULL
	`, change.PluginID, change.FilePath).Scan(&existingID)

	if err == sql.ErrNoRows {
		// Insert new change
		_, err = s.db.ExecContext(ctx, `
			INSERT INTO FileChanges (PluginId, FilePath, ChangeType, LocalHash, LocalModifiedAt, DetectedAt)
			VALUES (?, ?, ?, ?, ?, datetime('now'))
		`, change.PluginID, change.FilePath, change.ChangeType, change.LocalHash, change.LocalModifiedAt)
	} else if err == nil {
		// Update existing change
		_, err = s.db.ExecContext(ctx, `
			UPDATE FileChanges
			SET ChangeType = ?, LocalHash = ?, LocalModifiedAt = ?, DetectedAt = datetime('now')
			WHERE Id = ?
		`, change.ChangeType, change.LocalHash, change.LocalModifiedAt, existingID)
	}

	return err
}

func (s *serviceImpl) MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error {
	s.log.Info("Marking files as synced", "pluginId", pluginID, "siteId", siteID, "files", len(files))

	for _, path := range files {
		_, err := s.db.ExecContext(ctx, `
			UPDATE FileChanges
			SET SyncedAt = datetime('now')
			WHERE PluginId = ? AND FilePath = ? AND SyncedAt IS NULL
		`, pluginID, path)
		if err != nil {
			return apperror.Wrap(err, apperror.ErrDatabaseExec, "failed to mark file synced")
		}
	}

	// Update mapping sync status
	_, err := s.db.ExecContext(ctx, `
		UPDATE PluginMappings
		SET SyncStatus = 'synced', LastSyncAt = datetime('now'), UpdatedAt = datetime('now')
		WHERE PluginId = ? AND SiteId = ?
	`, pluginID, siteID)

	return err
}

func (s *serviceImpl) ClearChanges(ctx context.Context, pluginID int64) error {
	_, err := s.db.ExecContext(ctx, `
		DELETE FROM FileChanges WHERE PluginId = ?
	`, pluginID)
	return err
}
```

---

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS FileChanges (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    PluginId INTEGER NOT NULL,
    FilePath TEXT NOT NULL,
    ChangeType TEXT NOT NULL, -- added, modified, deleted
    LocalHash TEXT,
    RemoteHash TEXT,
    LocalModifiedAt TEXT,
    DetectedAt TEXT NOT NULL,
    SyncedAt TEXT,
    FOREIGN KEY (PluginId) REFERENCES Plugins(Id)
);

CREATE INDEX IF NOT EXISTS idx_changes_plugin ON FileChanges(PluginId);
CREATE INDEX IF NOT EXISTS idx_changes_synced ON FileChanges(SyncedAt);
```

---

## API Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| GET | `/api/sync/check/:pluginId/:siteId` | Check sync status |
| GET | `/api/sync/check/:pluginId` | Check all sites for plugin |
| GET | `/api/sync/check` | Check all mappings |
| GET | `/api/sync/changes/:pluginId/:siteId` | Get file changes |
| POST | `/api/sync/mark-synced` | Mark files as synced |

---

*See also: [32-publish-service-impl.md](32-publish-service-impl.md)*
