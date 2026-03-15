package publish

import (
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/wordpress"
)

// runCloudUploadStage uploads the backup to selected cloud storage accounts.
// Failures log warnings but do not block the publish pipeline.
func (s *Service) runCloudUploadStage(pctx *publishContext) {
	accountIds := pctx.Options.CloudStorageAccountIds
	hasNoAccounts := len(accountIds) == 0

	if hasNoAccounts {
		s.broadcastCloudUploadSkipped(pctx)

		return
	}

	s.broadcastProgress(pctx.progress(publishstep.CloudUpload, 12, "Uploading backup to cloud storage..."))
	s.logCloudUploadInit(pctx, accountIds)

	s.uploadToEachAccount(pctx, accountIds)
}

// uploadToEachAccount iterates over account IDs and uploads the backup to each.
func (s *Service) uploadToEachAccount(pctx *publishContext, accountIds []int) {
	total := len(accountIds)
	successCount := 0

	for i, accountId := range accountIds {
		isSuccess := s.uploadToAccount(pctx, accountId, i+1, total)

		if isSuccess {
			successCount++
		}
	}

	s.logCloudUploadSummary(pctx, successCount, total)
}

// uploadToAccount uploads the backup to a single cloud storage account.
func (s *Service) uploadToAccount(pctx *publishContext, accountId, current, total int) bool {
	s.broadcastCloudUploadAccountProgress(pctx, accountId, current, total)

	req := wordpress.CloudStorageUploadRequest{
		AccountId:  accountId,
		FilePath:   pctx.Mapping.RemoteSlug,
		RemotePath: fmt.Sprintf("backups/%s", pctx.Mapping.RemoteSlug),
	}

	result := pctx.WPClient.UploadToCloudStorage(req)
	if result.HasError() {
		s.logCloudUploadAccountFailed(pctx, accountId, result.AppError().Error())

		return false
	}

	uploadResult := result.Value()
	s.logCloudUploadAccountSuccess(pctx, accountId, uploadResult)

	return true
}

// broadcastCloudUploadSkipped broadcasts that cloud upload was skipped.
func (s *Service) broadcastCloudUploadSkipped(pctx *publishContext) {
	skipCtx := StageContext{
		What:   "Cloud storage upload",
		Result: "Skipped: no cloud storage accounts selected",
	}
	skipLog := pctx.stageLog(loglevel.Info, publishstep.CloudUpload, skipCtx)
	s.broadcastStageLog(skipLog)
}

// logCloudUploadInit broadcasts the cloud upload initiation log.
func (s *Service) logCloudUploadInit(pctx *publishContext, accountIds []int) {
	details := toDetails(CloudUploadInitDetails{
		AccountIds: accountIds,
		PluginSlug: pctx.Mapping.RemoteSlug,
	})
	initLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.CloudUpload,
		Message:  fmt.Sprintf("Uploading backup to %d cloud storage account(s)", len(accountIds)),
		Details:  details,
	}
	s.broadcastDetailedLog(initLog)
}

// broadcastCloudUploadAccountProgress sends per-account progress.
func (s *Service) broadcastCloudUploadAccountProgress(pctx *publishContext, accountId, current, total int) {
	msg := fmt.Sprintf("Uploading to cloud storage account %d (%d/%d)", accountId, current, total)
	pct := 12 + (current * 5 / total)

	s.broadcastProgress(pctx.progress(publishstep.CloudUpload, pct, msg))
}

// logCloudUploadAccountFailed logs a warning when a single account upload fails.
func (s *Service) logCloudUploadAccountFailed(pctx *publishContext, accountId int, errMsg string) {
	details := toDetails(CloudUploadAccountResultDetails{
		AccountId: accountId,
		Status:    stagestatus.Failed.String(),
		Error:     errMsg,
	})
	failLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Warn,
		Step:     publishstep.CloudUpload,
		Message:  fmt.Sprintf("Cloud upload failed for account %d: %s (publish will continue)", accountId, errMsg),
		Details:  details,
	}
	s.broadcastDetailedLog(failLog)
}

// logCloudUploadAccountSuccess logs success for a single account upload.
func (s *Service) logCloudUploadAccountSuccess(pctx *publishContext, accountId int, result wordpress.CloudStorageUploadResult) {
	details := toDetails(CloudUploadAccountResultDetails{
		AccountId:  accountId,
		Status:     stagestatus.Completed.String(),
		RemotePath: result.RemotePath,
		RemoteUrl:  result.RemoteUrl,
		Bytes:      result.Bytes,
	})
	successLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.CloudUpload,
		Message:  fmt.Sprintf("Cloud upload complete for account %d (%s)", accountId, formatBytes(result.Bytes)),
		Details:  details,
	}
	s.broadcastDetailedLog(successLog)
}

// logCloudUploadSummary logs the final summary of cloud uploads.
func (s *Service) logCloudUploadSummary(pctx *publishContext, successCount, total int) {
	details := toDetails(CloudUploadSummaryDetails{
		SuccessCount: successCount,
		TotalCount:   total,
	})
	summaryLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.CloudUpload,
		Message:  fmt.Sprintf("Cloud storage upload complete: %d/%d succeeded", successCount, total),
		Details:  details,
	}
	s.broadcastDetailedLog(summaryLog)
}
