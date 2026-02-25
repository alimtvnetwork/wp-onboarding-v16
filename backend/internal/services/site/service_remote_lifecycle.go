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

// CheckRemotePluginExists performs a lightweight pre-flight check
func (s *Service) CheckRemotePluginExists(ctx context.Context, siteId int64, pluginSlug string) (bool, string, string, error) {
	result := s.GetById(ctx, siteId)
	if result.HasError() {
		return false, "", "", apperror.Wrap(result.AppError(), apperror.ErrNotFound, "site not found")
	}
	site := result.Value()
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		return false, "", "", apperror.Wrap(err, apperror.ErrInternal, "failed to decrypt password")
	}
	client := s.wpClientFactory(site.Url, site.Username, string(password), nil)
	return client.CheckPluginExistsViaUploader(pluginSlug)
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func (s *Service) EnableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	return s.executeRemotePluginAction(ctx, siteId, pluginSlug, "enable", func(client *wordpress.Client) error {
		return client.EnablePluginViaUploader(pluginSlug)
	})
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
func (s *Service) DisableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	return s.executeRemotePluginAction(ctx, siteId, pluginSlug, "disable", func(client *wordpress.Client) error {
		return client.DisablePluginViaUploader(pluginSlug)
	})
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site
func (s *Service) DeleteRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) error {
	return s.executeRemotePluginAction(ctx, siteId, pluginSlug, "delete", func(client *wordpress.Client) error {
		if disableErr := client.DisablePluginViaUploader(pluginSlug); disableErr != nil {
			if apiErr, ok := disableErr.(*wordpress.APIError); ok && apiErr.StatusCode == http.StatusNotFound {
				s.log.Info("Plugin not found during pre-delete disable (skipped safely)", "slug", pluginSlug)
			} else {
				s.log.Warn("Pre-delete disable failed (continuing with delete)", "slug", pluginSlug, "error", disableErr.Error())
			}
		}
		return client.DeletePluginViaUploader(pluginSlug)
	})
}

// executeRemotePluginAction runs a remote plugin action with session logging
func (s *Service) executeRemotePluginAction(ctx context.Context, siteId int64, pluginSlug, action string, execFn func(*wordpress.Client) error) error {
	startTime := time.Now()

	site, err := s.resolveRemoteSite(ctx, siteId)
	if err != nil {
		return err
	}

	sessionId := s.initRemoteActionSession(siteId, pluginSlug, action, &site)
	s.broadcastRemoteActionStarted(siteId, pluginSlug, action, &site, sessionId)

	client, err := s.connectForRemoteAction(siteId, action, &site, sessionId)
	if err != nil {
		return err
	}

	s.logRemoteStageStart(sessionId, siteId, action, pluginSlug, &site)

	err = execFn(client)
	durationMs := time.Since(startTime).Milliseconds()

	if err != nil {
		s.handleRemoteActionError(ctx, client, sessionId, siteId, action, pluginSlug, &site, err, durationMs)
		return err
	}

	s.handleRemoteActionSuccess(ctx, sessionId, siteId, action, pluginSlug, &site, durationMs)
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
func (s *Service) initRemoteActionSession(siteId int64, pluginSlug, action string, site *models.Site) string {
	if s.sessionService == nil {
		return ""
	}

	var sessionType session.SessionType
	switch action {
	case "enable":
		sessionType = session.SessionTypeRemotePluginEnable
	case "disable":
		sessionType = session.SessionTypeRemotePluginDisable
	case "delete":
		sessionType = session.SessionTypeRemotePluginDelete
	default:
		sessionType = session.SessionType("remote_plugin_action")
	}

	sessionId, _ := s.sessionService.StartSession(sessionType, 0, siteId, pluginSlug, site.Name)
	return sessionId
}

// broadcastRemoteActionStarted sends session start logs and WS broadcast.
func (s *Service) broadcastRemoteActionStarted(siteId int64, pluginSlug, action string, site *models.Site, sessionId string) {
	s.logRemoteAction(sessionId, siteId, action, "info", "start", fmt.Sprintf("Starting %s action for plugin: %s", action, pluginSlug), session.ToJSON(RemoteActionContext{SiteId: siteId, SiteName: site.Name, SiteUrl: site.Url, PluginSlug: pluginSlug}))

	if s.sessionService != nil && sessionId != "" {
		s.sessionService.SaveRequest(sessionId, &session.SessionRequest{
			URL:    fmt.Sprintf("/api/v1/sites/%d/remote-plugins/%s/%s", siteId, pluginSlug, action),
			Method: "POST",
			Body:   toJson(RemoteActionRequestBody{SiteId: siteId, PluginSlug: pluginSlug, Action: action}),
		})
	}

	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_started", RemoteActionStartedEvent{SiteId: siteId, SiteName: site.Name, Action: action, PluginSlug: pluginSlug}, sessionId)
	}
}

// connectForRemoteAction decrypts credentials and creates a WordPress client.
func (s *Service) connectForRemoteAction(siteId int64, action string, site *models.Site, sessionId string) (*wordpress.Client, error) {
	s.logRemoteAction(sessionId, siteId, action, "info", "decrypt", "Decrypting site credentials...", nil)

	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		errMsg := "failed to decrypt password"
		s.logRemoteAction(sessionId, siteId, action, "error", "decrypt", errMsg, session.ToJSON(ErrorDetail{Error: err.Error()}))
		s.endRemoteSession(sessionId, "error", errMsg)
		return nil, apperror.Wrap(err, apperror.ErrInternal, errMsg)
	}

	s.logRemoteAction(sessionId, siteId, action, "info", "connect", fmt.Sprintf("Connecting to WordPress site: %s", site.Url), nil)
	return s.wpClientFactory(site.Url, site.Username, string(password), nil), nil
}

// logRemoteStageStart logs the stage start for the remote action execution.
func (s *Service) logRemoteStageStart(sessionId string, siteId int64, action, pluginSlug string, site *models.Site) {
	if s.sessionService != nil && sessionId != "" {
		s.sessionService.LogStageStart(sessionId, action)
	}
	s.logRemoteAction(sessionId, siteId, action, "info", action, fmt.Sprintf("Executing %s action on plugin: %s", action, pluginSlug), session.ToJSON(RemoteActionExecDetails{TargetUrl: site.Url, PluginSlug: pluginSlug}))
}

// handleRemoteActionError processes a failed remote action: logs, broadcasts, writes error file.
func (s *Service) handleRemoteActionError(ctx context.Context, client *wordpress.Client, sessionId string, siteId int64, action, pluginSlug string, site *models.Site, err error, durationMs int64) {
	errDetails := s.extractErrorDetails(err)

	s.saveRemoteErrorResponse(sessionId, errDetails, err)
	s.logRemoteAction(sessionId, siteId, action, "error", action, fmt.Sprintf("Failed to %s plugin: %s", action, pluginSlug), session.ToJSON(errDetails))

	if s.sessionService != nil && sessionId != "" {
		s.sessionService.LogStageEnd(sessionId, action, "error", durationMs)
	}

	s.fetchAndAttachRemotePhpErrors(client, sessionId, siteId, action, pluginSlug, site.Name, site.Url, errDetails)
	s.logToErrorFile(action, siteId, pluginSlug, site.Name, site.Url, errDetails)
	s.endRemoteSession(sessionId, "error", err.Error())

	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_complete", RemoteActionCompleteEvent{SiteId: siteId, SiteName: site.Name, Action: action, PluginSlug: pluginSlug, Success: false, Error: err.Error(), DurationMs: durationMs}, sessionId)
	}
}

// saveRemoteErrorResponse saves the error response to the session.
func (s *Service) saveRemoteErrorResponse(sessionId string, errDetails *ExtractedErrorDetails, err error) {
	if s.sessionService == nil || sessionId == "" {
		return
	}

	var bodyJson json.RawMessage
	if errDetails.ResponseBody != "" {
		if json.Valid([]byte(errDetails.ResponseBody)) {
			bodyJson = json.RawMessage(errDetails.ResponseBody)
		} else {
			bodyJson, _ = json.Marshal(errDetails.ResponseBody)
		}
	}

	s.sessionService.SaveResponse(sessionId, &session.SessionResponse{RequestURL: errDetails.Url, ResponseURL: errDetails.Url, StatusCode: errDetails.StatusCode, Body: bodyJson})
	phpFrames := s.buildPhpStackFrames(errDetails)
	goFrames := session.CaptureGoStack(2)
	s.sessionService.SaveError(sessionId, &session.SessionStackTrace{Golang: goFrames, PHP: phpFrames}, err.Error(), session.ToJSON(errDetails))
}

// handleRemoteActionSuccess processes a successful remote action: logs, broadcasts, invalidates cache.
func (s *Service) handleRemoteActionSuccess(ctx context.Context, sessionId string, siteId int64, action, pluginSlug string, site *models.Site, durationMs int64) {
	if s.sessionService != nil && sessionId != "" {
		s.sessionService.SaveResponse(sessionId, &session.SessionResponse{
			RequestURL:  fmt.Sprintf("%s/wp-json/riseup-asia-uploader/v1/plugins/%s", site.Url, action),
			ResponseURL: site.Url, StatusCode: 200,
			Body: toJson(RemoteActionSuccessBody{Success: true, Action: action, Plugin: pluginSlug}),
		})
		s.sessionService.LogStageEnd(sessionId, action, "success", durationMs)
	}

	s.logRemoteAction(sessionId, siteId, action, "info", action, fmt.Sprintf("Successfully %sd plugin: %s", action, pluginSlug), session.ToJSON(DurationDetail{DurationMs: durationMs}))
	_ = s.InvalidateRemotePluginsCache(ctx, siteId)
	s.endRemoteSession(sessionId, "success", "")

	if s.wsHub != nil {
		s.wsHub.BroadcastWithSession("remote_plugin_action_complete", RemoteActionCompleteEvent{SiteId: siteId, SiteName: site.Name, Action: action, PluginSlug: pluginSlug, Success: true, DurationMs: durationMs}, sessionId)
	}

	s.log.Info(fmt.Sprintf("Remote plugin %sd", action), "siteId", siteId, "plugin", pluginSlug)
}
