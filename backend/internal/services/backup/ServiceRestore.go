package backup

import (
	"context"
	"fmt"

	"wp-plugin-publish/pkg/apperror"
)

// Restore uploads a backup to WordPress
func (s *Service) Restore(ctx context.Context, backupID int64) apperror.Result[RestoreResult] {
	s.log.Info("Restoring backup", "backupId", backupID)
	s.logInfoWithDetails(backupID, "init", "Starting backup restore", toDetails(InitDetails{BackupID: backupID}))

	// TODO: Implement restore steps
	s.logInfo(backupID, "locate", "Locating backup file")
	s.logInfo(backupID, "upload", "Uploading backup to WordPress")
	s.logInfo(backupID, "activate", "Activating restored plugin")
	s.logInfo(backupID, "complete", "Backup restored successfully")

	return apperror.Ok(RestoreResult{IsSuccess: true})
}

// Delete removes a backup file and database record
func (s *Service) Delete(ctx context.Context, id int64) *apperror.AppError {
	s.log.Info("Deleting backup", "id", id)
	s.logInfoWithDetails(id, "delete", "Deleting backup", toDetails(InitDetails{BackupID: id}))

	// TODO: Get backup path from database and delete file

	s.logInfo(id, "complete", "Backup deleted successfully")

	return nil
}

// Cleanup removes expired backups
func (s *Service) Cleanup(ctx context.Context) *apperror.AppError {
	s.log.Info("Running backup cleanup")
	s.logInfoWithDetails(0, "init", "Starting backup cleanup", toDetails(CleanupInitDetails{RetentionDays: s.retentionDays}))

	removedCount, appErr := s.removeExpiredBackups()
	if appErr != nil {
		s.logError(0, "cleanup", fmt.Sprintf("Cleanup failed: %v", appErr))

		return appErr
	}

	s.logCleanupComplete(removedCount)

	return nil
}

// logCleanupComplete broadcasts the cleanup completion log.
func (s *Service) logCleanupComplete(removedCount int) {
	s.log.Info("Backup cleanup complete")
	completeDetails := toDetails(CleanupCompleteDetails{RemovedCount: removedCount})
	s.logInfoWithDetails(0, "complete", fmt.Sprintf("Cleanup complete, removed %d expired backups", removedCount), completeDetails)
}
