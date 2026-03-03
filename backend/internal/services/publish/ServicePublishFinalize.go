package publish

import (
	"context"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	pluginstatus "wp-plugin-publish/internal/enums/pluginstatustype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	"wp-plugin-publish/pkg/apperror"
)

// broadcastUploadComplete sends the upload stage complete event.
func (s *Service) broadcastUploadComplete(pctx *publishContext, uploadStage Stage, isAlreadyActivated bool) {
	uploadDetails := toDetails(UploadStageDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		Activated:  isAlreadyActivated,
	})
	uploadComplete := pctx.stageComplete(stageCompleteInput{
		StageName:  publishstep.Upload,
		Status:     uploadStage.Status.String(),
		DurationMs: uploadStage.Duration,
		Details:    uploadDetails,
	})
	s.broadcastStageComplete(uploadComplete)
}

// reportUploadFailure records and broadcasts an upload failure.
func (s *Service) reportUploadFailure(pctx *publishContext, uploadStage Stage) *apperror.AppError {
	pctx.Result.ErrorMessage = uploadStage.Message
	s.broadcastProgress(pctx.progress(publishstep.Failed, 60, uploadStage.Message))

	return apperror.New(apperror.ErrWPUploadFailed, uploadStage.Message)
}

// activateCleanupInput bundles parameters for runActivateAndCleanup.
type activateCleanupInput struct {
	IsAlreadyActivated bool
	PreUploadBackupZip string
}

// runActivateAndCleanup handles the activate and cleanup stages.
func (s *Service) runActivateAndCleanup(ctx context.Context, pctx *publishContext, input activateCleanupInput) {
	activateStage := s.executeActivateStage(pctx, input.IsAlreadyActivated)
	pctx.Result.Stages = append(pctx.Result.Stages, activateStage)

	s.broadcastActivateComplete(pctx, activateStage, input.IsAlreadyActivated)
	s.handleActivateResult(ctx, pctx, activateResultInput{ActivateStage: activateStage, PreUploadBackupZip: input.PreUploadBackupZip})

	cleanupStage := s.executeCleanupStage(ctx, pctx)
	pctx.Result.Stages = append(pctx.Result.Stages, cleanupStage)
}

// broadcastActivateComplete sends the activate stage complete event.
func (s *Service) broadcastActivateComplete(pctx *publishContext, activateStage Stage, isAlreadyActivated bool) {
	activateDetails := toDetails(ActivateSkipDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		Skipped:    isAlreadyActivated,
	})
	activateComplete := pctx.stageComplete(stageCompleteInput{
		StageName:  publishstep.Activate,
		Status:     activateStage.Status.String(),
		DurationMs: activateStage.Duration,
		Details:    activateDetails,
	})
	s.broadcastStageComplete(activateComplete)
}

// activateResultInput bundles parameters for handleActivateResult.
type activateResultInput struct {
	ActivateStage      Stage
	PreUploadBackupZip string
}

// handleActivateResult sets activation status and triggers rollback if needed.
func (s *Service) handleActivateResult(ctx context.Context, pctx *publishContext, input activateResultInput) {
	if input.ActivateStage.Status.IsFailed() {
		pctx.Result.ActivationStatus = loglevel.Error.Lower()
		pctx.Result.ErrorMessage = input.ActivateStage.Message
		s.handleRollback(rollbackInput{
			Ctx:                ctx,
			Pctx:               pctx,
			PreUploadBackupZip: input.PreUploadBackupZip,
			ActivateStage:      input.ActivateStage,
		})

		return
	}

	pctx.Result.ActivationStatus = pluginstatus.Active.String()
}

