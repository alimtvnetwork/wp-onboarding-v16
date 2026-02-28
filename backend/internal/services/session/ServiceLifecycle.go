// Package session — session lifecycle: start, log, stage markers, end.
package session

import (
	"encoding/json"
	"fmt"
	"os"
	"strings"
	"time"

	"github.com/google/uuid"
	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
)

// StartSession creates a new session directory and returns its ID
func (s *Service) StartSession(input StartSessionInput) (string, *apperror.AppError) {
	sessionID := uuid.New().String()
	session := buildNewSession(sessionID, input)

	initErr := s.initSessionDir(sessionID)
	if initErr != nil {

		return "", initErr
	}

	file, fileErr := s.createSessionLogFile(sessionID)
	if fileErr != nil {

		return "", fileErr
	}
	session.logFile = file

	writeSessionHeader(file, sessionID, input, session.StartedAt)
	s.registerSession(sessionID, session)

	return sessionID, nil
}

// registerSession stores the session in the map and logs it.
func (s *Service) registerSession(sessionID string, session *Session) {
	s.mu.Lock()
	s.sessions[sessionID] = session
	s.mu.Unlock()

	isLogAvailable := s.log != nil

	if isLogAvailable {
		s.log.Info("Session started", "sessionId", sessionID, "type", session.Type)
	}
}

// buildNewSession constructs a new Session from the input.
func buildNewSession(sessionID string, input StartSessionInput) *Session {
	return &Session{
		ID:         sessionID,
		Type:       input.Type,
		PluginID:   input.PluginID,
		SiteID:     input.SiteID,
		PluginName: input.PluginName,
		SiteName:   input.SiteName,
		Status:     stagestatus.Running.String(),
		StartedAt:  time.Now().UTC(),
		Metadata:   json.RawMessage(`{}`),
	}
}

// initSessionDir creates the session directory on disk.
func (s *Service) initSessionDir(sessionID string) *apperror.AppError {
	dirResult := s.getSessionDir(sessionID)

	if dirResult.HasError() {
		return dirResult.AppError()
	}

	sessionDir := dirResult.Value()
	mkErr := os.MkdirAll(sessionDir, 0755)

	if mkErr != nil {
		return apperror.Wrap(mkErr, apperror.ErrSessionInit, "create session directory").
			WithPath(sessionDir)
	}

	return nil
}

// createSessionLogFile creates and returns the session.log file handle.
func (s *Service) createSessionLogFile(sessionID string) (*os.File, *apperror.AppError) {
	logResult := s.getLogPath(sessionID)

	if logResult.HasError() {
		return nil, logResult.AppError()
	}

	logPath := logResult.Value()
	file, createErr := os.Create(logPath)

	if createErr != nil {

		return nil, apperror.Wrap(createErr, apperror.ErrSessionStore, "create session log file").
			WithPath(logPath)
	}

	return file, nil
}

// writeSessionHeader writes the formatted session header to the log file.
func writeSessionHeader(file *os.File, sessionID string, input StartSessionInput, startedAt time.Time) {
	header := "═══════════════════════════════════════════════════════════════════════════════\n"
	header += fmt.Sprintf(" SESSION: %s\n", sessionID)
	header += fmt.Sprintf(" TYPE: %s\n", input.Type)
	header += fmt.Sprintf(" STARTED: %s\n", startedAt.Format("2006-01-02 15:04:05 UTC"))
	hasPluginName := input.PluginName != ""

	if hasPluginName {
		header += fmt.Sprintf(" PLUGIN: %s (ID: %d)\n", input.PluginName, input.PluginID)
	}

	hasSiteName := input.SiteName != ""

	if hasSiteName {
		header += fmt.Sprintf(" SITE: %s (ID: %d)\n", input.SiteName, input.SiteID)
	}
	header += "═══════════════════════════════════════════════════════════════════════════════\n\n"
	file.WriteString(header)
}

// LogInput bundles parameters for Log.
type LogInput struct {
	SessionID string
	Level     string
	Step      string
	Message   string
	Details   json.RawMessage
}

// Log writes a log entry to the session
func (s *Service) Log(input LogInput) {
	session := s.getActiveSession(input.SessionID)
	isSessionMissing := session == nil

	if isSessionMissing {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	isLogFileMissing := session.logFile == nil

	if isLogFileMissing {
		return
	}

	logLine := formatLogLine(input)
	session.logFile.WriteString(logLine)
	writeLogDetails(session.logFile, input.Details)
}

// getActiveSession returns the in-memory session or nil if not found.
func (s *Service) getActiveSession(sessionID string) *Session {
	s.mu.RLock()
	session, isFound := s.sessions[sessionID]
	s.mu.RUnlock()

	isNotFound := !isFound

	if isNotFound {
		return nil
	}
	return session
}

// formatLogLine formats a timestamped log line string.
func formatLogLine(input LogInput) string {
	timestamp := time.Now().UTC().Format("2006-01-02 15:04:05")
	parsed, parseErr := loglevel.Parse(input.Level)
	levelUpper := loglevel.Info.String()
	isParsed := parseErr == nil

	if isParsed {
		levelUpper = strings.ToUpper(parsed.String())
	}
	return fmt.Sprintf("[%s] [%s] [%s] %s\n", timestamp, levelUpper, input.Step, input.Message)
}

// writeLogDetails writes indented JSON details to the log file if present.
func writeLogDetails(file *os.File, details json.RawMessage) {
	hasNoDetails := len(details) == 0

	if hasNoDetails {
		return
	}
	var parsedJSON json.RawMessage
	isParseable := json.Unmarshal(details, &parsedJSON) == nil

	if isParseable {
		detailsJSON, _ := json.MarshalIndent(parsedJSON, "    ", "  ")
		file.WriteString(fmt.Sprintf("    %s\n", string(detailsJSON)))
	}
}

// LogStageStart writes a stage header to the session log
func (s *Service) LogStageStart(sessionID, stageName string) {
	session := s.getActiveSession(sessionID)
	isSessionMissing := session == nil

	if isSessionMissing {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	isLogFileMissing := session.logFile == nil

	if isLogFileMissing {
		return
	}

	session.logFile.WriteString(buildStageHeader(stageName))
}

// buildStageHeader formats a stage start header string.
func buildStageHeader(stageName string) string {
	header := "\n───────────────────────────────────────────────────────────────────────────────\n"
	header += fmt.Sprintf(" STAGE: %s\n", stageName)
	header += "───────────────────────────────────────────────────────────────────────────────\n"
	return header
}

// StageEndInput bundles parameters for LogStageEnd.
type StageEndInput struct {
	SessionID  string
	StageName  string
	Status     string
	DurationMs int64
}

// LogStageEnd writes a stage completion marker
func (s *Service) LogStageEnd(input StageEndInput) {
	session := s.getActiveSession(input.SessionID)
	isSessionMissing := session == nil

	if isSessionMissing {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	isLogFileMissing := session.logFile == nil

	if isLogFileMissing {
		return
	}

	statusIcon := resolveStageIcon(input.Status)
	footer := fmt.Sprintf("\n%s STAGE %s completed (%s) in %dms\n", statusIcon, input.StageName, input.Status, input.DurationMs)
	session.logFile.WriteString(footer)
}

// resolveStageIcon returns the icon character for a stage status.
func resolveStageIcon(status string) string {
	parsedStatus, _ := stagestatus.Parse(status)
	if parsedStatus.IsFailed() {
		return "✗"
	}
	if parsedStatus.IsSkipped() {
		return "○"
	}
	return "✓"
}

// EndSession marks a session as complete
func (s *Service) EndSession(sessionID, status, errorMsg string) {
	s.mu.Lock()
	session, isFound := s.sessions[sessionID]
	s.mu.Unlock()

	isNotFound := !isFound

	if isNotFound {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	applyEndState(session, status, errorMsg)
	s.logSessionEnd(sessionID, status)
}

// applyEndState updates the session fields and writes the footer.
func applyEndState(session *Session, status, errorMsg string) {
	now := time.Now().UTC()
	session.Status = status
	session.EndedAt = &now
	session.ErrorMsg = errorMsg
	writeSessionFooter(session, now, status, errorMsg)
}

// logSessionEnd logs session end if logger is available.
func (s *Service) logSessionEnd(sessionID, status string) {
	isLogAvailable := s.log != nil

	if isLogAvailable {
		s.log.Info("Session ended", "sessionId", sessionID, "status", status)
	}
}

// writeSessionFooter writes the end-of-session footer and closes the log file.
func writeSessionFooter(session *Session, now time.Time, status, errorMsg string) {
	isLogFileMissing := session.logFile == nil

	if isLogFileMissing {
		return
	}

	footer := buildFooterString(session.StartedAt, now, status, errorMsg)
	session.logFile.WriteString(footer)
	session.logFile.Close()
	session.logFile = nil
}

// buildFooterString formats the session end footer.
func buildFooterString(startedAt, now time.Time, status, errorMsg string) string {
	duration := now.Sub(startedAt)
	footer := "\n═══════════════════════════════════════════════════════════════════════════════\n"
	footer += fmt.Sprintf(" SESSION ENDED: %s\n", now.Format("2006-01-02 15:04:05 UTC"))
	footer += fmt.Sprintf(" STATUS: %s\n", status)
	footer += fmt.Sprintf(" DURATION: %v\n", duration.Round(time.Millisecond))
	hasErrorMsg := errorMsg != ""

	if hasErrorMsg {
		footer += fmt.Sprintf(" ERROR: %s\n", errorMsg)
	}
	footer += "═══════════════════════════════════════════════════════════════════════════════\n"
	return footer
}
