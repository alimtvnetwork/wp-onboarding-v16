package site

import (
	"context"
	"encoding/json"
	"fmt"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// broadcastRemoteActionStarted sends session start logs and WS broadcast.
func (s *Service) broadcastRemoteActionStarted(ref *remoteActionRef) {
	logInput := RemoteActionLogInput{
		Level:   "info",
		Step:    "start",
		Message: fmt.Sprintf("Starting %s action for plugin: %s", ref.Action, ref.PluginSlug),
		Details: session.ToJSON(buildRemoteActionContext(ref)),
	}

	s.logRemoteAction(ref, logInput)
	s.saveRemoteActionRequest(ref)
	s.broadcastRemoteActionStartedWS(ref)
}

// buildRemoteActionContext creates context details for logging.
func buildRemoteActionContext(ref *remoteActionRef) RemoteActionContext {
	return RemoteActionContext{
		SiteId:     ref.SiteID,
		SiteName:   ref.Site.Name,
		SiteUrl:    ref.Site.Url,
		PluginSlug: ref.PluginSlug,
	}
}

// broadcastRemoteActionStartedWS sends the WS broadcast for action start.
func (s *Service) broadcastRemoteActionStartedWS(ref *remoteActionRef) {
	if s.wsHub == nil {
		return
	}

	event := RemoteActionStartedEvent{
		SiteId:     ref.SiteID,
		SiteName:   ref.Site.Name,
		Action:     ref.Action,
		PluginSlug: ref.PluginSlug,
	}

	s.wsHub.BroadcastWithSession("remote_plugin_action_started", event, ref.SessionID)
}

// saveRemoteActionRequest saves the request to the session log.
func (s *Service) saveRemoteActionRequest(ref *remoteActionRef) {
	if s.sessionService == nil || ref.SessionID == "" {

		return
	}

	body := toJson(RemoteActionRequestBody{
		SiteId:     ref.SiteID,
		PluginSlug: ref.PluginSlug,
		Action:     ref.Action,
	})

	s.sessionService.SaveRequest(ref.SessionID, &session.SessionRequest{
		URL:    wordpress.GoApiSitePluginRoute(ref.SiteID, ref.PluginSlug, ref.Action),
		Method: "POST",
		Body:   body,
	})
}

// handleRemoteActionError processes a failed remote action.
func (s *Service) handleRemoteActionError(ctx context.Context, ref *remoteActionRef, appErr *apperror.AppError, durationMs int64) {
	errDetails := s.extractErrorDetails(appErr)

	s.saveRemoteErrorResponse(ref.SessionID, errDetails, appErr)
	s.logRemoteErrorAction(ref, errDetails)
	s.logRemoteErrorStageEnd(ref, durationMs)
	s.finalizeRemoteError(ctx, ref, errDetails, appErr, durationMs)
}

// logRemoteErrorAction logs the error action details.
func (s *Service) logRemoteErrorAction(ref *remoteActionRef, errDetails *ExtractedErrorDetails) {
	logInput := RemoteActionLogInput{
		Level:   "error",
		Step:    ref.Action,
		Message: fmt.Sprintf("Failed to %s plugin: %s", ref.Action, ref.PluginSlug),
		Details: session.ToJSON(errDetails),
	}

	s.logRemoteAction(ref, logInput)
}

// logRemoteErrorStageEnd logs the stage end for a failed action.
func (s *Service) logRemoteErrorStageEnd(ref *remoteActionRef, durationMs int64) {
	if s.sessionService == nil || ref.SessionID == "" {
		return
	}

	stageInput := session.StageEndInput{
		SessionID:  ref.SessionID,
		StageName:  ref.Action,
		Status:     "error",
		DurationMs: durationMs,
	}

	s.sessionService.LogStageEnd(stageInput)
}

// finalizeRemoteError handles PHP errors, error file, session end, and broadcast.
func (s *Service) finalizeRemoteError(ctx context.Context, ref *remoteActionRef, errDetails *ExtractedErrorDetails, appErr *apperror.AppError, durationMs int64) {
	s.fetchAndAttachRemotePhpErrors(ref, errDetails)
	s.logToErrorFile(ref, errDetails)
	s.endRemoteSession(ref.SessionID, "error", appErr.Error())

	completeInput := remoteActionCompleteInput{
		Ref:        ref,
		IsSuccess:  false,
		ErrMsg:     appErr.Error(),
		DurationMs: durationMs,
	}

	s.broadcastRemoteActionComplete(completeInput)
}

// saveRemoteErrorResponse saves the error response to the session.
func (s *Service) saveRemoteErrorResponse(sessionId string, errDetails *ExtractedErrorDetails, appErr *apperror.AppError) {
	if s.sessionService == nil || sessionId == "" {

		return
	}

	s.saveRemoteErrorHttpResponse(sessionId, errDetails)
	s.saveRemoteErrorStackAndDetails(sessionId, errDetails, appErr)
}

// saveRemoteErrorHttpResponse saves the HTTP response portion of the error.
func (s *Service) saveRemoteErrorHttpResponse(sessionId string, errDetails *ExtractedErrorDetails) {
	bodyJson := buildErrorBodyJson(errDetails.ResponseBody)

	resp := &session.SessionResponse{
		RequestURL:  errDetails.Url,
		ResponseURL: errDetails.Url,
		StatusCode:  errDetails.StatusCode,
		Body:        bodyJson,
	}

	s.sessionService.SaveResponse(sessionId, resp)
}

// saveRemoteErrorStackAndDetails saves stack trace and error details.
func (s *Service) saveRemoteErrorStackAndDetails(sessionId string, errDetails *ExtractedErrorDetails, appErr *apperror.AppError) {
	phpFrames := s.buildPhpStackFrames(errDetails)
	goFrames := session.CaptureGoStack(2)

	saveInput := session.SaveErrorInput{
		SessionID: sessionId,
		StackTrace: &session.SessionStackTrace{
			Golang: goFrames,
			PHP:    phpFrames,
		},
		ErrorMsg: appErr.Error(),
		Details:  session.ToJSON(errDetails),
	}

	s.sessionService.SaveError(saveInput)
}

// buildErrorBodyJson converts a response body string to JSON.
func buildErrorBodyJson(responseBody string) json.RawMessage {
	isBodyEmpty := responseBody == ""

	if isBodyEmpty {

		return nil
	}

	if json.Valid([]byte(responseBody)) {

		return json.RawMessage(responseBody)
	}

	bodyJson, _ := json.Marshal(responseBody)

	return bodyJson
}

// handleRemoteActionSuccess processes a successful remote action.
func (s *Service) handleRemoteActionSuccess(ctx context.Context, ref *remoteActionRef, durationMs int64) {
	s.saveRemoteSuccessResponse(ref, durationMs)
	s.logRemoteSuccessAction(ref, durationMs)
	s.finalizeRemoteSuccess(ctx, ref, durationMs)
}

// logRemoteSuccessAction logs the success action details.
func (s *Service) logRemoteSuccessAction(ref *remoteActionRef, durationMs int64) {
	logInput := RemoteActionLogInput{
		Level:   "info",
		Step:    ref.Action,
		Message: fmt.Sprintf("Successfully %sd plugin: %s", ref.Action, ref.PluginSlug),
		Details: session.ToJSON(DurationDetail{DurationMs: durationMs}),
	}

	s.logRemoteAction(ref, logInput)
}

// finalizeRemoteSuccess invalidates cache, ends session, broadcasts, and logs.
func (s *Service) finalizeRemoteSuccess(ctx context.Context, ref *remoteActionRef, durationMs int64) {
	_ = s.InvalidateRemotePluginsCache(ctx, ref.SiteID)
	s.endRemoteSession(ref.SessionID, "success", "")

	completeInput := remoteActionCompleteInput{
		Ref:        ref,
		IsSuccess:  true,
		DurationMs: durationMs,
	}

	s.broadcastRemoteActionComplete(completeInput)
	s.log.Info(fmt.Sprintf("Remote plugin %sd", ref.Action), "siteId", ref.SiteID, "plugin", ref.PluginSlug)
}

// saveRemoteSuccessResponse records the success response in the session.
func (s *Service) saveRemoteSuccessResponse(ref *remoteActionRef, durationMs int64) {
	if s.sessionService == nil || ref.SessionID == "" {

		return
	}

	s.saveRemoteSuccessHttpResponse(ref)
	s.logRemoteSuccessStageEnd(ref, durationMs)
}

// saveRemoteSuccessHttpResponse saves the HTTP response for a successful action.
func (s *Service) saveRemoteSuccessHttpResponse(ref *remoteActionRef) {
	body := toJson(RemoteActionSuccessBody{
		IsSuccess: true,
		Action:    ref.Action,
		Plugin:    ref.PluginSlug,
	})

	resp := &session.SessionResponse{
		RequestURL:  wordpress.BuildWPPluginURL(ref.Site.Url, wordpress.RiseupAsiaNamespace, ep.Plugins),
		ResponseURL: ref.Site.Url,
		StatusCode:  200,
		Body:        body,
	}

	s.sessionService.SaveResponse(ref.SessionID, resp)
}

// logRemoteSuccessStageEnd logs the stage end for a successful action.
func (s *Service) logRemoteSuccessStageEnd(ref *remoteActionRef, durationMs int64) {
	stageInput := session.StageEndInput{
		SessionID:  ref.SessionID,
		StageName:  ref.Action,
		Status:     "success",
		DurationMs: durationMs,
	}

	s.sessionService.LogStageEnd(stageInput)
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

	event := RemoteActionCompleteEvent{
		SiteId:     input.Ref.SiteID,
		SiteName:   input.Ref.Site.Name,
		Action:     input.Ref.Action,
		PluginSlug: input.Ref.PluginSlug,
		IsSuccess:  input.IsSuccess,
		Error:      input.ErrMsg,
		DurationMs: input.DurationMs,
	}

	s.wsHub.BroadcastWithSession("remote_plugin_action_complete", event, input.Ref.SessionID)
}
