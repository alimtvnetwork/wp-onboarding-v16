package publish

import (
	"encoding/base64"
	"fmt"
	"os"
	"path/filepath"
	"time"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	"wp-plugin-publish/internal/wordpress"
)

// ─── Pre-Upload Backup ──────────────────────────────────────────────────────

// createPreUploadBackup exports the remote plugin for rollback capability
func (s *Service) createPreUploadBackup(ctx context.Context, pctx *publishContext) string {
	s.broadcastProgress(pctx.progress(publishstep.PreBackup, 45, "Creating pre-upload backup for rollback..."))

	return s.exportRemoteForRollback(pctx)
}

// exportRemoteForRollback exports and saves the remote plugin zip.
func (s *Service) exportRemoteForRollback(pctx *publishContext) string {
	exportResult := pctx.WPClient.ExportPlugin(pctx.Mapping.RemoteSlug)
	if exportResult.HasError() {
		skipCtx := StageContext{
			What:   "Pre-upload backup for rollback",
			Result: fmt.Sprintf("Skipped: %s (rollback won't be available)", exportResult.AppError().Error()),
		}
		skipLog := pctx.stageLog(loglevel.Warn, publishstep.PreBackup, skipCtx)
		s.broadcastStageLog(skipLog)

		return ""
	}

	result := exportResult.Value()
	isExportEmpty := result == nil || result.PluginZip == ""

	if isExportEmpty {
		return ""
	}

	return s.saveRollbackZip(pctx, result)
}

// saveRollbackZip decodes and writes the rollback zip to disk.
func (s *Service) saveRollbackZip(pctx *publishContext, exportResult *wordpress.ExportPluginResult) string {
	zipData, decErr := base64.StdEncoding.DecodeString(exportResult.PluginZip)
	if decErr != nil {
		return ""
	}

	backupPath := s.writeRollbackZipToDisk(pctx.Mapping.RemoteSlug, zipData)
	isBackupPathEmpty := backupPath == ""

	if isBackupPathEmpty {
		return ""
	}

	s.logRollbackZipSaved(pctx, zipData, exportResult.FileCount)

	return backupPath
}

// writeRollbackZipToDisk writes the decoded zip data to a temp file.
func (s *Service) writeRollbackZipToDisk(remoteSlug string, zipData []byte) string {
	backupPath := filepath.Join(s.tempDir, fmt.Sprintf("%s-rollback-%d.zip", remoteSlug, time.Now().Unix()))

	writeErr := os.WriteFile(backupPath, zipData, 0644)
	if writeErr != nil {
		return ""
	}

	return backupPath
}

// logRollbackZipSaved logs that the rollback zip was saved.
func (s *Service) logRollbackZipSaved(pctx *publishContext, zipData []byte, fileCount int) {
	savedCtx := StageContext{
		What:   "Pre-upload backup created",
		Result: fmt.Sprintf("Saved %s (%d files)", formatBytes(int64(len(zipData))), fileCount),
	}
	savedLog := pctx.stageLog(loglevel.Info, publishstep.PreBackup, savedCtx)
	s.broadcastStageLog(savedLog)
}
