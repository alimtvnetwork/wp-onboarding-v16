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
