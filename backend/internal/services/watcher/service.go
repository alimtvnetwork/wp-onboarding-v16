// Package watcher provides file system watching for plugin directories
package watcher

import (
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/ws"
)

// Config holds watcher configuration
type Config struct {
	DB            *database.DB
	Logger        *logger.Logger
	PluginService *plugin.Service
	SyncService   *sync.Service
	WSHub         *ws.Hub
	PollInterval  time.Duration
	DebounceMs    int
}

// Service manages file watchers for all registered plugins
type Service struct {
	db            *database.DB
	log           *logger.Logger
	pluginService *plugin.Service
	syncService   *sync.Service
	wsHub         *ws.Hub
	pollInterval  time.Duration
	debounceMs    int
	watchers      map[int64]*pluginWatcher
	mu            sync.RWMutex
	stopCh        chan struct{}
}

// pluginWatcher watches a single plugin directory
type pluginWatcher struct {
	pluginID     int64
	path         string
	stopCh       chan struct{}
	lastScan     map[string]fileInfo
	excludes     []string
}

// fileInfo holds file metadata for change detection
type fileInfo struct {
	ModTime int64
	Size    int64
	Hash    string
}

// New creates a new watcher service
func New(cfg Config) *Service {
	return &Service{
		db:            cfg.DB,
		log:           cfg.Logger,
		pluginService: cfg.PluginService,
		syncService:   cfg.SyncService,
		wsHub:         cfg.WSHub,
		pollInterval:  cfg.PollInterval,
		debounceMs:    cfg.DebounceMs,
		watchers:      make(map[int64]*pluginWatcher),
		stopCh:        make(chan struct{}),
	}
}

// StartAll starts watchers for all plugins with watching enabled
func (s *Service) StartAll() error {
	// TODO: Query plugins with WatchEnabled = true
	// TODO: Start a watcher for each
	s.log.Info("File watchers started")
	return nil
}

// StopAll stops all active watchers
func (s *Service) StopAll() {
	s.mu.Lock()
	defer s.mu.Unlock()

	for id, w := range s.watchers {
		close(w.stopCh)
		delete(s.watchers, id)
	}

	s.log.Info("File watchers stopped")
}

// StartPlugin starts watching a specific plugin
func (s *Service) StartPlugin(pluginID int64, path string, excludes []string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	// Check if already watching
	if _, exists := s.watchers[pluginID]; exists {
		return nil
	}

	w := &pluginWatcher{
		pluginID: pluginID,
		path:     path,
		stopCh:   make(chan struct{}),
		lastScan: make(map[string]fileInfo),
		excludes: excludes,
	}

	s.watchers[pluginID] = w

	// Start watching goroutine
	go s.watch(w)

	s.log.Info("Started watching plugin", "pluginId", pluginID, "path", path)
	return nil
}

// StopPlugin stops watching a specific plugin
func (s *Service) StopPlugin(pluginID int64) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if w, exists := s.watchers[pluginID]; exists {
		close(w.stopCh)
		delete(s.watchers, pluginID)
		s.log.Info("Stopped watching plugin", "pluginId", pluginID)
	}
}

// watch is the main loop for watching a plugin directory
func (s *Service) watch(w *pluginWatcher) {
	ticker := time.NewTicker(s.pollInterval)
	defer ticker.Stop()

	// Initial scan
	s.scanDirectory(w)

	for {
		select {
		case <-w.stopCh:
			return
		case <-ticker.C:
			changes := s.scanDirectory(w)
			if len(changes) > 0 {
				s.handleChanges(w.pluginID, changes)
			}
		}
	}
}

// scanDirectory scans a directory and returns detected changes
func (s *Service) scanDirectory(w *pluginWatcher) []FileChange {
	// TODO: Implement directory scanning with MD5 hashing
	// TODO: Compare with lastScan to detect changes
	// TODO: Update lastScan with current state
	return nil
}

// handleChanges processes detected file changes
func (s *Service) handleChanges(pluginID int64, changes []FileChange) {
	// TODO: Store changes in database
	// TODO: Broadcast via WebSocket
	s.wsHub.Broadcast(ws.EventFileChange, map[string]interface{}{
		"pluginId": pluginID,
		"changes":  changes,
	})
}

// FileChange represents a detected file change
type FileChange struct {
	Path       string `json:"path"`
	ChangeType string `json:"type"` // created, modified, deleted, renamed
	OldPath    string `json:"oldPath,omitempty"`
}
