// Package session — stage markers and session end lifecycle.
package session

import (
	"fmt"
	"time"

	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
)

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

	isMissing := !isFound

	if isMissing {
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
