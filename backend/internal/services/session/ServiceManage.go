// Package session — management: list, delete, cleanup.
package session

import (
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"time"

	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// dirInfo holds a session directory name and modification time for sorting.
type dirInfo struct {
	name    string
	modTime time.Time
}

// ListSessions returns recent sessions
func (s *Service) ListSessions(limit int) apperror.ResultSlice[*SessionSummary] {
	if limit <= 0 {
		limit = 100
	}

	entries, err := os.ReadDir(s.sessionsDir)
	if err != nil {
		return apperror.FailSliceWrap[*SessionSummary](err, apperror.ErrFSRead, "read sessions directory")
	}

	sessionDirs := collectSessionDirs(entries)
	sortAndLimit(&sessionDirs, limit)

	summaries := s.buildSummaries(sessionDirs)
	return apperror.OkSlice(summaries)
}

// collectSessionDirs scans directory entries for session directories and legacy files.
func collectSessionDirs(entries []os.DirEntry) []dirInfo {
	var dirs []dirInfo
	for _, entry := range entries {
		info, err := entry.Info()
		if err != nil {
			continue
		}
		if entry.IsDir() {
			dirs = append(dirs, dirInfo{name: entry.Name(), modTime: info.ModTime()})
		} else if filepath.Ext(entry.Name()) == ".log" {
			dirs = append(dirs, dirInfo{name: entry.Name()[:len(entry.Name())-4], modTime: info.ModTime()})
		}
	}
	return dirs
}

// sortAndLimit sorts by newest first and trims to limit.
func sortAndLimit(dirs *[]dirInfo, limit int) {
	sort.Slice(*dirs, func(i, j int) bool {
		return (*dirs)[i].modTime.After((*dirs)[j].modTime)
	})
	if len(*dirs) > limit {
		*dirs = (*dirs)[:limit]
	}
}

// buildSummaries builds session summaries from directory info.
func (s *Service) buildSummaries(dirs []dirInfo) []*SessionSummary {
	summaries := make([]*SessionSummary, 0, len(dirs))
	for _, d := range dirs {
		summaries = append(summaries, s.buildSingleSummary(d))
	}
	return summaries
}

// buildSingleSummary builds a summary for one session entry.
func (s *Service) buildSingleSummary(d dirInfo) *SessionSummary {
	s.mu.RLock()
	session, isFound := s.sessions[d.name]
	s.mu.RUnlock()

	if isFound {
		return summaryFromSession(session)
	}

	return &SessionSummary{
		ID:        d.name,
		Status:    stagestatus.Completed.String(),
		StartedAt: d.modTime,
	}
}

// summaryFromSession builds a SessionSummary from an in-memory Session.
func summaryFromSession(session *Session) *SessionSummary {
	return &SessionSummary{
		ID:         session.ID,
		Type:       session.Type,
		PluginID:   session.PluginID,
		SiteID:     session.SiteID,
		PluginName: session.PluginName,
		SiteName:   session.SiteName,
		Status:     session.Status,
		StartedAt:  session.StartedAt,
		EndedAt:    session.EndedAt,
	}
}

// DeleteSession removes a session's directory (or legacy file)
func (s *Service) DeleteSession(sessionID string) *apperror.AppError {
	s.closeAndRemoveSession(sessionID)

	dirResult := s.getSessionDir(sessionID)

	if dirResult.HasError() {

		return dirResult.AppError()
	}

	sessionDir := dirResult.Value()

	if pathutil.IsDir(sessionDir) {

		return s.removeDirSession(sessionDir)
	}

	return s.removeLegacySession(sessionID)
}

// closeAndRemoveSession closes the log file and removes from in-memory map.
func (s *Service) closeAndRemoveSession(sessionID string) {
	s.mu.Lock()
	if session, isFound := s.sessions[sessionID]; isFound {
		session.mu.Lock()
		if session.logFile != nil {
			session.logFile.Close()
			session.logFile = nil
		}
		session.mu.Unlock()
		delete(s.sessions, sessionID)
	}
	s.mu.Unlock()
}

// removeDirSession removes a directory-based session.
func (s *Service) removeDirSession(sessionDir string) *apperror.AppError {
	return pathutil.RemoveDir(sessionDir, "sessionDir")
}

// removeLegacySession removes a legacy flat-file session.
func (s *Service) removeLegacySession(sessionID string) *apperror.AppError {
	legacyPath, err := pathutil.Join(s.sessionsDir, sessionID+".log")
	if err != nil {

		return apperror.Wrap(err, apperror.ErrSessionDelete, "resolve legacy session path")
	}

	return pathutil.RemoveFile(legacyPath, "legacyPath")
}

// cleanupLoop periodically removes old session directories
func (s *Service) cleanupLoop() {
	ticker := time.NewTicker(1 * time.Hour)
	defer ticker.Stop()

	for range ticker.C {
		s.cleanupOldSessions()
	}
}

// cleanupOldSessions removes sessions older than retention period
func (s *Service) cleanupOldSessions() {
	cutoff := time.Now().Add(-time.Duration(s.retentionDays) * 24 * time.Hour)

	entries, err := os.ReadDir(s.sessionsDir)
	if err != nil {
		return
	}

	for _, entry := range entries {
		s.removeIfExpired(entry, cutoff)
	}
}

// removeIfExpired removes a single entry if it's older than the cutoff.
func (s *Service) removeIfExpired(entry os.DirEntry, cutoff time.Time) {
	info, err := entry.Info()
	if err != nil {
		return
	}
	isNotExpired := !info.ModTime().Before(cutoff)
	if isNotExpired {
		return
	}
	s.removeSessionEntry(entry)
}

// removeSessionEntry resolves the full path and removes the entry.
func (s *Service) removeSessionEntry(entry os.DirEntry) {
	fullPath, err := pathutil.Join(s.sessionsDir, entry.Name())
	if err != nil {
		return
	}

	pathutil.RemoveEntry(
		fullPath,
		entry.IsDir(),
		"fullPath",
	)
}

// ClearAllSessions removes all session directories and files
func (s *Service) ClearAllSessions() *apperror.AppError {
	s.closeAllSessions()

	entries, err := os.ReadDir(s.sessionsDir)
	if err != nil {

		return apperror.Wrap(err, apperror.ErrSessionClear, "read sessions directory").
			WithPath(s.sessionsDir)
	}

	return s.clearEntries(entries)
}

// clearEntries removes all entries and logs the result.
func (s *Service) clearEntries(entries []os.DirEntry) *apperror.AppError {
	removeErrors := s.removeAllEntries(entries)
	if len(removeErrors) > 0 {

		return apperror.New(apperror.ErrSessionClear, "failed to remove session entries").
			WithDetails(fmt.Sprintf("count=%d", len(removeErrors)))
	}

	if s.log != nil {
		s.log.Info("All sessions cleared", "count", len(entries))
	}

	return nil
}

// closeAllSessions closes all open session log files and clears the map.
func (s *Service) closeAllSessions() {
	s.mu.Lock()
	for id, session := range s.sessions {
		session.mu.Lock()
		if session.logFile != nil {
			session.logFile.Close()
			session.logFile = nil
		}
		session.mu.Unlock()
		delete(s.sessions, id)
	}
	s.mu.Unlock()
}

// removeAllEntries removes all files and directories in the sessions folder.
func (s *Service) removeAllEntries(entries []os.DirEntry) []*apperror.AppError {
	var errors []*apperror.AppError
	for _, entry := range entries {
		entryErr := s.removeEntryByName(entry)
		if entryErr != nil {
			errors = append(errors, entryErr)
		}
	}

	return errors
}

// removeEntryByName resolves and removes a single named entry.
func (s *Service) removeEntryByName(entry os.DirEntry) *apperror.AppError {
	fullPath, err := pathutil.Join(s.sessionsDir, entry.Name())
	if err != nil {

		return nil
	}

	return pathutil.RemoveEntry(
		fullPath,
		entry.IsDir(),
		"fullPath",
	)
}
