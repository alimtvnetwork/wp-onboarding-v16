package backup

import (
	"fmt"
	"os"
	"path/filepath"
	"time"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// removeExpiredBackups walks the backup directory and removes files past retention.
func (s *Service) removeExpiredBackups() apperror.Result[int] {
	cutoff := time.Now().AddDate(0, 0, -s.retentionDays)
	var removedCount int

	err := filepath.Walk(s.backupDir, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			return nil
		}

		checkInput := expiredCheckInput{
			Path:   path,
			Info:   info,
			Cutoff: cutoff,
			Count:  &removedCount,
		}

		return s.removeIfExpired(checkInput)
	})

	if err != nil {
		return apperror.FailWrap[int](err, apperror.ErrFSRead, "cleanup failed")
	}

	return apperror.Ok(removedCount)
}

// expiredCheckInput bundles parameters for removeIfExpired.
type expiredCheckInput struct {
	Path   string
	Info   os.FileInfo
	Cutoff time.Time
	Count  *int
}

// removeIfExpired removes a file if it's older than the cutoff.
func (s *Service) removeIfExpired(input expiredCheckInput) error {
	isFresh := !input.Info.ModTime().Before(input.Cutoff)

	if isFresh {
		return nil
	}

	s.log.Debug("Removing expired backup", "path", input.Path, "modified", input.Info.ModTime())
	expiredDetails := toDetails(ExpiredBackupDetails{ModifiedAt: input.Info.ModTime().Format(time.RFC3339)})
	s.broadcastLog(BackupLogInput{
		PluginId: 0,
		Level:    loglevel.Debug.Lower(),
		Step:     "remove",
		Message:  fmt.Sprintf("Removing expired backup: %s", filepath.Base(input.Path)),
		Details:  expiredDetails,
	})

	appErr := pathutil.RemoveFile(input.Path, "path")
	if appErr == nil {
		*input.Count++
	}

	return nil
}
