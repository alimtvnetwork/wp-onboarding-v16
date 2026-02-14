// Package session provides session-based logging for operations
package session

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/google/uuid"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/pathutil"
)

// SessionType identifies the type of operation
type SessionType string

const (
	SessionTypePublish             SessionType = "publish"
	SessionTypeSync                SessionType = "sync"
	SessionTypeBackup              SessionType = "backup"
	SessionTypeConnect             SessionType = "connect"
	SessionTypeBulkPublish         SessionType = "bulk_publish"
	SessionTypeRemotePluginEnable  SessionType = "remote_plugin_enable"
	SessionTypeRemotePluginDisable SessionType = "remote_plugin_disable"
	SessionTypeRemotePluginDelete  SessionType = "remote_plugin_delete"
)

// Session represents an active or completed operation session
type Session struct {
	ID         string                 `json:"id"`
	Type       SessionType            `json:"type"`
	PluginID   int64                  `json:"pluginId,omitempty"`
	SiteID     int64                  `json:"siteId,omitempty"`
	PluginName string                 `json:"pluginName,omitempty"`
	SiteName   string                 `json:"siteName,omitempty"`
	Status     string                 `json:"status"` // running, success, error
	StartedAt  time.Time              `json:"startedAt"`
	EndedAt    *time.Time             `json:"endedAt,omitempty"`
	ErrorMsg   string                 `json:"errorMessage,omitempty"`
	Metadata   map[string]any         `json:"metadata,omitempty"`
	logFile    *os.File
	mu         sync.Mutex
}

// LogEntry represents a single log entry in a session
type LogEntry struct {
	Timestamp string                 `json:"timestamp"`
	Level     string                 `json:"level"`  // debug, info, warn, error
	Step      string                 `json:"step"`   // backup, package, upload, activate, etc.
	Message   string                 `json:"message"`
	Details   map[string]any         `json:"details,omitempty"`
}

// SessionDiagnostics is the structured payload returned for error modal / session detail view
type SessionDiagnostics struct {
	Request           *SessionRequest    `json:"request,omitempty"`
	Response          *SessionResponse   `json:"response,omitempty"`
	StackTrace        *SessionStackTrace `json:"stackTrace,omitempty"`
	PHPStackTraceLog  string             `json:"phpStackTraceLog,omitempty"`
}

// SessionRequest captures the original inbound request
type SessionRequest struct {
	URL     string                 `json:"url"`
	Method  string                 `json:"method"`
	Headers map[string]string      `json:"headers,omitempty"`
	Body    map[string]any         `json:"body,omitempty"`
}

// SessionResponse captures the delegated response from WordPress
type SessionResponse struct {
	RequestURL  string                 `json:"requestUrl"`
	ResponseURL string                 `json:"responseUrl"`
	StatusCode  int                    `json:"statusCode"`
	Headers     map[string]string      `json:"headers,omitempty"`
	Body        any                    `json:"body,omitempty"`
}

// SessionStackTrace holds dual Go + PHP stack traces
type SessionStackTrace struct {
	Golang []StackFrame `json:"golang,omitempty"`
	PHP    []StackFrame `json:"php,omitempty"`
}

// StackFrame represents a single frame in a stack trace
type StackFrame struct {
	Function string `json:"function"`
	File     string `json:"file,omitempty"`
	Line     int    `json:"line,omitempty"`
	Class    string `json:"class,omitempty"`
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

// getSessionDir returns the directory path for a session
func (s *Service) getSessionDir(sessionID string) string {
	return pathutil.MustJoin(s.sessionsDir, sessionID)
}

// getLogPath returns the file path for a session's main log
func (s *Service) getLogPath(sessionID string) string {
	return pathutil.MustJoin(s.getSessionDir(sessionID), "session.log")
}

// getRequestPath returns the file path for request.json
func (s *Service) getRequestPath(sessionID string) string {
	return pathutil.MustJoin(s.getSessionDir(sessionID), "request.json")
}

// getResponsePath returns the file path for response.json
func (s *Service) getResponsePath(sessionID string) string {
	return pathutil.MustJoin(s.getSessionDir(sessionID), "response.json")
}

// getErrorLogPath returns the file path for error.log
func (s *Service) getErrorLogPath(sessionID string) string {
	return pathutil.MustJoin(s.getSessionDir(sessionID), "error.log")
}

// StartSession creates a new session directory and returns its ID
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
		Metadata:   make(map[string]any),
	}

	// Create session directory
	sessionDir := s.getSessionDir(sessionID)
	if err := os.MkdirAll(sessionDir, 0755); err != nil {
		return "", fmt.Errorf("create session directory: %w", err)
	}

	// Create session.log file
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
func (s *Service) Log(sessionID, level, step, message string, details map[string]any) {
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

// SaveRequest persists the inbound request as request.json in the session folder
func (s *Service) SaveRequest(sessionID string, req *SessionRequest) {
	if req == nil {
		return
	}
	data, err := json.MarshalIndent(req, "", "  ")
	if err != nil {
		if s.log != nil {
			s.log.Error("Failed to marshal request JSON", "sessionId", sessionID, "error", err)
		}
		return
	}
	path := s.getRequestPath(sessionID)
	if err := os.WriteFile(path, data, 0644); err != nil {
		if s.log != nil {
			s.log.Error("Failed to write request.json", "sessionId", sessionID, "error", err)
		}
	}
}

// SaveResponse persists the delegated response as response.json in the session folder
func (s *Service) SaveResponse(sessionID string, resp *SessionResponse) {
	if resp == nil {
		return
	}
	data, err := json.MarshalIndent(resp, "", "  ")
	if err != nil {
		if s.log != nil {
			s.log.Error("Failed to marshal response JSON", "sessionId", sessionID, "error", err)
		}
		return
	}
	path := s.getResponsePath(sessionID)
	if err := os.WriteFile(path, data, 0644); err != nil {
		if s.log != nil {
			s.log.Error("Failed to write response.json", "sessionId", sessionID, "error", err)
		}
	}
}

// SaveError persists error details (including stack traces) as error.log in the session folder
func (s *Service) SaveError(sessionID string, stackTrace *SessionStackTrace, errorMsg string, details map[string]any) {
	errorData := map[string]any{
		"timestamp": time.Now().UTC().Format("2006-01-02 15:04:05 UTC"),
		"error":     errorMsg,
	}
	if stackTrace != nil {
		errorData["stackTrace"] = stackTrace
	}
	if len(details) > 0 {
		errorData["details"] = details
	}

	data, err := json.MarshalIndent(errorData, "", "  ")
	if err != nil {
		if s.log != nil {
			s.log.Error("Failed to marshal error data", "sessionId", sessionID, "error", err)
		}
		return
	}
	path := s.getErrorLogPath(sessionID)
	if err := os.WriteFile(path, data, 0644); err != nil {
		if s.log != nil {
			s.log.Error("Failed to write error.log", "sessionId", sessionID, "error", err)
		}
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
			// Fallback: check for legacy flat file
			legacyPath := pathutil.MustJoin(s.sessionsDir, sessionID+".log")
			data, err = os.ReadFile(legacyPath)
			if err != nil {
				return "", fmt.Errorf("session not found: %s", sessionID)
			}
			return string(data), nil
		}
		return "", fmt.Errorf("read session log: %w", err)
	}
	return string(data), nil
}

// GetSessionDiagnostics returns the structured diagnostics for a session (request, response, stackTrace)
func (s *Service) GetSessionDiagnostics(sessionID string) (*SessionDiagnostics, error) {
	diag := &SessionDiagnostics{}

	// Read request.json
	if data, err := os.ReadFile(s.getRequestPath(sessionID)); err == nil {
		var req SessionRequest
		if json.Unmarshal(data, &req) == nil {
			diag.Request = &req
		}
	}

	// Read response.json
	if data, err := os.ReadFile(s.getResponsePath(sessionID)); err == nil {
		var resp SessionResponse
		if json.Unmarshal(data, &resp) == nil {
			diag.Response = &resp
		}
	}

	// Read error.log for stack traces
	if data, err := os.ReadFile(s.getErrorLogPath(sessionID)); err == nil {
		var errorData map[string]any
		if json.Unmarshal(data, &errorData) == nil {
			if stData, ok := errorData["stackTrace"]; ok {
				stJSON, _ := json.Marshal(stData)
				var st SessionStackTrace
				if json.Unmarshal(stJSON, &st) == nil {
					diag.StackTrace = &st
				}
			}
		}
	}

	// Extract PHP stacktrace.txt content from session logs
	if logsContent, err := s.GetSessionLogs(sessionID); err == nil {
		diag.PHPStackTraceLog = extractPHPStackTraceFromLogs(logsContent)
	}

	return diag, nil
}

// extractPHPStackTraceFromLogs scans session log lines for the remote_php_stacktrace
// entry and extracts the embedded stacktrace.txt content from its JSON context.
func extractPHPStackTraceFromLogs(logs string) string {
	// Session logs are line-delimited; look for lines containing "remote_php_stacktrace"
	for _, line := range strings.Split(logs, "\n") {
		if !strings.Contains(line, "remote_php_stacktrace") {
			continue
		}
		// The log line has a JSON context block; extract "content" field
		// Find the JSON object in the line (after the log prefix)
		braceIdx := strings.Index(line, "{")
		if braceIdx < 0 {
			continue
		}
		var ctx map[string]any
		if json.Unmarshal([]byte(line[braceIdx:]), &ctx) == nil {
			if content, ok := ctx["content"].(string); ok && content != "" {
				return content
			}
		}
	}
	return ""
}

// ListSessions returns recent sessions
func (s *Service) ListSessions(limit int) ([]*SessionSummary, error) {
	if limit <= 0 {
		limit = 100
	}

	// Get all entries in sessions dir (now directories, not files)
	entries, err := os.ReadDir(s.sessionsDir)
	if err != nil {
		return nil, fmt.Errorf("read sessions directory: %w", err)
	}

	type dirInfo struct {
		name    string
		modTime time.Time
	}
	var sessionDirs []dirInfo

	for _, entry := range entries {
		if entry.IsDir() {
			// New folder-based sessions
			info, err := entry.Info()
			if err != nil {
				continue
			}
			sessionDirs = append(sessionDirs, dirInfo{
				name:    entry.Name(),
				modTime: info.ModTime(),
			})
		} else if filepath.Ext(entry.Name()) == ".log" {
			// Legacy flat file sessions
			info, err := entry.Info()
			if err != nil {
				continue
			}
			sessionDirs = append(sessionDirs, dirInfo{
				name:    entry.Name()[:len(entry.Name())-4], // Remove .log extension
				modTime: info.ModTime(),
			})
		}
	}

	// Sort by modification time (newest first)
	sort.Slice(sessionDirs, func(i, j int) bool {
		return sessionDirs[i].modTime.After(sessionDirs[j].modTime)
	})

	// Limit results
	if len(sessionDirs) > limit {
		sessionDirs = sessionDirs[:limit]
	}

	// Build summaries
	summaries := make([]*SessionSummary, 0, len(sessionDirs))
	for _, d := range sessionDirs {
		sessionID := d.name

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
			summaries = append(summaries, &SessionSummary{
				ID:        sessionID,
				Status:    "completed",
				StartedAt: d.modTime,
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

// DeleteSession removes a session's directory (or legacy file)
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

	// Try removing directory first (new format)
	sessionDir := s.getSessionDir(sessionID)
	if info, err := os.Stat(sessionDir); err == nil && info.IsDir() {
		if err := os.RemoveAll(sessionDir); err != nil {
			return fmt.Errorf("delete session directory: %w", err)
		}
		return nil
	}

	// Fallback: try removing legacy flat file
	legacyPath := pathutil.MustJoin(s.sessionsDir, sessionID+".log")
	if err := os.Remove(legacyPath); err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("delete session log: %w", err)
	}
	return nil
}

// loadSessionFromDisk attempts to load session info from disk
func (s *Service) loadSessionFromDisk(sessionID string) (*Session, error) {
	// Check for folder-based session
	sessionDir := s.getSessionDir(sessionID)
	if info, err := os.Stat(sessionDir); err == nil && info.IsDir() {
		return &Session{
			ID:        sessionID,
			Status:    "completed",
			StartedAt: info.ModTime(),
		}, nil
	}

	// Fallback: check for legacy flat file
	legacyPath := pathutil.MustJoin(s.sessionsDir, sessionID+".log")
	info, err := os.Stat(legacyPath)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, fmt.Errorf("session not found: %s", sessionID)
		}
		return nil, fmt.Errorf("stat session: %w", err)
	}

	return &Session{
		ID:        sessionID,
		Status:    "completed",
		StartedAt: info.ModTime(),
	}, nil
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
		info, err := entry.Info()
		if err != nil {
			continue
		}
		if info.ModTime().Before(cutoff) {
			fullPath := pathutil.MustJoin(s.sessionsDir, entry.Name())
			if entry.IsDir() {
				os.RemoveAll(fullPath)
			} else {
				os.Remove(fullPath)
			}
		}
	}
}

// ClearAllSessions removes all session directories and files
func (s *Service) ClearAllSessions() error {
	s.mu.Lock()
	// Close all open sessions
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

	// Remove all entries in sessions directory
	entries, err := os.ReadDir(s.sessionsDir)
	if err != nil {
		return fmt.Errorf("read sessions directory: %w", err)
	}

	var removeErrors []error
	for _, entry := range entries {
		fullPath := pathutil.MustJoin(s.sessionsDir, entry.Name())
		var err error
		if entry.IsDir() {
			err = os.RemoveAll(fullPath)
		} else {
			err = os.Remove(fullPath)
		}
		if err != nil && !os.IsNotExist(err) {
			removeErrors = append(removeErrors, err)
		}
	}

	if len(removeErrors) > 0 {
		return fmt.Errorf("failed to remove %d session entries", len(removeErrors))
	}

	if s.log != nil {
		s.log.Info("All sessions cleared", "count", len(entries))
	}

	return nil
}

// SetMetadata sets metadata on a session
func (s *Service) SetMetadata(sessionID, key string, value any) {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if !exists {
		return
	}

	session.mu.Lock()
	if session.Metadata == nil {
		session.Metadata = make(map[string]any)
	}
	session.Metadata[key] = value
	session.mu.Unlock()
}
