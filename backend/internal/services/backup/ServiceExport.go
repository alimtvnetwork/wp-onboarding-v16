package backup

import (
	"archive/zip"
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
)

// exportState holds state during an export operation.
type exportState struct {
	FilesCount int
	TotalBytes int64
}

// ExportToZip creates a zip archive of specified files/directories
func (s *Service) ExportToZip(ctx context.Context, sourcePaths []string, outputPath string) apperror.Result[ExportResult] {
	startTime := time.Now()
	s.log.Info("Starting export", "sources", len(sourcePaths), "output", outputPath)
	initLog := BackupLogInput{
		PluginId: 0,
		Step:     "init",
		Message:  fmt.Sprintf("Starting export to %s", filepath.Base(outputPath)),
		Details:  toDetails(ExportInitDetails{SourceCount: len(sourcePaths)}),
	}
	s.logInfoWithDetails(initLog)

	handleResult := s.createExportZip(outputPath)
	if handleResult.HasError() {
		return apperror.Fail[ExportResult](handleResult.AppError())
	}

	handle := handleResult.Value()
	defer handle.Cleanup()

	state := s.exportSources(handle.Writer, sourcePaths)
	s.logExportComplete(outputPath, state, startTime)

	exportResult := ExportResult{
		OutputPath: outputPath,
		FilesCount: state.FilesCount,
		TotalBytes: state.TotalBytes,
		Duration:   time.Since(startTime),
	}

	return apperror.Ok(exportResult)
}

// exportZipHandle holds the zip writer and cleanup function.
type exportZipHandle struct {
	Writer  *zip.Writer
	Cleanup func()
}

// createExportZip creates the output directory and zip file.
func (s *Service) createExportZip(outputPath string) apperror.Result[*exportZipHandle] {
	mkErr := os.MkdirAll(filepath.Dir(outputPath), 0755)
	if mkErr != nil {
		s.logError(0, "prepare", fmt.Sprintf("Failed to create output directory: %v", mkErr))

		return apperror.FailWrap[*exportZipHandle](mkErr, apperror.ErrFSWrite, "failed to create output directory")
	}

	zipFile, createErr := os.Create(outputPath)
	if createErr != nil {
		s.logError(0, "create", fmt.Sprintf("Failed to create zip file: %v", createErr))

		return apperror.FailWrap[*exportZipHandle](createErr, apperror.ErrFSZip, "failed to create zip file")
	}

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)

	cleanup := func() {
		zipWriter.Close()
		zipFile.Close()
	}

	return apperror.Ok(&exportZipHandle{Writer: zipWriter, Cleanup: cleanup})
}

// exportSources processes all source paths into the zip archive.
func (s *Service) exportSources(zipWriter *zip.Writer, sourcePaths []string) *exportState {
	state := &exportState{}

	for _, sourcePath := range sourcePaths {
		s.exportSingleSource(zipWriter, sourcePath, state)
	}

	return state
}

// exportSingleSource adds a single source (file or directory) to the zip.
func (s *Service) exportSingleSource(zipWriter *zip.Writer, sourcePath string, state *exportState) {
	fi, statErr := pathutil.StatFile(sourcePath)
	if statErr != nil {
		s.log.Warn("Skipping source", "path", sourcePath, "error", statErr)
		s.logWarn(0, "skip", fmt.Sprintf("Skipping source: %s", sourcePath))

		return
	}

	if fi.Info.IsDir() {
		s.exportDirectory(zipWriter, sourcePath, state)

		return
	}

	s.exportFile(exportFileInput{
		ZipWriter: zipWriter,
		FilePath:  sourcePath,
		ZipPath:   filepath.Base(sourcePath),
		State:     state,
	})
}

// exportDirectory walks a directory and adds all files to the zip.
func (s *Service) exportDirectory(zipWriter *zip.Writer, sourcePath string, state *exportState) {
	baseDir := filepath.Base(sourcePath)
	s.logInfo(0, "scan", fmt.Sprintf("Scanning directory: %s", baseDir))

	filepath.Walk(sourcePath, func(path string, fi os.FileInfo, err error) error {
		if err != nil || fi.IsDir() {
			return nil
		}

		relPath, _ := filepath.Rel(sourcePath, path)
		zipEntryPath := filepath.ToSlash(filepath.Join(baseDir, relPath))
		s.exportFile(exportFileInput{
			ZipWriter: zipWriter,
			FilePath:  path,
			ZipPath:   zipEntryPath,
			State:     state,
		})

		return nil
	})
}

// exportFileInput bundles parameters for exportFile.
type exportFileInput struct {
	ZipWriter *zip.Writer
	FilePath  string
	ZipPath   string
	State     *exportState
}

// exportFile adds a single file to the zip archive.
func (s *Service) exportFile(input exportFileInput) {
	result := s.addFileToZip(input.ZipWriter, input.FilePath, input.ZipPath)
	if result.HasError() {
		s.log.Warn("Failed to add file", "path", input.FilePath, "error", result.AppError())

		return
	}

	input.State.FilesCount++
	input.State.TotalBytes += result.Value()
}

// logExportComplete broadcasts the export completion log.
func (s *Service) logExportComplete(outputPath string, state *exportState, startTime time.Time) {
	duration := time.Since(startTime)
	s.log.Info("Export complete", "output", outputPath, "filesCount", state.FilesCount, "totalBytes", state.TotalBytes, "durationMs", duration.Milliseconds())

	completeDetails := toDetails(ExportCompleteDetails{
		FilesCount: state.FilesCount,
		TotalBytes: state.TotalBytes,
		DurationMs: duration.Milliseconds(),
	})
	completeLog := BackupLogInput{
		PluginId: 0,
		Step:     "complete",
		Message:  fmt.Sprintf("Export complete: %d files, %d bytes", state.FilesCount, state.TotalBytes),
		Details:  completeDetails,
	}
	s.logInfoWithDetails(completeLog)
}

// addFileToZip adds a single file to a zip archive
func (s *Service) addFileToZip(zw *zip.Writer, sourcePath, zipPath string) apperror.Result[int64] {
	file, err := os.Open(sourcePath)
	if err != nil {
		return apperror.FailWrap[int64](err, apperror.ErrFSRead, "failed to open file for zipping")
	}
	defer file.Close()

	info, statErr := file.Stat()
	if statErr != nil {
		return apperror.FailWrap[int64](statErr, apperror.ErrFSRead, "failed to stat file for zipping")
	}

	header := &zip.FileHeader{
		Name:     zipPath,
		Method:   zip.Deflate,
		Modified: info.ModTime(),
	}

	writer, headerErr := zw.CreateHeader(header)
	if headerErr != nil {
		return apperror.FailWrap[int64](headerErr, apperror.ErrFSZip, "failed to create zip header")
	}

	written, copyErr := io.Copy(writer, file)
	if copyErr != nil {
		return apperror.FailWrap[int64](copyErr, apperror.ErrFSZip, "failed to copy file into zip")
	}

	return apperror.Ok(written)
}
