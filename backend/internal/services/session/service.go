// Package session provides session-based logging for operations
package session

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"sync"
	"time"

	"github.com/google/uuid"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/pathutil"
)

// SessionType identifies the type of operation
type SessionType string

const (
	SessionTypePublish     SessionType = "publish"
	SessionTypeSync        SessionType = "sync"
	SessionTypeBackup      SessionType = "backup"
	SessionTypeConnect     SessionType = "connect"
	SessionTypeBulkPublish SessionType = "bulk_publish"
)

// Session represents an active or completed operation session
type Session struct {
	ID          string            `json:"id"`
	Type        SessionType       `json:"type"`
	PluginID    int64             `json:"pluginId,omitempty"`
	SiteID      int64             `json:"siteId,omitempty"`
	PluginName  string            `json:"pluginName,omitempty"`
	SiteName    string            `json:"siteName,omitempty"`
	Status      string            `json:"status"` // running, success, error
	StartedAt   time.Time         `json:"startedAt"`
	EndedAt     *time.Time        `json:"endedAt,omitempty"`
	ErrorMsg    string            `json:"errorMessage,omitempty"`
	Metadata    map[string]interface{} `json:"metadata,omitempty"`
	logFile     *os.File
	mu          sync.Mutex
}

// LogEntry represents a single log entry in a session
type LogEntry struct {
	Timestamp string                 `json:"timestamp"`
	Level     string                 `json:"level"`  // debug, info, warn, error
	Step      string                 `json:"step"`   // backup, package, upload, activate, etc.
	Message   string                 `json:"message"`
	Details   map[string]interface{} `json:"details,omitempty"`
}

// Config holds session service configuration
type Config struct {
	DataDir       string         // Base data directory
	Logger        *logger.Logger
	RetentionDays int            // Days to keep old sessions (default 7)
}

// Service manages operation sessions and their logs
type Service struct {
	dataDir       string
	sessionsDir   string
	log           *logger.Logger
	retentionDays int
	sessions      map[string]*Session
	mu            sync.RWMutex
}

// New creates a new session service
func New(cfg Config) (*Service, error) {
	retentionDays := cfg.RetentionDays
	if retentionDays <= 0 {
		retentionDays = 7
	}

	sessionsDir := pathutil.MustJoin(cfg.DataDir, "sessions")
	
	// Ensure sessions directory exists
	if err := os.MkdirAll(sessionsDir, 0755); err != nil {
		return nil, fmt.Errorf("create sessions directory: %w", err)
	}

	s := &Service{
		dataDir:       cfg.DataDir,
		sessionsDir:   sessionsDir,
		log:           cfg.Logger,
		retentionDays: retentionDays,
		sessions:      make(map[string]*Session),
	}

	// Start cleanup goroutine
	go s.cleanupLoop()

	return s, nil
}

// StartSession creates a new session and returns its ID
func (s *Service) StartSession(sessionType SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error) {
	sessionID := uuid.New().String()
	
	session := &Session{
		ID:         sessionID,
		Type:       sessionType,
		PluginID:   pluginID,
		SiteID:     siteID,
		PluginName: pluginName,
		SiteName:   siteName,
		Status:     "running",
		StartedAt:  time.Now().UTC(),
		Metadata:   make(map[string]interface{}),
	}

	// Create log file
	logPath := s.getLogPath(sessionID)
	file, err := os.Create(logPath)
	if err != nil {
		return "", fmt.Errorf("create session log file: %w", err)
	}
	session.logFile = file

	// Write session header
	header := fmt.Sprintf("═══════════════════════════════════════════════════════════════════════════════\n")
	header += fmt.Sprintf(" SESSION: %s\n", sessionID)
	header += fmt.Sprintf(" TYPE: %s\n", sessionType)
	header += fmt.Sprintf(" STARTED: %s\n", session.StartedAt.Format("2006-01-02 15:04:05 UTC"))
	if pluginName != "" {
		header += fmt.Sprintf(" PLUGIN: %s (ID: %d)\n", pluginName, pluginID)
	}
	if siteName != "" {
		header += fmt.Sprintf(" SITE: %s (ID: %d)\n", siteName, siteID)
	}
	header += fmt.Sprintf("═══════════════════════════════════════════════════════════════════════════════\n\n")
	file.WriteString(header)

	s.mu.Lock()
	s.sessions[sessionID] = session
	s.mu.Unlock()

	if s.log != nil {
		s.log.Info("Session started", "sessionId", sessionID, "type", sessionType)
	}

	return sessionID, nil
}

// Log writes a log entry to the session
func (s *Service) Log(sessionID, level, step, message string, details map[string]interface{}) {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if !exists {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	if session.logFile == nil {
		return
	}

	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	levelUpper := map[string]string{
		"debug": "DEBUG",
		"info":  "INFO",
		"warn":  "WARN",
		"error": "ERROR",
	}[level]
	if levelUpper == "" {
		levelUpper = "INFO"
	}

	// Format: [YYYY-MM-DD HH:MM:SS] [LEVEL] [step] Message
	logLine := fmt.Sprintf("[%s] [%s] [%s] %s\n", timestamp, levelUpper, step, message)
	session.logFile.WriteString(logLine)

	// Write details as indented JSON if present
	if len(details) > 0 {
		detailsJSON, _ := json.MarshalIndent(details, "    ", "  ")
		session.logFile.WriteString(fmt.Sprintf("    %s\n", string(detailsJSON)))
	}
}

// LogStageStart writes a stage header to the session log
func (s *Service) LogStageStart(sessionID, stageName string) {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if !exists {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	if session.logFile == nil {
		return
	}

	header := fmt.Sprintf("\n───────────────────────────────────────────────────────────────────────────────\n")
	header += fmt.Sprintf(" STAGE: %s\n", stageName)
	header += fmt.Sprintf("───────────────────────────────────────────────────────────────────────────────\n")
	session.logFile.WriteString(header)
}

// LogStageEnd writes a stage completion marker
func (s *Service) LogStageEnd(sessionID, stageName, status string, durationMs int64) {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if !exists {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	if session.logFile == nil {
		return
	}

	statusIcon := "✓"
	if status == "error" || status == "failed" {
		statusIcon = "✗"
	} else if status == "skipped" {
		statusIcon = "○"
	}

	footer := fmt.Sprintf("\n%s STAGE %s completed (%s) in %dms\n", statusIcon, stageName, status, durationMs)
	session.logFile.WriteString(footer)
}

// EndSession marks a session as complete
func (s *Service) EndSession(sessionID, status, errorMsg string) {
	s.mu.Lock()
	session, exists := s.sessions[sessionID]
	s.mu.Unlock()

	if !exists {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	now := time.Now().UTC()
	session.Status = status
	session.EndedAt = &now
	session.ErrorMsg = errorMsg

	if session.logFile != nil {
		duration := now.Sub(session.StartedAt)
		footer := fmt.Sprintf("\n═══════════════════════════════════════════════════════════════════════════════\n")
		footer += fmt.Sprintf(" SESSION ENDED: %s\n", now.Format("2006-01-02 15:04:05 UTC"))
		footer += fmt.Sprintf(" STATUS: %s\n", status)
		footer += fmt.Sprintf(" DURATION: %v\n", duration.Round(time.Millisecond))
		if errorMsg != "" {
			footer += fmt.Sprintf(" ERROR: %s\n", errorMsg)
		}
		footer += fmt.Sprintf("═══════════════════════════════════════════════════════════════════════════════\n")
		session.logFile.WriteString(footer)
		session.logFile.Close()
		session.logFile = nil
	}

	if s.log != nil {
		s.log.Info("Session ended", "sessionId", sessionID, "status", status)
	}
}

// GetSession returns session info
func (s *Service) GetSession(sessionID string) (*Session, error) {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if exists {
		return session, nil
	}

	// Try to load from disk
	return s.loadSessionFromDisk(sessionID)
}

// GetSessionLogs returns the full log content for a session
func (s *Service) GetSessionLogs(sessionID string) (string, error) {
	logPath := s.getLogPath(sessionID)
	data, err := os.ReadFile(logPath)
	if err != nil {
		if os.IsNotExist(err) {
			return "", fmt.Errorf("session not found: %s", sessionID)
		}
		return "", fmt.Errorf("read session log: %w", err)
	}
	return string(data), nil
}

// ListSessions returns recent sessions
func (s *Service) ListSessions(limit int) ([]*SessionSummary, error) {
	if limit <= 0 {
		limit = 100
	}

	// Get all log files
	files, err := os.ReadDir(s.sessionsDir)
	if err != nil {
		return nil, fmt.Errorf("read sessions directory: %w", err)
	}

	type fileInfo struct {
		name    string
		modTime time.Time
	}
	var logFiles []fileInfo

	for _, f := range files {
		if f.IsDir() || filepath.Ext(f.Name()) != ".log" {
			continue
		}
		info, err := f.Info()
		if err != nil {
			continue
		}
		logFiles = append(logFiles, fileInfo{
			name:    f.Name(),
			modTime: info.ModTime(),
		})
	}

	// Sort by modification time (newest first)
	sort.Slice(logFiles, func(i, j int) bool {
		return logFiles[i].modTime.After(logFiles[j].modTime)
	})

	// Limit results
	if len(logFiles) > limit {
		logFiles = logFiles[:limit]
	}

	// Build summaries
	summaries := make([]*SessionSummary, 0, len(logFiles))
	for _, f := range logFiles {
		sessionID := f.name[:len(f.name)-4] // Remove .log extension
		
		// Check if session is in memory
		s.mu.RLock()
		session, exists := s.sessions[sessionID]
		s.mu.RUnlock()

		if exists {
			summaries = append(summaries, &SessionSummary{
				ID:         session.ID,
				Type:       session.Type,
				PluginID:   session.PluginID,
				SiteID:     session.SiteID,
				PluginName: session.PluginName,
				SiteName:   session.SiteName,
				Status:     session.Status,
				StartedAt:  session.StartedAt,
				EndedAt:    session.EndedAt,
			})
		} else {
			// Load basic info from file header
			summaries = append(summaries, &SessionSummary{
				ID:        sessionID,
				Status:    "completed",
				StartedAt: f.modTime,
			})
		}
	}

	return summaries, nil
}

// SessionSummary provides a brief overview of a session
type SessionSummary struct {
	ID         string      `json:"id"`
	Type       SessionType `json:"type"`
	PluginID   int64       `json:"pluginId,omitempty"`
	SiteID     int64       `json:"siteId,omitempty"`
	PluginName string      `json:"pluginName,omitempty"`
	SiteName   string      `json:"siteName,omitempty"`
	Status     string      `json:"status"`
	StartedAt  time.Time   `json:"startedAt"`
	EndedAt    *time.Time  `json:"endedAt,omitempty"`
}

// DeleteSession removes a session's log file
func (s *Service) DeleteSession(sessionID string) error {
	// Close if still open
	s.mu.Lock()
	if session, exists := s.sessions[sessionID]; exists {
		session.mu.Lock()
		if session.logFile != nil {
			session.logFile.Close()
			session.logFile = nil
		}
		session.mu.Unlock()
		delete(s.sessions, sessionID)
	}
	s.mu.Unlock()

	// Delete file
	logPath := s.getLogPath(sessionID)
	if err := os.Remove(logPath); err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("delete session log: %w", err)
	}
	return nil
}

// getLogPath returns the file path for a session's log
func (s *Service) getLogPath(sessionID string) string {
	return pathutil.MustJoin(s.sessionsDir, sessionID+".log")
}

// loadSessionFromDisk attempts to load session info from a log file
func (s *Service) loadSessionFromDisk(sessionID string) (*Session, error) {
	logPath := s.getLogPath(sessionID)
	info, err := os.Stat(logPath)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, fmt.Errorf("session not found: %s", sessionID)
		}
		return nil, fmt.Errorf("stat session log: %w", err)
	}

	// Return basic session info from file metadata
	return &Session{
		ID:        sessionID,
		Status:    "completed",
		StartedAt: info.ModTime(),
	}, nil
}

// cleanupLoop periodically removes old session files
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

	files, err := os.ReadDir(s.sessionsDir)
	if err != nil {
		return
	}

	for _, f := range files {
		if f.IsDir() || filepath.Ext(f.Name()) != ".log" {
			continue
		}
		info, err := f.Info()
		if err != nil {
			continue
		}
		if info.ModTime().Before(cutoff) {
			os.Remove(pathutil.MustJoin(s.sessionsDir, f.Name()))
		}
	}
}

// SetMetadata sets metadata on a session
func (s *Service) SetMetadata(sessionID, key string, value interface{}) {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if !exists {
		return
	}

	session.mu.Lock()
	if session.Metadata == nil {
		session.Metadata = make(map[string]interface{})
	}
	session.Metadata[key] = value
	session.mu.Unlock()
}
