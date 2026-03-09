package publish

import (
	"crypto/md5"
	"fmt"
	"io"
	"os"

	"wp-plugin-publish/pkg/apperror"
)

// formatBytes formats byte count as human-readable string
func formatBytes(bytes int64) string {
	const unit = 1024
	if bytes < unit {
		return fmt.Sprintf("%d B", bytes)
	}
	div, exp := int64(unit), 0
	for n := bytes / unit; n >= unit; n /= unit {
		div *= unit
		exp++
	}
	return fmt.Sprintf("%.1f %cB", float64(bytes)/float64(div), "KMGTPE"[exp])
}

// truncateString truncates a string to maxLen with ellipsis
func truncateString(s string, maxLen int) string {
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen-3] + "..."
}

// calculateFileHash computes MD5 hash of a file.
func (s *Service) calculateFileHash(path string) apperror.Result[string] {
	file, err := os.Open(path)
	if err != nil {
		return apperror.FailWrap[string](err, apperror.ErrFSRead, "failed to open file for hashing")
	}
	defer file.Close()

	h := md5.New()
	_, copyErr := io.Copy(h, file)
	if copyErr != nil {
		return apperror.FailWrap[string](copyErr, apperror.ErrFSRead, "failed to read file for hashing")
	}

	return apperror.Ok(fmt.Sprintf("%x", h.Sum(nil)))
}
