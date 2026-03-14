package publish

import (
	"context"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	"wp-plugin-publish/internal/models"
)

// ─── Stage Execution ─────────────────────────────────────────────────────────

// executeBackupStage runs the backup stage of the publish pipeline.
// It delegates to backupService.Create to persist a real backup ZIP.
func (s *Service) executeBackupStage(ctx context.Context, pctx *publishContext) Stage {
	return s.runStage("backup", func() error {
		s.broadcastProgress(pctx.progress(publishstep.Backup, 10, "Creating backup..."))
		s.broadcastBackupInitLog(pctx)

		backupResult := s.backupService.Create(ctx, pctx.Mapping.Id)
		if backupResult.HasError() {
			return backupResult.AppError()
		}

		backup := backupResult.Value()
		pctx.Result.BackupId = &backup.Id

		s.broadcastBackupCompleteLog(pctx, backup)

		return nil
	})
}

// broadcastBackupCompleteLog sends the backup completion log.
func (s *Service) broadcastBackupCompleteLog(pctx *publishContext, backup models.Backup) {
	completeDetails := toDetails(BackupCompleteDetails{
		BackupId: backup.Id,
		FilePath: backup.FilePath,
		FileSize: backup.FileSize,
	})
	completeLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Backup,
		Message:  fmt.Sprintf("Backup created (ID: %d, size: %s)", backup.Id, formatBytes(backup.FileSize)),
		Details:  completeDetails,
	}
	s.broadcastDetailedLog(completeLog)
}

// broadcastBackupInitLog sends the backup initiation log.
func (s *Service) broadcastBackupInitLog(pctx *publishContext) {
	backupDetails := toDetails(BackupStageDetails{
		MappingId:  pctx.Mapping.Id,
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

		result, appErr := s.buildPluginPackage(pctx)
		if appErr != nil {
			return appErr
		}

		buildResult = result

		return nil
	})

	return buildPackageStageResult(buildResult, stage)
}

// buildPackageStageResult constructs the PackageStageResult from build output.
func buildPackageStageResult(buildResult *PackageBuildResult, stage Stage) PackageStageResult {
	isBuildMissing := buildResult == nil

	if isBuildMissing {
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
func (s *Service) buildPluginPackage(pctx *publishContext) (*PackageBuildResult, *apperror.AppError) {
	s.broadcastPackageStartLog(pctx)

	buildResult, appErr := s.buildZip(pctx)
	if appErr != nil {
		return nil, apperror.Wrap(appErr, apperror.ErrInternal, "failed to create plugin ZIP package")
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
	isZipPathEmpty := buildResult.ZipPath == ""

	if isZipPathEmpty {
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
func (s *Service) buildZip(pctx *publishContext) (*PackageBuildResult, *apperror.AppError) {
	hasSelectedMode := pctx.Options.Mode.IsSelected()
	hasFiles := len(pctx.Options.Files) > 0
	isSelectiveMode :=
		hasSelectedMode &&
		hasFiles

	if isSelectiveMode {
		return s.buildSelectiveZipResult(pctx)
	}

	return s.buildFullZipResult(pctx)
}

// buildSelectiveZipResult creates a selective ZIP with chosen files.
func (s *Service) buildSelectiveZipResult(pctx *publishContext) (*PackageBuildResult, *apperror.AppError) {
	s.broadcastSelectiveZipLog(pctx)

	zipResult := s.createSelectiveZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.Options.Files)
	if zipResult.HasError() {
		return nil, zipResult.AppError()
	}

	return &PackageBuildResult{ZipPath: zipResult.Value(), FileCount: len(pctx.Options.Files)}, nil
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
func (s *Service) buildFullZipResult(pctx *publishContext) (*PackageBuildResult, *apperror.AppError) {
	fullLog := DetailedLogInput{
		PluginId: pctx.PluginId,
		SiteId:   pctx.SiteId,
		Level:    loglevel.Info,
		Step:     publishstep.Package,
		Message:  fmt.Sprintf("Creating full ZIP with ~%d files", pctx.PluginInfo.FileCount),
	}
	s.broadcastDetailedLog(fullLog)

	zipResult := s.createFullZip(pctx.PluginInfo.Path, pctx.PluginInfo.Name, pctx.PluginInfo.ExcludePatterns)
	if zipResult.HasError() {
		return nil, zipResult.AppError()
	}

	return &PackageBuildResult{ZipPath: zipResult.Value(), FileCount: pctx.PluginInfo.FileCount}, nil
}
