// Package requestsession provides storage for per-request session logs
package requestsession

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"sync"
	"time"

	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/pathutil"
)

// Store implements middleware.SessionStore with file-based persistence
type Store struct {
	dataDir       string
	log           *logger.Logger
	retentionDays int
	cache         map[string]*middleware.RequestSession
	mu            sync.RWMutex
}

// Config holds store configuration
type Config struct {
	DataDir       string
	Logger        *logger.Logger
	RetentionDays int
}

// New creates a new request session store
func New(cfg Config) (*Store, error) {
	retentionDays := cfg.RetentionDays
	if retentionDays <= 0 {
		retentionDays = 1 // Keep request sessions for 1 day by default (high volume)
	}

	dataDir, err := pathutil.Join(cfg.DataDir, "request-sessions")
	if err != nil {
		return nil, fmt.Errorf("resolve request sessions directory: %w", err)
	}
	if err := os.MkdirAll(dataDir, 0755); err != nil {
		return nil, fmt.Errorf("create request sessions directory: %w", err)
	}

	s := &Store{
		dataDir:       dataDir,
		log:           cfg.Logger,
		retentionDays: retentionDays,
		cache:         make(map[string]*middleware.RequestSession),
	}

	// Start cleanup goroutine
	go s.cleanupLoop()

	return s, nil
}

// SaveRequestSession persists a request session to disk
func (s *Store) SaveRequestSession(session *middleware.RequestSession) error {
	s.mu.Lock()
	s.cache[session.ID] = session
	s.mu.Unlock()

	// Create date-based directory structure for organization
	dateDir := session.StartedAt.Format("2006-01-02")
	hourDir := session.StartedAt.Format("15")
	sessionDir, err := pathutil.Join(s.dataDir, dateDir, hourDir)
	if err != nil {
		return fmt.Errorf("resolve session directory: %w", err)
	}
	
	if err := os.MkdirAll(sessionDir, 0755); err != nil {
		return fmt.Errorf("create session directory: %w", err)
	}

	// Write session JSON
	sessionPath, err := pathutil.Join(sessionDir, session.ID+".json")
	if err != nil {
		return fmt.Errorf("resolve session file path: %w", err)
	}
	data, err := json.MarshalIndent(session, "", "  ")
	if err != nil {
		return fmt.Errorf("marshal session: %w", err)
	}

	if err := os.WriteFile(sessionPath, data, 0644); err != nil {
		return fmt.Errorf("write session file: %w", err)
	}

	return nil
}

// GetRequestSession retrieves a session by ID
func (s *Store) GetRequestSession(id string) (*middleware.RequestSession, error) {
	// Check cache first
	s.mu.RLock()
	if session, ok := s.cache[id]; ok {
		s.mu.RUnlock()
		return session, nil
	}
	s.mu.RUnlock()

	// Search on disk (scan date directories)
	var found *middleware.RequestSession
	err := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return nil // Skip errors
		}
		if d.IsDir() || filepath.Ext(d.Name()) != ".json" {
			return nil
		}
		if d.Name() == id+".json" {
			data, err := os.ReadFile(path)
			if err != nil {
				return nil
			}
			var session middleware.RequestSession
			if err := json.Unmarshal(data, &session); err != nil {
				return nil
			}
			found = &session
			return filepath.SkipAll
		}
		return nil
	})

	if err != nil {
		return nil, fmt.Errorf("search session: %w", err)
	}

	if found == nil {
		return nil, fmt.Errorf("session not found: %s", id)
	}

	return found, nil
}

// ListRequestSessions returns recent sessions with pagination
func (s *Store) ListRequestSessions(limit, offset int) ([]*middleware.RequestSession, int, error) {
	if limit <= 0 {
		limit = 100
	}

	var allSessions []*middleware.RequestSession

	// Walk through all session files
	err := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return nil
		}
		if d.IsDir() || filepath.Ext(d.Name()) != ".json" {
			return nil
		}

		data, err := os.ReadFile(path)
		if err != nil {
			return nil
		}

		var session middleware.RequestSession
		if err := json.Unmarshal(data, &session); err != nil {
			return nil
		}

		allSessions = append(allSessions, &session)
		return nil
	})

	if err != nil {
		return nil, 0, fmt.Errorf("list sessions: %w", err)
	}

	// Sort by start time (newest first)
	sort.Slice(allSessions, func(i, j int) bool {
		return allSessions[i].StartedAt.After(allSessions[j].StartedAt)
	})

	total := len(allSessions)

	// Apply pagination
	if offset >= len(allSessions) {
		return []*middleware.RequestSession{}, total, nil
	}

	end := offset + limit
	if end > len(allSessions) {
		end = len(allSessions)
	}

	return allSessions[offset:end], total, nil
}

// DeleteRequestSession removes a session
func (s *Store) DeleteRequestSession(id string) error {
	s.mu.Lock()
	delete(s.cache, id)
	s.mu.Unlock()

	// Find and delete file
	var deleted bool
	err := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return nil
		}
		if d.IsDir() || d.Name() != id+".json" {
			return nil
		}
		if err := os.Remove(path); err == nil {
			deleted = true
		}
		return filepath.SkipAll
	})

	if err != nil {
		return fmt.Errorf("delete session: %w", err)
	}

	if !deleted {
		return fmt.Errorf("session not found: %s", id)
	}

	return nil
}

// ClearRequestSessions removes all sessions
func (s *Store) ClearRequestSessions() error {
	s.mu.Lock()
	s.cache = make(map[string]*middleware.RequestSession)
	s.mu.Unlock()

	// Remove all contents but keep the directory
	entries, err := os.ReadDir(s.dataDir)
	if err != nil {
		return fmt.Errorf("read sessions directory: %w", err)
	}

	for _, entry := range entries {
		path, err := pathutil.Join(s.dataDir, entry.Name())
		if err != nil {
			s.log.Error("Failed to resolve session entry path", "entry", entry.Name(), "error", err)
			continue
		}
		if err := os.RemoveAll(path); err != nil {
			s.log.Error("Failed to remove session entry", "path", path, "error", err)
		}
	}

	if s.log != nil {
		s.log.Info("Request sessions cleared")
	}

	return nil
}

// cleanupLoop periodically removes old sessions
func (s *Store) cleanupLoop() {
	ticker := time.NewTicker(1 * time.Hour)
	defer ticker.Stop()

	for range ticker.C {
		s.cleanupOldSessions()
	}
}

// cleanupOldSessions removes sessions older than retention period
func (s *Store) cleanupOldSessions() {
	cutoff := time.Now().Add(-time.Duration(s.retentionDays) * 24 * time.Hour)
	cutoffDate := cutoff.Format("2006-01-02")

	entries, err := os.ReadDir(s.dataDir)
	if err != nil {
		return
	}

	for _, entry := range entries {
		if !entry.IsDir() {
			continue
		}
		// Date directories are named YYYY-MM-DD
		if entry.Name() < cutoffDate {
			path, err := pathutil.Join(s.dataDir, entry.Name())
			if err == nil {
				os.RemoveAll(path)
			}
		}
	}
}

// GetSessionFiles returns the directory path for a session (for raw file access)
func (s *Store) GetSessionFiles(id string) (string, error) {
	session, err := s.GetRequestSession(id)
	if err != nil {
		return "", err
	}

	dateDir := session.StartedAt.Format("2006-01-02")
	hourDir := session.StartedAt.Format("15")
	return pathutil.Join(s.dataDir, dateDir, hourDir, id+".json")
}
