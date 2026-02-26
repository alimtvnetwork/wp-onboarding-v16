package publish

import (
	"encoding/base64"
	"fmt"
	"os"
	"path/filepath"
	"time"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// ─── Stage Execution ─────────────────────────────────────────────────────────

// executeBackupStage runs the backup stage of the publish pipeline
func (s *Service) executeBackupStage(pctx *publishContext) Stage {
	return s.runStage("backup", func() error {
		backupProgress := ProgressInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Step:     publishstep.Backup,
			Progress: 10,
			Message:  "Creating backup...",
		}
		s.broadcastProgress(backupProgress)

		backupDetails := toDetails(BackupStageDetails{
			MappingID:  pctx.Mapping.ID,
			RemoteSlug: pctx.Mapping.RemoteSlug,
		})
		backupLog := DetailedLogInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Backup,
			Message:  "Initiating remote plugin backup",
			Details:  backupDetails,
		}
		s.broadcastDetailedLog(backupLog)

		return nil
	})
}

// executePackageStage builds the ZIP package and returns the path, file count, and stage result
func (s *Service) executePackageStage(pctx *publishContext) (string, int, Stage) {
	var zipPath string
	var fileCount int

	stage := s.runStage("package", func() error {
		pkgProgress := ProgressInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Step:     publishstep.Packaging,
			Progress: 30,
			Message:  "Building package...",
		}
		s.broadcastProgress(pkgProgress)

		var err error
		zipPath, fileCount, err = s.buildPluginPackage(pctx)

		return err
	})

	return zipPath, fileCount, stage
}

// buildPluginPackage creates the ZIP for full or selective mode.
func (s *Service) buildPluginPackage(pctx *publishContext) (string, int, error) {
	pkgDetails := toDetails(PackageDetails{
		PluginPath:      pctx.PluginInfo.Path,
		PluginName:      pctx.PluginInfo.Name,
		Mode:            pctx.Options.Mode,
		ExcludePatterns: pctx.PluginInfo.ExcludePatterns,
	})
	pkgLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("Packaging plugin from: %s", pctx.PluginInfo.Path),
		Details:  pkgDetails,
	}
	s.broadcastDetailedLog(pkgLog)

	zipPath, fileCount, err := s.buildZip(pctx)
	if err != nil {
		return "", 0, apperror.Wrap(err, apperror.ErrInternal, "failed to create plugin ZIP package")
	}

	if zipPath != "" {
		zipInput := logZipInput{
			PluginId:  pctx.PluginId,
			SiteId:    pctx.SiteId,
			ZipPath:   zipPath,
			FileCount: fileCount,
		}
		s.logZipCreated(zipInput)
	}

	return zipPath, fileCount, nil
}

// buildZip delegates to selective or full zip creation.
func (s *Service) buildZip(pctx *publishContext) (string, int, error) {
	if pctx.Options.Mode.IsSelected() && len(pctx.Options.Files) > 0 {
		selectDetails := toDetails(SelectedFilesDetails{
			SelectedFiles: pctx.Options.Files,
		})
		selectLog := DetailedLogInput{
			PluginId: pctx.PluginId,
			SiteId:   pctx.SiteId,
			Level:    loglevel.Info,
			Step:     publishstep.Package,
			Message:  fmt.Sprintf("Creating selective ZIP with %d files", len(pctx.Options.Files)),
			Details:  selectDetails,
		}
		s.broadcastDetailedLog(selectLog)

		path, err := s.createSelectiveZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.Options.Files)

		return path, len(pctx.Options.Files), err
	}

	fullLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("Creating full ZIP with ~%d files", pctx.PluginInfo.FileCount),
	}
	s.broadcastDetailedLog(fullLog)

	path, err := s.createFullZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.PluginInfo.ExcludePatterns)

	return path, pctx.PluginInfo.FileCount, err
}

// ─── Pre-Upload Backup ──────────────────────────────────────────────────────

// createPreUploadBackup exports the remote plugin for rollback capability
func (s *Service) createPreUploadBackup(ctx context.Context, pctx *publishContext) string {
	s.broadcastProgress(pctx.progress(publishstep.PreBackup, 45, "Creating pre-upload backup for rollback..."))

	return s.exportRemoteForRollback(pctx)
}

// exportRemoteForRollback exports and saves the remote plugin zip.
func (s *Service) exportRemoteForRollback(pctx *publishContext) string {
	exportResult, exportErr := pctx.WPClient.ExportPlugin(pctx.Mapping.RemoteSlug)
	if exportErr != nil {
		skipCtx := StageContext{
			What:   "Pre-upload backup for rollback",
			Result: fmt.Sprintf("Skipped: %s (rollback won't be available)", exportErr.Error()),
		}
		skipLog := pctx.stageLog(loglevel.Warn, publishstep.PreBackup, skipCtx)
		s.broadcastStageLog(skipLog)

		return ""
	}

	if exportResult == nil || exportResult.PluginZip == "" {
		return ""
	}

	return s.saveRollbackZip(pctx, exportResult)
}

// saveRollbackZip decodes and writes the rollback zip to disk.
func (s *Service) saveRollbackZip(pctx *publishContext, exportResult *wordpress.ExportResult) string {
	zipData, decErr := base64.StdEncoding.DecodeString(exportResult.PluginZip)
	if decErr != nil {
		return ""
	}

	backupPath := filepath.Join(s.tempDir, fmt.Sprintf("%s-rollback-%d.zip", pctx.Mapping.RemoteSlug, time.Now().Unix()))
	if writeErr := os.WriteFile(backupPath, zipData, 0644); writeErr != nil {
		return ""
	}

	savedCtx := StageContext{
		What:   "Pre-upload backup created",
		Result: fmt.Sprintf("Saved %s (%d files)", formatBytes(int64(len(zipData))), exportResult.FileCount),
	}
	savedLog := pctx.stageLog(loglevel.Info, publishstep.PreBackup, savedCtx)
	s.broadcastStageLog(savedLog)

	return backupPath
}
