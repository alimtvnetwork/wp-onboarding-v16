// Package pathutil — file/directory removal helpers returning structured errors.
package pathutil

import (
	"os"

	"wp-plugin-publish/pkg/apperror"
)

// RemoveFile removes a single file. Returns nil if the file doesn't exist.
// The varName parameter is included in the error context for debugging.
func RemoveFile(path string, varName string) *apperror.AppError {
	abs, err := ToAbsolute(path)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrPathInvalid, "failed to resolve path for removal").
			WithPath(path).
			WithValue("varName", varName)
	}

	err = os.Remove(abs)
	if err == nil {
		return nil
	}

	isNotFound := os.IsNotExist(err)

	if isNotFound {
		return nil
	}

	return apperror.Wrap(err, apperror.ErrFSDelete, "failed to remove file").
		WithPath(abs).
		WithValue("varName", varName)
}

// RemoveDir removes a directory and all its contents. Returns nil if the directory doesn't exist.
// The varName parameter is included in the error context for debugging.
func RemoveDir(path string, varName string) *apperror.AppError {
	abs, err := ToAbsolute(path)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrPathInvalid, "failed to resolve path for removal").
			WithPath(path).
			WithValue("varName", varName)
	}

	err = os.RemoveAll(abs)
	if err == nil {
		return nil
	}

	isNotFound := os.IsNotExist(err)

	if isNotFound {
		return nil
	}

	return apperror.Wrap(err, apperror.ErrFSDelete, "failed to remove directory").
		WithPath(abs).
		WithValue("varName", varName)
}

// RemoveFileUnchecked removes a file without returning an error. Suitable for cleanup/defer.
// Logs nothing — use RemoveFile when error handling is needed.
func RemoveFileUnchecked(path string) {
	abs, err := ToAbsolute(path)
	if err != nil {
		return
	}

	os.Remove(abs)
}

// RemoveEntry removes a file or directory based on the isDir flag. Returns nil if not found.
func RemoveEntry(
	path string,
	isDir bool,
	varName string,
) *apperror.AppError {
	if isDir {
		return RemoveDir(path, varName)
	}

	return RemoveFile(path, varName)
}
