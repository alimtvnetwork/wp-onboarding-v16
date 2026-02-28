// Package requestsession provides storage for per-request session logs
package requestsession

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sort"
	"sync"
	"time"

	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
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
		retentionDays = 1
	}

	dataDir, err := pathutil.Join(cfg.DataDir, "request-sessions")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionInit, "resolve request sessions directory")
	}
	err = os.MkdirAll(dataDir, 0755)
	if err != nil {

		return nil, apperror.Wrap(err, apperror.ErrSessionInit, "create request sessions directory")
	}

	s := &Store{
		dataDir:       dataDir,
		log:           cfg.Logger,
		retentionDays: retentionDays,
		cache:         make(map[string]*middleware.RequestSession),
	}

	go s.cleanupLoop()

	return s, nil
}

// SaveRequestSession persists a request session to disk
func (s *Store) SaveRequestSession(session *middleware.RequestSession) error {
	s.mu.Lock()
	s.cache[session.ID] = session
	s.mu.Unlock()

	dateDir := session.StartedAt.Format("2006-01-02")
	hourDir := session.StartedAt.Format("15")
	sessionDir, err := pathutil.Join(s.dataDir, dateDir, hourDir)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionStore, "resolve session directory")
	}

	err = os.MkdirAll(sessionDir, 0755)
	if err != nil {

		return apperror.Wrap(err, apperror.ErrSessionStore, "create session directory")
	}

	sessionPath, err := pathutil.Join(sessionDir, session.ID+".json")
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionStore, "resolve session file path")
	}
	data, err := json.MarshalIndent(session, "", "  ")
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionStore, "marshal session")
	}

	err = os.WriteFile(sessionPath, data, 0644)
	if err != nil {

		return apperror.Wrap(err, apperror.ErrSessionStore, "write session file")
	}

	return nil
}

// GetRequestSession retrieves a session by ID
func (s *Store) GetRequestSession(id string) (*middleware.RequestSession, error) {
	s.mu.RLock()
	if session, isCached := s.cache[id]; isCached {
		s.mu.RUnlock()

		return session, nil
	}
	s.mu.RUnlock()

	var found *middleware.RequestSession
	err := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return nil
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
			err := json.Unmarshal(data, &session)
			if err != nil {

				return nil
			}
			found = &session
			return filepath.SkipAll
		}
		return nil
	})

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionList, "search session")
	}

	if found == nil {
		return nil, apperror.New(apperror.ErrSessionNotFound, "session not found: "+id)
	}

	return found, nil
}

// ListRequestSessions returns recent sessions with pagination
func (s *Store) ListRequestSessions(limit, offset int) (*middleware.SessionListResult, error) {
	if limit <= 0 {
		limit = 100
	}

	var allSessions []*middleware.RequestSession

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
		err := json.Unmarshal(data, &session)
		if err != nil {

			return nil
		}

		allSessions = append(allSessions, &session)
		return nil
	})

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionList, "list sessions")
	}

	sort.Slice(allSessions, func(i, j int) bool {
		return allSessions[i].StartedAt.After(allSessions[j].StartedAt)
	})

	total := len(allSessions)

	if offset >= len(allSessions) {
		return &middleware.SessionListResult{Sessions: []*middleware.RequestSession{}, Total: total}, nil
	}

	end := offset + limit
	if end > len(allSessions) {
		end = len(allSessions)
	}

	return &middleware.SessionListResult{Sessions: allSessions[offset:end], Total: total}, nil
}

// DeleteRequestSession removes a session
func (s *Store) DeleteRequestSession(id string) error {
	s.mu.Lock()
	delete(s.cache, id)
	s.mu.Unlock()

	var isDeleted bool
	err := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return nil
		}
		if d.IsDir() || d.Name() != id+".json" {
			return nil
		}
		removeErr := pathutil.RemoveFile(path, "path")
		if removeErr == nil {
			isDeleted = true
		}
		return filepath.SkipAll
	})

	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionDelete, "delete session")
	}

	isSessionMissing := !isDeleted

	if isSessionMissing {
		return apperror.New(apperror.ErrSessionNotFound, "session not found: "+id)
	}

	return nil
}

// ClearRequestSessions removes all sessions
func (s *Store) ClearRequestSessions() error {
	s.mu.Lock()
	s.cache = make(map[string]*middleware.RequestSession)
	s.mu.Unlock()

	entries, err := os.ReadDir(s.dataDir)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionClear, "read sessions directory")
	}

	for _, entry := range entries {
		path, err := pathutil.Join(s.dataDir, entry.Name())
		if err != nil {
			s.log.Error("Failed to resolve session entry path", "entry", entry.Name(), "error", err)
			continue
		}
		removeErr := pathutil.RemoveDir(path, "path")
		if removeErr != nil {
			s.log.Error("Failed to remove session entry", "path", path, "error", removeErr)
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
		if entry.Name() < cutoffDate {
			path, err := pathutil.Join(s.dataDir, entry.Name())
			pathutil.RemoveFileUnchecked(path)
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
