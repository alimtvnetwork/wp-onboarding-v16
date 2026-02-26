package publish

import (
	"context"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	enumstatus "wp-plugin-publish/internal/enums/status"
	"wp-plugin-publish/internal/models"
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

	rollbackCtx := StageContext{
		What:  "Rolling back plugin after activation failure",
		Why:   fmt.Sprintf("Activation failed: %s", activateStage.Message),
		Where: pctx.SiteInfo.URL,
	}
	rollbackLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, rollbackCtx)
	s.broadcastStageLog(rollbackLog)

	s.rollbackDeactivate(pctx)

	return s.rollbackRestore(ctx, pctx, preUploadBackupZip)
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
		noBackupCtx := StageContext{
			What:   "No pre-upload backup available",
			Result: "Plugin deactivated but files not restored. Manual intervention may be needed.",
		}
		noBackupLog := pctx.stageLog(loglevel.Warn, publishstep.Rollback, noBackupCtx)
		s.broadcastStageLog(noBackupLog)

		return nil
	}

	restoreCtx := StageContext{
		What: "Re-uploading pre-publish backup to restore previous version",
	}
	restoreLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, restoreCtx)
	s.broadcastStageLog(restoreLog)

	_, _, _, uploadErr := s.uploadPlugin(ctx, pctx.WPClient, preUploadBackupZip, pctx.Mapping.RemoteSlug)

	if uploadErr != nil {
		return apperror.Wrap(uploadErr, apperror.ErrWPConnection, "rollback upload failed")
	}

	doneCtx := StageContext{
		What:   "Rollback upload complete",
		Result: "Previous plugin version restored successfully",
	}
	doneLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, doneCtx)
	s.broadcastStageLog(doneLog)

	return nil
}

// reportRollbackOutcome logs and sets the final rollback status on the result.
func (s *Service) reportRollbackOutcome(pctx *publishContext, rollbackStage Stage, result *PublishResult) {
	if rollbackStage.Status.IsFailed() {
		result.RollbackStatus = enumstatus.Failed.String()
		result.RollbackMessage = rollbackStage.Message

		failCtx := StageContext{
			What:   "Rollback failed",
			Result: rollbackStage.Message,
		}
		failLog := pctx.stageLog(loglevel.Error, publishstep.Rollback, failCtx)
		s.broadcastStageLog(failLog)
	} else {
		result.RollbackStatus = enumstatus.Success.String()
		result.RollbackMessage = "Previous version restored"

		successCtx := StageContext{
			What:   "Rollback completed successfully",
			Result: "Site should be stable with previous plugin version",
		}
		successLog := pctx.stageLog(loglevel.Info, publishstep.Rollback, successCtx)
		s.broadcastStageLog(successLog)
	}
}

// ─── Cleanup ─────────────────────────────────────────────────────────────────

// executeCleanupStage marks files as synced
func (s *Service) executeCleanupStage(ctx context.Context, pctx *publishContext) Stage {
	return s.runStage("cleanup", func() error {
		cleanProgress := ProgressInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Step:     publishstep.Cleanup,
			Progress: 95,
			Message:  "Marking files as synced...",
		}
		s.broadcastProgress(cleanProgress)

		cleanLog := DetailedLogInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Cleanup,
			Message:  "Updating local sync state",
		}
		s.broadcastDetailedLog(cleanLog)

		if pctx.Options.Mode.IsSelected() && len(pctx.Options.Files) > 0 {
			return s.syncService.MarkSynced(ctx, pctx.PluginId, pctx.SiteId, pctx.Options.Files)
		}

		return s.syncService.ClearChanges(ctx, pctx.PluginId)
	})
}

// countFilesUpdated returns the number of files updated based on publish mode
func (s *Service) countFilesUpdated(options PublishOptions, pluginInfo models.Plugin, fileCount int) int {
	if options.Mode.IsSelected() {
		return len(options.Files)
	}

	return pluginInfo.FileCount
}

// ─── Completion ──────────────────────────────────────────────────────────────

// broadcastCompletion sends the final publish status broadcast
func (s *Service) broadcastCompletion(pctx *publishContext) {
	completionStep, completionMessage := resolveCompletionStatus(pctx.Result)

	logLevel := loglevel.Info
	if !pctx.Result.IsSuccess {
		logLevel = loglevel.Error
	}

	completionDetails := toDetails(CompletionDetails{
		IsSuccess:    pctx.Result.IsSuccess,
		FilesUpdated: pctx.Result.FilesUpdated,
		DurationMs:   pctx.Result.Duration,
	})
	completionLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    logLevel,
		Step:     publishstep.Complete,
		Message:  completionMessage,
		Details:  completionDetails,
	}
	s.broadcastDetailedLog(completionLog)

	completionProgress := ProgressInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Step:     completionStep,
		Progress: 100,
		Message:  completionMessage,
	}
	s.broadcastProgress(completionProgress)
}

// resolveCompletionStatus returns step and message for completion broadcast.
func resolveCompletionStatus(result *PublishResult) (publishstep.Variant, string) {
	if result.IsSuccess {
		return publishstep.Completed, fmt.Sprintf("Published %d files in %dms", result.FilesUpdated, result.Duration)
	}
	msg := result.ErrorMessage
	if msg == "" {
		msg = "Publish failed - check logs for details"
	}

	return publishstep.Failed, msg
}

// ─── History ─────────────────────────────────────────────────────────────────

// recordHistory records the publish result to the history service
func (s *Service) recordHistory(pluginInfo models.Plugin, siteInfo *models.Site, options PublishOptions, result *PublishResult) {
	if s.historyService == nil {
		return
	}

	input := historyEntryInput{
		PluginInfo: pluginInfo,
		SiteInfo:   siteInfo,
		Options:    options,
		Result:     result,
	}
	entry := buildHistoryEntry(input)
	if _, err := s.historyService.Record(entry); err != nil {
		s.log.Error("Failed to record publish history", "error", err)
	}
}

// historyEntryInput bundles parameters for buildHistoryEntry.
type historyEntryInput struct {
	PluginInfo models.Plugin
	SiteInfo   *models.Site
	Options    PublishOptions
	Result     *PublishResult
}

// buildHistoryEntry constructs a PublishHistory from the publish context.
func buildHistoryEntry(input historyEntryInput) models.PublishHistory {
	historyStatus := enumstatus.Success.String()
	if !input.Result.IsSuccess {
		historyStatus = enumstatus.Failed.String()
	}

	return models.PublishHistory{
		PluginId:         input.PluginInfo.ID,
		PluginName:       input.PluginInfo.Name,
		SiteId:           input.SiteInfo.ID,
		SiteName:         input.SiteInfo.Name,
		SiteUrl:          input.SiteInfo.URL,
		SessionId:        input.Result.SessionId,
		Status:           historyStatus,
		Mode:             input.Options.Mode.Value(),
		FilesUpdated:     input.Result.FilesUpdated,
		ActivationStatus: input.Result.ActivationStatus,
		RollbackStatus:   input.Result.RollbackStatus,
		RollbackMessage:  input.Result.RollbackMessage,
		ErrorMessage:     input.Result.ErrorMessage,
		DurationMs:       input.Result.Duration,
	}
}
