// Package session — stage markers and session end lifecycle.
package session

import (
	"fmt"
	"time"

	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
)

// LogStageStart writes a stage header to the session log
func (s *Service) LogStageStart(sessionId, stageName string) {
	session := s.getActiveSession(sessionId)
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
	SessionId  string
	StageName  string
	Status     string
	DurationMs int64
}

// LogStageEnd writes a stage completion marker
func (s *Service) LogStageEnd(input StageEndInput) {
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
func (s *Service) EndSession(sessionId, status, errorMsg string) {
	s.mu.Lock()
	session, isFound := s.sessions[sessionId]
	s.mu.Unlock()

	isMissing := !isFound

	if isMissing {
		return
	}

	session.mu.Lock()
	defer session.mu.Unlock()

	applyEndState(session, status, errorMsg)
	s.logSessionEnd(sessionId, status)
}

// applyEndState updates the session fields and writes the footer.
func applyEndState(session *Session, status, errorMsg string) {
	now := time.Now().UTC()
	session.Status = status
	session.EndedAt = &now
	session.ErrorMsg = errorMsg

	footerInput := sessionFooterInput{
		Session:  session,
		Now:      now,
		Status:   status,
		ErrorMsg: errorMsg,
	}
	writeSessionFooter(footerInput)
}

// logSessionEnd logs session end if logger is available.
func (s *Service) logSessionEnd(sessionId, status string) {
	isLogAvailable := s.log != nil

	if isLogAvailable {
		s.log.Info("Session ended", "sessionId", sessionId, "status", status)
	}
}

// sessionFooterInput bundles parameters for writeSessionFooter.
type sessionFooterInput struct {
	Session  *Session
	Now      time.Time
	Status   string
	ErrorMsg string
}

// writeSessionFooter writes the end-of-session footer and closes the log file.
func writeSessionFooter(input sessionFooterInput) {
	isLogFileMissing := input.Session.logFile == nil

	if isLogFileMissing {
		return
	}

	strInput := footerStringInput{
		StartedAt: input.Session.StartedAt,
		Now:       input.Now,
		Status:    input.Status,
		ErrorMsg:  input.ErrorMsg,
	}
	footer := buildFooterString(strInput)
	input.Session.logFile.WriteString(footer)
	input.Session.logFile.Close()
	input.Session.logFile = nil
}

// footerStringInput bundles parameters for buildFooterString.
type footerStringInput struct {
	StartedAt time.Time
	Now       time.Time
	Status    string
	ErrorMsg  string
}

// buildFooterString formats the session end footer.
func buildFooterString(input footerStringInput) string {
	duration := input.Now.Sub(input.StartedAt)
	footer := "\n═══════════════════════════════════════════════════════════════════════════════\n"
	footer += fmt.Sprintf(" SESSION ENDED: %s\n", input.Now.Format("2006-01-02 15:04:05 UTC"))
	footer += fmt.Sprintf(" STATUS: %s\n", input.Status)
	footer += fmt.Sprintf(" DURATION: %v\n", duration.Round(time.Millisecond))
	hasErrorMsg := input.ErrorMsg != ""

	if hasErrorMsg {
		footer += fmt.Sprintf(" ERROR: %s\n", input.ErrorMsg)
	}
	footer += "═══════════════════════════════════════════════════════════════════════════════\n"
	return footer
}
