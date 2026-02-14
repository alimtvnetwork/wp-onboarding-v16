// Package sync provides local-remote file synchronization
package sync

import (
	"context"
	"crypto/md5"
	"encoding/base64"
	"encoding/hex"
	"fmt"
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
	"wp-plugin-publish/pkg/pathutil"
)

// PushSyncResult represents the result of a sync push operation
type PushSyncResult struct {
	PluginID     int64  `json:"pluginId"`
	SiteID       int64  `json:"siteId"`
	FilesUpdated int    `json:"filesUpdated"`
	FilesDeleted int    `json:"filesDeleted"`
	FilesIgnored int    `json:"filesIgnored"`
	TotalChanges int    `json:"totalChanges"`
	Success      bool   `json:"success"`
	ErrorMessage string `json:"errorMessage,omitempty"`
}

// Service interface for sync operations
type Service interface {
	// Sync checking
	CheckSync(ctx context.Context, pluginID, siteID int64) (*SyncResult, error)
	CheckAllSites(ctx context.Context, pluginID int64) (*BatchSyncResult, error)
	CheckAllPlugins(ctx context.Context) ([]SyncResult, error)

	// Sync push (applies changes including deletions to remote)
	PushSync(ctx context.Context, pluginID, siteID int64) (*PushSyncResult, error)

	// File change management
	GetFileChanges(ctx context.Context, pluginID, siteID int64) ([]models.FileChange, error)
	RecordFileChange(ctx context.Context, change *models.FileChange) error
	MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error
	ClearChanges(ctx context.Context, pluginID int64) error
}

// FileEntry represents a file with its hash, modification time, and size
type FileEntry struct {
	Path       string    `json:"path"`
	Hash       string    `json:"hash"`
	ModifiedAt time.Time `json:"modifiedAt"`
	Size       int64     `json:"size"`
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

// SitePasswordDecryptor interface for getting decrypted site passwords
type SitePasswordDecryptor interface {
	GetDecryptedPassword(ctx context.Context, siteID int64) (string, error)
}

// Config holds sync service configuration
type Config struct {
	DB                    *database.DB
	Logger                *logger.Logger
	PluginService         *plugin.Service
	SitePasswordDecryptor SitePasswordDecryptor
	WPClientFactory       func(url, user, pass string) *wordpress.Client
	WSHub                 *ws.Hub
}

type serviceImpl struct {
	db                    *database.DB
	log                   *logger.Logger
	pluginService         *plugin.Service
	sitePasswordDecryptor SitePasswordDecryptor
	wpClientFactory       func(url, user, pass string) *wordpress.Client
	wsHub                 *ws.Hub
}

// New creates a new sync service
func New(cfg Config) Service {
	return &serviceImpl{
		db:                    cfg.DB,
		log:                   cfg.Logger,
		pluginService:         cfg.PluginService,
		sitePasswordDecryptor: cfg.SitePasswordDecryptor,
		wpClientFactory:       cfg.WPClientFactory,
		wsHub:                 cfg.WSHub,
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

	// Get mapping to find remote slug
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		result.ErrorMessage = "No site mapping found: " + err.Error()
		s.broadcastProgress(pluginID, siteID, "error", 100, result.ErrorMessage)
		return result, nil
	}

	// Get site info and decrypt credentials
	s.broadcastProgress(pluginID, siteID, "comparing", 40, "Retrieving site credentials...")
	siteInfo, err := s.getSiteInfo(ctx, siteID)
	if err != nil {
		result.ErrorMessage = "Failed to get site info: " + err.Error()
		s.broadcastProgress(pluginID, siteID, "error", 100, result.ErrorMessage)
		return result, nil
	}

	password, err := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if err != nil {
		result.ErrorMessage = "Failed to decrypt credentials: " + err.Error()
		s.broadcastProgress(pluginID, siteID, "error", 100, result.ErrorMessage)
		return result, nil
	}

	// Fetch remote file manifest via WordPress sync-manifest endpoint
	s.broadcastProgress(pluginID, siteID, "comparing", 50, "Fetching remote file manifest...")
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	remoteManifest, err := wpClient.GetPluginSyncManifest(ctx, mapping.RemoteSlug)

	remoteFiles := make(map[string]FileEntry)
	if err != nil {
		// Log warning but continue with empty remote (graceful degradation)
		s.log.Warn("Failed to fetch remote sync manifest, comparing local only",
			"pluginId", pluginID, "siteId", siteID, "slug", mapping.RemoteSlug, "error", err)
		s.broadcastProgress(pluginID, siteID, "comparing", 60, "Remote manifest unavailable, comparing local only...")
	} else {
		for _, rf := range remoteManifest {
			remoteFiles[rf.Path] = FileEntry{
				Path:       rf.Path,
				Hash:       rf.Hash,
				ModifiedAt: rf.ModifiedAt,
				Size:       rf.Size,
			}
		}
	}
	result.RemoteFiles = len(remoteFiles)

	// Compare files using enhanced comparison with timestamps
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

// PushSync performs a full comparison and pushes all changes (including deletions) to the remote site
func (s *serviceImpl) PushSync(ctx context.Context, pluginID, siteID int64) (*PushSyncResult, error) {
	result := &PushSyncResult{
		PluginID: pluginID,
		SiteID:   siteID,
	}

	// 1. Run comparison to get changes
	s.broadcastProgress(pluginID, siteID, "checking", 0, "Running sync comparison...")
	syncResult, err := s.CheckSync(ctx, pluginID, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "sync comparison failed")
	}
	if syncResult.ErrorMessage != "" {
		result.ErrorMessage = syncResult.ErrorMessage
		return result, nil
	}

	if syncResult.InSync {
		result.Success = true
		s.broadcastProgress(pluginID, siteID, "complete", 100, "Already in sync, nothing to push")
		return result, nil
	}

	// 2. Get plugin info for reading local files
	plug, err := s.pluginService.GetByID(ctx, pluginID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin")
	}

	// 3. Get mapping for remote slug
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get mapping")
	}

	// 4. Get site info and credentials
	siteInfo, err := s.getSiteInfo(ctx, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site info")
	}
	password, err := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt credentials")
	}

	// 5. Build SyncFile array from changes
	s.broadcastProgress(pluginID, siteID, "packaging", 40, "Packaging file changes...")
	var syncFiles []wordpress.SyncFile

	absPluginPath, err := pathutil.ToAbsolute(plug.Path)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	for _, change := range syncResult.Changes {
		switch change.ChangeType {
		case "added", "modified":
			// Only push local-newer or local-only files
			if change.Direction == "remote_newer" {
				continue // skip remote-newer files (pull, not push)
			}
			localPath := filepath.Join(absPluginPath, filepath.FromSlash(change.FilePath))
			content, readErr := os.ReadFile(localPath)
			if readErr != nil {
				s.log.Warn("Failed to read file for sync, skipping",
					"path", change.FilePath, "error", readErr)
				continue
			}
			syncFiles = append(syncFiles, wordpress.SyncFile{
				Path:    change.FilePath,
				Content: base64.StdEncoding.EncodeToString(content),
				Action:  "replace",
			})

		case "deleted":
			// File exists on remote but not locally → send delete
			syncFiles = append(syncFiles, wordpress.SyncFile{
				Path:   change.FilePath,
				Action: "delete",
			})
		}
	}

	if len(syncFiles) == 0 {
		result.Success = true
		s.broadcastProgress(pluginID, siteID, "complete", 100, "No pushable changes found")
		return result, nil
	}

	result.TotalChanges = len(syncFiles)
	s.log.Info("Pushing sync changes",
		"plugin", plug.Name,
		"pluginId", pluginID,
		"site", mapping.SiteName,
		"siteId", siteID,
		"totalFiles", len(syncFiles),
	)

	// 6. Push to remote via WordPress client
	s.broadcastProgress(pluginID, siteID, "pushing", 60,
		fmt.Sprintf("Pushing %d files to remote...", len(syncFiles)))

	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	syncPushResult, err := wpClient.SyncPluginFilesViaUploader(mapping.RemoteSlug, syncFiles)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(pluginID, siteID, "error", 100, "Sync push failed: "+err.Error())
		return result, nil
	}

	result.FilesUpdated = syncPushResult.FilesUpdated
	result.FilesDeleted = syncPushResult.FilesDeleted
	result.FilesIgnored = syncPushResult.FilesIgnored
	result.Success = syncPushResult.Success

	// 7. Update sync status
	if result.Success {
		s.updateMappingSyncStatus(ctx, pluginID, siteID, true)
	}

	s.broadcastProgress(pluginID, siteID, "complete", 100,
		fmt.Sprintf("Sync complete: %d updated, %d deleted, %d ignored",
			result.FilesUpdated, result.FilesDeleted, result.FilesIgnored))

	s.log.Info("Sync push completed",
		"plugin", plug.Name,
		"pluginId", pluginID,
		"site", mapping.SiteName,
		"siteId", siteID,
		"updated", result.FilesUpdated,
		"deleted", result.FilesDeleted,
		"ignored", result.FilesIgnored,
	)

	return result, nil
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

// scanLocalFiles scans the plugin directory and returns file entries with hashes and timestamps
func (s *serviceImpl) scanLocalFiles(pluginPath string, excludePatterns []string) (map[string]FileEntry, error) {
	files := make(map[string]FileEntry)

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path").
			WithContext("path", pluginPath)
	}

	err = filepath.Walk(absPluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // Skip files we can't access
		}

		if info.IsDir() {
			for _, pattern := range excludePatterns {
				if strings.Contains(path, pattern) {
					return filepath.SkipDir
				}
			}
			return nil
		}

		relPath, err := filepath.Rel(absPluginPath, path)
		if err != nil {
			return nil
		}

		for _, pattern := range excludePatterns {
			if matched, _ := filepath.Match(pattern, filepath.Base(path)); matched {
				return nil
			}
		}

		if strings.HasPrefix(filepath.Base(path), ".") {
			return nil
		}

		hash, err := s.calculateFileHash(path)
		if err != nil {
			return nil
		}

		key := filepath.ToSlash(relPath)
		files[key] = FileEntry{
			Path:       key,
			Hash:       hash,
			ModifiedAt: info.ModTime().UTC(),
			Size:       info.Size(),
		}
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

// compareFiles compares local and remote file entries with timestamp-based conflict resolution
func (s *serviceImpl) compareFiles(local, remote map[string]FileEntry) []models.FileChange {
	var changes []models.FileChange

	for path, localEntry := range local {
		localMod := localEntry.ModifiedAt
		if remoteEntry, exists := remote[path]; exists {
			if localEntry.Hash != remoteEntry.Hash {
				remoteMod := remoteEntry.ModifiedAt
				direction := "local_newer"
				if remoteMod.After(localMod) {
					direction = "remote_newer"
				}
				changes = append(changes, models.FileChange{
					FilePath:         path,
					ChangeType:       "modified",
					LocalHash:        localEntry.Hash,
					RemoteHash:       remoteEntry.Hash,
					LocalModifiedAt:  &localMod,
					RemoteModifiedAt: &remoteMod,
					LocalSize:        localEntry.Size,
					RemoteSize:       remoteEntry.Size,
					Direction:        direction,
				})
			}
		} else {
			changes = append(changes, models.FileChange{
				FilePath:        path,
				ChangeType:      "added",
				LocalHash:       localEntry.Hash,
				LocalModifiedAt: &localMod,
				LocalSize:       localEntry.Size,
				Direction:       "local_only",
			})
		}
	}

	for path, remoteEntry := range remote {
		if _, exists := local[path]; !exists {
			remoteMod := remoteEntry.ModifiedAt
			changes = append(changes, models.FileChange{
				FilePath:         path,
				ChangeType:       "deleted",
				RemoteHash:       remoteEntry.Hash,
				RemoteModifiedAt: &remoteMod,
				RemoteSize:       remoteEntry.Size,
				Direction:        "remote_only",
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

// siteInfo holds minimal site data needed for sync
type siteInfo struct {
	URL      string
	Username string
}

// getSiteInfo retrieves minimal site info for creating a WP client
func (s *serviceImpl) getSiteInfo(ctx context.Context, siteID int64) (*siteInfo, error) {
	query := `SELECT Url, Username FROM Sites WHERE Id = ?`
	row := s.db.QueryRowContext(ctx, query, siteID)

	var info siteInfo
	if err := row.Scan(&info.URL, &info.Username); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get site info").
			WithContext("siteId", siteID)
	}
	return &info, nil
}

// getMapping retrieves a specific plugin-site mapping
func (s *serviceImpl) getMapping(ctx context.Context, pluginID, siteID int64) (*models.PluginMapping, error) {
	query := `
		SELECT pm.Id, pm.PluginId, pm.SiteId, pm.RemoteSlug, pm.SyncStatus,
		       s.Name as SiteName, s.Url as SiteUrl
		FROM PluginMappings pm
		JOIN Sites s ON s.Id = pm.SiteId
		WHERE pm.PluginId = ? AND pm.SiteId = ?
	`
	row := s.db.QueryRowContext(ctx, query, pluginID, siteID)

	var m models.PluginMapping
	var syncStatus string
	if err := row.Scan(&m.ID, &m.PluginID, &m.SiteID, &m.RemoteSlug, &syncStatus, &m.SiteName, &m.SiteURL); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get plugin-site mapping").
			WithPluginID(pluginID).WithSiteID(siteID)
	}
	m.SyncStatus = syncStatus
	return &m, nil
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

	ws.Broadcast(s.wsHub, ws.EventSyncProgress, ws.SyncStepProgressData{
		PluginID: pluginID,
		SiteID:   siteID,
		Step:     step,
		Progress: progress,
		Total:    100,
		Message:  message,
	})
	
	// Also broadcast detailed log entry for frontend live log display
	s.wsHub.BroadcastSyncLog(pluginID, siteID, "info", step, message, nil)
	
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
