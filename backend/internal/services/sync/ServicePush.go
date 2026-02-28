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
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// pushDeps bundles resolved dependencies for a push operation.
type pushDeps struct {
	Plugin  models.Plugin
	Mapping models.PluginMapping
	SiteUrl string
	SiteUser string
	Password string
}

// PushSync performs a full comparison and pushes all changes to the remote site.
func (s *serviceImpl) PushSync(ctx context.Context, pluginID, siteID int64) apperror.Result[PushSyncResult] {
	sr, earlyResult := s.runCheckAndValidate(ctx, pluginID, siteID)
	if earlyResult != nil {
		return *earlyResult
	}

	deps := s.resolvePushDeps(ctx, pluginID, siteID)
	if deps.HasError() {
		return apperror.Fail[PushSyncResult](deps.AppError())
	}

	return s.executePush(ctx, deps.Value(), sr, pluginID, siteID)
}

// runCheckAndValidate runs sync comparison and returns early results for in-sync or error states.
func (s *serviceImpl) runCheckAndValidate(
	ctx context.Context,
	pluginID int64,
	siteID int64,
) (SyncResult, *apperror.Result[PushSyncResult]) {
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Checking.Value(), Progress: 0, Message: "Running sync comparison..."})

	syncResult := s.CheckSync(ctx, pluginID, siteID)
	if syncResult.HasError() {
		r := apperror.FailWrap[PushSyncResult](syncResult.AppError(), apperror.ErrInternal, "sync comparison failed")
		return SyncResult{}, &r
	}

	sr := syncResult.Value()
	if sr.ErrorMessage != "" {
		r := apperror.Ok(PushSyncResult{PluginID: pluginID, SiteID: siteID, ErrorMessage: sr.ErrorMessage})
		return SyncResult{}, &r
	}

	if sr.IsInSync {
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "Already in sync, nothing to push"})
		r := apperror.Ok(PushSyncResult{PluginID: pluginID, SiteID: siteID, IsSuccess: true})
		return SyncResult{}, &r
	}

	return sr, nil
}

// resolvePushDeps fetches plugin, mapping, and credentials needed for push.
func (s *serviceImpl) resolvePushDeps(ctx context.Context, pluginID, siteID int64) apperror.Result[pushDeps] {
	plugResult := s.pluginService.GetById(ctx, pluginID)
	if plugResult.HasError() {
		return apperror.FailWrap[pushDeps](plugResult.AppError(), apperror.ErrDatabaseQuery, "failed to get plugin")
	}

	mappingResult := s.getMapping(ctx, pluginID, siteID)
	if mappingResult.HasError() {
		return apperror.Fail[pushDeps](mappingResult.AppError())
	}

	siteInfoResult := s.getSiteInfo(ctx, siteID)
	if siteInfoResult.HasError() {
		return apperror.Fail[pushDeps](siteInfoResult.AppError())
	}

	passwordResult := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if passwordResult.HasError() {
		return apperror.Fail[pushDeps](passwordResult.AppError())
	}

	siteInfo := siteInfoResult.Value()
	return apperror.Ok(pushDeps{
		Plugin:   plugResult.Value(),
		Mapping:  mappingResult.Value(),
		SiteUrl:  siteInfo.Url,
		SiteUser: siteInfo.Username,
		Password: passwordResult.Value(),
	})
}

// executePush builds sync files, pushes to remote, and returns the result.
func (s *serviceImpl) executePush(
	ctx context.Context,
	deps pushDeps,
	sr SyncResult,
	pluginID int64,
	siteID int64,
) apperror.Result[PushSyncResult] {
	syncFilesResult := s.buildSyncFiles(buildSyncFilesInput{PluginPath: deps.Plugin.Path, SyncResult: sr, PluginID: pluginID, SiteID: siteID})
	if syncFilesResult.HasError() {
		return apperror.Fail[PushSyncResult](syncFilesResult.AppError())
	}

	syncFiles := syncFilesResult.Items()
	if len(syncFiles) == 0 {
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100, Message: "No pushable changes found"})
		return apperror.Ok(PushSyncResult{PluginID: pluginID, SiteID: siteID, IsSuccess: true})
	}

	return s.pushFilesToRemote(ctx, deps, syncFiles, pluginID, siteID)
}

// pushFilesToRemote sends files to the remote site and returns the result.
func (s *serviceImpl) pushFilesToRemote(
	ctx context.Context,
	deps pushDeps,
	syncFiles []wordpress.SyncFile,
	pluginID int64,
	siteID int64,
) apperror.Result[PushSyncResult] {
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Pushing.Value(), Progress: 60,
		Message: fmt.Sprintf("Pushing %d files to remote...", len(syncFiles))})

	wpClient := s.wpClientFactory(deps.SiteUrl, deps.SiteUser, deps.Password)
	pushResult, err := wpClient.SyncPluginFilesViaUploader(deps.Mapping.RemoteSlug, syncFiles)
	if err != nil {
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Error.Value(), Progress: 100, Message: "Sync push failed: " + err.Error()})
		return apperror.Ok(PushSyncResult{PluginID: pluginID, SiteID: siteID, TotalChanges: len(syncFiles), ErrorMessage: err.Error()})
	}

	result := PushSyncResult{
		PluginID:     pluginID,
		SiteID:       siteID,
		TotalChanges: len(syncFiles),
		FilesUpdated: pushResult.FilesUpdated,
		FilesDeleted: pushResult.FilesDeleted,
		FilesIgnored: pushResult.FilesIgnored,
		IsSuccess:    pushResult.IsSuccess,
	}

	if result.IsSuccess {
		s.updateMappingSyncStatus(ctx, pluginID, siteID, true)
	}

	s.broadcastPushComplete(deps.Plugin.Name, result, pluginID, siteID)
	return apperror.Ok(result)
}

// broadcastPushComplete broadcasts and logs the push completion.
func (s *serviceImpl) broadcastPushComplete(
	pluginName string,
	result PushSyncResult,
	pluginID int64,
	siteID int64,
) {
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Complete.Value(), Progress: 100,
		Message: fmt.Sprintf("Sync complete: %d updated, %d deleted, %d ignored",
			result.FilesUpdated, result.FilesDeleted, result.FilesIgnored)})

	s.log.Info("Sync push completed",
		"pluginId", pluginID,
		"siteId", siteID,
		"updated", result.FilesUpdated,
		"deleted", result.FilesDeleted,
		"ignored", result.FilesIgnored,
	)
}

// buildSyncFilesInput bundles parameters for buildSyncFiles.
type buildSyncFilesInput struct {
	PluginPath string
	SyncResult SyncResult
	PluginID   int64
	SiteID     int64
}

// buildSyncFiles constructs SyncFile array from changes.
func (s *serviceImpl) buildSyncFiles(input buildSyncFilesInput) apperror.ResultSlice[wordpress.SyncFile] {
	s.broadcastProgress(SyncProgressInput{PluginID: input.PluginID, SiteID: input.SiteID, Step: syncstep.Packaging.Value(), Progress: 40, Message: "Packaging file changes..."})

	absPluginPath, err := pathutil.ToAbsolute(input.PluginPath)
	if err != nil {
		return apperror.FailSliceWrap[wordpress.SyncFile](err, apperror.ErrFSRead, "failed to resolve plugin path")
	}

	var syncFiles []wordpress.SyncFile
	for _, change := range input.SyncResult.Changes {
		file := s.buildSyncFileEntry(change, absPluginPath)
		if file != nil {
			syncFiles = append(syncFiles, *file)
		}
	}

	return apperror.OkSlice(syncFiles)
}

// buildSyncFileEntry creates a SyncFile from a single FileChange, or nil if skipped.
func (s *serviceImpl) buildSyncFileEntry(change models.FileChange, absPluginPath string) *wordpress.SyncFile {
	switch change.ChangeType {
	case changetype.Added.Value(), changetype.Modified.Value():
		if change.Direction == syncdirection.RemoteNewer.Value() {
			return nil
		}
		localPath := filepath.Join(absPluginPath, filepath.FromSlash(change.FilePath))
		content, readErr := os.ReadFile(localPath)
		if readErr != nil {
			s.log.Warn("Failed to read file for sync, skipping", "path", change.FilePath, "error", readErr)
			return nil
		}
		return &wordpress.SyncFile{
			Path:    change.FilePath,
			Content: base64.StdEncoding.EncodeToString(content),
			Action:  "replace",
		}

	case changetype.Deleted.Value():
		return &wordpress.SyncFile{
			Path:   change.FilePath,
			Action: "delete",
		}
	}

	return nil
}
