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

// handleRollback performs rollback when activation fails
func (s *Service) handleRollback(ctx context.Context, pctx *publishContext, preUploadBackupZip string, activateStage Stage) {
	if !pctx.Options.IsRollbackOnFailure {
		pctx.Result.RollbackStatus = stagestatus.Skipped.String()
		pctx.Result.RollbackMessage = "Rollback disabled by user"

		return
	}

	rollbackStage := s.runStageWithSession(pctx.SessionId, "rollback", func() error {
		return s.executeRollbackSteps(ctx, pctx, preUploadBackupZip, activateStage)
	})
	pctx.Result.Stages = append(pctx.Result.Stages, rollbackStage)
	s.reportRollbackOutcome(pctx, rollbackStage, pctx.Result)
}

// executeRollbackSteps deactivates the broken plugin and optionally re-uploads the backup.
func (s *Service) executeRollbackSteps(ctx context.Context, pctx *publishContext, preUploadBackupZip string, activateStage Stage) error {
	s.broadcastProgress(pctx.progress(publishstep.Rollback, 85, "Activation failed — rolling back..."))
	s.broadcastRollbackStartLog(pctx, activateStage)
	s.rollbackDeactivate(pctx)

	return s.rollbackRestore(ctx, pctx, preUploadBackupZip)
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

	if disableErr := pctx.WPClient.DisablePluginViaUploader(pctx.Mapping.RemoteSlug); disableErr != nil {
		failCtx := StageContext{
			What:   "Deactivation during rollback",
			Result: fmt.Sprintf("Could not deactivate: %s (site may already be safe)", disableErr.Error()),
		}
		failLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, failCtx)
		s.broadcastStageLog(failLog)
	}
}

// rollbackRestore re-uploads the pre-upload backup if available.
func (s *Service) rollbackRestore(ctx context.Context, pctx *publishContext, preUploadBackupZip string) error {
	if preUploadBackupZip == "" {
		return s.reportNoBackupAvailable(pctx)
	}

	return s.performRollbackUpload(ctx, pctx, preUploadBackupZip)
}

// reportNoBackupAvailable logs that no backup is available for rollback.
func (s *Service) reportNoBackupAvailable(pctx *publishContext) error {
	noBackupCtx := StageContext{
		What:   "No pre-upload backup available",
		Result: "Plugin deactivated but files not restored. Manual intervention may be needed.",
	}
	noBackupLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, noBackupCtx)
	s.broadcastStageLog(noBackupLog)

	return nil
}

// performRollbackUpload re-uploads the backup ZIP and logs the result.
func (s *Service) performRollbackUpload(ctx context.Context, pctx *publishContext, preUploadBackupZip string) error {
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
	} else {
		s.reportRollbackSuccess(pctx, result)
	}
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
