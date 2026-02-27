package backup

import (
	"archive/zip"
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// importState holds state during an import operation.
type importState struct {
	FilesCount int
	TotalBytes int64
}

// ImportFromZip extracts a zip archive to the specified directory
func (s *Service) ImportFromZip(ctx context.Context, zipPath, destDir string, isOverwrite bool) apperror.Result[ImportResult] {
	startTime := time.Now()
	s.log.Info("Starting import", "zip", zipPath, "dest", destDir, "overwrite", isOverwrite)
	s.logInfoWithDetails(0, "init", fmt.Sprintf("Starting import from %s", filepath.Base(zipPath)), toDetails(ImportInitDetails{Destination: destDir, IsOverwrite: isOverwrite}))

	reader, err := s.openImportZip(zipPath, destDir, isOverwrite)
	if err != nil {
		return apperror.Fail[ImportResult](err)
	}
	defer reader.Close()

	state, err := s.extractAllFiles(reader, destDir)
	if err != nil {
		return apperror.Fail[ImportResult](err)
	}

	s.logImportComplete(destDir, state, startTime)

	return apperror.Ok(ImportResult{
		DestPath:   destDir,
		FilesCount: state.FilesCount,
		TotalBytes: state.TotalBytes,
		Duration:   time.Since(startTime),
	})
}

// openImportZip validates and opens the zip file.
func (s *Service) openImportZip(zipPath, destDir string, isOverwrite bool) (*zip.ReadCloser, error) {
	reader, err := zip.OpenReader(zipPath)
	if err != nil {
		s.logError(0, "open", fmt.Sprintf("Failed to open zip: %v", err))

		return nil, apperror.Wrap(err, apperror.ErrFSZip, "failed to open zip")
	}

	if err := s.validateImportDest(destDir, isOverwrite); err != nil {
		reader.Close()

		return nil, err
	}

	return reader, nil
}

// validateImportDest checks if the destination is safe to write to.
func (s *Service) validateImportDest(destDir string, isOverwrite bool) error {
	_, statErr := pathutil.StatDir(destDir)
	isDestExists := statErr == nil
	isConflict := isDestExists && !isOverwrite

	if isConflict {
		s.logError(0, "check", "Destination exists, overwrite not enabled")

		return apperror.New(apperror.ErrFSWrite, "destination exists, use overwrite=true to replace")
	}

	if err := os.MkdirAll(destDir, 0755); err != nil {
		s.logError(0, "prepare", fmt.Sprintf("Failed to create destination: %v", err))

		return apperror.Wrap(err, apperror.ErrFSWrite, "failed to create destination")
	}

	return nil
}

// extractAllFiles extracts all files from the zip reader.
func (s *Service) extractAllFiles(reader *zip.ReadCloser, destDir string) (*importState, error) {
	state := &importState{}
	s.logInfo(0, "extract", fmt.Sprintf("Extracting %d files", len(reader.File)))

	for _, file := range reader.File {
		if file.FileInfo().IsDir() {
			continue
		}

		if err := s.extractSingleFile(file, destDir, state); err != nil {
			return state, err
		}
	}

	return state, nil
}

// extractSingleFile extracts one file from the zip archive.
func (s *Service) extractSingleFile(file *zip.File, destDir string, state *importState) error {
	destPath, ok := s.resolveExtractPath(file.Name, destDir)
	if !ok {
		return nil
	}

	if err := os.MkdirAll(filepath.Dir(destPath), 0755); err != nil {
		s.logError(0, "mkdir", fmt.Sprintf("Failed to create directory: %v", err))

		return apperror.Wrap(err, apperror.ErrFSWrite, "failed to create directory")
	}

	written, err := s.extractFile(file, destPath)
	if err != nil {
		s.logError(0, "extract", fmt.Sprintf("Failed to extract %s: %v", file.Name, err))

		return apperror.Wrap(err, apperror.ErrFSWrite, "failed to extract file")
	}

	state.FilesCount++
	state.TotalBytes += written

	return nil
}

// resolveExtractPath validates and returns the safe destination path for a zip entry.
func (s *Service) resolveExtractPath(name, destDir string) (string, bool) {
	destPath, err := pathutil.Join(destDir, name)
	if err != nil {
		s.log.Warn("Failed to resolve zip entry path", "path", name, "error", err)

		return "", false
	}

	isZipSlip := !strings.HasPrefix(destPath, filepath.Clean(destDir)+string(os.PathSeparator))
	if isZipSlip {
		s.log.Warn("Skipping potentially dangerous file path", "path", name)
		s.logWarn(0, "security", fmt.Sprintf("Skipping dangerous path: %s", name))

		return "", false
	}

	return destPath, true
}

// extractFile extracts a single file from a zip archive
func (s *Service) extractFile(file *zip.File, destPath string) (int64, error) {
	src, err := file.Open()
	if err != nil {
		return 0, err
	}
	defer src.Close()

	dst, err := os.Create(destPath)
	if err != nil {
		return 0, err
	}
	defer dst.Close()

	return io.Copy(dst, src)
}

// logImportComplete broadcasts the import completion log.
func (s *Service) logImportComplete(destDir string, state *importState, startTime time.Time) {
	duration := time.Since(startTime)
	s.log.Info("Import complete", "dest", destDir, "filesCount", state.FilesCount, "totalBytes", state.TotalBytes, "durationMs", duration.Milliseconds())

	completeDetails := toDetails(ImportCompleteDetails{
		FilesCount: state.FilesCount,
		TotalBytes: state.TotalBytes,
		DurationMs: duration.Milliseconds(),
	})
	s.logInfoWithDetails(0, "complete", fmt.Sprintf("Import complete: %d files, %d bytes", state.FilesCount, state.TotalBytes), completeDetails)
}
