package publish

import (
	"encoding/json"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/services/session"
)

// ─── Session Logging Helpers ─────────────────────────────────────────────────

// sessionLogInput bundles parameters for sessionLog.
type sessionLogInput struct {
	SessionId string
	Level     loglevel.Variant
	Step      publishstep.Variant
	Message   string
	Details   json.RawMessage
}

func (s *Service) sessionLog(input sessionLogInput) {
	isSessionUnavailable := s.sessionService == nil
	isSessionIdMissing := input.SessionId == ""

	if isSessionUnavailable || isSessionIdMissing {
		return
	}

	logInput := session.LogInput{
		SessionId: input.SessionId,
		Level:     input.Level.Lower(),
		Step:      input.Step.Value(),
		Message:   input.Message,
		Details:   input.Details,
	}
	s.sessionService.Log(logInput)
}

func (s *Service) sessionLogStageStart(sessionId, stageName string) {
	isSessionUnavailable := s.sessionService == nil
	isSessionIdMissing := sessionId == ""

	if isSessionUnavailable || isSessionIdMissing {
		return
	}
	s.sessionService.LogStageStart(sessionId, stageName)
}

// sessionStageEndInput bundles parameters for sessionLogStageEnd.
type sessionStageEndInput struct {
	SessionId  string
	StageName  string
	Status     stagestatus.Variant
	DurationMs int64
}

func (s *Service) sessionLogStageEnd(input sessionStageEndInput) {
	isSessionUnavailable := s.sessionService == nil
	isSessionIdMissing := input.SessionId == ""

	if isSessionUnavailable || isSessionIdMissing {
		return
	}

	stageEnd := session.StageEndInput{
		SessionId:  input.SessionId,
		StageName:  input.StageName,
		Status:     input.Status.String(),
		DurationMs: input.DurationMs,
	}
	s.sessionService.LogStageEnd(stageEnd)
}

func (s *Service) endSession(sessionId, status, errorMsg string) {
	isSessionUnavailable := s.sessionService == nil
	isSessionIdMissing := sessionId == ""

	if isSessionUnavailable || isSessionIdMissing {
		return
	}
	s.sessionService.EndSession(sessionId, status, errorMsg)
}
