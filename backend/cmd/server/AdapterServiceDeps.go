package main

import (
	"encoding/json"

	"wp-plugin-publish/internal/models"
	publishhistory "wp-plugin-publish/internal/services/publish_history"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/pkg/apperror"
)

// SiteSessionAdapter bridges *session.Service to site.SessionService.
type SiteSessionAdapter struct {
	service *session.Service
}

// NewSiteSessionAdapter creates a site session adapter.
func NewSiteSessionAdapter(service *session.Service) *SiteSessionAdapter {
	return &SiteSessionAdapter{service: service}
}

// StartSession delegates to session.Service.StartSession.
func (a *SiteSessionAdapter) StartSession(input session.StartSessionInput) apperror.Result[string] {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return apperror.Fail[string](
			apperror.New(apperror.ErrSessionInit, "session service is not initialized"),
		)
	}

	return a.service.StartSession(input)
}

// Log adapts site-style log parameters into session.LogInput.
func (a *SiteSessionAdapter) Log(sessionId, level, step, message string, details json.RawMessage) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return
	}

	a.service.Log(session.LogInput{
		SessionId: sessionId,
		Level:     level,
		Step:      step,
		Message:   message,
		Details:   details,
	})
}

// LogStageStart delegates to session.Service.LogStageStart.
func (a *SiteSessionAdapter) LogStageStart(sessionId, stageName string) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return
	}

	a.service.LogStageStart(sessionId, stageName)
}

// LogStageEnd delegates to session.Service.LogStageEnd.
func (a *SiteSessionAdapter) LogStageEnd(sessionId, stageName, status string, durationMs int64) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return
	}

	a.service.LogStageEnd(sessionId, stageName, status, durationMs)
}

// EndSession delegates to session.Service.EndSession.
func (a *SiteSessionAdapter) EndSession(sessionId, status, errorMsg string) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return
	}

	a.service.EndSession(sessionId, status, errorMsg)
}

// SaveRequest delegates to session.Service.SaveRequest.
func (a *SiteSessionAdapter) SaveRequest(sessionId string, req *session.SessionRequest) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return
	}

	a.service.SaveRequest(sessionId, req)
}

// SaveResponse delegates to session.Service.SaveResponse.
func (a *SiteSessionAdapter) SaveResponse(sessionId string, resp *session.SessionResponse) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return
	}

	a.service.SaveResponse(sessionId, resp)
}

// SaveError delegates to session.Service.SaveError.
func (a *SiteSessionAdapter) SaveError(sessionId string, stackTrace *session.SessionStackTrace, errorMsg string, details json.RawMessage) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return
	}

	a.service.SaveError(sessionId, stackTrace, errorMsg, details)
}

// PublishHistoryRecorderAdapter bridges *publishhistory.Service to publish.PublishHistoryRecorder.
type PublishHistoryRecorderAdapter struct {
	service *publishhistory.Service
}

// NewPublishHistoryRecorderAdapter creates a publish history adapter.
func NewPublishHistoryRecorderAdapter(service *publishhistory.Service) *PublishHistoryRecorderAdapter {
	return &PublishHistoryRecorderAdapter{service: service}
}

// Record delegates to publishhistory.Service.Record and exposes standard error.
func (a *PublishHistoryRecorderAdapter) Record(entry models.PublishHistory) (*models.PublishHistory, error) {
	isServiceMissing := a == nil || a.service == nil

	if isServiceMissing {
		return nil, apperror.New(apperror.ErrInternal, "publish history service is not initialized")
	}

	saved, appErr := a.service.Record(entry)

	if appErr != nil {
		return nil, appErr
	}

	return saved, nil
}
