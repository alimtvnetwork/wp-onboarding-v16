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
	session, isFound := s.sessions[sessionID]
	s.mu.RUnlock()

	if isFound {
		return apperror.Ok(session)
	}

	diskResult := s.loadSessionFromDisk(sessionID)
	if diskResult.HasError() {
		return apperror.Fail[*Session](diskResult.AppError())
	}

	return apperror.Ok(diskResult.Value())
}

// GetSessionLogs returns the full log content for a session
func (s *Service) GetSessionLogs(sessionID string) apperror.Result[string] {
	logResult := s.getLogPath(sessionID)

	if logResult.HasError() {
		return apperror.Fail[string](logResult.AppError())
	}

	return s.readLogFileOrLegacy(sessionID, logResult.Value())
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
	diag.PhpStackTraceLog = s.loadPhpStackTrace(sessionID)

	return apperror.Ok(diag)
}

// loadDiagnosticRequest reads request.json from the session directory.
func (s *Service) loadDiagnosticRequest(sessionID string) *SessionRequest {
	reqResult := s.getRequestPath(sessionID)

	if reqResult.HasError() {
		return nil
	}

	data, err := os.ReadFile(reqResult.Value())

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
	respResult := s.getResponsePath(sessionID)

	if respResult.HasError() {
		return nil
	}

	data, err := os.ReadFile(respResult.Value())

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
	errResult := s.getErrorLogPath(sessionID)

	if errResult.HasError() {
		return nil
	}

	data, err := os.ReadFile(errResult.Value())

	if err != nil {
		return nil
	}

	var errorData ErrorLogData

	if json.Unmarshal(data, &errorData) != nil {
		return nil
	}

	return errorData.StackTrace
}

// loadPhpStackTrace extracts the PHP stacktrace from session logs.
func (s *Service) loadPhpStackTrace(sessionID string) string {
	logsResult := s.GetSessionLogs(sessionID)
	isLogsUnavailable := !logsResult.IsSafe()

	if isLogsUnavailable {
		return ""
	}
	return extractPhpStackTraceFromLogs(logsResult.Value())
}

// extractPhpStackTraceFromLogs scans session log lines for the remote_php_stacktrace
// entry and extracts the embedded stacktrace.txt content from its JSON context.
func extractPhpStackTraceFromLogs(logs string) string {
	for _, line := range strings.Split(logs, "\n") {
		content := extractPhpContentFromLine(line)
		if content != "" {
			return content
		}
	}
	return ""
}

// extractPhpContentFromLine extracts PHP stacktrace content from a single log line.
func extractPhpContentFromLine(line string) string {
	isUnrelatedLine := !strings.Contains(line, "remote_php_stacktrace")

	if isUnrelatedLine {
		return ""
	}
	braceIdx := strings.Index(line, "{")
	isBraceMissing := braceIdx < 0

	if isBraceMissing {
		return ""
	}
	return parsePhpContent(line[braceIdx:])
}

// parsePhpContent unmarshals the JSON fragment and returns the content field.
func parsePhpContent(jsonFragment string) string {
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
func (s *Service) loadSessionFromDisk(sessionID string) apperror.Result[*Session] {
	dirResult := s.getSessionDir(sessionID)

	if dirResult.HasError() {
		return apperror.Fail[*Session](dirResult.AppError())
	}

	sessionDir := dirResult.Value()

	dirInfo, dirErr := pathutil.StatDir(sessionDir)
	if dirErr == nil {
		return apperror.Ok(&Session{
			Id:        sessionID,
			Status:    stagestatus.Completed.String(),
			StartedAt: dirInfo.Info.ModTime(),
		})
	}

	return s.loadLegacySession(sessionID)
}

// loadLegacySession loads a session from a legacy flat file.
func (s *Service) loadLegacySession(sessionID string) apperror.Result[*Session] {
	legacyPath, err := pathutil.Join(s.sessionsDir, sessionID+".log")
	if err != nil {
		return apperror.FailWrap[*Session](err, apperror.ErrSessionNotFound, "resolve legacy session path")
	}

	return s.statLegacyFile(sessionID, legacyPath)
}

// statLegacyFile stats the legacy file and returns a Session or a typed error.
func (s *Service) statLegacyFile(sessionID, legacyPath string) apperror.Result[*Session] {
	fi, statErr := pathutil.StatFile(legacyPath)
	if statErr != nil {
		return apperror.Fail[*Session](wrapLegacyStatError(statErr, sessionID, legacyPath))
	}

	return apperror.Ok(&Session{
		Id:        sessionID,
		Status:    stagestatus.Completed.String(),
		StartedAt: fi.Info.ModTime(),
	})
}

// wrapLegacyStatError wraps the stat error with appropriate context.
func wrapLegacyStatError(statErr error, sessionID, legacyPath string) *apperror.AppError {
	if apperror.Is(statErr, apperror.ErrFSNotFound) {
		return apperror.New(apperror.ErrSessionNotFound, "session not found").
			WithDetails(sessionID)
	}

	return apperror.Wrap(statErr, apperror.ErrSessionNotFound, "stat session file").
		WithPath(legacyPath)
}
