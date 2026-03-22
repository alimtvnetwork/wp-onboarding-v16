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

// BroadcastRemoteActionStarted converts site event to ws typed broadcast.
func (a *SiteWSHubAdapter) BroadcastRemoteActionStarted(data site.RemoteActionStartedEvent, sessionId string) {
	a.hub.BroadcastRemoteActionStarted(ws.RemoteActionStartedData{
		SiteId:     data.SiteId,
		SiteName:   data.SiteName,
		Action:     data.Action,
		PluginSlug: data.PluginSlug,
	}, sessionId)
}

// BroadcastRemoteActionComplete converts site event to ws typed broadcast.
func (a *SiteWSHubAdapter) BroadcastRemoteActionComplete(data site.RemoteActionCompleteEvent, sessionId string) {
	a.hub.BroadcastRemoteActionComplete(ws.RemoteActionCompleteData{
		SiteId:     data.SiteId,
		SiteName:   data.SiteName,
		Action:     data.Action,
		PluginSlug: data.PluginSlug,
		IsSuccess:  data.IsSuccess,
		Error:      data.Error,
		DurationMs: data.DurationMs,
	}, sessionId)
}
