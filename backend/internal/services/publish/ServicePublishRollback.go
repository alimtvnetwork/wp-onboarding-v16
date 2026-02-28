package publish

import (
	"context"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	enumstatus "wp-plugin-publish/internal/enums/statustype"
	"wp-plugin-publish/pkg/apperror"
)

// ─── Rollback ────────────────────────────────────────────────────────────────

// rollbackInput bundles parameters for handleRollback and executeRollbackSteps.
type rollbackInput struct {
	Ctx                context.Context
	Pctx               *publishContext
	PreUploadBackupZip string
	ActivateStage      Stage
}

// handleRollback performs rollback when activation fails
func (s *Service) handleRollback(input rollbackInput) {
	isRollbackDisabled := !input.Pctx.Options.IsRollbackOnFailure

	if isRollbackDisabled {
		input.Pctx.Result.RollbackStatus = stagestatus.Skipped.String()
		input.Pctx.Result.RollbackMessage = "Rollback disabled by user"

		return
	}

	rollbackStage := s.runStageWithSession(input.Pctx.SessionId, "rollback", func() error {
		appErr := s.executeRollbackSteps(input)
		if appErr != nil {
			return appErr
		}

		return nil
	})
	input.Pctx.Result.Stages = append(input.Pctx.Result.Stages, rollbackStage)
	s.reportRollbackOutcome(input.Pctx, rollbackStage, input.Pctx.Result)
}

// executeRollbackSteps deactivates the broken plugin and optionally re-uploads the backup.
func (s *Service) executeRollbackSteps(input rollbackInput) *apperror.AppError {
	s.broadcastProgress(input.Pctx.progress(publishstep.Rollback, 85, "Activation failed — rolling back..."))
	s.broadcastRollbackStartLog(input.Pctx, input.ActivateStage)
	s.rollbackDeactivate(input.Pctx)

	return s.rollbackRestore(input.Ctx, input.Pctx, input.PreUploadBackupZip)
}

// broadcastRollbackStartLog sends the rollback initiation log.
func (s *Service) broadcastRollbackStartLog(pctx *publishContext, activateStage Stage) {
	rollbackCtx := StageContext{
		What:  "Rolling back plugin after activation failure",
		Why:   fmt.Sprintf("Activation failed: %s", activateStage.Message),
		Where: pctx.SiteInfo.URL,
	}
	rollbackLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, rollbackCtx)
	s.broadcastStageLog(rollbackLog)
}

// rollbackDeactivate deactivates the broken plugin during rollback.
func (s *Service) rollbackDeactivate(pctx *publishContext) {
	deactCtx := StageContext{
		What: "Deactivating broken plugin to stabilize site",
	}
	deactLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, deactCtx)
	s.broadcastStageLog(deactLog)

	disableErr := pctx.WPClient.DisablePluginViaUploader(pctx.Mapping.RemoteSlug)
	if disableErr != nil {
		failCtx := StageContext{
			What:   "Deactivation during rollback",
			Result: fmt.Sprintf("Could not deactivate: %s (site may already be safe)", disableErr.Error()),
		}
		failLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, failCtx)
		s.broadcastStageLog(failLog)
	}
}

// rollbackRestore re-uploads the pre-upload backup if available.
func (s *Service) rollbackRestore(ctx context.Context, pctx *publishContext, preUploadBackupZip string) *apperror.AppError {
	isBackupMissing := preUploadBackupZip == ""

	if isBackupMissing {
		s.logNoBackupAvailable(pctx)

		return nil
	}

	return s.performRollbackUpload(ctx, pctx, preUploadBackupZip)
}

// logNoBackupAvailable logs that no backup is available for rollback.
func (s *Service) logNoBackupAvailable(pctx *publishContext) {
	noBackupCtx := StageContext{
		What:   "No pre-upload backup available",
		Result: "Plugin deactivated but files not restored. Manual intervention may be needed.",
	}
	noBackupLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, noBackupCtx)
	s.broadcastStageLog(noBackupLog)
}

// performRollbackUpload re-uploads the backup ZIP and logs the result.
func (s *Service) performRollbackUpload(ctx context.Context, pctx *publishContext, preUploadBackupZip string) *apperror.AppError {
	s.logRollbackRestoreStart(pctx)

	uploadResult := s.uploadPlugin(ctx, pctx.WPClient, preUploadBackupZip, pctx.Mapping.RemoteSlug)
	if uploadResult.HasError() {

		return apperror.Wrap(uploadResult.AppError(), apperror.ErrWPConnection, "rollback upload failed")
	}

	s.logRollbackRestoreComplete(pctx)

	return nil
}

// logRollbackRestoreStart logs the rollback restore start.
func (s *Service) logRollbackRestoreStart(pctx *publishContext) {
	restoreCtx := StageContext{
		What: "Re-uploading pre-publish backup to restore previous version",
	}
	restoreLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, restoreCtx)
	s.broadcastStageLog(restoreLog)
}

// logRollbackRestoreComplete logs the rollback restore completion.
func (s *Service) logRollbackRestoreComplete(pctx *publishContext) {
	doneCtx := StageContext{
		What:   "Rollback upload complete",
		Result: "Previous plugin version restored successfully",
	}
	doneLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, doneCtx)
	s.broadcastStageLog(doneLog)
}

// reportRollbackOutcome logs and sets the final rollback status on the result.
func (s *Service) reportRollbackOutcome(pctx *publishContext, rollbackStage Stage, result *PublishResult) {
	if rollbackStage.Status.IsFailed() {
		s.reportRollbackFailed(pctx, rollbackStage, result)

		return
	}

	s.reportRollbackSuccess(pctx, result)
}

// reportRollbackFailed sets the failed rollback status and logs it.
func (s *Service) reportRollbackFailed(pctx *publishContext, rollbackStage Stage, result *PublishResult) {
	result.RollbackStatus = enumstatus.Failed.String()
	result.RollbackMessage = rollbackStage.Message

	failCtx := StageContext{
		What:   "Rollback failed",
		Result: rollbackStage.Message,
	}
	failLog := pctx.stageLog(loglevel.Error, publishstep.Rollback, failCtx)
	s.broadcastStageLog(failLog)
}

// reportRollbackSuccess sets the successful rollback status and logs it.
func (s *Service) reportRollbackSuccess(pctx *publishContext, result *PublishResult) {
	result.RollbackStatus = enumstatus.Success.String()
	result.RollbackMessage = "Previous version restored"

	successCtx := StageContext{
		What:   "Rollback completed successfully",
		Result: "Site should be stable with previous plugin version",
	}
	successLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, successCtx)
	s.broadcastStageLog(successLog)
}
