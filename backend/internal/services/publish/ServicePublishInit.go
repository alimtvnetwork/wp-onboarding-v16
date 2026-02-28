package publish

import (
	"context"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/pkg/apperror"

	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
)

// publishInitResult holds the result of publish initialization.
type publishInitResult struct {
	PluginInfo models.Plugin
	SiteInfo   *models.Site
	Password   string
	SessionID  string
}

// initPublishInput bundles parameters for initPublishContext.
type initPublishInput struct {
	PluginID int64
	SiteID   int64
	Result   *PublishResult
}

// initPublishContext loads plugin, site, credentials, and starts a session.
func (s *Service) initPublishContext(ctx context.Context, input initPublishInput) (*publishInitResult, *apperror.AppError) {
	pluginInfo, appErr := s.loadPublishPlugin(ctx, input)
	if appErr != nil {

		return nil, appErr
	}

	creds, appErr := s.loadPublishCredentials(ctx, input)
	if appErr != nil {

		return nil, appErr
	}

	sessionID, _ := s.startPublishSession(startPublishSessionInput{
		PluginID:   input.PluginID,
		SiteID:     input.SiteID,
		PluginInfo: pluginInfo,
		SiteInfo:   creds.Site,
	})

	return &publishInitResult{PluginInfo: pluginInfo, SiteInfo: creds.Site, Password: creds.Password, SessionID: sessionID}, nil
}

// loadPublishPlugin loads the plugin and handles init failure.
func (s *Service) loadPublishPlugin(ctx context.Context, input initPublishInput) (models.Plugin, *apperror.AppError) {
	pluginResult := s.pluginService.GetByID(ctx, input.PluginID)
	if pluginResult.HasError() {
		s.failInit(failInitInput{
			PluginID: input.PluginID,
			SiteID:   input.SiteID,
			Err:      pluginResult.AppError(),
			Result:   input.Result,
		})

		return models.Plugin{}, pluginResult.AppError()
	}

	return pluginResult.Value(), nil
}

// loadPublishCredentials loads site credentials and handles init failure.
func (s *Service) loadPublishCredentials(ctx context.Context, input initPublishInput) (*SiteCredentialsResult, *apperror.AppError) {
	credsResult := s.getSiteCredentials(ctx, input.SiteID)
	if credsResult.HasError() {
		s.failInit(failInitInput{
			PluginID: input.PluginID,
			SiteID:   input.SiteID,
			Err:      credsResult.AppError(),
			Result:   input.Result,
		})

		return nil, credsResult.AppError()
	}

	creds := credsResult.Value()

	return &creds, nil
}

// failInitInput bundles parameters for failInit.
type failInitInput struct {
	PluginID int64
	SiteID   int64
	Err      error
	Result   *PublishResult
}

// failInit records error and broadcasts failure for init context.
func (s *Service) failInit(input failInitInput) {
	input.Result.ErrorMessage = input.Err.Error()

	failProgress := ProgressInput{
		PluginId: input.PluginID,
		SiteId:   input.SiteID,
		Step:     publishstep.Failed,
		Progress: 0,
		Message:  input.Err.Error(),
	}
	s.broadcastProgress(failProgress)
}

// startPublishSessionInput bundles parameters for startPublishSession.
type startPublishSessionInput struct {
	PluginID   int64
	SiteID     int64
	PluginInfo models.Plugin
	SiteInfo   *models.Site
}

// startPublishSession initializes a session for the publish operation
func (s *Service) startPublishSession(input startPublishSessionInput) (string, error) {
	isSessionServiceMissing := s.sessionService == nil

	if isSessionServiceMissing {

		return "", nil
	}

	startInput := session.StartSessionInput{
		Type:       session.SessionTypePublish,
		PluginID:   input.PluginID,
		SiteID:     input.SiteID,
		PluginName: input.PluginInfo.Name,
		SiteName:   input.SiteInfo.Name,
	}

	return s.executeStartSession(startInput)
}

// executeStartSession starts the session and wraps errors.
func (s *Service) executeStartSession(startInput session.StartSessionInput) (string, *apperror.AppError) {
	result := s.sessionService.StartSession(startInput)
	if result.HasError() {
		s.log.Warn("Failed to start session", "error", result.AppError())

		return "", result.AppError()
	}

	return result.Value(), nil
}
