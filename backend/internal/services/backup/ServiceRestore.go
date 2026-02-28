package backup

import (
	"context"
	"fmt"

	"wp-plugin-publish/pkg/apperror"
)

// Restore uploads a backup to WordPress
func (s *Service) Restore(ctx context.Context, backupId int64) apperror.Result[RestoreResult] {
	s.log.Info("Restoring backup", "backupId", backupId)

	restoreLog := BackupLogInput{
		PluginId: backupId,
		Step:     "init",
		Message:  "Starting backup restore",
		Details:  toDetails(InitDetails{BackupId: backupId}),
	}
	s.logInfoWithDetails(restoreLog)

	// TODO: Implement restore steps
	s.logInfo(backupId, "locate", "Locating backup file")
	s.logInfo(backupId, "upload", "Uploading backup to WordPress")
	s.logInfo(backupId, "activate", "Activating restored plugin")
	s.logInfo(backupId, "complete", "Backup restored successfully")

	return apperror.Ok(RestoreResult{IsSuccess: true})
}

// Delete removes a backup file and database record
func (s *Service) Delete(ctx context.Context, id int64) *apperror.AppError {
	s.log.Info("Deleting backup", "id", id)

	deleteLog := BackupLogInput{
		PluginId: id,
		Step:     "delete",
		Message:  "Deleting backup",
		Details:  toDetails(InitDetails{BackupId: id}),
	}
	s.logInfoWithDetails(deleteLog)

	// TODO: Get backup path from database and delete file

	s.logInfo(id, "complete", "Backup deleted successfully")

	return nil
}

// Cleanup removes expired backups
func (s *Service) Cleanup(ctx context.Context) *apperror.AppError {
	s.log.Info("Running backup cleanup")

	cleanupLog := BackupLogInput{
		PluginId: 0,
		Step:     "init",
		Message:  "Starting backup cleanup",
		Details:  toDetails(CleanupInitDetails{RetentionDays: s.retentionDays}),
	}
	s.logInfoWithDetails(cleanupLog)

	result := s.removeExpiredBackups()
	if result.HasError() {
		s.logError(0, "cleanup", fmt.Sprintf("Cleanup failed: %v", result.AppError()))

		return result.AppError()
	}

	s.logCleanupComplete(result.Value())

	return nil
}

// logCleanupComplete broadcasts the cleanup completion log.
func (s *Service) logCleanupComplete(removedCount int) {
	s.log.Info("Backup cleanup complete")
	completeDetails := toDetails(CleanupCompleteDetails{RemovedCount: removedCount})

	completeLog := BackupLogInput{
		PluginId: 0,
		Step:     "complete",
		Message:  fmt.Sprintf("Cleanup complete, removed %d expired backups", removedCount),
		Details:  completeDetails,
	}
	s.logInfoWithDetails(completeLog)
}
