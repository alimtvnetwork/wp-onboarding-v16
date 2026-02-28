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

// StartSession creates a new session directory and returns its Id
func (s *Service) StartSession(input StartSessionInput) apperror.Result[string] {
	sessionId := uuid.New().String()
	session := buildNewSession(sessionId, input)

	initErr := s.initSessionDir(sessionId)
	if initErr != nil {
		return apperror.FailWrap[string](initErr, apperror.ErrSessionInit, "init session dir")
	}

	fileResult := s.createSessionLogFile(sessionId)
	if fileResult.HasError() {
		return apperror.Fail[string](fileResult.AppError())
	}

	session.logFile = fileResult.Value()

	return s.finalizeSessionStart(sessionId, session, input)
}

// finalizeSessionStart writes the header, registers the session, and returns the Id.
func (s *Service) finalizeSessionStart(sessionId string, session *Session, input StartSessionInput) apperror.Result[string] {
	writeSessionHeader(sessionHeaderInput{
		File:      session.logFile,
		SessionId: sessionId,
		Input:     input,
		StartedAt: session.StartedAt,
	})

	s.registerSession(sessionId, session)

	return apperror.Ok(sessionId)
}

// registerSession stores the session in the map and logs it.
func (s *Service) registerSession(sessionId string, session *Session) {
	s.mu.Lock()
	s.sessions[sessionId] = session
	s.mu.Unlock()

	isLogAvailable := s.log != nil

	if isLogAvailable {
		s.log.Info("Session started", "sessionId", sessionId, "type", session.Type)
	}
}

// buildNewSession constructs a new Session from the input.
func buildNewSession(sessionId string, input StartSessionInput) *Session {
	return &Session{
		Id:         sessionId,
		Type:       input.Type,
		PluginId:   input.PluginId,
		SiteId:     input.SiteId,
		PluginName: input.PluginName,
		SiteName:   input.SiteName,
		Status:     stagestatus.Running.String(),
		StartedAt:  time.Now().UTC(),
		Metadata:   json.RawMessage(`{}`),
	}
}

// initSessionDir creates the session directory on disk.
func (s *Service) initSessionDir(sessionId string) *apperror.AppError {
	dirResult := s.getSessionDir(sessionId)

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
func (s *Service) createSessionLogFile(sessionId string) apperror.Result[*os.File] {
	logResult := s.getLogPath(sessionId)

	if logResult.HasError() {
		return apperror.Fail[*os.File](logResult.AppError())
	}

	logPath := logResult.Value()
	file, createErr := os.Create(logPath)

	if createErr != nil {
		return apperror.FailWrap[*os.File](createErr, apperror.ErrSessionStore, "create session log file")
	}

	return apperror.Ok(file)
}

// sessionHeaderInput bundles parameters for writeSessionHeader.
type sessionHeaderInput struct {
	File      *os.File
	SessionId string
	Input     StartSessionInput
	StartedAt time.Time
}

// writeSessionHeader writes the formatted session header to the log file.
func writeSessionHeader(shi sessionHeaderInput) {
	header := "═══════════════════════════════════════════════════════════════════════════════\n"
	header += fmt.Sprintf(" SESSION: %s\n", shi.SessionId)
	header += fmt.Sprintf(" TYPE: %s\n", shi.Input.Type)
	header += fmt.Sprintf(" STARTED: %s\n", shi.StartedAt.Format("2006-01-02 15:04:05 UTC"))
	hasPluginName := shi.Input.PluginName != ""

	if hasPluginName {
		header += fmt.Sprintf(" PLUGIN: %s (ID: %d)\n", shi.Input.PluginName, shi.Input.PluginId)
	}

	hasSiteName := shi.Input.SiteName != ""

	if hasSiteName {
		header += fmt.Sprintf(" SITE: %s (ID: %d)\n", shi.Input.SiteName, shi.Input.SiteId)
	}
	header += "═══════════════════════════════════════════════════════════════════════════════\n\n"
	shi.File.WriteString(header)
}

// LogInput bundles parameters for Log.
type LogInput struct {
	SessionId string
	Level     string
	Step      string
	Message   string
	Details   json.RawMessage
}

// Log writes a log entry to the session
func (s *Service) Log(input LogInput) {
	session := s.getActiveSession(input.SessionId)
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
func (s *Service) getActiveSession(sessionId string) *Session {
	s.mu.RLock()
	session, isFound := s.sessions[sessionId]
	s.mu.RUnlock()

	isMissing := !isFound

	if isMissing {
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
	isDetailsEmpty := len(details) == 0

	if isDetailsEmpty {
		return
	}
	var parsedJson json.RawMessage
	isParseable := json.Unmarshal(details, &parsedJson) == nil

	if isParseable {
		detailsJson, _ := json.MarshalIndent(parsedJson, "    ", "  ")
		file.WriteString(fmt.Sprintf("    %s\n", string(detailsJson)))
	}
}
