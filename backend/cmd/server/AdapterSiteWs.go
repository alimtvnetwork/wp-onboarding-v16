package main

import (
	"encoding/json"

	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/ws"
)

// SiteWSHubAdapter bridges site.WSHub interface with *ws.Hub.
type SiteWSHubAdapter struct {
	hub *ws.Hub
}

// BroadcastConnectionTestProgress converts site.ConnectionProgressInput to ws types.
func (a *SiteWSHubAdapter) BroadcastConnectionTestProgress(data site.ConnectionProgressInput) {
	a.hub.BroadcastConnectionTestProgress(ws.ConnectionTestProgressData{
		SiteId:  data.SiteId,
		Step:    data.Step,
		Status:  data.Status,
		Message: data.Message,
		Details: data.Details,
	})
}

// BroadcastLog delegates to ws.Hub.BroadcastLog.
func (a *SiteWSHubAdapter) BroadcastLog(level string, message string, context json.RawMessage) {
	a.hub.BroadcastLog(level, message, context)
}

// BroadcastRemotePluginLogWithSession converts site.RemotePluginLogInput to ws types.
func (a *SiteWSHubAdapter) BroadcastRemotePluginLogWithSession(input site.RemotePluginLogInput) {
	a.hub.BroadcastRemotePluginLogWithSession(ws.RemotePluginLogInput{
		SiteId:    input.SiteId,
		Action:    input.Action,
		SessionId: input.SessionId,
		Level:     input.Level,
		Step:      input.Step,
		Message:   input.Message,
		Details:   input.Details,
	})
}

// BroadcastWithSession delegates to ws.Hub.BroadcastWithSession.
func (a *SiteWSHubAdapter) BroadcastWithSession(eventType string, data any, sessionId string) {
	a.hub.BroadcastWithSession(eventType, data, sessionId)
}
