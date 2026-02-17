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

// SyncStatusType represents the sync check result status
type SyncStatusType string

const (
	SyncStatusSynced  SyncStatusType = "synced"
	SyncStatusPending SyncStatusType = "pending"
	SyncStatusError   SyncStatusType = "error"
)

// ChangeType represents the type of file change detected
type ChangeType string

const (
	ChangeTypeAdded    ChangeType = "added"
	ChangeTypeModified ChangeType = "modified"
	ChangeTypeDeleted  ChangeType = "deleted"
)

// SyncResult represents the result of a sync check
type SyncResult struct {
	PluginID      int64          `json:"pluginId"`
	SiteID        int64          `json:"siteId"`
	PluginName    string         `json:"pluginName"`
	SiteName      string         `json:"siteName"`
	Status        SyncStatusType `json:"status"`
	TotalFiles    int            `json:"totalFiles"`
	ChangedFiles  int            `json:"changedFiles"`
	AddedFiles    int            `json:"addedFiles"`
	ModifiedFiles int            `json:"modifiedFiles"`
	DeletedFiles  int            `json:"deletedFiles"`
	Changes       []FileChange   `json:"changes"`
	CheckedAt     time.Time      `json:"checkedAt"`
	Error         string         `json:"error,omitempty"`
}

// FileChange represents a detected file difference
type FileChange struct {
	Path        string     `json:"path"`
	ChangeType  ChangeType `json:"type"`
	LocalHash   string     `json:"localHash,omitempty"`
	RemoteHash  string     `json:"remoteHash,omitempty"`
	LocalSize   int64      `json:"localSize,omitempty"`
	RemoteSize  int64      `json:"remoteSize,omitempty"`
	LocalMTime  time.Time  `json:"localMTime,omitempty"`
	RemoteMTime time.Time  `json:"remoteMTime,omitempty"`
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

// --- Broadcast detail structs (broadcast_details.go) ---

// SyncStartedEvent is broadcast when a sync check begins
type SyncStartedEvent struct {
	PluginID int64 `json:"pluginId"`
	SiteID   int64 `json:"siteId"`
}

// SyncCompleteEvent is broadcast when a sync check completes
type SyncCompleteEvent struct {
	PluginID     int64  `json:"pluginId"`
	SiteID       int64  `json:"siteId"`
	Status       string `json:"status"`
	ChangedFiles int    `json:"changedFiles"`
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

func (s *serviceImpl) CheckSync(
	ctx context.Context,
	pluginID int64,
	siteID int64,
) (*SyncResult, error) {
	s.log.Info("Checking sync status", "pluginId", pluginID, "siteId", siteID)

	s.wsHub.Broadcast(ws.EventSyncStarted, SyncStartedEvent{
		PluginID: pluginID,
		SiteID:   siteID,
	})

	result := &SyncResult{
		PluginID:  pluginID,
		SiteID:    siteID,
		CheckedAt: time.Now(),
		Changes:   []FileChange{},
	}

	if err := s.populateSyncContext(ctx, result, pluginID, siteID); err != nil {
		return result, err
	}

	localScan, err := s.pluginService.ScanDirectory(ctx, result.pluginPath)
	if err != nil {
		result.Status = SyncStatusError
		result.Error = err.Error()

		return result, err
	}
	result.TotalFiles = localScan.FileCount

	s.analyzeSyncChanges(ctx, result, localScan, siteID)
	s.updateMappingSyncStatus(ctx, result.Status, pluginID, siteID)
	s.broadcastSyncComplete(result)

	return result, nil
}

func (s *serviceImpl) populateSyncContext(
	ctx context.Context,
	result *SyncResult,
	pluginID int64,
	siteID int64,
) error {
	plugin, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		result.Status = SyncStatusError
		result.Error = err.Error()

		return err
	}
	result.PluginName = plugin.Name
	result.pluginPath = plugin.Path

	var site models.Site
	err = s.db.QueryRowContext(ctx, `
		SELECT Id, Name, Url, Username, PasswordEncrypted
		FROM Sites WHERE Id = ?
	`, siteID).Scan(&site.ID, &site.Name, &site.URL, &site.Username, &site.PasswordEncrypted)
	if err != nil {
		result.Status = SyncStatusError
		result.Error = "site not found"

		return apperror.New(apperror.ErrNotFound, "site not found")
	}
	result.SiteName = site.Name
	result.site = site

	var remoteSlug string
	err = s.db.QueryRowContext(ctx, `
		SELECT RemoteSlug FROM PluginMappings
		WHERE PluginId = ? AND SiteId = ?
	`, pluginID, siteID).Scan(&remoteSlug)
	if err != nil {
		result.Status = SyncStatusError
		result.Error = "plugin not mapped to site"

		return apperror.New(apperror.ErrNotFound, "mapping not found")
	}
	result.remoteSlug = remoteSlug

	return nil
}

func (s *serviceImpl) analyzeSyncChanges(
	ctx context.Context,
	result *SyncResult,
	localScan *plugin.ScanResult,
	siteID int64,
) {
	wpClient := s.wpClientFactory(result.site.URL, result.site.Username, string(result.site.PasswordEncrypted))
	remoteFiles, err := wpClient.GetPluginFiles(ctx, result.remoteSlug)
	if err != nil {
		s.log.Warn("Could not fetch remote files", "error", err)
		s.markAllFilesAsAdded(result, localScan.Files)

		return
	}

	result.Changes = s.compareFiles(localScan.Files, remoteFiles)
	s.tallySyncChanges(result)
	result.ChangedFiles = len(result.Changes)

	result.Status = SyncStatusSynced
	if result.ChangedFiles > 0 {
		result.Status = SyncStatusPending
	}
}

func (s *serviceImpl) markAllFilesAsAdded(result *SyncResult, files []plugin.FileInfo) {
	for _, f := range files {
		if f.IsDirectory {
			continue
		}

		result.Changes = append(result.Changes, FileChange{
			Path:       f.Path,
			ChangeType: ChangeTypeAdded,
			LocalHash:  f.Hash,
			LocalSize:  f.Size,
			LocalMTime: f.ModifiedAt,
		})
		result.AddedFiles++
	}
	result.ChangedFiles = result.AddedFiles
	result.Status = SyncStatusPending
}

func (s *serviceImpl) tallySyncChanges(result *SyncResult) {
	for _, c := range result.Changes {
		switch c.ChangeType {
		case ChangeTypeAdded:
			result.AddedFiles++
		case ChangeTypeModified:
			result.ModifiedFiles++
		case ChangeTypeDeleted:
			result.DeletedFiles++
		}
	}
}

func (s *serviceImpl) updateMappingSyncStatus(ctx context.Context, status string, pluginID, siteID int64) {
	s.db.ExecContext(ctx, `
		UPDATE PluginMappings 
		SET SyncStatus = ?, UpdatedAt = datetime('now')
		WHERE PluginId = ? AND SiteId = ?
	`, status, pluginID, siteID)
}

func (s *serviceImpl) broadcastSyncComplete(result *SyncResult) {
	s.wsHub.Broadcast(ws.EventSyncComplete, SyncCompleteEvent{
		PluginID:     pluginID,
		SiteID:       siteID,
		Status:       result.Status,
		ChangedFiles: result.ChangedFiles,
	})

	return result, nil
}

func (s *serviceImpl) CheckAllSites(
	ctx context.Context,
	pluginID int64,
) (*BatchSyncResult, error) {
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
			batch.Results = append(batch.Results, *result)

			continue
		}

		s.classifyBatchResult(batch, result)
		batch.Results = append(batch.Results, *result)
	}

	return batch, nil
}

func (s *serviceImpl) classifyBatchResult(batch *BatchSyncResult, result *SyncResult) {
	switch result.Status {
	case SyncStatusSynced:
		batch.Summary.SyncedSites++
	case SyncStatusPending:
		batch.Summary.PendingSites++
	default:
		batch.Summary.ErrorSites++
	}
	batch.Summary.TotalChanges += result.ChangedFiles
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
	remoteMap := buildRemoteMap(remote)
	localPaths := make(map[string]bool)
	var changes []FileChange

	for _, lf := range local {
		if lf.IsDirectory {
			continue
		}
		localPaths[lf.Path] = true

		change := s.compareLocalFile(lf, remoteMap)
		if change != nil {
			changes = append(changes, *change)
		}
	}

	deletions := s.findDeletedFiles(remote, localPaths)
	changes = append(changes, deletions...)

	return changes
}

func buildRemoteMap(remote []wordpress.RemoteFile) map[string]wordpress.RemoteFile {
	m := make(map[string]wordpress.RemoteFile)
	for _, f := range remote {
		m[f.Path] = f
	}

	return m
}

func (s *serviceImpl) compareLocalFile(lf plugin.FileInfo, remoteMap map[string]wordpress.RemoteFile) *FileChange {
	rf, exists := remoteMap[lf.Path]
	if !exists {
		return &FileChange{
			Path:       lf.Path,
			ChangeType: ChangeTypeAdded,
			LocalHash:  lf.Hash,
			LocalSize:  lf.Size,
			LocalMTime: lf.ModifiedAt,
		}
	}

	if lf.Hash == rf.Hash {
		return nil
	}

	return &FileChange{
		Path:        lf.Path,
		ChangeType:  ChangeTypeModified,
		LocalHash:   lf.Hash,
		RemoteHash:  rf.Hash,
		LocalSize:   lf.Size,
		RemoteSize:  rf.Size,
		LocalMTime:  lf.ModifiedAt,
		RemoteMTime: rf.ModifiedAt,
	}
}

func (s *serviceImpl) findDeletedFiles(remote []wordpress.RemoteFile, localPaths map[string]bool) []FileChange {
	var changes []FileChange
	for _, rf := range remote {
		if localPaths[rf.Path] {
			continue
		}

		changes = append(changes, FileChange{
			Path:        rf.Path,
			ChangeType:  ChangeTypeDeleted,
			RemoteHash:  rf.Hash,
			RemoteSize:  rf.Size,
			RemoteMTime: rf.ModifiedAt,
		})
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

func (s *serviceImpl) GetFileChanges(
	ctx context.Context,
	pluginID int64,
	siteID int64,
) ([]models.FileChange, error) {
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

func (s *serviceImpl) MarkSynced(
	ctx context.Context,
	pluginID int64,
	siteID int64,
	files []string,
) error {
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
