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
	"wp-plugin-publish/internal/enums/change_type"
	"wp-plugin-publish/internal/enums/sync_direction"
	"wp-plugin-publish/internal/enums/sync_step"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/dbutil"
	"wp-plugin-publish/pkg/pathutil"
)

// PushSyncResult represents the result of a sync push operation
type PushSyncResult struct {
	PluginID     int64
	SiteID       int64
	FilesUpdated int
	FilesDeleted int
	FilesIgnored int
	TotalChanges int
	IsSuccess    bool
	ErrorMessage string `json:",omitempty"`
}

// Service interface for sync operations
type Service interface {
	// Sync checking — Result-wrapped returns
	CheckSync(ctx context.Context, pluginID, siteID int64) apperror.Result[SyncResult]
	CheckAllSites(ctx context.Context, pluginID int64) apperror.Result[BatchSyncResult]
	CheckAllPlugins(ctx context.Context) apperror.ResultSlice[SyncResult]

	// Sync push — Result-wrapped return
	PushSync(ctx context.Context, pluginID, siteID int64) apperror.Result[PushSyncResult]

	// File change management — Result-wrapped or plain error
	GetFileChanges(ctx context.Context, pluginID, siteID int64) apperror.ResultSlice[models.FileChange]
	RecordFileChange(ctx context.Context, change *models.FileChange) error
	MarkSynced(ctx context.Context, pluginID, siteID int64, files []string) error
	ClearChanges(ctx context.Context, pluginID int64) error
}

// FileEntry represents a file with its hash, modification time, and size
type FileEntry struct {
	Path       string
	Hash       string
	ModifiedAt time.Time
	Size       int64
}

// SyncResult represents the result of a sync check
type SyncResult struct {
	PluginID     int64
	SiteID       int64
	SiteName     string              `json:",omitempty"`
	IsInSync     bool                `json:"InSync"`
	LocalFiles   int
	RemoteFiles  int
	Added        int
	Modified     int
	Deleted      int
	Changes      []models.FileChange `json:",omitempty"`
	CheckedAt    time.Time
	ErrorMessage string              `json:",omitempty"`
}

// BatchSyncResult represents sync results for multiple sites
type BatchSyncResult struct {
	PluginID   int64
	PluginName string
	Results    []SyncResult
	TotalSites int
	IsInSync   int                  `json:"InSync"`
	OutOfSync  int
	Errors     int
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
	dbu                   *dbutil.DB
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
		dbu:                   dbutil.New(cfg.DB.DB),
		log:                   cfg.Logger,
		pluginService:         cfg.PluginService,
		sitePasswordDecryptor: cfg.SitePasswordDecryptor,
		wpClientFactory:       cfg.WPClientFactory,
		wsHub:                 cfg.WSHub,
	}
}

// CheckSync compares local vs remote files for a specific plugin-site mapping
func (s *serviceImpl) CheckSync(ctx context.Context, pluginID, siteID int64) apperror.Result[SyncResult] {
	result := SyncResult{
		PluginID:  pluginID,
		SiteID:    siteID,
		CheckedAt: time.Now(),
	}

	// Broadcast start
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Checking.Value(), Progress: 0, Message: "Starting sync check..."})

	// Get plugin info
	plugResult := s.pluginService.GetByID(ctx, pluginID)
	if plugResult.HasError() {
		result.ErrorMessage = plugResult.AppError().Error()
		return apperror.Ok(result)
	}
	plug := plugResult.Value()

	// Scan local files
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Scanning.Value(), Progress: 20, Message: "Scanning local files..."})
	localFiles, err := s.scanLocalFiles(plug.Path, plug.ExcludePatterns)
	if err != nil {
		result.ErrorMessage = err.Error()
		return apperror.Ok(result)
	}
	result.LocalFiles = len(localFiles)

	// Get mapping to find remote slug
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		result.ErrorMessage = "No site mapping found: " + err.Error()
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Error.Value(), Progress: 100, Message: result.ErrorMessage})
		return apperror.Ok(result)
	}

	// Get site info and decrypt credentials
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 40, Message: "Retrieving site credentials..."})
	siteInfo, err := s.getSiteInfo(ctx, siteID)
	if err != nil {
		result.ErrorMessage = "Failed to get site info: " + err.Error()
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Error.Value(), Progress: 100, Message: result.ErrorMessage})
		return apperror.Ok(result)
	}

	password, err := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if err != nil {
		result.ErrorMessage = "Failed to decrypt credentials: " + err.Error()
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Error.Value(), Progress: 100, Message: result.ErrorMessage})
		return apperror.Ok(result)
	}

	// Fetch remote file manifest via WordPress sync-manifest endpoint
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 50, Message: "Fetching remote file manifest..."})
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	remoteManifest, err := wpClient.GetPluginSyncManifest(ctx, mapping.RemoteSlug)

	remoteFiles := make(map[string]FileEntry)
	if err != nil {
		// Log warning but continue with empty remote (graceful degradation)
		s.log.Warn("Failed to fetch remote sync manifest, comparing local only",
			"pluginId", pluginID, "siteId", siteID, "slug", mapping.RemoteSlug, "error", err)
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 60, Message: "Remote manifest unavailable, comparing local only..."})
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
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 70, Message: "Comparing files..."})
	changes := s.compareFiles(localFiles, remoteFiles)
	
	result.Changes = changes
	result.Added = countByType(changes, changetype.Added.Value())
	result.Modified = countByType(changes, changetype.Modified.Value())
	result.Deleted = countByType(changes, changetype.Deleted.Value())
	result.IsInSync = len(changes) == 0

	// Update mapping sync status
	s.updateMappingSyncStatus(ctx, pluginID, siteID, result.IsInSync)

	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "Sync check complete"})

	s.log.Info("Sync check completed",
		"pluginId", pluginID,
		"siteId", siteID,
		"isInSync", result.IsInSync,
		"changes", len(changes))

	return apperror.Ok(result)
}

// CheckAllSites checks sync status for all sites mapped to a plugin
func (s *serviceImpl) CheckAllSites(ctx context.Context, pluginID int64) apperror.Result[BatchSyncResult] {
	result := BatchSyncResult{
		PluginID: pluginID,
		Results:  []SyncResult{},
	}

	// Get plugin info
	plugResult := s.pluginService.GetByID(ctx, pluginID)
	if plugResult.HasError() {
		return apperror.Fail[BatchSyncResult](plugResult.AppError())
	}
	plug := plugResult.Value()
	result.PluginName = plug.Name

	// Get all mappings for this plugin
	mappings, err := s.getMappings(ctx, pluginID)
	if err != nil {
		return apperror.FailWrap[BatchSyncResult](err, apperror.ErrDBRead, "failed to get mappings")
	}
	result.TotalSites = len(mappings)

	// Check each site
	for _, mapping := range mappings {
		syncResult := s.CheckSync(ctx, pluginID, mapping.SiteID)
		if syncResult.IsSafe() {
			sr := syncResult.Value()
			sr.SiteName = mapping.SiteName
			result.Results = append(result.Results, sr)

			if sr.ErrorMessage != "" {
				result.Errors++
			} else if sr.IsInSync {
				result.IsInSync++
			} else {
				result.OutOfSync++
			}
		}
	}

	return apperror.Ok(result)
}

// CheckAllPlugins checks sync status for all registered plugins
func (s *serviceImpl) CheckAllPlugins(ctx context.Context) apperror.ResultSlice[SyncResult] {
	var results []SyncResult

	// Get all plugins
	pluginListResult := s.pluginService.List(ctx)
	if pluginListResult.HasError() {
		return apperror.FailSlice[SyncResult](pluginListResult.AppError())
	}

	pluginList := pluginListResult.Items()

	for _, plug := range pluginList {
		batchResult := s.CheckAllSites(ctx, plug.ID)
		if batchResult.IsSafe() {
			results = append(results, batchResult.Value().Results...)
		}
	}

	if results == nil {
		results = []SyncResult{}
	}
	return apperror.OkSlice(results)
}

// PushSync performs a full comparison and pushes all changes (including deletions) to the remote site
func (s *serviceImpl) PushSync(ctx context.Context, pluginID, siteID int64) apperror.Result[PushSyncResult] {
	result := PushSyncResult{
		PluginID: pluginID,
		SiteID:   siteID,
	}

	// 1. Run comparison to get changes
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Checking.Value(), Progress: 0, Message: "Running sync comparison..."})
	syncResult := s.CheckSync(ctx, pluginID, siteID)
	if syncResult.HasError() {
		return apperror.FailWrap[PushSyncResult](syncResult.AppError(), apperror.ErrInternal, "sync comparison failed")
	}
	sr := syncResult.Value()
	if sr.ErrorMessage != "" {
		result.ErrorMessage = sr.ErrorMessage
		return apperror.Ok(result)
	}

	if sr.IsInSync {
		result.IsSuccess = true
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "Already in sync, nothing to push"})
		return apperror.Ok(result)
	}

	// 2. Get plugin info for reading local files
	plugResult := s.pluginService.GetByID(ctx, pluginID)
	if plugResult.HasError() {
		return apperror.FailWrap[PushSyncResult](plugResult.AppError(), apperror.ErrDatabaseQuery, "failed to get plugin")
	}
	plug := plugResult.Value()

	// 3. Get mapping for remote slug
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return apperror.FailWrap[PushSyncResult](err, apperror.ErrDatabaseQuery, "failed to get mapping")
	}

	// 4. Get site info and credentials
	siteInfo, err := s.getSiteInfo(ctx, siteID)
	if err != nil {
		return apperror.FailWrap[PushSyncResult](err, apperror.ErrDatabaseQuery, "failed to get site info")
	}
	password, err := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if err != nil {
		return apperror.FailWrap[PushSyncResult](err, apperror.ErrInternal, "failed to decrypt credentials")
	}

	// 5. Build SyncFile array from changes
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Packaging.Value(), Progress: 40, Message: "Packaging file changes..."})
	var syncFiles []wordpress.SyncFile

	absPluginPath, err := pathutil.ToAbsolute(plug.Path)
	if err != nil {
		return apperror.FailWrap[PushSyncResult](err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	for _, change := range sr.Changes {
		switch change.ChangeType {
		case changetype.Added.Value(), changetype.Modified.Value():
			// Only push local-newer or local-only files
			if change.Direction == syncdirection.RemoteNewer.Value() {
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

		case changetype.Deleted.Value():
			// File exists on remote but not locally → send delete
			syncFiles = append(syncFiles, wordpress.SyncFile{
				Path:   change.FilePath,
				Action: "delete",
			})
		}
	}

	if len(syncFiles) == 0 {
		result.IsSuccess = true
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "No pushable changes found"})
		return apperror.Ok(result)
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
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Pushing.Value(), Progress: 60,
		Message: fmt.Sprintf("Pushing %d files to remote...", len(syncFiles))})

	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	syncPushResult, err := wpClient.SyncPluginFilesViaUploader(mapping.RemoteSlug, syncFiles)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Error.Value(), Progress: 100, Message: "Sync push failed: " + err.Error()})
		return apperror.Ok(result)
	}

	result.FilesUpdated = syncPushResult.FilesUpdated
	result.FilesDeleted = syncPushResult.FilesDeleted
	result.FilesIgnored = syncPushResult.FilesIgnored
	result.IsSuccess = syncPushResult.IsSuccess

	// 7. Update sync status
	if result.IsSuccess {
		s.updateMappingSyncStatus(ctx, pluginID, siteID, true)
	}

	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100,
		Message: fmt.Sprintf("Sync complete: %d updated, %d deleted, %d ignored",
			result.FilesUpdated, result.FilesDeleted, result.FilesIgnored)})

	s.log.Info("Sync push completed",
		"plugin", plug.Name,
		"pluginId", pluginID,
		"site", mapping.SiteName,
		"siteId", siteID,
		"updated", result.FilesUpdated,
		"deleted", result.FilesDeleted,
		"ignored", result.FilesIgnored,
	)

	return apperror.Ok(result)
}

// GetFileChanges, RecordFileChange, MarkSynced, ClearChanges moved to crud.go.

// scanLocalFiles scans the plugin directory and returns file entries with hashes and timestamps
func (s *serviceImpl) scanLocalFiles(pluginPath string, excludePatterns []string) (map[string]FileEntry, error) {
	files := make(map[string]FileEntry)

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path").
			WithPath(pluginPath)
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
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to open file for hashing").
			WithFilePath(path)
	}
	defer file.Close()

	hash := md5.New()
	if _, err := io.Copy(hash, file); err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to read file for hashing").
			WithFilePath(path)
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
			direction := syncdirection.LocalNewer.Value()
				if remoteMod.After(localMod) {
					direction = syncdirection.RemoteNewer.Value()
				}
				changes = append(changes, models.FileChange{
					FilePath:         path,
					ChangeType:       changetype.Modified.Value(),
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
				ChangeType:      changetype.Added.Value(),
				LocalHash:       localEntry.Hash,
				LocalModifiedAt: &localMod,
				LocalSize:       localEntry.Size,
				Direction:       syncdirection.LocalOnly.Value(),
			})
		}
	}

	for path, remoteEntry := range remote {
		if _, exists := local[path]; !exists {
			remoteMod := remoteEntry.ModifiedAt
			changes = append(changes, models.FileChange{
				FilePath:         path,
				ChangeType:       changetype.Deleted.Value(),
				RemoteHash:       remoteEntry.Hash,
				RemoteModifiedAt: &remoteMod,
				RemoteSize:       remoteEntry.Size,
				Direction:        syncdirection.RemoteOnly.Value(),
			})
		}
	}

	return changes
}

// getMappings, getSiteInfo, getMapping, updateMappingSyncStatus moved to crud.go.

// SyncProgressInput bundles parameters for broadcastProgress.
type SyncProgressInput struct {
	PluginID int64
	SiteID   int64
	Step     string
	Progress int
	Message  string
}

// broadcastProgress sends sync progress via WebSocket with detailed step info
func (s *serviceImpl) broadcastProgress(input SyncProgressInput) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventSyncProgress, ws.SyncStepProgressData{
		PluginID: input.PluginID,
		SiteID:   input.SiteID,
		Step:     input.Step,
		Progress: input.Progress,
		Total:    100,
		Message:  input.Message,
	})
	
	// Also broadcast detailed log entry for frontend live log display
	s.wsHub.BroadcastSyncLog(ws.OperationLogInput{
		PluginID: input.PluginID, SiteID: input.SiteID,
		Entry: ws.OperationLogEntry{Level: "info", Step: input.Step, Message: input.Message},
	})
	
	s.log.Debug("Sync progress", "pluginId", input.PluginID, "siteId", input.SiteID, "step", input.Step, "progress", input.Progress, "message", input.Message)
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
