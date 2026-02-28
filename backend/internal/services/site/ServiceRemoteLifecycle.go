package site

import (
	"context"
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
func (s *Service) CheckRemotePluginExists(ctx context.Context, siteId int64, pluginSlug string) (*wordpress.PluginExistsResult, *apperror.AppError) {
	result := s.GetById(ctx, siteId)

	if result.HasError() {

		return nil, apperror.Wrap(result.AppError(), apperror.ErrNotFound, "site not found")
	}

	site := result.Value()
	password, decryptErr := decrypt(site.PasswordEncrypted, s.encryptionKey)

	if decryptErr != nil {

		return nil, apperror.Wrap(decryptErr, apperror.ErrInternal, "failed to decrypt password")
	}

	client := s.wpClientFactory(site.Url, site.Username, string(password), nil)
	existsResult, wpErr := client.CheckPluginExistsViaUploader(pluginSlug)

	if wpErr != nil {

		return nil, apperror.Wrap(wpErr, apperror.ErrWPPluginGet, "check plugin exists via uploader")
	}

	return existsResult, nil
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func (s *Service) EnableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError {
	input := remoteActionInput{
		SiteId:     siteId,
		PluginSlug: pluginSlug,
		Action:     "enable",
		ExecFn: func(client *wordpress.Client) *apperror.AppError {
			wpErr := client.EnablePluginViaUploader(pluginSlug)

			if wpErr != nil {

				return apperror.Wrap(wpErr, apperror.ErrWPPluginActivate, "enable plugin via uploader")
			}

			return nil
		},
	}

	return s.executeRemotePluginAction(ctx, input)
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
func (s *Service) DisableRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError {
	input := remoteActionInput{
		SiteId:     siteId,
		PluginSlug: pluginSlug,
		Action:     "disable",
		ExecFn: func(client *wordpress.Client) *apperror.AppError {
			wpErr := client.DisablePluginViaUploader(pluginSlug)

			if wpErr != nil {

				return apperror.Wrap(wpErr, apperror.ErrWPPluginActivate, "disable plugin via uploader")
			}

			return nil
		},
	}

	return s.executeRemotePluginAction(ctx, input)
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site
func (s *Service) DeleteRemotePlugin(ctx context.Context, siteId int64, pluginSlug string) *apperror.AppError {
	input := remoteActionInput{
		SiteId:     siteId,
		PluginSlug: pluginSlug,
		Action:     "delete",
		ExecFn: func(client *wordpress.Client) *apperror.AppError {
			s.preDeleteDisable(client, pluginSlug)
			wpErr := client.DeletePluginViaUploader(pluginSlug)

			if wpErr != nil {

				return apperror.Wrap(wpErr, apperror.ErrWPPluginDelete, "delete plugin via uploader")
			}

			return nil
		},
	}

	return s.executeRemotePluginAction(ctx, input)
}

// preDeleteDisable attempts to disable the plugin before deletion, logging but not failing on error.
func (s *Service) preDeleteDisable(client *wordpress.Client, pluginSlug string) {
	disableErr := client.DisablePluginViaUploader(pluginSlug)

	if disableErr == nil {

		return
	}

	apiErr := wordpress.ExtractAPIError(disableErr)
	isNotFoundError := apiErr != nil && apiErr.StatusCode == http.StatusNotFound

	if isNotFoundError {
		s.log.Info("Plugin not found during pre-delete disable (skipped safely)", "slug", pluginSlug)
	} else {
		s.log.Warn("Pre-delete disable failed (continuing with delete)", "slug", pluginSlug, "error", disableErr.Error())
	}
}

// remoteActionInput bundles parameters for executeRemotePluginAction.
type remoteActionInput struct {
	SiteId     int64
	PluginSlug string
	Action     string
	ExecFn     func(*wordpress.Client) *apperror.AppError
}

// executeRemotePluginAction runs a remote plugin action with session logging
func (s *Service) executeRemotePluginAction(ctx context.Context, input remoteActionInput) *apperror.AppError {
	startTime := time.Now()

	ref, refErr := s.setupRemoteActionRef(ctx, input)

	if refErr != nil {

		return refErr
	}

	client, connectErr := s.connectForRemoteAction(ref)

	if connectErr != nil {

		return connectErr
	}

	ref.Client = client

	return s.runRemoteAction(ctx, ref, startTime, input.ExecFn)
}

// setupRemoteActionRef resolves the site, creates the ref, and starts session.
func (s *Service) setupRemoteActionRef(ctx context.Context, input remoteActionInput) (*remoteActionRef, *apperror.AppError) {
	site, siteErr := s.resolveRemoteSite(ctx, input.SiteId)

	if siteErr != nil {

		return nil, siteErr
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
func (s *Service) runRemoteAction(ctx context.Context, ref *remoteActionRef, startTime time.Time, execFn func(*wordpress.Client) *apperror.AppError) *apperror.AppError {
	s.logRemoteStageStart(ref)

	appErr := execFn(ref.Client)
	durationMs := time.Since(startTime).Milliseconds()

	if appErr != nil {
		s.handleRemoteActionError(ctx, ref, appErr, durationMs)

		return appErr
	}

	s.handleRemoteActionSuccess(ctx, ref, durationMs)

	return nil
}

// resolveRemoteSite fetches and returns the site model for a remote action.
func (s *Service) resolveRemoteSite(ctx context.Context, siteId int64) (models.Site, *apperror.AppError) {
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
		Type:       sessionType,
		PluginID:   0,
		SiteID:     ref.SiteID,
		PluginName: ref.PluginSlug,
		SiteName:   ref.Site.Name,
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

// connectForRemoteAction decrypts credentials and creates a WordPress client.
func (s *Service) connectForRemoteAction(ref *remoteActionRef) (*wordpress.Client, *apperror.AppError) {
	s.logRemoteAction(ref, RemoteActionLogInput{
		Level:   "info",
		Step:    "decrypt",
		Message: "Decrypting site credentials...",
	})

	password, decryptErr := decrypt(ref.Site.PasswordEncrypted, s.encryptionKey)

	if decryptErr != nil {
		errMsg := "failed to decrypt password"
		appErr := apperror.Wrap(decryptErr, apperror.ErrInternal, errMsg)
		s.logRemoteAction(ref, RemoteActionLogInput{
			Level:   "error",
			Step:    "decrypt",
			Message: errMsg,
			Details: session.ToJSON(AppErrorDetail{Error: appErr.Error()}),
		})
		s.endRemoteSession(ref.SessionID, "error", errMsg)

		return nil, appErr
	}

	s.logRemoteAction(ref, RemoteActionLogInput{
		Level:   "info",
		Step:    "connect",
		Message: fmt.Sprintf("Connecting to WordPress site: %s", ref.Site.Url),
	})

	return s.wpClientFactory(ref.Site.Url, ref.Site.Username, string(password), nil), nil
}

// logRemoteStageStart logs the stage start for the remote action execution.
func (s *Service) logRemoteStageStart(ref *remoteActionRef) {
	hasSessionService := s.sessionService != nil
	hasSessionId := ref.SessionID != ""

	if hasSessionService && hasSessionId {
		s.sessionService.LogStageStart(ref.SessionID, ref.Action)
	}

	s.logRemoteAction(ref, RemoteActionLogInput{
		Level:   "info",
		Step:    ref.Action,
		Message: fmt.Sprintf("Executing %s action on plugin: %s", ref.Action, ref.PluginSlug),
		Details: session.ToJSON(RemoteActionExecDetails{
			TargetUrl:  ref.Site.Url,
			PluginSlug: ref.PluginSlug,
		}),
	})
}
