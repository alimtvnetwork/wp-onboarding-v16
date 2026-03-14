package requestsession

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sort"
	"time"

	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// ListRequestSessions returns recent sessions with pagination
func (s *Store) ListRequestSessions(limit, offset int) (*middleware.SessionListResult, *apperror.AppError) {
	isLimitUnset := limit <= 0

	if isLimitUnset {
		limit = 100
	}

	var allSessions []*middleware.RequestSession

	walkErr := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
		if err != nil {
			return nil
		}
		if d.IsDir() || filepath.Ext(d.Name()) != ".json" {
			return nil
		}

		data, readErr := os.ReadFile(path)
		if readErr != nil {
			return nil
		}

		var session middleware.RequestSession
		unmarshalErr := json.Unmarshal(data, &session)
		if unmarshalErr != nil {
			return nil
		}

		allSessions = append(allSessions, &session)
		return nil
	})

	if walkErr != nil {
		return nil, apperror.Wrap(walkErr, apperror.ErrSessionList, "list sessions")
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
func (s *Store) DeleteRequestSession(id string) *apperror.AppError {
	s.mu.Lock()
	delete(s.cache, id)
	s.mu.Unlock()

	var isDeleted bool
	walkErr := filepath.WalkDir(s.dataDir, func(path string, d os.DirEntry, err error) error {
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

	if walkErr != nil {
		return apperror.Wrap(walkErr, apperror.ErrSessionDelete, "delete session")
	}

	isSessionMissing := !isDeleted

	if isSessionMissing {
		return apperror.New(apperror.ErrSessionNotFound, "session not found: "+id)
	}

	return nil
}

// ClearRequestSessions removes all sessions
func (s *Store) ClearRequestSessions() *apperror.AppError {
	s.mu.Lock()
	s.cache = make(map[string]*middleware.RequestSession)
	s.mu.Unlock()

	entries, err := os.ReadDir(s.dataDir)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionClear, "read sessions directory")
	}

	for _, entry := range entries {
		path, pathErr := pathutil.Join(s.dataDir, entry.Name())
		if pathErr != nil {
			s.log.Error("Failed to resolve session entry path", "entry", entry.Name(), "error", pathErr)
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
		isFile := !entry.IsDir()

		if isFile {
			continue
		}

		isExpired := entry.Name() < cutoffDate

		if isExpired {
			path, pathErr := pathutil.Join(s.dataDir, entry.Name())
			if pathErr != nil {
				continue
			}

			pathutil.RemoveFileUnchecked(path)
		}
	}
}

// GetSessionFiles returns the directory path for a session (for raw file access)
func (s *Store) GetSessionFiles(id string) (string, *apperror.AppError) {
	session, err := s.GetRequestSession(id)
	if err != nil {
		return "", err
	}

	dateDir := session.StartedAt.Format("2006-01-02")
	hourDir := session.StartedAt.Format("15")
	path, pathErr := pathutil.Join(s.dataDir, dateDir, hourDir, id+".json")
	if pathErr != nil {
		return "", apperror.Wrap(pathErr, apperror.ErrSessionStore, "resolve session file path")
	}

	return path, nil
}
