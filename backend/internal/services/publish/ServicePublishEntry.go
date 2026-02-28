package publish

import (
	"context"
	"fmt"
	"time"

	healthstatus "wp-plugin-publish/internal/enums/healthstatustype"
	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	"wp-plugin-publish/internal/enums/publishtype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
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
func (p *publishContext) stageLog(
	level loglevel.Variant,
	stage publishstep.Variant,
	ctx StageContext,
) StageLogInput {
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
func (p *publishContext) progress(
	step publishstep.Variant,
	pct int,
	message string,
) ProgressInput {
	return ProgressInput{
		PluginId:  p.PluginId,
		SiteId:    p.SiteId,
		SessionId: p.SessionId,
		Step:      step,
		Progress:  pct,
		Message:   message,
	}
}

// stageCompleteInput bundles parameters for stageComplete.
type stageCompleteInput struct {
	StageName  publishstep.Variant
	Status     string
	DurationMs int64
	Details    []byte
}

// stageComplete builds a StageCompleteInput from the context fields.
func (p *publishContext) stageComplete(input stageCompleteInput) StageCompleteInput {
	return StageCompleteInput{
		PluginId:   p.PluginId,
		SiteId:     p.SiteId,
		SessionId:  p.SessionId,
		StageName:  input.StageName,
		Status:     input.Status,
		DurationMs: input.DurationMs,
		Details:    input.Details,
	}
}

// ─── Publish Entry Points ────────────────────────────────────────────────────

// Publish publishes plugin changes to a WordPress site
func (s *Service) Publish(ctx context.Context, pluginID, siteID int64, opts PublishOptions) apperror.Result[PublishResult] {
	result := s.initPublishResult()

	initResult, appErr := s.initPublishContext(ctx, initPublishInput{
		PluginID: pluginID,
		SiteID:   siteID,
		Result:   result,
	})
	if appErr != nil {

		return apperror.Ok(*result)
	}

	result.SessionId = initResult.SessionID
	s.broadcastPublishStart(pluginID, siteID, initResult)

	pctx := s.buildPublishContext(buildPublishContextInput{
		PluginID:   pluginID,
		SiteID:     siteID,
		InitResult: initResult,
		Opts:       opts,
		Result:     result,
	})

	return s.executeAndFinalize(ctx, pctx, initResult)
}

// initPublishResult creates the initial publish result.
func (s *Service) initPublishResult() *PublishResult {
	return &PublishResult{
		ActivationStatus: healthstatus.Unknown.DBValue(),
		Stages:           []Stage{},
	}
}

// broadcastPublishStart sends the initial progress and session log.
func (s *Service) broadcastPublishStart(
	pluginID int64,
	siteID int64,
	initResult *publishInitResult,
) {
	s.broadcastPublishStartProgress(pluginID, siteID, initResult)
	s.logPublishStartSession(initResult)
}

// broadcastPublishStartProgress sends the started progress event.
func (s *Service) broadcastPublishStartProgress(
	pluginID int64,
	siteID int64,
	initResult *publishInitResult,
) {
	startProgress := ProgressInput{
		PluginId:  pluginID,
		SiteId:    siteID,
		SessionId: initResult.SessionID,
		Step:      publishstep.Started,
		Progress:  0,
		Message:   "Starting publish...",
	}
	s.broadcastProgress(startProgress)
}

// logPublishStartSession writes the publish start session log.
func (s *Service) logPublishStartSession(initResult *publishInitResult) {
	initLog := sessionLogInput{
		SessionId: initResult.SessionID,
		Level:     loglevel.Info,
		Step:      publishstep.Init,
		Message:   fmt.Sprintf("Starting publish for %s to %s", initResult.PluginInfo.Name, initResult.SiteInfo.Name),
	}
	s.sessionLog(initLog)
}

// buildPublishContextInput bundles parameters for buildPublishContext.
type buildPublishContextInput struct {
	PluginID   int64
	SiteID     int64
	InitResult *publishInitResult
	Opts       PublishOptions
	Result     *PublishResult
}

// buildPublishContext constructs the publishContext struct.
func (s *Service) buildPublishContext(input buildPublishContextInput) *publishContext {
	return &publishContext{
		PluginId:   input.PluginID,
		SiteId:     input.SiteID,
		SessionId:  input.InitResult.SessionID,
		PluginInfo: input.InitResult.PluginInfo,
		Options:    input.Opts,
		Result:     input.Result,
		StartTime:  time.Now(),
	}
}

// executeAndFinalize runs the pipeline and finalizes the result.
func (s *Service) executeAndFinalize(ctx context.Context, pctx *publishContext, initResult *publishInitResult) apperror.Result[PublishResult] {
	appErr := s.runPublishPipeline(ctx, pctx, pipelineCredentials{SiteInfo: initResult.SiteInfo, Password: initResult.Password})
	if appErr != nil {

		return apperror.Ok(*pctx.Result)
	}

	s.finalizePublishResult(pctx)

	return apperror.Ok(*pctx.Result)
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
