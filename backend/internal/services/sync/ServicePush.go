// Package sync — push sync operations.
package sync

import (
	"context"
	"encoding/base64"
	"fmt"
	"os"
	"path/filepath"

	changetype "wp-plugin-publish/internal/enums/changetype"
	syncdirection "wp-plugin-publish/internal/enums/syncdirectiontype"
	syncstep "wp-plugin-publish/internal/enums/syncsteptype"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// PushSync performs a full comparison and pushes all changes to the remote site
func (s *serviceImpl) PushSync(ctx context.Context, pluginID, siteID int64) apperror.Result[PushSyncResult] {
	result := PushSyncResult{
		PluginID: pluginID,
		SiteID:   siteID,
	}

	// 1. Run comparison
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Checking.Value(), Progress: 0, Message: "Running sync comparison..."})
	syncResult := s.CheckSync(ctx, pluginID, siteID)
	if syncResult.HasError() {
		return apperror.FailWrap[PushSyncResult](syncResult.AppError(), apperror.ErrInternal, "sync comparison failed")
	}
	sr := syncResult.Value()
	if sr.ErrorMessage != "" {
		result.ErrorMessage = sr.ErrorMessage

		return apperror.Ok(result)
	}

	if sr.IsInSync {
		result.IsSuccess = true
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "Already in sync, nothing to push"})

		return apperror.Ok(result)
	}

	// 2. Get plugin info
	plugResult := s.pluginService.GetByID(ctx, pluginID)
	if plugResult.HasError() {
		return apperror.FailWrap[PushSyncResult](plugResult.AppError(), apperror.ErrDatabaseQuery, "failed to get plugin")
	}
	plug := plugResult.Value()

	// 3. Get mapping
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return apperror.FailWrap[PushSyncResult](err, apperror.ErrDatabaseQuery, "failed to get mapping")
	}

	// 4. Get site credentials
	siteInfo, err := s.getSiteInfo(ctx, siteID)
	if err != nil {
		return apperror.FailWrap[PushSyncResult](err, apperror.ErrDatabaseQuery, "failed to get site info")
	}
	password, err := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if err != nil {
		return apperror.FailWrap[PushSyncResult](err, apperror.ErrInternal, "failed to decrypt credentials")
	}

	// 5. Build sync files
	syncFiles, buildErr := s.buildSyncFiles(plug.Path, sr, pluginID, siteID)
	if buildErr != nil {
		return apperror.FailWrap[PushSyncResult](buildErr, apperror.ErrFSRead, "failed to build sync files")
	}

	if len(syncFiles) == 0 {
		result.IsSuccess = true
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "No pushable changes found"})

		return apperror.Ok(result)
	}

	result.TotalChanges = len(syncFiles)

	// 6. Push to remote
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Pushing.Value(), Progress: 60,
		Message: fmt.Sprintf("Pushing %d files to remote...", len(syncFiles))})

	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	syncPushResult, err := wpClient.SyncPluginFilesViaUploader(mapping.RemoteSlug, syncFiles)
	if err != nil {
		result.ErrorMessage = err.Error()
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Error.Value(), Progress: 100, Message: "Sync push failed: " + err.Error()})

		return apperror.Ok(result)
	}

	result.FilesUpdated = syncPushResult.FilesUpdated
	result.FilesDeleted = syncPushResult.FilesDeleted
	result.FilesIgnored = syncPushResult.FilesIgnored
	result.IsSuccess = syncPushResult.IsSuccess

	if result.IsSuccess {
		s.updateMappingSyncStatus(ctx, pluginID, siteID, true)
	}

	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100,
		Message: fmt.Sprintf("Sync complete: %d updated, %d deleted, %d ignored",
			result.FilesUpdated, result.FilesDeleted, result.FilesIgnored)})

	s.log.Info("Sync push completed",
		"plugin", plug.Name,
		"pluginId", pluginID,
		"site", mapping.SiteName,
		"siteId", siteID,
		"updated", result.FilesUpdated,
		"deleted", result.FilesDeleted,
		"ignored", result.FilesIgnored,
	)

	return apperror.Ok(result)
}

// buildSyncFiles constructs SyncFile array from changes.
func (s *serviceImpl) buildSyncFiles(pluginPath string, sr SyncResult, pluginID, siteID int64) ([]wordpress.SyncFile, error) {
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Packaging.Value(), Progress: 40, Message: "Packaging file changes..."})

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {

		return nil, err
	}

	var syncFiles []wordpress.SyncFile
	for _, change := range sr.Changes {
		switch change.ChangeType {
		case changetype.Added.Value(), changetype.Modified.Value():
			if change.Direction == syncdirection.RemoteNewer.Value() {
				continue
			}
			localPath := filepath.Join(absPluginPath, filepath.FromSlash(change.FilePath))
			content, readErr := os.ReadFile(localPath)
			if readErr != nil {
				s.log.Warn("Failed to read file for sync, skipping",
					"path", change.FilePath, "error", readErr)
				continue
			}
			syncFiles = append(syncFiles, wordpress.SyncFile{
				Path:    change.FilePath,
				Content: base64.StdEncoding.EncodeToString(content),
				Action:  "replace",
			})

		case changetype.Deleted.Value():
			syncFiles = append(syncFiles, wordpress.SyncFile{
				Path:   change.FilePath,
				Action: "delete",
			})
		}
	}

	return syncFiles, nil
}
