package publish

import (
	"encoding/json"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
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
	if s.sessionService == nil || input.SessionId == "" {
		return
	}

	logInput := session.LogInput{
		SessionID: input.SessionId,
		Level:     input.Level.Lower(),
		Step:      input.Step.Value(),
		Message:   input.Message,
		Details:   input.Details,
	}
	s.sessionService.Log(logInput)
}

func (s *Service) sessionLogStageStart(sessionId, stageName string) {
	if s.sessionService == nil || sessionId == "" {
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
	if s.sessionService == nil || input.SessionId == "" {
		return
	}

	stageEnd := session.StageEndInput{
		SessionID:  input.SessionId,
		StageName:  input.StageName,
		Status:     input.Status.String(),
		DurationMs: input.DurationMs,
	}
	s.sessionService.LogStageEnd(stageEnd)
}

func (s *Service) endSession(sessionId, status, errorMsg string) {
	if s.sessionService == nil || sessionId == "" {
		return
	}
	s.sessionService.EndSession(sessionId, status, errorMsg)
}
