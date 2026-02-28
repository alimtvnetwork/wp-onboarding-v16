// Package session — query operations: get session, logs, diagnostics.
package session

import (
	"encoding/json"
	"os"
	"strings"

	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// GetSession returns session info
func (s *Service) GetSession(sessionID string) apperror.Result[*Session] {
	s.mu.RLock()
	session, exists := s.sessions[sessionID]
	s.mu.RUnlock()

	if exists {
		return apperror.Ok(session)
	}

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

	return s.readLogFileOrLegacy(sessionID, logPath)
}

// readLogFileOrLegacy reads the log file, falling back to legacy format.
func (s *Service) readLogFileOrLegacy(sessionID, logPath string) apperror.Result[string] {
	data, err := os.ReadFile(logPath)
	if err == nil {
		return apperror.Ok(string(data))
	}
	if os.IsNotExist(err) {
		return s.readLegacySessionLog(sessionID)
	}
	return apperror.FailWrap[string](err, apperror.ErrFSRead, "read session log").
		WithValue("sessionId", sessionID)
}

// readLegacySessionLog attempts to read a legacy flat-file session log.
func (s *Service) readLegacySessionLog(sessionID string) apperror.Result[string] {
	legacyPath, legacyErr := pathutil.Join(s.sessionsDir, sessionID+".log")
	if legacyErr != nil {
		return apperror.FailNew[string](apperror.ErrNotFound, "session not found").
			WithValue("sessionId", sessionID)
	}

	data, err := os.ReadFile(legacyPath)
	if err != nil {
		return apperror.FailNew[string](apperror.ErrNotFound, "session not found").
			WithValue("sessionId", sessionID)
	}
	return apperror.Ok(string(data))
}

// GetSessionDiagnostics returns the structured diagnostics for a session
func (s *Service) GetSessionDiagnostics(sessionID string) apperror.Result[SessionDiagnostics] {
	diag := SessionDiagnostics{}

	diag.Request = s.loadDiagnosticRequest(sessionID)
	diag.Response = s.loadDiagnosticResponse(sessionID)
	diag.StackTrace = s.loadDiagnosticStackTrace(sessionID)
	diag.PHPStackTraceLog = s.loadPHPStackTrace(sessionID)

	return apperror.Ok(diag)
}

// loadDiagnosticRequest reads request.json from the session directory.
func (s *Service) loadDiagnosticRequest(sessionID string) *SessionRequest {
	reqPath, err := s.getRequestPath(sessionID)
	if err != nil {
		return nil
	}
	data, err := os.ReadFile(reqPath)
	if err != nil {
		return nil
	}
	var req SessionRequest
	if json.Unmarshal(data, &req) != nil {
		return nil
	}
	return &req
}

// loadDiagnosticResponse reads response.json from the session directory.
func (s *Service) loadDiagnosticResponse(sessionID string) *SessionResponse {
	respPath, err := s.getResponsePath(sessionID)
	if err != nil {
		return nil
	}
	data, err := os.ReadFile(respPath)
	if err != nil {
		return nil
	}
	var resp SessionResponse
	if json.Unmarshal(data, &resp) != nil {
		return nil
	}
	return &resp
}

// loadDiagnosticStackTrace reads error.log and extracts the stack trace.
func (s *Service) loadDiagnosticStackTrace(sessionID string) *SessionStackTrace {
	errPath, err := s.getErrorLogPath(sessionID)
	if err != nil {
		return nil
	}
	data, err := os.ReadFile(errPath)
	if err != nil {
		return nil
	}
	var errorData ErrorLogData
	if json.Unmarshal(data, &errorData) != nil {
		return nil
	}
	return errorData.StackTrace
}

// loadPHPStackTrace extracts the PHP stacktrace from session logs.
func (s *Service) loadPHPStackTrace(sessionID string) string {
	logsResult := s.GetSessionLogs(sessionID)
	if !logsResult.IsSafe() {
		return ""
	}
	return extractPHPStackTraceFromLogs(logsResult.Value())
}

// extractPHPStackTraceFromLogs scans session log lines for the remote_php_stacktrace
// entry and extracts the embedded stacktrace.txt content from its JSON context.
func extractPHPStackTraceFromLogs(logs string) string {
	for _, line := range strings.Split(logs, "\n") {
		content := extractPHPContentFromLine(line)
		if content != "" {
			return content
		}
	}
	return ""
}

// extractPHPContentFromLine extracts PHP stacktrace content from a single log line.
func extractPHPContentFromLine(line string) string {
	if !strings.Contains(line, "remote_php_stacktrace") {
		return ""
	}
	braceIdx := strings.Index(line, "{")
	if braceIdx < 0 {
		return ""
	}
	return parsePHPContent(line[braceIdx:])
}

// parsePHPContent unmarshals the JSON fragment and returns the content field.
func parsePHPContent(jsonFragment string) string {
	// stackTraceContentContext extracts "content" from remote_php_stacktrace log JSON.
	type stackTraceContentContext struct {
		Content string `json:"content"` // external key (session log JSON)
	}
	var ctx stackTraceContentContext
	if json.Unmarshal([]byte(jsonFragment), &ctx) == nil {
		return ctx.Content
	}
	return ""
}

// loadSessionFromDisk attempts to load session info from disk
func (s *Service) loadSessionFromDisk(sessionID string) (*Session, error) {
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

	return s.loadLegacySession(sessionID)
}

// loadLegacySession loads a session from a legacy flat file.
func (s *Service) loadLegacySession(sessionID string) (*Session, error) {
	legacyPath, err := pathutil.Join(s.sessionsDir, sessionID+".log")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrSessionNotFound, "resolve legacy session path")
	}
	return s.statLegacyFile(sessionID, legacyPath)
}

// statLegacyFile stats the legacy file and returns a Session or a typed error.
func (s *Service) statLegacyFile(sessionID, legacyPath string) (*Session, error) {
	fi, statErr := pathutil.StatFile(legacyPath)
	if statErr != nil {
		return nil, wrapLegacyStatError(statErr, sessionID, legacyPath)
	}
	return &Session{
		ID:        sessionID,
		Status:    stagestatus.Completed.String(),
		StartedAt: fi.Info.ModTime(),
	}, nil
}

// wrapLegacyStatError wraps the stat error with appropriate context.
func wrapLegacyStatError(statErr error, sessionID, legacyPath string) error {
	if apperror.Is(statErr, apperror.ErrFSNotFound) {
		return apperror.New(apperror.ErrSessionNotFound, "session not found").
			WithDetails(sessionID)
	}
	return apperror.Wrap(statErr, apperror.ErrSessionNotFound, "stat session file").
		WithPath(legacyPath)
}
