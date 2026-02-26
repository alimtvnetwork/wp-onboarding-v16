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
	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/pkg/apperror"
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
	ID         string          `json:",omitempty"`
	Type       SessionType
	PluginID   int64           `json:",omitempty"`
	SiteID     int64           `json:",omitempty"`
	PluginName string          `json:",omitempty"`
	SiteName   string          `json:",omitempty"`
	Status     string          // running, success, error
	StartedAt  time.Time
	EndedAt    *time.Time      `json:",omitempty"`
	ErrorMsg   string          `json:",omitempty"`
	Metadata   json.RawMessage `json:",omitempty"`
	logFile    *os.File
	mu         sync.Mutex
}

// LogEntry represents a single log entry in a session
type LogEntry struct {
	Timestamp string
	Level     string          // debug, info, warn, error
	Step      string          // backup, package, upload, activate, etc.
	Message   string
	Details   json.RawMessage `json:",omitempty"`
}

// SessionDiagnostics is the structured payload returned for error modal / session detail view
type SessionDiagnostics struct {
	Request           *SessionRequest    `json:",omitempty"`
	Response          *SessionResponse   `json:",omitempty"`
	StackTrace        *SessionStackTrace `json:",omitempty"`
	PHPStackTraceLog  string             `json:",omitempty"`
}

// SessionRequest captures the original inbound request
type SessionRequest struct {
	URL     string
	Method  string
	Headers map[string]string `json:",omitempty"`
	Body    json.RawMessage   `json:",omitempty"`
}

// SessionResponse captures the delegated response from WordPress
type SessionResponse struct {
	RequestURL  string
	ResponseURL string
	StatusCode  int
	Headers     map[string]string `json:",omitempty"`
	Body        json.RawMessage   `json:",omitempty"`
}

// SessionStackTrace holds dual Go + PHP stack traces
type SessionStackTrace struct {
	Golang []StackFrame `json:",omitempty"`
	PHP    []StackFrame `json:",omitempty"`
}

// StackFrame represents a single frame in a stack trace
type StackFrame struct {
	Function string
	File     string `json:",omitempty"`
	Line     int    `json:",omitempty"`
	Class    string `json:",omitempty"`
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

	sessionsDir, err := pathutil.Join(cfg.DataDir, "sessions")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionInit, "resolve sessions directory")
	}

	// Ensure sessions directory exists
	if err := os.MkdirAll(sessionsDir, 0755); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionInit, "create sessions directory").
			WithPath(sessionsDir)
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
func (s *Service) getSessionDir(sessionID string) (string, error) {
	return pathutil.Join(s.sessionsDir, sessionID)
}

// getLogPath returns the file path for a session's main log
func (s *Service) getLogPath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "session.log")
}

// getRequestPath returns the file path for request.json
func (s *Service) getRequestPath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "request.json")
}

// getResponsePath returns the file path for response.json
func (s *Service) getResponsePath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "response.json")
}

// getErrorLogPath returns the file path for error.log
func (s *Service) getErrorLogPath(sessionID string) (string, error) {
	dir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", err
	}
	return pathutil.Join(dir, "error.log")
}

// StartSession creates a new session directory and returns its ID
func (s *Service) StartSession(input StartSessionInput) (string, error) {
	sessionType := input.Type
	pluginID := input.PluginID
	siteID := input.SiteID
	pluginName := input.PluginName
	siteName := input.SiteName
	sessionID := uuid.New().String()

	session := &Session{
		ID:         sessionID,
		Type:       sessionType,
		PluginID:   pluginID,
		SiteID:     siteID,
		PluginName: pluginName,
		SiteName:   siteName,
		Status:     stagestatus.Running.String(),
		StartedAt:  time.Now().UTC(),
		Metadata:   json.RawMessage(`{}`),
	}

	// Create session directory
	sessionDir, err := s.getSessionDir(sessionID)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrSessionInit, "resolve session directory")
	}
	if err := os.MkdirAll(sessionDir, 0755); err != nil {
		return "", apperror.Wrap(err, apperror.ErrSessionInit, "create session directory").
			WithPath(sessionDir)
	}

	// Create session.log file
	logPath, err := s.getLogPath(sessionID)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrSessionInit, "resolve session log path")
	}
	file, err := os.Create(logPath)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrSessionStore, "create session log file").
			WithPath(logPath)
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
func (s *Service) Log(sessionID, level, step, message string, details json.RawMessage) {
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
	parsed, parseErr := loglevel.Parse(level)
	levelUpper := loglevel.Info.String()
	if parseErr == nil {
		levelUpper = strings.ToUpper(parsed.String())
	}

	// Format: [YYYY-MM-DD HH:MM:SS] [LEVEL] [step] Message
	logLine := fmt.Sprintf("[%s] [%s] [%s] %s\n", timestamp, levelUpper, step, message)
	session.logFile.WriteString(logLine)

	// Write details as indented JSON if present
	if len(details) > 0 {
		// Re-indent the raw JSON for readability
		var parsed json.RawMessage
		if json.Unmarshal(details, &parsed) == nil {
			detailsJSON, _ := json.MarshalIndent(parsed, "    ", "  ")
			session.logFile.WriteString(fmt.Sprintf("    %s\n", string(detailsJSON)))
		}
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
	parsedStatus, _ := stagestatus.Parse(status)
	if parsedStatus.IsFailed() {
		statusIcon = "✗"
	} else if parsedStatus.IsSkipped() {
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
	path, err := s.getRequestPath(sessionID)
	if err != nil {
		if s.log != nil {
			s.log.Error("Failed to resolve request path", "sessionId", sessionID, "error", err)
		}
		return
	}
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
	path, err := s.getResponsePath(sessionID)
	if err != nil {
		if s.log != nil {
			s.log.Error("Failed to resolve response path", "sessionId", sessionID, "error", err)
		}
		return
	}
	if err := os.WriteFile(path, data, 0644); err != nil {
		if s.log != nil {
			s.log.Error("Failed to write response.json", "sessionId", sessionID, "error", err)
		}
	}
}

// ErrorLogData is the typed structure persisted as error.log in session folders.
type ErrorLogData struct {
	Timestamp  string             `json:"timestamp"`  // external key (error.log JSON file)
	Error      string             `json:"error"`      // external key
	StackTrace *SessionStackTrace `json:"stackTrace,omitempty"` // external key
	Details    json.RawMessage    `json:"details,omitempty"`    // external key
}

// SaveError persists error details (including stack traces) as error.log in the session folder
func (s *Service) SaveError(sessionID string, stackTrace *SessionStackTrace, errorMsg string, details json.RawMessage) {
	errorData := ErrorLogData{
		Timestamp:  time.Now().UTC().Format("2006-01-02 15:04:05 UTC"),
		Error:      errorMsg,
		StackTrace: stackTrace,
		Details:    details,
	}

	data, err := json.MarshalIndent(errorData, "", "  ")
	if err != nil {
		if s.log != nil {
			s.log.Error("Failed to marshal error data", "sessionId", sessionID, "error", err)
		}
		return
	}
	path, err := s.getErrorLogPath(sessionID)
	if err != nil {
		if s.log != nil {
			s.log.Error("Failed to resolve error log path", "sessionId", sessionID, "error", err)
		}
		return
	}
	if err := os.WriteFile(path, data, 0644); err != nil {
		if s.log != nil {
			s.log.Error("Failed to write error.log", "sessionId", sessionID, "error", err)
		}
	}
}

// GetSession returns session info
func (s *Service) GetSession(sessionID string) apperror.Result[*Session] {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if exists {
		return apperror.Ok(session)
	}

	// Try to load from disk
	loaded, err := s.loadSessionFromDisk(sessionID)
	if err != nil {
		return apperror.FailWrap[*Session](err, apperror.ErrNotFound, "session not found").
			WithValue("sessionId", sessionID)
	}
	return apperror.Ok(loaded)
}

// GetSessionLogs returns the full log content for a session
func (s *Service) GetSessionLogs(sessionID string) apperror.Result[string] {
	logPath, err := s.getLogPath(sessionID)
	if err != nil {
		return apperror.FailWrap[string](err, apperror.ErrFSRead, "resolve session log path").
			WithValue("sessionId", sessionID)
	}
	data, err := os.ReadFile(logPath)
	if err != nil {
		if os.IsNotExist(err) {
			// Fallback: check for legacy flat file
			legacyPath, legacyErr := pathutil.Join(s.sessionsDir, sessionID+".log")
			if legacyErr != nil {
				return apperror.FailNew[string](apperror.ErrNotFound, "session not found").
					WithValue("sessionId", sessionID)
			}
			data, err = os.ReadFile(legacyPath)
			if err != nil {
				return apperror.FailNew[string](apperror.ErrNotFound, "session not found").
					WithValue("sessionId", sessionID)
			}
			return apperror.Ok(string(data))
		}
		return apperror.FailWrap[string](err, apperror.ErrFSRead, "read session log").
			WithValue("sessionId", sessionID)
	}
	return apperror.Ok(string(data))
}

// GetSessionDiagnostics returns the structured diagnostics for a session (request, response, stackTrace)
func (s *Service) GetSessionDiagnostics(sessionID string) apperror.Result[SessionDiagnostics] {
	diag := SessionDiagnostics{}

	// Read request.json
	if reqPath, err := s.getRequestPath(sessionID); err == nil {
		if data, err := os.ReadFile(reqPath); err == nil {
			var req SessionRequest
			if json.Unmarshal(data, &req) == nil {
				diag.Request = &req
			}
		}
	}

	// Read response.json
	if respPath, err := s.getResponsePath(sessionID); err == nil {
		if data, err := os.ReadFile(respPath); err == nil {
			var resp SessionResponse
			if json.Unmarshal(data, &resp) == nil {
				diag.Response = &resp
			}
		}
	}

	// Read error.log for stack traces
	if errPath, err := s.getErrorLogPath(sessionID); err == nil {
		if data, err := os.ReadFile(errPath); err == nil {
			var errorData ErrorLogData
			if json.Unmarshal(data, &errorData) == nil {
				if errorData.StackTrace != nil {
					diag.StackTrace = errorData.StackTrace
				}
			}
		}
	}

	// Extract PHP stacktrace.txt content from session logs
	logsResult := s.GetSessionLogs(sessionID)
	if logsResult.IsSafe() {
		diag.PHPStackTraceLog = extractPHPStackTraceFromLogs(logsResult.Value())
	}

	return apperror.Ok(diag)
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
		// stackTraceContentContext is used to extract the "content" field from
		// remote_php_stacktrace log lines serialised as JSON.
		type stackTraceContentContext struct {
			Content string `json:"content"` // external key (session log JSON)
		}
		var ctx stackTraceContentContext
		if json.Unmarshal([]byte(line[braceIdx:]), &ctx) == nil {
			if ctx.Content != "" {
				return ctx.Content
			}
		}
	}
	return ""
}

// ListSessions returns recent sessions
func (s *Service) ListSessions(limit int) apperror.ResultSlice[*SessionSummary] {
	if limit <= 0 {
		limit = 100
	}

	// Get all entries in sessions dir (now directories, not files)
	entries, err := os.ReadDir(s.sessionsDir)
	if err != nil {
		return apperror.FailSliceWrap[*SessionSummary](err, apperror.ErrFSRead, "read sessions directory")
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
				Status:    stagestatus.Completed.String(),
				StartedAt: d.modTime,
			})
		}
	}

	return apperror.OkSlice(summaries)
}

// SessionSummary provides a brief overview of a session
type SessionSummary struct {
	ID         string
	Type       SessionType
	PluginID   int64      `json:",omitempty"`
	SiteID     int64      `json:",omitempty"`
	PluginName string     `json:",omitempty"`
	SiteName   string     `json:",omitempty"`
	Status     string
	StartedAt  time.Time
	EndedAt    *time.Time `json:",omitempty"`
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
	sessionDir, err := s.getSessionDir(sessionID)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionDelete, "resolve session directory")
	}
	if pathutil.IsDir(sessionDir) {
		if err := os.RemoveAll(sessionDir); err != nil {
			return apperror.Wrap(err, apperror.ErrSessionDelete, "delete session directory").
				WithPath(sessionDir)
		}

		return nil
	}

	// Fallback: try removing legacy flat file
	legacyPath, err := pathutil.Join(s.sessionsDir, sessionID+".log")
	if err != nil {
		return apperror.Wrap(err, apperror.ErrSessionDelete, "resolve legacy session path")
	}
	if err := os.Remove(legacyPath); err != nil && !os.IsNotExist(err) {
		return apperror.Wrap(err, apperror.ErrSessionDelete, "delete session log").
			WithPath(legacyPath)
	}
	return nil
}

// loadSessionFromDisk attempts to load session info from disk
func (s *Service) loadSessionFromDisk(sessionID string) (*Session, error) {
	// Check for folder-based session
	sessionDir, err := s.getSessionDir(sessionID)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionNotFound, "resolve session directory")
	}
	dirInfo, dirErr := pathutil.StatDir(sessionDir)
	if dirErr == nil {
		return &Session{
			ID:        sessionID,
			Status:    stagestatus.Completed.String(),
			StartedAt: dirInfo.Info.ModTime(),
		}, nil
	}

	// Fallback: check for legacy flat file
	legacyPath, err := pathutil.Join(s.sessionsDir, sessionID+".log")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionNotFound, "resolve legacy session path")
	}
	fi, statErr := pathutil.StatFile(legacyPath)
	if statErr != nil {
		if apperror.Is(statErr, apperror.ErrFSNotFound) {
			return nil, apperror.New(apperror.ErrSessionNotFound, "session not found").
				WithDetails(sessionID)
		}

		return nil, apperror.Wrap(statErr, apperror.ErrSessionNotFound, "stat session file").
			WithPath(legacyPath)
	}

	return &Session{
		ID:        sessionID,
		Status:    stagestatus.Completed.String(),
		StartedAt: fi.Info.ModTime(),
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
			fullPath, err := pathutil.Join(s.sessionsDir, entry.Name())
			if err != nil {
				continue
			}
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
		return apperror.Wrap(err, apperror.ErrSessionClear, "read sessions directory").
			WithPath(s.sessionsDir)
	}

	var removeErrors []error
	for _, entry := range entries {
		fullPath, err := pathutil.Join(s.sessionsDir, entry.Name())
		if err != nil {
			continue
		}
		var removeErr error
		if entry.IsDir() {
			removeErr = os.RemoveAll(fullPath)
		} else {
			removeErr = os.Remove(fullPath)
		}
		if removeErr != nil && !os.IsNotExist(removeErr) {
			removeErrors = append(removeErrors, removeErr)
		}
	}

	if len(removeErrors) > 0 {
		return apperror.New(apperror.ErrSessionClear, "failed to remove session entries").
			WithDetails(fmt.Sprintf("count=%d", len(removeErrors)))
	}

	if s.log != nil {
		s.log.Info("All sessions cleared", "count", len(entries))
	}

	return nil
}

// SetMetadata sets a key-value pair on a session's metadata JSON object.
func (s *Service) SetMetadata(sessionID, key string, value json.RawMessage) {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if !exists {
		return
	}

	session.mu.Lock()
	var m map[string]json.RawMessage
	if len(session.Metadata) == 0 || json.Unmarshal(session.Metadata, &m) != nil {
		m = make(map[string]json.RawMessage)
	}
	m[key] = value
	session.Metadata, _ = json.Marshal(m)
	session.mu.Unlock()
}
