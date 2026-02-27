package site

import (
	"context"
	"encoding/json"
	"fmt"

	"wp-plugin-publish/internal/services/session"
)

// broadcastRemoteActionStarted sends session start logs and WS broadcast.
func (s *Service) broadcastRemoteActionStarted(ref *remoteActionRef) {
	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "start", Message: fmt.Sprintf("Starting %s action for plugin: %s", ref.Action, ref.PluginSlug), Details: session.ToJSON(RemoteActionContext{SiteId: ref.SiteID, SiteName: ref.Site.Name, SiteUrl: ref.Site.Url, PluginSlug: ref.PluginSlug})})

	s.saveRemoteActionRequest(ref)

	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_started", RemoteActionStartedEvent{SiteId: ref.SiteID, SiteName: ref.Site.Name, Action: ref.Action, PluginSlug: ref.PluginSlug}, ref.SessionID)
	}
}

// saveRemoteActionRequest saves the request to the session log.
func (s *Service) saveRemoteActionRequest(ref *remoteActionRef) {
	if s.sessionService == nil || ref.SessionID == "" {
		return
	}
	s.sessionService.SaveRequest(ref.SessionID, &session.SessionRequest{
		URL:    fmt.Sprintf("/api/v1/sites/%d/remote-plugins/%s/%s", ref.SiteID, ref.PluginSlug, ref.Action),
		Method: "POST",
		Body:   toJson(RemoteActionRequestBody{SiteId: ref.SiteID, PluginSlug: ref.PluginSlug, Action: ref.Action}),
	})
}

// handleRemoteActionError processes a failed remote action: logs, broadcasts, writes error file.
func (s *Service) handleRemoteActionError(ctx context.Context, ref *remoteActionRef, err error, durationMs int64) {
	errDetails := s.extractErrorDetails(err)

	s.saveRemoteErrorResponse(ref.SessionID, errDetails, err)
	s.logRemoteAction(ref, RemoteActionLogInput{Level: "error", Step: ref.Action, Message: fmt.Sprintf("Failed to %s plugin: %s", ref.Action, ref.PluginSlug), Details: session.ToJSON(errDetails)})

	if s.sessionService != nil && ref.SessionID != "" {
		s.sessionService.LogStageEnd(session.StageEndInput{SessionID: ref.SessionID, StageName: ref.Action, Status: "error", DurationMs: durationMs})
	}

	s.fetchAndAttachRemotePhpErrors(ref, errDetails)
	s.logToErrorFile(ref, errDetails)
	s.endRemoteSession(ref.SessionID, "error", err.Error())
	s.broadcastRemoteActionComplete(remoteActionCompleteInput{Ref: ref, IsSuccess: false, ErrMsg: err.Error(), DurationMs: durationMs})
}

// saveRemoteErrorResponse saves the error response to the session.
func (s *Service) saveRemoteErrorResponse(sessionId string, errDetails *ExtractedErrorDetails, err error) {
	if s.sessionService == nil || sessionId == "" {
		return
	}

	bodyJson := buildErrorBodyJson(errDetails.ResponseBody)

	s.sessionService.SaveResponse(sessionId, &session.SessionResponse{RequestURL: errDetails.Url, ResponseURL: errDetails.Url, StatusCode: errDetails.StatusCode, Body: bodyJson})
	phpFrames := s.buildPhpStackFrames(errDetails)
	goFrames := session.CaptureGoStack(2)
	s.sessionService.SaveError(session.SaveErrorInput{SessionID: sessionId, StackTrace: &session.SessionStackTrace{Golang: goFrames, PHP: phpFrames}, ErrorMsg: err.Error(), Details: session.ToJSON(errDetails)})
}

// buildErrorBodyJson converts a response body string to JSON.
func buildErrorBodyJson(responseBody string) json.RawMessage {
	if responseBody == "" {
		return nil
	}
	if json.Valid([]byte(responseBody)) {
		return json.RawMessage(responseBody)
	}
	bodyJson, _ := json.Marshal(responseBody)

	return bodyJson
}

// handleRemoteActionSuccess processes a successful remote action: logs, broadcasts, invalidates cache.
func (s *Service) handleRemoteActionSuccess(ctx context.Context, ref *remoteActionRef, durationMs int64) {
	s.saveRemoteSuccessResponse(ref, durationMs)

	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: ref.Action, Message: fmt.Sprintf("Successfully %sd plugin: %s", ref.Action, ref.PluginSlug), Details: session.ToJSON(DurationDetail{DurationMs: durationMs})})
	_ = s.InvalidateRemotePluginsCache(ctx, ref.SiteID)
	s.endRemoteSession(ref.SessionID, "success", "")
	s.broadcastRemoteActionComplete(remoteActionCompleteInput{Ref: ref, IsSuccess: true, DurationMs: durationMs})

	s.log.Info(fmt.Sprintf("Remote plugin %sd", ref.Action), "siteId", ref.SiteID, "plugin", ref.PluginSlug)
}

// saveRemoteSuccessResponse records the success response in the session.
func (s *Service) saveRemoteSuccessResponse(ref *remoteActionRef, durationMs int64) {
	if s.sessionService == nil || ref.SessionID == "" {
		return
	}
	s.sessionService.SaveResponse(ref.SessionID, &session.SessionResponse{
		RequestURL:  fmt.Sprintf("%s/wp-json/riseup-asia-uploader/v1/plugins/%s", ref.Site.Url, ref.Action),
		ResponseURL: ref.Site.Url, StatusCode: 200,
		Body: toJson(RemoteActionSuccessBody{Success: true, Action: ref.Action, Plugin: ref.PluginSlug}),
	})
	s.sessionService.LogStageEnd(session.StageEndInput{SessionID: ref.SessionID, StageName: ref.Action, Status: "success", DurationMs: durationMs})
}

// remoteActionCompleteInput bundles parameters for broadcastRemoteActionComplete.
type remoteActionCompleteInput struct {
	Ref        *remoteActionRef
	IsSuccess  bool
	ErrMsg     string
	DurationMs int64
}

// broadcastRemoteActionComplete sends a WebSocket broadcast for action completion.
func (s *Service) broadcastRemoteActionComplete(input remoteActionCompleteInput) {
	if s.wsHub == nil {
		return
	}
	s.wsHub.BroadcastWithSession("remote_plugin_action_complete", RemoteActionCompleteEvent{
		SiteId:     input.Ref.SiteID,
		SiteName:   input.Ref.Site.Name,
		Action:     input.Ref.Action,
		PluginSlug: input.Ref.PluginSlug,
		IsSuccess:  input.IsSuccess,
		Error:      input.ErrMsg,
		DurationMs: input.DurationMs,
	}, input.Ref.SessionID)
}
