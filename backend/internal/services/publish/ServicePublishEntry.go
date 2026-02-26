package publish

import (
	"context"
	"fmt"
	"time"

	healthstatus "wp-plugin-publish/internal/enums/health_status"
	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	publishtype "wp-plugin-publish/internal/enums/publish_type"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/pkg/apperror"
)

// publishContext bundles the recurring identifiers and dependencies that flow through the publish pipeline.
type publishContext struct {
	PluginId   int64
	SiteId     int64
	SessionId  string
	WPClient   *wordpress.Client
	Mapping    *models.PluginMapping
	SiteInfo   *models.Site
	PluginInfo models.Plugin
	Options    PublishOptions
	Result     *PublishResult
	StartTime  time.Time
}

// stageLog builds a StageLogInput from the context fields.
func (p *publishContext) stageLog(level loglevel.Variant, stage publishstep.Variant, ctx StageContext) StageLogInput {
	return StageLogInput{
		PluginId:  p.PluginId,
		SiteId:    p.SiteId,
		SessionId: p.SessionId,
		Level:     level,
		Stage:     stage,
		Ctx:       ctx,
	}
}

// progress builds a ProgressInput from the context fields.
func (p *publishContext) progress(step publishstep.Variant, pct int, message string) ProgressInput {
	return ProgressInput{
		PluginId:  p.PluginId,
		SiteId:    p.SiteId,
		SessionId: p.SessionId,
		Step:      step,
		Progress:  pct,
		Message:   message,
	}
}

// stageComplete builds a StageCompleteInput from the context fields.
func (p *publishContext) stageComplete(stageName publishstep.Variant, status string, durationMs int64, details []byte) StageCompleteInput {
	return StageCompleteInput{
		PluginId:   p.PluginId,
		SiteId:     p.SiteId,
		SessionId:  p.SessionId,
		StageName:  stageName,
		Status:     status,
		DurationMs: durationMs,
		Details:    details,
	}
}

// ─── Publish Entry Points ────────────────────────────────────────────────────

// Publish publishes plugin changes to a WordPress site
func (s *Service) Publish(ctx context.Context, pluginID, siteID int64, opts PublishOptions) apperror.Result[PublishResult] {
	result := &PublishResult{
		ActivationStatus: healthstatus.Unknown.DBValue(),
		Stages:           []Stage{},
	}

	pluginInfo, siteInfo, password, sessionID, err := s.initPublishContext(ctx, pluginID, siteID, result)
	if err != nil {
		return apperror.Ok(*result)
	}
	result.SessionId = sessionID

	startProgress := ProgressInput{
		PluginId:  pluginID,
		SiteId:    siteID,
		SessionId: sessionID,
		Step:      publishstep.Started,
		Progress:  0,
		Message:   "Starting publish...",
	}
	s.broadcastProgress(startProgress)

	initLog := sessionLogInput{
		SessionId: sessionID,
		Level:     loglevel.Info,
		Step:      publishstep.Init,
		Message:   fmt.Sprintf("Starting publish for %s to %s", pluginInfo.Name, siteInfo.Name),
	}
	s.sessionLog(initLog)

	pctx := &publishContext{
		PluginId:   pluginID,
		SiteId:     siteID,
		SessionId:  sessionID,
		PluginInfo: pluginInfo,
		Options:    opts,
		Result:     result,
		StartTime:  time.Now(),
	}

	if err := s.runPublishPipeline(ctx, pctx, siteInfo, password); err != nil {
		return apperror.Ok(*result)
	}

	s.finalizePublishResult(pctx)

	return apperror.Ok(*result)
}

// initPublishContext loads plugin, site, credentials, and starts a session.
func (s *Service) initPublishContext(ctx context.Context, pluginID, siteID int64, result *PublishResult) (models.Plugin, *models.Site, string, string, error) {
	pluginResult := s.pluginService.GetByID(ctx, pluginID)
	if pluginResult.HasError() {
		return s.failInit(pluginID, siteID, pluginResult.AppError(), result)
	}

	siteInfo, password, err := s.getSiteCredentials(ctx, siteID)
	if err != nil {
		return s.failInit(pluginID, siteID, err, result)
	}

	pluginInfo := pluginResult.Value()
	sessionID, _ := s.startPublishSession(pluginID, siteID, pluginInfo, siteInfo)

	return pluginInfo, siteInfo, password, sessionID, nil
}

// failInit records error and broadcasts failure for init context.
func (s *Service) failInit(pluginID, siteID int64, err error, result *PublishResult) (models.Plugin, *models.Site, string, string, error) {
	result.ErrorMessage = err.Error()

	failProgress := ProgressInput{
		PluginId: pluginID,
		SiteId:   siteID,
		Step:     publishstep.Failed,
		Progress: 0,
		Message:  err.Error(),
	}
	s.broadcastProgress(failProgress)

	return models.Plugin{}, nil, "", "", err
}

// PublishFiles publishes specific files to a WordPress site
func (s *Service) PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) apperror.Result[PublishResult] {
	opts := PublishOptions{
		Mode:           publishtype.Selected,
		Files:          files,
		IsCreateBackup: false,
	}

	return s.Publish(ctx, pluginID, siteID, opts)
}

// startPublishSession initializes a session for the publish operation
func (s *Service) startPublishSession(pluginID, siteID int64, pluginInfo models.Plugin, siteInfo *models.Site) (string, error) {
	if s.sessionService == nil {
		return "", nil
	}

	startInput := session.StartSessionInput{
		Type:       session.SessionTypePublish,
		PluginID:   pluginID,
		SiteID:     siteID,
		PluginName: pluginInfo.Name,
		SiteName:   siteInfo.Name,
	}
	sessionID, err := s.sessionService.StartSession(startInput)
	if err != nil {
		s.log.Warn("Failed to start session", "error", err)

		return "", apperror.Wrap(err, apperror.ErrSessionInit, "failed to start publish session")
	}

	return sessionID, nil
}
