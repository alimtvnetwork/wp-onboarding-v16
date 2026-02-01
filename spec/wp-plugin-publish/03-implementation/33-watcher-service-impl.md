# 33 — File Watcher Service Implementation

> **Location:** `spec/wp-plugin-publish/03-implementation/33-watcher-service-impl.md`  
> **Updated:** 2026-02-01  
> **Status:** Implementation Spec

---

## Overview

Complete Go implementation for the File Watcher Service. This service monitors plugin directories for file changes and triggers sync operations via WebSocket notifications.

---

## File Structure

```
backend/internal/services/watcher/
├── service.go      # Main service interface and constructor
├── scanner.go      # Directory scanning with hash comparison
├── watcher.go      # Per-plugin watcher goroutine
└── types.go        # Types and configuration
```

---

## Implementation: types.go

```go
package watcher

import "time"

// FileChange represents a detected file modification
type FileChange struct {
	Path       string    `json:"path"`
	ChangeType string    `json:"type"` // created, modified, deleted, renamed
	OldPath    string    `json:"oldPath,omitempty"`
	Hash       string    `json:"hash,omitempty"`
	Size       int64     `json:"size,omitempty"`
	ModTime    time.Time `json:"modTime,omitempty"`
}

// ScanResult contains the outcome of a directory scan
type ScanResult struct {
	PluginID     int64        `json:"pluginId"`
	Path         string       `json:"path"`
	ScanTime     time.Time    `json:"scanTime"`
	DurationMs   int64        `json:"durationMs"`
	FilesScanned int          `json:"filesScanned"`
	Changes      []FileChange `json:"changes"`
}

// fileInfo holds cached file metadata for change detection
type fileInfo struct {
	ModTime int64
	Size    int64
	Hash    string
}

// pluginWatcher manages watching for a single plugin
type pluginWatcher struct {
	pluginID int64
	path     string
	excludes []string
	stopCh   chan struct{}
	lastScan map[string]fileInfo
}
```

---

## Implementation: service.go

```go
package watcher

import (
	"context"
	"sync"
	"time"

	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/ws"
)

// Service interface for file watching
type Service interface {
	StartAll(ctx context.Context) error
	StopAll()
	StartPlugin(pluginID int64, path string, excludes []string) error
	StopPlugin(pluginID int64)
	TriggerScan(pluginID int64) (*ScanResult, error)
	GetWatchedPlugins() []int64
}

// Config holds watcher configuration
type Config struct {
	DB            *database.DB
	Logger        *logger.Logger
	PluginService plugin.Service
	SyncService   sync.Service
	WSHub         *ws.Hub
	PollInterval  time.Duration
	DebounceMs    int
}

type serviceImpl struct {
	db            *database.DB
	log           *logger.Logger
	pluginService plugin.Service
	syncService   sync.Service
	wsHub         *ws.Hub
	pollInterval  time.Duration
	debounceMs    int
	watchers      map[int64]*pluginWatcher
	mu            sync.RWMutex
	stopCh        chan struct{}
}

// New creates a new watcher service
func New(cfg Config) Service {
	if cfg.PollInterval == 0 {
		cfg.PollInterval = 5 * time.Second
	}
	if cfg.DebounceMs == 0 {
		cfg.DebounceMs = 500
	}

	return &serviceImpl{
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

func (s *serviceImpl) StartAll(ctx context.Context) error {
	s.log.Info("Starting all file watchers")

	// Query plugins with watch enabled
	plugins, err := s.pluginService.List(ctx)
	if err != nil {
		return err
	}

	for _, p := range plugins {
		if p.WatchEnabled {
			s.StartPlugin(p.ID, p.Path, p.ExcludePatterns)
		}
	}

	s.log.Info("File watchers started", "count", len(s.watchers))
	return nil
}

func (s *serviceImpl) StopAll() {
	s.mu.Lock()
	defer s.mu.Unlock()

	for id, w := range s.watchers {
		close(w.stopCh)
		delete(s.watchers, id)
	}

	s.log.Info("All file watchers stopped")
}

func (s *serviceImpl) StartPlugin(pluginID int64, path string, excludes []string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	// Check if already watching
	if _, exists := s.watchers[pluginID]; exists {
		s.log.Debug("Plugin already being watched", "pluginId", pluginID)
		return nil
	}

	w := &pluginWatcher{
		pluginID: pluginID,
		path:     path,
		excludes: excludes,
		stopCh:   make(chan struct{}),
		lastScan: make(map[string]fileInfo),
	}

	s.watchers[pluginID] = w

	// Start watching goroutine
	go s.watchLoop(w)

	s.log.Info("Started watching plugin", "pluginId", pluginID, "path", path)
	return nil
}

func (s *serviceImpl) StopPlugin(pluginID int64) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if w, exists := s.watchers[pluginID]; exists {
		close(w.stopCh)
		delete(s.watchers, pluginID)
		s.log.Info("Stopped watching plugin", "pluginId", pluginID)
	}
}

func (s *serviceImpl) GetWatchedPlugins() []int64 {
	s.mu.RLock()
	defer s.mu.RUnlock()

	ids := make([]int64, 0, len(s.watchers))
	for id := range s.watchers {
		ids = append(ids, id)
	}
	return ids
}
```

---

## Implementation: watcher.go

```go
package watcher

import (
	"time"

	"wp-plugin-publish/internal/ws"
)

// watchLoop is the main loop for watching a plugin directory
func (s *serviceImpl) watchLoop(w *pluginWatcher) {
	ticker := time.NewTicker(s.pollInterval)
	defer ticker.Stop()

	// Initial scan to populate baseline
	s.scanDirectory(w)

	var pendingChanges []FileChange
	var debounceTimer *time.Timer

	for {
		select {
		case <-w.stopCh:
			if debounceTimer != nil {
				debounceTimer.Stop()
			}
			return

		case <-ticker.C:
			changes := s.scanDirectory(w)
			if len(changes) > 0 {
				pendingChanges = append(pendingChanges, changes...)

				// Reset debounce timer
				if debounceTimer != nil {
					debounceTimer.Stop()
				}
				debounceTimer = time.AfterFunc(time.Duration(s.debounceMs)*time.Millisecond, func() {
					s.broadcastChanges(w.pluginID, pendingChanges)
					pendingChanges = nil
				})
			}
		}
	}
}

// broadcastChanges sends file changes via WebSocket and records them
func (s *serviceImpl) broadcastChanges(pluginID int64, changes []FileChange) {
	if len(changes) == 0 {
		return
	}

	// Count change types
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

	s.log.Info("File changes detected",
		"pluginId", pluginID,
		"created", created,
		"modified", modified,
		"deleted", deleted,
	)

	// Broadcast via WebSocket
	s.wsHub.Broadcast(ws.EventFileChange, map[string]interface{}{
		"pluginId": pluginID,
		"changes":  changes,
		"summary": map[string]int{
			"created":  created,
			"modified": modified,
			"deleted":  deleted,
		},
	})

	// Record changes in sync service
	for _, c := range changes {
		s.syncService.RecordFileChange(nil, &models.FileChange{
			PluginID:   pluginID,
			FilePath:   c.Path,
			ChangeType: c.ChangeType,
			LocalHash:  c.Hash,
		})
	}
}

func (s *serviceImpl) TriggerScan(pluginID int64) (*ScanResult, error) {
	s.mu.RLock()
	w, exists := s.watchers[pluginID]
	s.mu.RUnlock()

	if !exists {
		// Plugin not being watched, get details and do one-time scan
		plugin, err := s.pluginService.GetByID(nil, pluginID)
		if err != nil {
			return nil, err
		}

		w = &pluginWatcher{
			pluginID: pluginID,
			path:     plugin.Path,
			excludes: plugin.ExcludePatterns,
			lastScan: make(map[string]fileInfo),
		}
	}

	startTime := time.Now()
	changes := s.scanDirectory(w)

	return &ScanResult{
		PluginID:     pluginID,
		Path:         w.path,
		ScanTime:     startTime,
		DurationMs:   time.Since(startTime).Milliseconds(),
		FilesScanned: len(w.lastScan),
		Changes:      changes,
	}, nil
}
```

---

## Implementation: scanner.go

```go
package watcher

import (
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"strings"
)

// scanDirectory scans a directory and returns detected changes
func (s *serviceImpl) scanDirectory(w *pluginWatcher) []FileChange {
	var changes []FileChange
	currentFiles := make(map[string]fileInfo)

	// Walk directory
	err := filepath.Walk(w.path, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // Skip inaccessible files
		}

		// Get relative path
		relPath, _ := filepath.Rel(w.path, path)
		if relPath == "." {
			return nil
		}

		// Skip directories
		if info.IsDir() {
			// Check if should skip this directory
			base := filepath.Base(path)
			if s.isExcluded(base, w.excludes) {
				return filepath.SkipDir
			}
			return nil
		}

		// Skip excluded files
		base := filepath.Base(path)
		if s.isExcluded(base, w.excludes) {
			return nil
		}

		// Calculate hash
		hash, _ := s.calculateHash(path)

		fi := fileInfo{
			ModTime: info.ModTime().Unix(),
			Size:    info.Size(),
			Hash:    hash,
		}
		currentFiles[relPath] = fi

		// Compare with last scan
		if lastInfo, exists := w.lastScan[relPath]; exists {
			// File existed before - check if modified
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
			// New file
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

	if err != nil {
		s.log.Error("Error scanning directory", "path", w.path, "error", err)
		return changes
	}

	// Check for deleted files
	for path := range w.lastScan {
		if _, exists := currentFiles[path]; !exists {
			changes = append(changes, FileChange{
				Path:       path,
				ChangeType: "deleted",
			})
		}
	}

	// Update last scan
	w.lastScan = currentFiles

	return changes
}

// isExcluded checks if a file/directory should be excluded
func (s *serviceImpl) isExcluded(name string, excludes []string) bool {
	// Always exclude hidden files and common directories
	if strings.HasPrefix(name, ".") {
		return true
	}

	defaultExcludes := []string{"node_modules", "vendor", ".git", ".svn", ".idea", ".vscode"}
	for _, ex := range defaultExcludes {
		if name == ex {
			return true
		}
	}

	// Check custom excludes
	for _, pattern := range excludes {
		if matched, _ := filepath.Match(pattern, name); matched {
			return true
		}
	}

	return false
}

// calculateHash computes MD5 hash of a file
func (s *serviceImpl) calculateHash(path string) (string, error) {
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
```

---

## WebSocket Events

| Event | Payload | Trigger |
|-------|---------|---------|
| `file:change` | `{pluginId, changes, summary}` | Files changed |
| `watcher:started` | `{pluginId, path}` | Watcher started |
| `watcher:stopped` | `{pluginId}` | Watcher stopped |
| `watcher:error` | `{pluginId, error}` | Scan error |

---

## API Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| GET | `/api/watcher/status` | Get all watchers status |
| POST | `/api/watcher/start/:pluginId` | Start watching plugin |
| POST | `/api/watcher/stop/:pluginId` | Stop watching plugin |
| POST | `/api/watcher/scan/:pluginId` | Trigger manual scan |

---

*See also: [34-git-service-impl.md](34-git-service-impl.md)*
