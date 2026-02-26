package publish

import (
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	"wp-plugin-publish/pkg/apperror"
)

// ─── Stage Execution ─────────────────────────────────────────────────────────

// executeBackupStage runs the backup stage of the publish pipeline
func (s *Service) executeBackupStage(pctx *publishContext) Stage {
	return s.runStage("backup", func() error {
		s.broadcastProgress(pctx.progress(publishstep.Backup, 10, "Creating backup..."))
		s.broadcastBackupInitLog(pctx)

		return nil
	})
}

// broadcastBackupInitLog sends the backup initiation log.
func (s *Service) broadcastBackupInitLog(pctx *publishContext) {
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
}

// PackageStageResult holds the output of the package stage.
type PackageStageResult struct {
	ZipPath   string
	FileCount int
	Stage     Stage
}

// executePackageStage builds the ZIP package and returns the result
func (s *Service) executePackageStage(pctx *publishContext) PackageStageResult {
	var buildResult *PackageBuildResult

	stage := s.runStage("package", func() error {
		s.broadcastProgress(pctx.progress(publishstep.Packaging, 30, "Building package..."))

		var err error
		buildResult, err = s.buildPluginPackage(pctx)

		return err
	})

	return buildPackageStageResult(buildResult, stage)
}

// buildPackageStageResult constructs the PackageStageResult from build output.
func buildPackageStageResult(buildResult *PackageBuildResult, stage Stage) PackageStageResult {
	if buildResult == nil {
		return PackageStageResult{Stage: stage}
	}

	return PackageStageResult{
		ZipPath:   buildResult.ZipPath,
		FileCount: buildResult.FileCount,
		Stage:     stage,
	}
}

// PackageBuildResult holds the output of building a plugin package.
type PackageBuildResult struct {
	ZipPath   string
	FileCount int
}

// buildPluginPackage creates the ZIP for full or selective mode.
func (s *Service) buildPluginPackage(pctx *publishContext) (*PackageBuildResult, error) {
	s.broadcastPackageStartLog(pctx)

	buildResult, err := s.buildZip(pctx)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create plugin ZIP package")
	}

	s.logZipIfCreated(pctx, buildResult)

	return buildResult, nil
}

// broadcastPackageStartLog sends the package initiation log.
func (s *Service) broadcastPackageStartLog(pctx *publishContext) {
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
}

// logZipIfCreated logs ZIP creation details if a ZIP was produced.
func (s *Service) logZipIfCreated(pctx *publishContext, buildResult *PackageBuildResult) {
	if buildResult.ZipPath == "" {
		return
	}

	zipInput := logZipInput{
		PluginId:  pctx.PluginId,
		SiteId:    pctx.SiteId,
		ZipPath:   buildResult.ZipPath,
		FileCount: buildResult.FileCount,
	}
	s.logZipCreated(zipInput)
}

// buildZip delegates to selective or full zip creation.
func (s *Service) buildZip(pctx *publishContext) (*PackageBuildResult, error) {
	isSelectiveMode := pctx.Options.Mode.IsSelected() && len(pctx.Options.Files) > 0

	if isSelectiveMode {
		return s.buildSelectiveZipResult(pctx)
	}

	return s.buildFullZipResult(pctx)
}

// buildSelectiveZipResult creates a selective ZIP with chosen files.
func (s *Service) buildSelectiveZipResult(pctx *publishContext) (*PackageBuildResult, error) {
	s.broadcastSelectiveZipLog(pctx)

	path, err := s.createSelectiveZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.Options.Files)
	if err != nil {
		return nil, err
	}

	return &PackageBuildResult{ZipPath: path, FileCount: len(pctx.Options.Files)}, nil
}

// broadcastSelectiveZipLog sends the selective ZIP creation log.
func (s *Service) broadcastSelectiveZipLog(pctx *publishContext) {
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
}

// buildFullZipResult creates a full ZIP with all plugin files.
func (s *Service) buildFullZipResult(pctx *publishContext) (*PackageBuildResult, error) {
	fullLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("Creating full ZIP with ~%d files", pctx.PluginInfo.FileCount),
	}
	s.broadcastDetailedLog(fullLog)

	path, err := s.createFullZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.PluginInfo.ExcludePatterns)
	if err != nil {
		return nil, err
	}

	return &PackageBuildResult{ZipPath: path, FileCount: pctx.PluginInfo.FileCount}, nil
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

	backupPath := s.writeRollbackZipToDisk(pctx.Mapping.RemoteSlug, zipData)
	if backupPath == "" {
		return ""
	}

	s.logRollbackZipSaved(pctx, zipData, exportResult.FileCount)

	return backupPath
}

// writeRollbackZipToDisk writes the decoded zip data to a temp file.
func (s *Service) writeRollbackZipToDisk(remoteSlug string, zipData []byte) string {
	backupPath := filepath.Join(s.tempDir, fmt.Sprintf("%s-rollback-%d.zip", remoteSlug, time.Now().Unix()))
	if writeErr := os.WriteFile(backupPath, zipData, 0644); writeErr != nil {
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
