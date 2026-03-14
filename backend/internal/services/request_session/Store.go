// Package requestsession provides storage for per-request session logs
package requestsession

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sync"

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
func New(cfg Config) (*Store, *apperror.AppError) {
	retentionDays := cfg.RetentionDays
	isRetentionUnset := retentionDays <= 0

	if isRetentionUnset {
		retentionDays = 1
	}

	dataDir, err := pathutil.Join(cfg.DataDir, "request-sessions")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionInit, "resolve request sessions directory")
	}

	mkdirErr := os.MkdirAll(dataDir, 0755)
	if mkdirErr != nil {
		return nil, apperror.Wrap(mkdirErr, apperror.ErrSessionInit, "create request sessions directory")
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
func (s *Store) SaveRequestSession(session *middleware.RequestSession) *apperror.AppError {
	s.mu.Lock()
	s.cache[session.Id] = session
	s.mu.Unlock()

	dateDir := session.StartedAt.Format("2006-01-02")
	hourDir := session.StartedAt.Format("15")
	sessionDir, err := pathutil.Join(s.dataDir, dateDir, hourDir)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionStore, "resolve session directory")
	}

	mkdirErr := os.MkdirAll(sessionDir, 0755)
	if mkdirErr != nil {
		return apperror.Wrap(mkdirErr, apperror.ErrSessionStore, "create session directory")
	}

	sessionPath, pathErr := pathutil.Join(sessionDir, session.Id+".json")
	if pathErr != nil {
		return apperror.Wrap(pathErr, apperror.ErrSessionStore, "resolve session file path")
	}

	data, marshalErr := json.MarshalIndent(session, "", "  ")
	if marshalErr != nil {
		return apperror.Wrap(marshalErr, apperror.ErrSessionStore, "marshal session")
	}

	writeErr := os.WriteFile(sessionPath, data, 0644)
	if writeErr != nil {
		return apperror.Wrap(writeErr, apperror.ErrSessionStore, "write session file")
	}

	return nil
}

// GetRequestSession retrieves a session by ID
func (s *Store) GetRequestSession(id string) (*middleware.RequestSession, *apperror.AppError) {
	s.mu.RLock()
	session, isCached := s.cache[id]
	if isCached {
		s.mu.RUnlock()
		return session, nil
	}
	s.mu.RUnlock()

	var found *middleware.RequestSession
	walkErr := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return nil
		}
		if d.IsDir() || filepath.Ext(d.Name()) != ".json" {
			return nil
		}
		if d.Name() == id+".json" {
			data, readErr := os.ReadFile(path)
			if readErr != nil {
				return nil
			}

			var session middleware.RequestSession
			unmarshalErr := json.Unmarshal(data, &session)
			if unmarshalErr != nil {
				return nil
			}

			found = &session
			return filepath.SkipAll
		}
		return nil
	})

	if walkErr != nil {
		return nil, apperror.Wrap(walkErr, apperror.ErrSessionList, "search session")
	}

	isSessionMissing := found == nil

	if isSessionMissing {
		return nil, apperror.New(apperror.ErrSessionNotFound, "session not found: "+id)
	}

	return found, nil
}
