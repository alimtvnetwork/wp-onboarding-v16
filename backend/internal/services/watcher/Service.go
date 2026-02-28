// Package watcher provides file system scanning for plugin directories (hybrid mode)
package watcher

import (
	"context"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
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
	PluginId     int64
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
	PublishService PublishService
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

// SetPublishService sets the publish service for auto-publish
func (s *Service) SetPublishService(ps PublishService) {
	s.publishService = ps
}

// InitializeCache loads the current file state for a plugin
func (s *Service) InitializeCache(ctx context.Context, pluginID int64) error {
	pResult := s.pluginService.GetById(ctx, pluginID)
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

	s.populateCache(cache)
	s.cache[pluginID] = cache

	s.log.Info("Initialized file cache", "plugin", p.Name, "pluginId", pluginID, "files", len(cache.lastScan))
	return nil
}

// TriggerScan performs a manual scan
func (s *Service) TriggerScan(ctx context.Context, pluginID int64) apperror.Result[ScanResult] {
	return s.performScan(ctx, pluginID, "manual")
}

// ScanAfterGitPull performs a scan after git pull
func (s *Service) ScanAfterGitPull(ctx context.Context, pluginID int64) apperror.Result[ScanResult] {
	return s.performScan(ctx, pluginID, "git_pull")
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
	s.log.Info("Cleared file cache", "pluginId", pluginID)
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
