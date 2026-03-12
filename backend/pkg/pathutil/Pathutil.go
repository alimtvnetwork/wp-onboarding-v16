// Package pathutil provides cross-platform path utilities with special handling for Windows long paths.
package pathutil

import (
	"os"
	"path/filepath"
	"runtime"
	"strings"
)

const (
	// windowsLongPathPrefix is required for paths exceeding 260 characters on Windows.
	// See: https://docs.microsoft.com/en-us/windows/win32/fileio/naming-a-file
	windowsLongPathPrefix = `\\?\`
)

// ToAbsolute converts any path (relative or absolute) to a fully resolved absolute path.
// On Windows, if the resulting path exceeds 260 characters, the long path prefix is added.
// This function MUST be used when providing paths to external systems (uploads, logs, etc.).
func ToAbsolute(path string) (string, error) {
	isPathEmpty := path == ""

	if isPathEmpty {
		return "", nil
	}

	// Clean and convert to absolute
	cleaned := filepath.Clean(path)
	abs, err := filepath.Abs(cleaned)

	if err != nil {
		return "", err
	}

	// Normalize slashes
	abs = filepath.Clean(abs)

	// Handle Windows long paths
	if runtime.GOOS == "windows" {
		abs = ensureWindowsLongPath(abs)
	}

	return abs, nil
}

// Join joins path elements and returns an absolute path.
// This should be used instead of filepath.Join when the result will be passed to external systems.
func Join(elem ...string) (string, error) {
	if len(elem) == 0 {
		return "", nil
	}
	joined := filepath.Join(elem...)
	return ToAbsolute(joined)
}

// ensureWindowsLongPath adds the long path prefix if needed on Windows.
func ensureWindowsLongPath(path string) string {
	// Skip if already has prefix
	if strings.HasPrefix(path, windowsLongPathPrefix) {
		return path
	}

	// Skip UNC paths (\\server\share) - they use different prefix
	if strings.HasPrefix(path, `\\`) {
		return path
	}

	// Add prefix if path is long or might become long
	// We use 240 as threshold to leave room for additional path segments
	if len(path) > 240 {
		return windowsLongPathPrefix + path
	}

	return path
}

// Exists checks if a path exists (after resolving to absolute).
func Exists(path string) bool {
	abs, err := ToAbsolute(path)
	if err != nil {
		return false
	}
	_, err = os.Stat(abs)
	return err == nil
}

// IsDir checks if a path is a directory (after resolving to absolute).
func IsDir(path string) bool {
	abs, err := ToAbsolute(path)
	if err != nil {
		return false
	}
	info, err := os.Stat(abs)
	if err != nil {
		return false
	}
	return info.IsDir()
}

// IsDirMissing returns true when the path does not exist or is not a directory.
func IsDirMissing(path string) bool { return !IsDir(path) }

// ForDisplay returns a path suitable for display in logs (absolute, forward slashes).
func ForDisplay(path string) string {
	abs, err := ToAbsolute(path)
	if err != nil {
		return path // Return original if resolution fails
	}
	// Use forward slashes for consistency in logs
	return filepath.ToSlash(abs)
}