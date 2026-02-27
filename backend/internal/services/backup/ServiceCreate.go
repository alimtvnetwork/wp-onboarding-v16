package backup

import (
	"context"
	"fmt"
	"os"
	"time"

	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// Create downloads the current remote plugin and saves as a backup
func (s *Service) Create(ctx context.Context, mappingID int64) apperror.Result[models.Backup] {
	s.log.Info("Creating backup", "mappingId", mappingID)
	s.logInfoWithDetails(mappingID, "init", "Starting backup creation", toDetails(InitDetails{MappingID: mappingID}))

	backupPath, err := s.createBackupFile(mappingID)
	if err != nil {
		return apperror.FailWrap[models.Backup](err, apperror.ErrBackupCreate, "failed to create backup file")
	}

	backup := s.buildBackupModel(mappingID, backupPath)
	s.runRetentionPolicy(ctx, mappingID)
	s.logBackupComplete(mappingID, backupPath, backup.FileSize)

	return apperror.Ok(backup)
}

// createBackupFile generates the backup zip and returns the path.
func (s *Service) createBackupFile(mappingID int64) (string, error) {
	timestamp := time.Now().Format("20060102-150405")
	filename := fmt.Sprintf("backup-%d-%s.zip", mappingID, timestamp)

	backupPath, err := pathutil.Join(s.backupDir, filename)
	if err != nil {
		return "", err
	}

	s.logInfo(mappingID, "prepare", fmt.Sprintf("Preparing backup file: %s", filename))

	file, err := os.Create(backupPath)
	if err != nil {
		s.logError(mappingID, "create", fmt.Sprintf("Failed to create backup file: %v", err))

		return "", err
	}
	file.Close()

	s.logInfoWithDetails(mappingID, "write", "Backup file created successfully", toDetails(PathDetails{Path: backupPath}))

	return backupPath, nil
}

// buildBackupModel creates the backup model struct.
func (s *Service) buildBackupModel(mappingID int64, backupPath string) models.Backup {
	return models.Backup{
		PluginMappingID: mappingID,
		FilePath:        backupPath,
		FileSize:        pathutil.FileSize(backupPath),
		CreatedAt:       time.Now(),
	}
}

// runRetentionPolicy enforces the retention policy and logs warnings on failure.
func (s *Service) runRetentionPolicy(ctx context.Context, mappingID int64) {
	retentionDetails := toDetails(RetentionDetails{
		MaxPerPlugin:  s.maxPerPlugin,
		RetentionDays: s.retentionDays,
	})
	s.logInfoWithDetails(mappingID, "retention", "Enforcing retention policy", retentionDetails)

	if err := s.enforceRetention(ctx, mappingID); err != nil {
		s.log.Warn("Failed to enforce retention", "error", err)
		s.logWarn(mappingID, "retention", fmt.Sprintf("Retention enforcement warning: %v", err))
	}
}

// logBackupComplete broadcasts the completion log.
func (s *Service) logBackupComplete(mappingID int64, backupPath string, size int64) {
	s.log.Info("Backup created", "mappingId", mappingID, "path", backupPath)
	completeDetails := toDetails(BackupCompleteDetails{Path: backupPath, FileSize: size})
	s.logInfoWithDetails(mappingID, "complete", "Backup created successfully", completeDetails)
}
