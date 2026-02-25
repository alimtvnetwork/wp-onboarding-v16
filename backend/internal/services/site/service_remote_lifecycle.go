package site

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	loglevel "wp-plugin-publish/internal/enums/log_level"
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

	siteResult := s.GetById(ctx, siteId)
	if siteResult.HasError() {
		return siteResult.AppError()
	}
	site := siteResult.Value()

	var sessionId string
	if s.sessionService != nil {
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
		sessionId, _ = s.sessionService.StartSession(sessionType, 0, siteId, pluginSlug, site.Name)
	}

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

	s.logRemoteAction(sessionId, siteId, action, "info", "decrypt", "Decrypting site credentials...", nil)
	password, err := decrypt(site.PasswordEncrypted, s.encryptionKey)
	if err != nil {
		errMsg := "failed to decrypt password"
		s.logRemoteAction(sessionId, siteId, action, "error", "decrypt", errMsg, session.ToJSON(ErrorDetail{Error: err.Error()}))
		s.endRemoteSession(sessionId, "error", errMsg)
		return apperror.Wrap(err, apperror.ErrInternal, errMsg)
	}

	s.logRemoteAction(sessionId, siteId, action, "info", "connect", fmt.Sprintf("Connecting to WordPress site: %s", site.Url), nil)
	client := s.wpClientFactory(site.Url, site.Username, string(password), nil)

	if s.sessionService != nil && sessionId != "" {
		s.sessionService.LogStageStart(sessionId, action)
	}
	s.logRemoteAction(sessionId, siteId, action, "info", action, fmt.Sprintf("Executing %s action on plugin: %s", action, pluginSlug), session.ToJSON(RemoteActionExecDetails{TargetUrl: site.Url, PluginSlug: pluginSlug}))

	err = execFn(client)
	durationMs := time.Since(startTime).Milliseconds()

	if err != nil {
		errDetails := s.extractErrorDetails(err)

		if s.sessionService != nil && sessionId != "" {
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
		return err
	}

	if s.sessionService != nil && sessionId != "" {
		s.sessionService.SaveResponse(sessionId, &session.SessionResponse{
			RequestURL: fmt.Sprintf("%s/wp-json/riseup-asia-uploader/v1/plugins/%s", site.Url, action),
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
	return nil
}
