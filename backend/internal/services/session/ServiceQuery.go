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
func (s *Service) GetSession(sessionId string) apperror.Result[*Session] {
	s.mu.RLock()
	session, isFound := s.sessions[sessionId]
	s.mu.RUnlock()

	if isFound {
		return apperror.Ok(session)
	}

	diskResult := s.loadSessionFromDisk(sessionId)
	if diskResult.HasError() {
		return apperror.Fail[*Session](diskResult.AppError())
	}

	return apperror.Ok(diskResult.Value())
}

// GetSessionLogs returns the full log content for a session
func (s *Service) GetSessionLogs(sessionId string) apperror.Result[string] {
	logResult := s.getLogPath(sessionId)

	if logResult.HasError() {
		return apperror.Fail[string](logResult.AppError())
	}

	return s.readLogFileOrLegacy(sessionId, logResult.Value())
}

// readLogFileOrLegacy reads the log file, falling back to legacy format.
func (s *Service) readLogFileOrLegacy(sessionId, logPath string) apperror.Result[string] {
	data, err := os.ReadFile(logPath)
	if err == nil {
		return apperror.Ok(string(data))
	}
	if os.IsNotExist(err) {
		return s.readLegacySessionLog(sessionId)
	}
	return apperror.FailWrap[string](err, apperror.ErrFSRead, "read session log").
		WithValue("sessionId", sessionId)
}

// readLegacySessionLog attempts to read a legacy flat-file session log.
func (s *Service) readLegacySessionLog(sessionId string) apperror.Result[string] {
	legacyPath, legacyErr := pathutil.Join(s.sessionsDir, sessionId+".log")
	if legacyErr != nil {
		return apperror.FailNew[string](apperror.ErrNotFound, "session not found").
			WithValue("sessionId", sessionId)
	}

	data, err := os.ReadFile(legacyPath)
	if err != nil {
		return apperror.FailNew[string](apperror.ErrNotFound, "session not found").
			WithValue("sessionId", sessionId)
	}
	return apperror.Ok(string(data))
}

// GetSessionDiagnostics returns the structured diagnostics for a session
func (s *Service) GetSessionDiagnostics(sessionId string) apperror.Result[SessionDiagnostics] {
	diag := SessionDiagnostics{}

	diag.Request = s.loadDiagnosticRequest(sessionId)
	diag.Response = s.loadDiagnosticResponse(sessionId)
	diag.StackTrace = s.loadDiagnosticStackTrace(sessionId)
	diag.PhpStackTraceLog = s.loadPhpStackTrace(sessionId)

	return apperror.Ok(diag)
}

// loadDiagnosticRequest reads request.json from the session directory.
func (s *Service) loadDiagnosticRequest(sessionId string) *SessionRequest {
	reqResult := s.getRequestPath(sessionId)

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
func (s *Service) loadDiagnosticResponse(sessionId string) *SessionResponse {
	respResult := s.getResponsePath(sessionId)

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
func (s *Service) loadDiagnosticStackTrace(sessionId string) *SessionStackTrace {
	errResult := s.getErrorLogPath(sessionId)

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
func (s *Service) loadPhpStackTrace(sessionId string) string {
	logsResult := s.GetSessionLogs(sessionId)
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
func (s *Service) loadSessionFromDisk(sessionId string) apperror.Result[*Session] {
	dirResult := s.getSessionDir(sessionId)

	if dirResult.HasError() {
		return apperror.Fail[*Session](dirResult.AppError())
	}

	sessionDir := dirResult.Value()

	dirInfo, dirErr := pathutil.StatDir(sessionDir)
	if dirErr == nil {
		return apperror.Ok(&Session{
			Id:        sessionId,
			Status:    stagestatus.Completed.String(),
			StartedAt: dirInfo.Info.ModTime(),
		})
	}

	return s.loadLegacySession(sessionId)
}

// loadLegacySession loads a session from a legacy flat file.
func (s *Service) loadLegacySession(sessionId string) apperror.Result[*Session] {
	legacyPath, err := pathutil.Join(s.sessionsDir, sessionId+".log")
	if err != nil {
		return apperror.FailWrap[*Session](err, apperror.ErrSessionNotFound, "resolve legacy session path")
	}

	return s.statLegacyFile(sessionId, legacyPath)
}

// statLegacyFile stats the legacy file and returns a Session or a typed error.
func (s *Service) statLegacyFile(sessionId, legacyPath string) apperror.Result[*Session] {
	fi, statErr := pathutil.StatFile(legacyPath)
	if statErr != nil {
		return apperror.Fail[*Session](wrapLegacyStatError(statErr, sessionId, legacyPath))
	}

	return apperror.Ok(&Session{
		Id:        sessionId,
		Status:    stagestatus.Completed.String(),
		StartedAt: fi.Info.ModTime(),
	})
}

// wrapLegacyStatError wraps the stat error with appropriate context.
func wrapLegacyStatError(statErr error, sessionId, legacyPath string) *apperror.AppError {
	if apperror.Is(statErr, apperror.ErrFSNotFound) {
		return apperror.New(apperror.ErrSessionNotFound, "session not found").
			WithDetails(sessionId)
	}

	return apperror.Wrap(statErr, apperror.ErrSessionNotFound, "stat session file").
		WithPath(legacyPath)
}
