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
	s.logInfoWithDetails(0, "init", fmt.Sprintf("Starting export to %s", filepath.Base(outputPath)), toDetails(ExportInitDetails{SourceCount: len(sourcePaths)}))

	zipWriter, cleanup, appErr := s.createExportZip(outputPath)
	if appErr != nil {

		return apperror.Fail[ExportResult](appErr)
	}
	defer cleanup()

	state := s.exportSources(zipWriter, sourcePaths)
	s.logExportComplete(outputPath, state, startTime)

	return apperror.Ok(ExportResult{
		OutputPath: outputPath,
		FilesCount: state.FilesCount,
		TotalBytes: state.TotalBytes,
		Duration:   time.Since(startTime),
	})
}

// createExportZip creates the output directory and zip file.
func (s *Service) createExportZip(outputPath string) (*zip.Writer, func(), *apperror.AppError) {
	mkErr := os.MkdirAll(filepath.Dir(outputPath), 0755)
	if mkErr != nil {
		s.logError(0, "prepare", fmt.Sprintf("Failed to create output directory: %v", mkErr))

		return nil, nil, apperror.Wrap(mkErr, apperror.ErrFSWrite, "failed to create output directory")
	}

	zipFile, createErr := os.Create(outputPath)
	if createErr != nil {
		s.logError(0, "create", fmt.Sprintf("Failed to create zip file: %v", createErr))

		return nil, nil, apperror.Wrap(createErr, apperror.ErrFSZip, "failed to create zip file")
	}

	zipWriter := zip.NewWriter(zipFile)
	ziputil.RegisterBestCompression(zipWriter)

	cleanup := func() {
		zipWriter.Close()
		zipFile.Close()
	}

	return zipWriter, cleanup, nil
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

	s.exportFile(zipWriter, sourcePath, filepath.Base(sourcePath), state)
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
		zipPath := filepath.ToSlash(filepath.Join(baseDir, relPath))
		s.exportFile(zipWriter, path, zipPath, state)

		return nil
	})
}

// exportFile adds a single file to the zip archive.
func (s *Service) exportFile(zipWriter *zip.Writer, filePath, zipPath string, state *exportState) {
	written, appErr := s.addFileToZip(zipWriter, filePath, zipPath)
	if appErr != nil {
		s.log.Warn("Failed to add file", "path", filePath, "error", appErr)

		return
	}

	state.FilesCount++
	state.TotalBytes += written
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
	s.logInfoWithDetails(0, "complete", fmt.Sprintf("Export complete: %d files, %d bytes", state.FilesCount, state.TotalBytes), completeDetails)
}

// addFileToZip adds a single file to a zip archive
func (s *Service) addFileToZip(zw *zip.Writer, sourcePath, zipPath string) (int64, *apperror.AppError) {
	file, err := os.Open(sourcePath)
	if err != nil {

		return 0, apperror.Wrap(err, apperror.ErrFSRead, "failed to open file for zipping").
			WithFilePath(sourcePath)
	}
	defer file.Close()

	info, statErr := file.Stat()
	if statErr != nil {

		return 0, apperror.Wrap(statErr, apperror.ErrFSRead, "failed to stat file for zipping").
			WithFilePath(sourcePath)
	}

	header := &zip.FileHeader{
		Name:     zipPath,
		Method:   zip.Deflate,
		Modified: info.ModTime(),
	}

	writer, headerErr := zw.CreateHeader(header)
	if headerErr != nil {

		return 0, apperror.Wrap(headerErr, apperror.ErrFSZip, "failed to create zip header").
			WithFilePath(zipPath)
	}

	written, copyErr := io.Copy(writer, file)
	if copyErr != nil {

		return 0, apperror.Wrap(copyErr, apperror.ErrFSZip, "failed to copy file into zip").
			WithFilePath(sourcePath)
	}

	return written, nil
}
