// Package watcher provides file system scanning for plugin directories (hybrid mode)
package watcher

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/enums/scantriggertype"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// FileChange represents a detected file modification
type FileChange struct {
	Path       string    `json:",omitempty"`
	ChangeType string    // created, modified, deleted
	Hash       string    `json:",omitempty"`
	Size       int64     `json:",omitempty"`
	ModTime    time.Time `json:",omitempty"`
}

// ScanResult contains the outcome of a directory scan
type ScanResult struct {
	PluginID     int64
	Path         string
	ScanTime     time.Time
	DurationMs   int64
	FilesScanned int
	Changes      []FileChange `json:",omitempty"`
	TriggerType  string       // "git_pull" or "manual"
}

// fileInfo holds cached file metadata for change detection
type fileInfo struct {
	ModTime int64
	Size    int64
	Hash    string
}

// pluginScanCache stores the last known state of a plugin's files
type pluginScanCache struct {
	pluginID int64
	path     string
	excludes []string
	lastScan map[string]fileInfo
}

// PublishService interface for triggering auto-publish
type PublishService interface {
	PublishPlugin(ctx context.Context, pluginID, siteID int64, mode string, isCreateBackup bool) (int, error)
}

// Config holds watcher configuration
type Config struct {
	DB             *database.DB
	Logger         *logger.Logger
	PluginService  *plugin.Service
	WSHub          *ws.Hub
	PublishService PublishService // Optional: for auto-publish functionality
}

// Service provides file scanning operations (hybrid mode - no polling)
type Service struct {
	db             *database.DB
	log            *logger.Logger
	pluginService  *plugin.Service
	wsHub          *ws.Hub
	publishService PublishService
	cache          map[int64]*pluginScanCache
	mu             sync.RWMutex
}

// New creates a new watcher service (no polling goroutines)
func New(cfg Config) *Service {
	return &Service{
		db:             cfg.DB,
		log:            cfg.Logger,
		pluginService:  cfg.PluginService,
		wsHub:          cfg.WSHub,
		publishService: cfg.PublishService,
		cache:          make(map[int64]*pluginScanCache),
	}
}

// SetPublishService sets the publish service for auto-publish (called after initialization)
func (s *Service) SetPublishService(ps PublishService) {
	s.publishService = ps
}

// InitializeCache loads the current file state for a plugin
func (s *Service) InitializeCache(ctx context.Context, pluginID int64) error {
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return pResult.AppError()
	}
	p := pResult.Value()

	s.mu.Lock()
	defer s.mu.Unlock()

	cache := &pluginScanCache{
		pluginID: pluginID,
		path:     p.Path,
		excludes: p.ExcludePatterns,
		lastScan: make(map[string]fileInfo),
	}

	// Perform initial scan to populate cache (no change detection)
	s.populateCache(cache)
	s.cache[pluginID] = cache

	s.log.Info("Initialized file cache", "plugin", p.Name, "pluginId", pluginID, "files", len(cache.lastScan))
	return nil
}

// TriggerScan performs a manual scan (user clicked refresh)
func (s *Service) TriggerScan(ctx context.Context, pluginID int64) apperror.Result[ScanResult] {
	return s.performScan(ctx, pluginID, scantriggertype.Manual.Value())
}

// ScanAfterGitPull performs a scan after git pull (automatic)
func (s *Service) ScanAfterGitPull(ctx context.Context, pluginID int64) apperror.Result[ScanResult] {
	return s.performScan(ctx, pluginID, scantriggertype.GitPull.Value())
}

// ScanAll scans all cached plugins
func (s *Service) ScanAll(ctx context.Context) apperror.ResultSlice[ScanResult] {
	s.mu.RLock()
	pluginIDs := make([]int64, 0, len(s.cache))
	for id := range s.cache {
		pluginIDs = append(pluginIDs, id)
	}
	s.mu.RUnlock()

	var results []ScanResult
	for _, id := range pluginIDs {
		result := s.TriggerScan(ctx, id)
		if result.IsSafe() {
			results = append(results, result.Value())
		}
	}
	return apperror.OkSlice(results)
}

// ClearCache removes a plugin from the cache
func (s *Service) ClearCache(pluginID int64) {
	s.mu.Lock()
	defer s.mu.Unlock()
	delete(s.cache, pluginID)
	s.log.Info("Cleared file cache", "pluginId", pluginID) // pluginID only — name not available at this call site
}

// GetCachedPlugins returns list of plugins with active cache
func (s *Service) GetCachedPlugins() []int64 {
	s.mu.RLock()
	defer s.mu.RUnlock()

	ids := make([]int64, 0, len(s.cache))
	for id := range s.cache {
		ids = append(ids, id)
	}
	return ids
}

// performScan executes the actual directory scan
func (s *Service) performScan(ctx context.Context, pluginID int64, triggerType string) apperror.Result[ScanResult] {
	startTime := time.Now()

	s.log.Debug("Scanning plugin", "pluginId", pluginID, "trigger", triggerType)

	// Get or create cache
	s.mu.Lock()
	cache, isFound := s.cache[pluginID]
	isNotCached := !isFound

	if isNotCached {
		// Initialize cache first
		s.mu.Unlock()

		err := s.InitializeCache(ctx, pluginID)
		if err != nil {

			return apperror.FailWrap[ScanResult](err, apperror.ErrInternal, "failed to initialize watcher cache")
		}

		s.mu.Lock()
		cache = s.cache[pluginID]
	}
	s.mu.Unlock()

	// Perform scan and detect changes
	changes := s.scanAndCompare(cache)

	result := ScanResult{
		PluginID:     pluginID,
		Path:         cache.path,
		ScanTime:     startTime,
		DurationMs:   time.Since(startTime).Milliseconds(),
		FilesScanned: len(cache.lastScan),
		Changes:      changes,
		TriggerType:  triggerType,
	}

	// Broadcast changes if any
	if len(changes) > 0 {
		s.broadcastChanges(pluginID, changes, triggerType)
		
		// Record changes in database
		for _, c := range changes {
			s.db.ExecContext(ctx, `
				INSERT INTO FileChanges (PluginId, FilePath, ChangeType, LocalHash, DetectedAt)
				VALUES (?, ?, ?, ?, datetime('now'))
			`, pluginID, c.Path, c.ChangeType, c.Hash)
		}

		// Trigger auto-publish if enabled
		go s.triggerAutoPublish(ctx, pluginID, changes)
	}

	s.log.Info("Scan complete", "pluginId", pluginID, "changes", len(changes), "duration", result.DurationMs)
	return apperror.Ok(result)
}

// triggerAutoPublish checks if plugin has autoPublish enabled and publishes to all mapped sites
func (s *Service) triggerAutoPublish(ctx context.Context, pluginID int64, changes []FileChange) {
	// Get plugin to check autoPublish flag
	pResult := s.pluginService.GetByID(ctx, pluginID)
	if pResult.HasError() {
		return
	}
	p := pResult.Value()

	isAutoPublishDisabled := !p.AutoPublish

	if isAutoPublishDisabled {
		return
	}

	hasMappings := len(p.Mappings) > 0
	isMappingsMissing := !hasMappings

	if isMappingsMissing {
		s.log.Debug("Auto-publish skipped: no site mappings", "plugin", p.Name, "pluginId", pluginID)
		return
	}

	s.log.Info("Auto-publish triggered",
		"plugin", p.Name,
		"pluginId", pluginID,
		"changes", len(changes),
		"sites", len(p.Mappings),
	)

	// Notify clients that auto-publish is starting
	ws.Broadcast(s.wsHub, ws.EventAutoPublishTriggered, ws.AutoPublishTriggeredData{
		PluginID:   pluginID,
		PluginName: p.Name,
		Changes:    len(changes),
		Sites:      len(p.Mappings),
	})

	if s.publishService == nil {
		s.log.Warn("Auto-publish: publish service not configured", "plugin", p.Name, "pluginId", pluginID)
		return
	}

	// Publish to all mapped sites
	successCount := 0
	for _, mapping := range p.Mappings {
		filesUpdated, err := s.publishService.PublishPlugin(ctx, pluginID, mapping.SiteID, "full", true)
		if err != nil {
			s.log.Error("Auto-publish failed",
				"plugin", p.Name,
				"site", mapping.SiteName,
				"pluginId", pluginID,
				"siteId", mapping.SiteID,
				"error", err,
			)
			ws.Broadcast(s.wsHub, ws.EventAutoPublishFailed, ws.AutoPublishFailedData{
				PluginID: pluginID,
				SiteID:   mapping.SiteID,
				SiteName: mapping.SiteName,
				Error:    err.Error(),
			})
			continue
		}
		successCount++
		ws.Broadcast(s.wsHub, ws.EventAutoPublishComplete, ws.AutoPublishCompleteData{
			PluginID:     pluginID,
			SiteID:       mapping.SiteID,
			SiteName:     mapping.SiteName,
			FilesUpdated: filesUpdated,
		})
	}

	s.log.Info("Auto-publish complete", "plugin", p.Name, "pluginId", pluginID, "successfulSites", successCount)
}

// populateCache performs initial scan without change detection
func (s *Service) populateCache(cache *pluginScanCache) {
	filepath.Walk(cache.path, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			// Skip excluded directories
			if info != nil && info.IsDir() && s.isExcluded(filepath.Base(path), cache.excludes) {
				return filepath.SkipDir
			}
			return nil
		}

		if s.isExcluded(filepath.Base(path), cache.excludes) {
			return nil
		}

		relPath, _ := filepath.Rel(cache.path, path)
		absPath, _ := pathutil.ToAbsolute(path)
		hash, _ := s.calculateHash(absPath)

		cache.lastScan[relPath] = fileInfo{
			ModTime: info.ModTime().Unix(),
			Size:    info.Size(),
			Hash:    hash,
		}
		return nil
	})
}

// scanAndCompare scans directory and returns detected changes
func (s *Service) scanAndCompare(cache *pluginScanCache) []FileChange {
	var changes []FileChange
	currentFiles := make(map[string]fileInfo)

	// Walk directory
	filepath.Walk(cache.path, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}

		// Skip directories
		if info.IsDir() {
			base := filepath.Base(path)
			if s.isExcluded(base, cache.excludes) {
				return filepath.SkipDir
			}
			return nil
		}

		// Skip excluded files
		base := filepath.Base(path)
		if s.isExcluded(base, cache.excludes) {
			return nil
		}

		relPath, _ := filepath.Rel(cache.path, path)
		absPath, _ := pathutil.ToAbsolute(path)
		hash, _ := s.calculateHash(absPath)

		fi := fileInfo{
			ModTime: info.ModTime().Unix(),
			Size:    info.Size(),
			Hash:    hash,
		}
		currentFiles[relPath] = fi

		// Compare with last scan
		lastInfo, isFound := cache.lastScan[relPath]
		if isFound {
			if lastInfo.Hash != fi.Hash {
				changes = append(changes, FileChange{
					Path:       relPath,
					ChangeType: "modified",
					Hash:       fi.Hash,
					Size:       fi.Size,
					ModTime:    info.ModTime(),
				})
			}
		} else {
			changes = append(changes, FileChange{
				Path:       relPath,
				ChangeType: "created",
				Hash:       fi.Hash,
				Size:       fi.Size,
				ModTime:    info.ModTime(),
			})
		}

		return nil
	})

	// Check for deleted files
	for path := range cache.lastScan {
		_, isFound := currentFiles[path]
		isDeleted := !isFound

		if isDeleted {
			changes = append(changes, FileChange{
				Path:       path,
				ChangeType: "deleted",
			})
		}
	}

	// Update cache
	cache.lastScan = currentFiles

	return changes
}

// broadcastChanges sends file changes via WebSocket
func (s *Service) broadcastChanges(pluginID int64, changes []FileChange, triggerType string) {
	var created, modified, deleted int
	for _, c := range changes {
		switch c.ChangeType {
		case "created":
			created++
		case "modified":
			modified++
		case "deleted":
			deleted++
		}
	}

	// Convert watcher FileChange to ws FileChangeItem
	wsChanges := make([]ws.FileChangeItem, len(changes))
	for i, c := range changes {
		wsChanges[i] = ws.FileChangeItem{
			Path:       c.Path,
			ChangeType: c.ChangeType,
			Hash:       c.Hash,
			Size:       c.Size,
			ModTime:    c.ModTime,
		}
	}
	ws.Broadcast(s.wsHub, ws.EventFileChange, ws.FileChangeBatchData{
		PluginID:    pluginID,
		TriggerType: triggerType,
		Changes:     wsChanges,
		Summary: ws.FileChangeSummary{
			Created:  created,
			Modified: modified,
			Deleted:  deleted,
		},
	})
}

// isExcluded checks if a file/directory should be excluded
func (s *Service) isExcluded(name string, excludes []string) bool {
	// Always exclude hidden files
	if strings.HasPrefix(name, ".") {
		return true
	}

	// Default excludes
	defaultExcludes := []string{"node_modules", "vendor", ".git", ".svn", ".idea", ".vscode"}
	for _, ex := range defaultExcludes {
		if name == ex {
			return true
		}
	}

	// Custom excludes
	for _, pattern := range excludes {
		matched, _ := filepath.Match(pattern, name)
		if matched {
			return true
		}
	}

	return false
}

// calculateHash computes MD5 hash of a file
func (s *Service) calculateHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	hash := md5.New()
	_, err = io.Copy(hash, file)
	if err != nil {
		return "", err
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}

// RecordFileChange records a file change in the database
func (s *Service) RecordFileChange(ctx context.Context, change *models.FileChange) error {
	_, err := s.db.ExecContext(ctx, `
		INSERT INTO FileChanges (PluginId, FilePath, ChangeType, LocalHash, DetectedAt)
		VALUES (?, ?, ?, ?, datetime('now'))
	`, change.PluginID, change.FilePath, change.ChangeType, change.LocalHash)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseInsert, "failed to record file change")
	}

	return nil
}
