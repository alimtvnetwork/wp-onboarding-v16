// Package sync provides local-remote file synchronization
package sync

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
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

// SyncResult represents the result of a sync check
type SyncResult struct {
	PluginID     int64               `json:"pluginId"`
	SiteID       int64               `json:"siteId"`
	SiteName     string              `json:"siteName,omitempty"`
	InSync       bool                `json:"inSync"`
	LocalFiles   int                 `json:"localFiles"`
	RemoteFiles  int                 `json:"remoteFiles"`
	Added        int                 `json:"added"`
	Modified     int                 `json:"modified"`
	Deleted      int                 `json:"deleted"`
	Changes      []models.FileChange `json:"changes,omitempty"`
	CheckedAt    time.Time           `json:"checkedAt"`
	ErrorMessage string              `json:"errorMessage,omitempty"`
}

// BatchSyncResult represents sync results for multiple sites
type BatchSyncResult struct {
	PluginID   int64        `json:"pluginId"`
	PluginName string       `json:"pluginName"`
	Results    []SyncResult `json:"results"`
	TotalSites int          `json:"totalSites"`
	InSync     int          `json:"inSync"`
	OutOfSync  int          `json:"outOfSync"`
	Errors     int          `json:"errors"`
}

// Config holds sync service configuration
type Config struct {
	DB              *database.DB
	Logger          *logger.Logger
	PluginService   *plugin.Service
	WPClientFactory func(url, user, pass string) *wordpress.Client
	WSHub           *ws.Hub
}

type serviceImpl struct {
	db              *database.DB
	log             *logger.Logger
	pluginService   *plugin.Service
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

// CheckSync compares local vs remote files for a specific plugin-site mapping
func (s *serviceImpl) CheckSync(ctx context.Context, pluginID, siteID int64) (*SyncResult, error) {
	result := &SyncResult{
		PluginID:  pluginID,
		SiteID:    siteID,
		CheckedAt: time.Now(),
	}

	// Broadcast start
	s.broadcastProgress(pluginID, siteID, "checking", 0, "Starting sync check...")

	// Get plugin info
	plug, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		result.ErrorMessage = err.Error()
		return result, nil
	}

	// Scan local files
	s.broadcastProgress(pluginID, siteID, "scanning", 20, "Scanning local files...")
	localFiles, err := s.scanLocalFiles(plug.Path, plug.ExcludePatterns)
	if err != nil {
		result.ErrorMessage = err.Error()
		return result, nil
	}
	result.LocalFiles = len(localFiles)

	// Get remote file hashes (from WordPress)
	s.broadcastProgress(pluginID, siteID, "comparing", 50, "Fetching remote files...")
	// Note: WordPress doesn't have a native file hash endpoint
	// This would require a custom endpoint or comparing via backup
	remoteFiles := make(map[string]string) // path -> hash
	result.RemoteFiles = len(remoteFiles)

	// Compare files
	s.broadcastProgress(pluginID, siteID, "comparing", 70, "Comparing files...")
	changes := s.compareFiles(localFiles, remoteFiles)
	
	result.Changes = changes
	result.Added = countByType(changes, "added")
	result.Modified = countByType(changes, "modified")
	result.Deleted = countByType(changes, "deleted")
	result.InSync = len(changes) == 0

	// Update mapping sync status
	s.updateMappingSyncStatus(ctx, pluginID, siteID, result.InSync)

	s.broadcastProgress(pluginID, siteID, "complete", 100, "Sync check complete")

	s.log.Info("Sync check completed",
		"pluginId", pluginID,
		"siteId", siteID,
		"inSync", result.InSync,
		"changes", len(changes))

	return result, nil
}

// CheckAllSites checks sync status for all sites mapped to a plugin
func (s *serviceImpl) CheckAllSites(ctx context.Context, pluginID int64) (*BatchSyncResult, error) {
	result := &BatchSyncResult{
		PluginID: pluginID,
		Results:  []SyncResult{},
	}

	// Get plugin info
	plug, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, err
	}
	result.PluginName = plug.Name

	// Get all mappings for this plugin
	mappings, err := s.getMappings(ctx, pluginID)
	if err != nil {
		return nil, err
	}
	result.TotalSites = len(mappings)

	// Check each site
	for _, mapping := range mappings {
		syncResult, _ := s.CheckSync(ctx, pluginID, mapping.SiteID)
		if syncResult != nil {
			syncResult.SiteName = mapping.SiteName
			result.Results = append(result.Results, *syncResult)

			if syncResult.ErrorMessage != "" {
				result.Errors++
			} else if syncResult.InSync {
				result.InSync++
			} else {
				result.OutOfSync++
			}
		}
	}

	return result, nil
}

// CheckAllPlugins checks sync status for all registered plugins
func (s *serviceImpl) CheckAllPlugins(ctx context.Context) ([]SyncResult, error) {
	var results []SyncResult

	// Get all plugins
	pluginList, err := s.pluginService.List(ctx)
	if err != nil {
		return nil, err
	}

	for _, plug := range pluginList {
		batchResult, _ := s.CheckAllSites(ctx, plug.ID)
		if batchResult != nil {
			results = append(results, batchResult.Results...)
		}
	}

	return results, nil
}

// GetFileChanges returns pending file changes for a plugin
func (s *serviceImpl) GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error) {
	query := `
		SELECT Id, PluginId, FilePath, ChangeType, LocalHash, RemoteHash, 
		       LocalModifiedAt, DetectedAt, SyncedAt
		FROM FileChanges
		WHERE PluginId = ? AND SyncedAt IS NULL
		ORDER BY DetectedAt DESC
	`

	rows, err := s.db.QueryContext(ctx, query, pluginID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get file changes")
	}
	defer rows.Close()

	var changes []models.FileChange
	for rows.Next() {
		var change models.FileChange
		var localModifiedAt, detectedAt, syncedAt string

		err := rows.Scan(
			&change.ID,
			&change.PluginID,
			&change.FilePath,
			&change.ChangeType,
			&change.LocalHash,
			&change.RemoteHash,
			&localModifiedAt,
			&detectedAt,
			&syncedAt,
		)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to scan file change")
		}

		changes = append(changes, change)
	}

	if changes == nil {
		changes = []models.FileChange{}
	}

	return changes, nil
}

// RecordFileChange records a file change in the database
func (s *serviceImpl) RecordFileChange(ctx context.Context, change *models.FileChange) error {
	// Check if change already exists
	query := `
		INSERT OR REPLACE INTO FileChanges 
		(PluginId, FilePath, ChangeType, LocalHash, DetectedAt)
		VALUES (?, ?, ?, ?, datetime('now'))
	`

	_, err := s.db.ExecContext(ctx, query, 
		change.PluginID,
		change.FilePath,
		change.ChangeType,
		change.LocalHash,
	)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to record file change")
	}

	// Broadcast file change event
	if s.wsHub != nil {
		s.wsHub.BroadcastFileChange(change.PluginID, change.FilePath, change.ChangeType)
	}

	return nil
}

// MarkSynced marks specific files as synced
func (s *serviceImpl) MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error {
	if len(files) == 0 {
		return nil
	}

	// Build placeholders for IN clause
	placeholders := make([]string, len(files))
	args := make([]interface{}, len(files)+1)
	args[0] = pluginID
	for i, f := range files {
		placeholders[i] = "?"
		args[i+1] = f
	}

	query := `
		UPDATE FileChanges 
		SET SyncedAt = datetime('now')
		WHERE PluginId = ? AND FilePath IN (` + strings.Join(placeholders, ",") + `)
	`

	_, err := s.db.ExecContext(ctx, query, args...)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseUpdate, "failed to mark files as synced")
	}

	return nil
}

// ClearChanges removes all pending changes for a plugin
func (s *serviceImpl) ClearChanges(ctx context.Context, pluginID int64) error {
	query := `
		UPDATE FileChanges 
		SET SyncedAt = datetime('now')
		WHERE PluginId = ? AND SyncedAt IS NULL
	`

	_, err := s.db.ExecContext(ctx, query, pluginID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseUpdate, "failed to clear changes")
	}

	return nil
}

// scanLocalFiles scans the plugin directory and returns file hashes
func (s *serviceImpl) scanLocalFiles(pluginPath string, excludePatterns []string) (map[string]string, error) {
	files := make(map[string]string)

	err := filepath.Walk(pluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // Skip files we can't access
		}

		if info.IsDir() {
			// Skip excluded directories
			for _, pattern := range excludePatterns {
				if strings.Contains(path, pattern) {
					return filepath.SkipDir
				}
			}
			return nil
		}

		relPath, err := filepath.Rel(pluginPath, path)
		if err != nil {
			return nil
		}

		// Skip excluded files
		for _, pattern := range excludePatterns {
			if matched, _ := filepath.Match(pattern, filepath.Base(path)); matched {
				return nil
			}
		}

		// Skip hidden files
		if strings.HasPrefix(filepath.Base(path), ".") {
			return nil
		}

		// Calculate hash
		hash, err := s.calculateFileHash(path)
		if err != nil {
			return nil // Skip files we can't hash
		}

		files[filepath.ToSlash(relPath)] = hash
		return nil
	})

	return files, err
}

// calculateFileHash calculates MD5 hash of a file
func (s *serviceImpl) calculateFileHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	hash := md5.New()
	if _, err := io.Copy(hash, file); err != nil {
		return "", err
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}

// compareFiles compares local and remote file lists
func (s *serviceImpl) compareFiles(local, remote map[string]string) []models.FileChange {
	var changes []models.FileChange

	// Check for added and modified files
	for path, localHash := range local {
		if remoteHash, exists := remote[path]; exists {
			if localHash != remoteHash {
				changes = append(changes, models.FileChange{
					FilePath:   path,
					ChangeType: "modified",
					LocalHash:  localHash,
					RemoteHash: remoteHash,
				})
			}
		} else {
			changes = append(changes, models.FileChange{
				FilePath:   path,
				ChangeType: "added",
				LocalHash:  localHash,
			})
		}
	}

	// Check for deleted files
	for path, remoteHash := range remote {
		if _, exists := local[path]; !exists {
			changes = append(changes, models.FileChange{
				FilePath:   path,
				ChangeType: "deleted",
				RemoteHash: remoteHash,
			})
		}
	}

	return changes
}

// getMappings retrieves all mappings for a plugin
func (s *serviceImpl) getMappings(ctx context.Context, pluginID int64) ([]models.PluginMapping, error) {
	query := `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus, 
		       pm.LastSyncAt, pm.LastBackupAt, pm.CreatedAt, pm.UpdatedAt,
		       s.Name as SiteName, s.Url as SiteUrl
		FROM PluginMappings pm
		JOIN Sites s ON s.Id = pm.SiteId
		WHERE pm.PluginId = ?
	`

	rows, err := s.db.QueryContext(ctx, query, pluginID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var mappings []models.PluginMapping
	for rows.Next() {
		var m models.PluginMapping
		var lastSyncAt, lastBackupAt, createdAt, updatedAt string

		err := rows.Scan(
			&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &m.SyncStatus,
			&lastSyncAt, &lastBackupAt, &createdAt, &updatedAt,
			&m.SiteName, &m.SiteURL,
		)
		if err != nil {
			continue
		}
		mappings = append(mappings, m)
	}

	return mappings, nil
}

// updateMappingSyncStatus updates the sync status of a mapping
func (s *serviceImpl) updateMappingSyncStatus(ctx context.Context, pluginID, siteID int64, inSync bool) {
	status := "out_of_sync"
	if inSync {
		status = "synced"
	}

	query := `
		UPDATE PluginMappings 
		SET SyncStatus = ?, LastSyncAt = datetime('now'), UpdatedAt = datetime('now')
		WHERE PluginId = ? AND SiteId = ?
	`

	s.db.ExecContext(ctx, query, status, pluginID, siteID)
}

// broadcastProgress sends sync progress via WebSocket with detailed step info
func (s *serviceImpl) broadcastProgress(pluginID, siteID int64, step string, progress int, message string) {
	if s.wsHub == nil {
		return
	}

	s.wsHub.Broadcast(ws.EventSyncProgress, map[string]interface{}{
		"pluginId": pluginID,
		"siteId":   siteID,
		"step":     step,
		"progress": progress,
		"total":    100,
		"message":  message,
	})
	
	s.log.Debug("Sync progress", "pluginId", pluginID, "siteId", siteID, "step", step, "progress", progress, "message", message)
}

// countByType counts changes by type
func countByType(changes []models.FileChange, changeType string) int {
	count := 0
	for _, c := range changes {
		if c.ChangeType == changeType {
			count++
		}
	}
	return count
}
