package backup

import (
	"fmt"
	"os"
	"path/filepath"
	"time"

	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/pkg/apperror"
)

// removeExpiredBackups walks the backup directory and removes files past retention.
func (s *Service) removeExpiredBackups() (int, error) {
	cutoff := time.Now().AddDate(0, 0, -s.retentionDays)
	var removedCount int

	err := filepath.Walk(s.backupDir, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return nil
		}

		return s.removeIfExpired(path, info, cutoff, &removedCount)
	})

	if err != nil {
		return removedCount, apperror.Wrap(err, apperror.ErrFSRead, "cleanup failed").WithBackupDir(s.backupDir)
	}

	return removedCount, nil
}

// removeIfExpired removes a file if it's older than the cutoff.
func (s *Service) removeIfExpired(path string, info os.FileInfo, cutoff time.Time, count *int) error {
	if !info.ModTime().Before(cutoff) {
		return nil
	}

	s.log.Debug("Removing expired backup", "path", path, "modified", info.ModTime())
	expiredDetails := toDetails(ExpiredBackupDetails{ModifiedAt: info.ModTime().Format(time.RFC3339)})
	s.broadcastLog(BackupLogInput{
		PluginID: 0,
		Level:    loglevel.Debug.Lower(),
		Step:     "remove",
		Message:  fmt.Sprintf("Removing expired backup: %s", filepath.Base(path)),
		Details:  expiredDetails,
	})

	if err := os.Remove(path); err == nil {
		*count++
	}

	return nil
}
