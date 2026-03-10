package publish

import (
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
)

// runRemoteBackupStage calls the remote WordPress backup endpoint before upload.
// On failure, logs a warning but does not block the pipeline.
func (s *Service) runRemoteBackupStage(pctx *publishContext) {
	s.broadcastProgress(pctx.progress(publishstep.RemoteBackup, 8, "Creating remote backup on WordPress site..."))
	s.logRemoteBackupInit(pctx)

	backupResult := pctx.WPClient.CreateRemoteBackup(pctx.Mapping.RemoteSlug)
	if backupResult.HasError() {
		s.logRemoteBackupSkipped(pctx, backupResult.AppError().Error())

		return
	}

	result := backupResult.Value()
	s.logRemoteBackupComplete(pctx, result.Filename, result.Size)
}

// logRemoteBackupInit broadcasts the remote backup initiation log.
func (s *Service) logRemoteBackupInit(pctx *publishContext) {
	details := toDetails(RemoteBackupInitDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		SiteUrl:    pctx.SiteInfo.Url,
	})
	initLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.RemoteBackup,
		Message:  fmt.Sprintf("Creating remote backup of %s on %s", pctx.Mapping.RemoteSlug, pctx.SiteInfo.Url),
		Details:  details,
	}
	s.broadcastDetailedLog(initLog)
}

// logRemoteBackupSkipped broadcasts a warning when remote backup fails.
func (s *Service) logRemoteBackupSkipped(pctx *publishContext, errMsg string) {
	skipCtx := StageContext{
		What:   "Remote plugin backup before publish",
		Result: fmt.Sprintf("Skipped: %s (publish will continue without remote backup)", errMsg),
	}
	skipLog := pctx.stageLog(loglevel.Warn, publishstep.RemoteBackup, skipCtx)
	s.broadcastStageLog(skipLog)
}

// logRemoteBackupComplete broadcasts the remote backup success log.
func (s *Service) logRemoteBackupComplete(pctx *publishContext, filename string, size int64) {
	details := toDetails(RemoteBackupCompleteDetails{
		RemoteSlug: pctx.Mapping.RemoteSlug,
		Filename:   filename,
		FileSize:   size,
	})
	completeLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.RemoteBackup,
		Message:  fmt.Sprintf("Remote backup created: %s (%s)", filename, formatBytes(size)),
		Details:  details,
	}
	s.broadcastDetailedLog(completeLog)
}
