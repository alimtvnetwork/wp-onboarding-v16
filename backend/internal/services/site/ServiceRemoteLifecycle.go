package site

import (
	"context"
	"fmt"
	"net/http"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// remoteActionRef bundles the recurring identifiers and dependencies that flow
// through every remote plugin action (enable/disable/delete).
type remoteActionRef struct {
	SessionId  string
	SiteId     int64
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
	existsResult := client.CheckPluginExistsViaUploader(pluginSlug)

	if existsResult.HasError() {

		return nil, apperror.Wrap(existsResult.AppError(), apperror.ErrWPPluginGet, "check plugin exists via uploader")
	}

	return existsResult.Value(), nil
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
	isMissingError := apiErr != nil && apiErr.StatusCode == http.StatusNotFound

	if isMissingError {
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

// resolveRemoteSite fetches and returns the site model for a remote action.
func (s *Service) resolveRemoteSite(ctx context.Context, siteId int64) (models.Site, *apperror.AppError) {
	siteResult := s.GetById(ctx, siteId)

	if siteResult.HasError() {

		return models.Site{}, siteResult.AppError()
	}

	return siteResult.Value(), nil
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
