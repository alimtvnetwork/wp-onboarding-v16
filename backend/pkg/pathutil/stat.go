// Package pathutil — file/directory stat helpers returning structured errors.
package pathutil

import (
	"os"

	"wp-plugin-publish/pkg/apperror"
)

// FileInfo wraps os.FileInfo with the resolved absolute path.
type FileInfo struct {
	Info os.FileInfo
	Path string // absolute path used for the stat call
}

// StatFile resolves the path to absolute and returns os.FileInfo wrapped in an AppError.
// Returns ErrFSNotFound when the file does not exist, ErrFSRead for other stat failures.
func StatFile(path string) (*FileInfo, *apperror.AppError) {
	abs, err := ToAbsolute(path)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrPathInvalid, "failed to resolve path").
			WithPath(path)
	}

	info, err := os.Stat(abs)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, apperror.New(apperror.ErrFSNotFound, "file not found").
				WithPath(abs)
		}

		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to stat file").
			WithPath(abs)
	}

	return &FileInfo{Info: info, Path: abs}, nil
}

// StatDir resolves the path and verifies it is a directory.
// Returns ErrFSNotFound when missing, ErrFSInvalid when the path is not a directory.
func StatDir(path string) (*FileInfo, *apperror.AppError) {
	fi, appErr := StatFile(path)
	if appErr != nil {
		return nil, appErr
	}

	if !fi.Info.IsDir() {
		return nil, apperror.New(apperror.ErrFSInvalid, "path is not a directory").
			WithPath(fi.Path)
	}

	return fi, nil
}

// IsFileExists returns true when the path exists (file or directory).
func IsFileExists(path string) bool {
	return Exists(path)
}

// IsFileMissing returns true when the path does not exist.
func IsFileMissing(path string) bool {
	return !Exists(path)
}

// FileSize returns the file size in bytes, or 0 if the file cannot be stat'd.
func FileSize(path string) int64 {
	fi, appErr := StatFile(path)
	if appErr != nil {
		return 0
	}

	return fi.Info.Size()
}
