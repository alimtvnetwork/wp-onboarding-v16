package publish

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/enums/logleveltype"
	"wp-plugin-publish/internal/enums/pluginstatustype"
	"wp-plugin-publish/internal/enums/publishsteptype"
	"wp-plugin-publish/internal/enums/statustype"
	"wp-plugin-publish/internal/models"
)

// ─── Cleanup ─────────────────────────────────────────────────────────────────

// executeCleanupStage marks files as synced
func (s *Service) executeCleanupStage(ctx context.Context, pctx *publishContext) Stage {
	return s.runStage("cleanup", func() error {
		s.broadcastCleanupProgress(pctx)

		if pctx.Options.Mode.IsSelected() && len(pctx.Options.Files) > 0 {
			return s.syncService.MarkSynced(ctx, pctx.PluginId, pctx.SiteId, pctx.Options.Files)
		}

		return s.syncService.ClearChanges(ctx, pctx.PluginId)
	})
}

// broadcastCleanupProgress sends cleanup progress and log.
func (s *Service) broadcastCleanupProgress(pctx *publishContext) {
	s.broadcastProgress(pctx.progress(publishsteptype.Cleanup, 95, "Marking files as synced..."))

	cleanLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    logleveltype.Info,
		Step:     publishsteptype.Cleanup,
		Message:  "Updating local sync state",
	}
	s.broadcastDetailedLog(cleanLog)
}

// countFilesUpdated returns the number of files updated based on publish mode
func (s *Service) countFilesUpdated(options PublishOptions, pluginInfo models.Plugin, fileCount int) int {
	if options.Mode.IsSelected() {
		return len(options.Files)
	}

	return pluginInfo.FileCount
}

// ─── Completion ──────────────────────────────────────────────────────────────

// finalizePublishResult computes final metrics, broadcasts completion, and records history.
func (s *Service) finalizePublishResult(pctx *publishContext) {
	pctx.Result.IsSuccess = pctx.Result.ActivationStatus == pluginstatustype.Active.String() ||
		pctx.Result.ActivationStatus == pluginstatustype.Inactive.String()
	pctx.Result.Duration = time.Since(pctx.StartTime).Milliseconds()

	s.broadcastCompletion(pctx)
	s.logPublishComplete(pctx)
	s.recordHistory(pctx)
}

// logPublishComplete writes the final publish log entry.
func (s *Service) logPublishComplete(pctx *publishContext) {
	s.log.Info("Plugin published",
		"pluginId", pctx.PluginId,
		"siteId", pctx.SiteId,
		"mode", pctx.Options.Mode,
		"files", pctx.Result.FilesUpdated,
		"duration", pctx.Result.Duration,
		"success", pctx.Result.IsSuccess,
	)
}

// broadcastCompletion sends the final publish status broadcast
func (s *Service) broadcastCompletion(pctx *publishContext) {
	completionStep, completionMessage := resolveCompletionStatus(pctx.Result)
	logLevel := resolveCompletionLogLevel(pctx.Result)

	s.broadcastCompletionLog(pctx, logLevel, completionMessage)
	s.broadcastProgress(pctx.progress(completionStep, 100, completionMessage))
}

// resolveCompletionLogLevel returns the appropriate log level for completion.
func resolveCompletionLogLevel(result *PublishResult) logleveltype.Variant {
	isFailed := !result.IsSuccess

	if isFailed {
		return logleveltype.Error
	}

	return logleveltype.Info
}

// broadcastCompletionLog sends the completion detailed log.
func (s *Service) broadcastCompletionLog(pctx *publishContext, logLevel logleveltype.Variant, message string) {
	completionDetails := toDetails(CompletionDetails{
		IsSuccess:    pctx.Result.IsSuccess,
		FilesUpdated: pctx.Result.FilesUpdated,
		DurationMs:   pctx.Result.Duration,
	})
	completionLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    logLevel,
		Step:     publishsteptype.Complete,
		Message:  message,
		Details:  completionDetails,
	}
	s.broadcastDetailedLog(completionLog)
}

// resolveCompletionStatus returns step and message for completion broadcast.
func resolveCompletionStatus(result *PublishResult) (publishsteptype.Variant, string) {
	if result.IsSuccess {
		return publishsteptype.Completed, fmt.Sprintf("Published %d files in %dms", result.FilesUpdated, result.Duration)
	}

	msg := result.ErrorMessage
	isMessageEmpty := msg == ""

	if isMessageEmpty {
		msg = "Publish failed - check logs for details"
	}

	return publishsteptype.Failed, msg
}

// ─── History ─────────────────────────────────────────────────────────────────

// recordHistory records the publish result to the history service
func (s *Service) recordHistory(pctx *publishContext) {
	if s.historyService == nil {

		return
	}

	input := historyEntryInput{
		PluginInfo: pctx.PluginInfo,
		SiteInfo:   pctx.SiteInfo,
		Options:    pctx.Options,
		Result:     pctx.Result,
	}
	entry := buildHistoryEntry(input)
	_, err := s.historyService.Record(entry)
	if err != nil {
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
	entry := buildHistoryBase(input)
	applyHistoryResult(&entry, input.Result)

	return entry
}

// buildHistoryBase constructs the base history entry from plugin/site/options.
func buildHistoryBase(input historyEntryInput) models.PublishHistory {
	historyStatus := statustype.Success.String()
	isFailed := !input.Result.IsSuccess

	if isFailed {
		historyStatus = statustype.Failed.String()
	}

	return models.PublishHistory{
		PluginId:   input.PluginInfo.ID,
		PluginName: input.PluginInfo.Name,
		SiteId:     input.SiteInfo.ID,
		SiteName:   input.SiteInfo.Name,
		SiteUrl:    input.SiteInfo.URL,
		Status:     historyStatus,
		Mode:       input.Options.Mode.Value(),
	}
}

// applyHistoryResult populates the result fields on a PublishHistory.
func applyHistoryResult(entry *models.PublishHistory, result *PublishResult) {
	entry.SessionId = result.SessionId
	entry.FilesUpdated = result.FilesUpdated
	entry.ActivationStatus = result.ActivationStatus
	entry.RollbackStatus = result.RollbackStatus
	entry.RollbackMessage = result.RollbackMessage
	entry.ErrorMessage = result.ErrorMessage
	entry.DurationMs = result.Duration
}
