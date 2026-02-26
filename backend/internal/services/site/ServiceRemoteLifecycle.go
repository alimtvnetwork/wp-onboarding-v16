package site

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// remoteActionRef bundles the recurring identifiers and dependencies that flow
// through every remote plugin action (enable/disable/delete).
type remoteActionRef struct {
	SessionID  string
	SiteID     int64
	Action     string
	PluginSlug string
	Site       *models.Site
	Client     *wordpress.Client
}

// CheckRemotePluginExists performs a lightweight pre-flight check
func (s *Service) CheckRemotePluginExists(ctx context.Context, siteId int64, pluginSlug string) (*wordpress.PluginExistsResult, error) {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return nil, apperror.Wrap(result.AppError(), apperror.ErrNotFound, "site not found")
	}
	site := result.Value()
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	client := s.wpClientFactory(site.Url, site.Username, string(password), nil)

	return client.CheckPluginExistsViaUploader(pluginSlug)
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func (s *Service) EnableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	input := remoteActionInput{
		SiteId:     siteId,
		PluginSlug: pluginSlug,
		Action:     "enable",
		ExecFn: func(client *wordpress.Client) error {
			return client.EnablePluginViaUploader(pluginSlug)
		},
	}

	return s.executeRemotePluginAction(ctx, input)
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
func (s *Service) DisableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	input := remoteActionInput{
		SiteId:     siteId,
		PluginSlug: pluginSlug,
		Action:     "disable",
		ExecFn: func(client *wordpress.Client) error {
			return client.DisablePluginViaUploader(pluginSlug)
		},
	}

	return s.executeRemotePluginAction(ctx, input)
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site
func (s *Service) DeleteRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	input := remoteActionInput{
		SiteId:     siteId,
		PluginSlug: pluginSlug,
		Action:     "delete",
		ExecFn: func(client *wordpress.Client) error {
			if disableErr := client.DisablePluginViaUploader(pluginSlug); disableErr != nil {
				if apiErr := wordpress.ExtractAPIError(disableErr); apiErr != nil && apiErr.StatusCode == http.StatusNotFound {
					s.log.Info("Plugin not found during pre-delete disable (skipped safely)", "slug", pluginSlug)
				} else {
					s.log.Warn("Pre-delete disable failed (continuing with delete)", "slug", pluginSlug, "error", disableErr.Error())
				}
			}

			return client.DeletePluginViaUploader(pluginSlug)
		},
	}

	return s.executeRemotePluginAction(ctx, input)
}

// remoteActionInput bundles parameters for executeRemotePluginAction.
type remoteActionInput struct {
	SiteId     int64
	PluginSlug string
	Action     string
	ExecFn     func(*wordpress.Client) error
}

// executeRemotePluginAction runs a remote plugin action with session logging
func (s *Service) executeRemotePluginAction(ctx context.Context, input remoteActionInput) error {
	startTime := time.Now()

	ref, err := s.setupRemoteActionRef(ctx, input)
	if err != nil {
		return err
	}

	client, err := s.connectForRemoteAction(ref)
	if err != nil {
		return err
	}
	ref.Client = client

	return s.runRemoteAction(ctx, ref, startTime, input.ExecFn)
}

// setupRemoteActionRef resolves the site, creates the ref, and starts session.
func (s *Service) setupRemoteActionRef(ctx context.Context, input remoteActionInput) (*remoteActionRef, error) {
	site, err := s.resolveRemoteSite(ctx, input.SiteId)
	if err != nil {
		return nil, err
	}

	ref := &remoteActionRef{
		SiteID:     input.SiteId,
		Action:     input.Action,
		PluginSlug: input.PluginSlug,
		Site:       &site,
	}

	ref.SessionID = s.initRemoteActionSession(ref)
	s.broadcastRemoteActionStarted(ref)

	return ref, nil
}

// runRemoteAction executes the action and handles success/failure.
func (s *Service) runRemoteAction(ctx context.Context, ref *remoteActionRef, startTime time.Time, execFn func(*wordpress.Client) error) error {
	s.logRemoteStageStart(ref)

	err := execFn(ref.Client)
	durationMs := time.Since(startTime).Milliseconds()

	if err != nil {
		s.handleRemoteActionError(ctx, ref, err, durationMs)

		return err
	}

	s.handleRemoteActionSuccess(ctx, ref, durationMs)

	return nil
}

// resolveRemoteSite fetches and returns the site model for a remote action.
func (s *Service) resolveRemoteSite(ctx context.Context, siteId int64) (models.Site, error) {
	siteResult := s.GetById(ctx, siteId)
	if siteResult.HasError() {
		return models.Site{}, siteResult.AppError()
	}

	return siteResult.Value(), nil
}

// initRemoteActionSession starts a session for the remote action and returns the session ID.
func (s *Service) initRemoteActionSession(ref *remoteActionRef) string {
	if s.sessionService == nil {
		return ""
	}

	sessionType := resolveRemoteSessionType(ref.Action)

	startInput := session.StartSessionInput{
		Type: sessionType, PluginID: 0, SiteID: ref.SiteID,
		PluginName: ref.PluginSlug, SiteName: ref.Site.Name,
	}
	sessionId, _ := s.sessionService.StartSession(startInput)

	return sessionId
}

// resolveRemoteSessionType maps an action string to a session type.
func resolveRemoteSessionType(action string) session.SessionType {
	switch action {
	case "enable":
		return session.SessionTypeRemotePluginEnable
	case "disable":
		return session.SessionTypeRemotePluginDisable
	case "delete":
		return session.SessionTypeRemotePluginDelete
	default:
		return session.SessionType("remote_plugin_action")
	}
}

// broadcastRemoteActionStarted sends session start logs and WS broadcast.
func (s *Service) broadcastRemoteActionStarted(ref *remoteActionRef) {
	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "start", Message: fmt.Sprintf("Starting %s action for plugin: %s", ref.Action, ref.PluginSlug), Details: session.ToJSON(RemoteActionContext{SiteId: ref.SiteID, SiteName: ref.Site.Name, SiteUrl: ref.Site.Url, PluginSlug: ref.PluginSlug})})

	if s.sessionService != nil && ref.SessionID != "" {
		s.sessionService.SaveRequest(ref.SessionID, &session.SessionRequest{
			URL:    fmt.Sprintf("/api/v1/sites/%d/remote-plugins/%s/%s", ref.SiteID, ref.PluginSlug, ref.Action),
			Method: "POST",
			Body:   toJson(RemoteActionRequestBody{SiteId: ref.SiteID, PluginSlug: ref.PluginSlug, Action: ref.Action}),
		})
	}

	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_started", RemoteActionStartedEvent{SiteId: ref.SiteID, SiteName: ref.Site.Name, Action: ref.Action, PluginSlug: ref.PluginSlug}, ref.SessionID)
	}
}

// connectForRemoteAction decrypts credentials and creates a WordPress client.
func (s *Service) connectForRemoteAction(ref *remoteActionRef) (*wordpress.Client, error) {
	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "decrypt", Message: "Decrypting site credentials..."})

	password, err := decrypt(ref.Site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		errMsg := "failed to decrypt password"
		s.logRemoteAction(ref, RemoteActionLogInput{Level: "error", Step: "decrypt", Message: errMsg, Details: session.ToJSON(ErrorDetail{Error: err.Error()})})
		s.endRemoteSession(ref.SessionID, "error", errMsg)

		return nil, apperror.Wrap(err, apperror.ErrInternal, errMsg)
	}

	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "connect", Message: fmt.Sprintf("Connecting to WordPress site: %s", ref.Site.Url)})

	return s.wpClientFactory(ref.Site.Url, ref.Site.Username, string(password), nil), nil
}

// logRemoteStageStart logs the stage start for the remote action execution.
func (s *Service) logRemoteStageStart(ref *remoteActionRef) {
	if s.sessionService != nil && ref.SessionID != "" {
		s.sessionService.LogStageStart(ref.SessionID, ref.Action)
	}
	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: ref.Action, Message: fmt.Sprintf("Executing %s action on plugin: %s", ref.Action, ref.PluginSlug), Details: session.ToJSON(RemoteActionExecDetails{TargetUrl: ref.Site.Url, PluginSlug: ref.PluginSlug})})
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
