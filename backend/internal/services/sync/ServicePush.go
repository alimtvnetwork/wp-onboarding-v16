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
	Plugin   models.Plugin
	Mapping  models.PluginMapping
	SiteUrl  string
	SiteUser string
	Password string
}

// PushSync performs a full comparison and pushes all changes to the remote site.
func (s *serviceImpl) PushSync(ctx context.Context, pluginId, siteId int64) apperror.Result[PushSyncResult] {
	sr, earlyResult := s.runCheckAndValidate(ctx, pluginId, siteId)
	if earlyResult != nil {
		return *earlyResult
	}

	deps := s.resolvePushDeps(ctx, pluginId, siteId)
	if deps.HasError() {
		return apperror.Fail[PushSyncResult](deps.AppError())
	}

	return s.executePush(ctx, deps.Value(), sr, pluginId, siteId)
}

// runCheckAndValidate runs sync comparison and returns early results for in-sync or error states.
func (s *serviceImpl) runCheckAndValidate(
	ctx context.Context,
	pluginId int64,
	siteId int64,
) (SyncResult, *apperror.Result[PushSyncResult]) {
	checkProgress := SyncProgressInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Step:     syncstep.Checking.Value(),
		Progress: 0,
		Message:  "Running sync comparison...",
	}
	s.broadcastProgress(checkProgress)

	syncResult := s.CheckSync(ctx, pluginId, siteId)
	if syncResult.HasError() {
		r := apperror.FailWrap[PushSyncResult](syncResult.AppError(), apperror.ErrInternal, "sync comparison failed")

		return SyncResult{}, &r
	}

	sr := syncResult.Value()
	if sr.ErrorMessage != "" {
		errorResult := PushSyncResult{
			PluginId:     pluginId,
			SiteId:       siteId,
			ErrorMessage: sr.ErrorMessage,
		}
		r := apperror.Ok(errorResult)

		return SyncResult{}, &r
	}

	if sr.IsInSync {
		inSyncProgress := SyncProgressInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Step:     syncstep.Complete.Value(),
			Progress: 100,
			Message:  "Already in sync, nothing to push",
		}
		s.broadcastProgress(inSyncProgress)

		inSyncResult := PushSyncResult{
			PluginId:  pluginId,
			SiteId:    siteId,
			IsSuccess: true,
		}
		r := apperror.Ok(inSyncResult)

		return SyncResult{}, &r
	}

	return sr, nil
}

// resolvePushDeps fetches plugin, mapping, and credentials needed for push.
func (s *serviceImpl) resolvePushDeps(ctx context.Context, pluginId, siteId int64) apperror.Result[pushDeps] {
	plugResult := s.pluginService.GetById(ctx, pluginId)
	if plugResult.HasError() {
		return apperror.FailWrap[pushDeps](plugResult.AppError(), apperror.ErrDatabaseQuery, "failed to get plugin")
	}

	mappingResult := s.getMapping(ctx, pluginId, siteId)
	if mappingResult.HasError() {
		return apperror.Fail[pushDeps](mappingResult.AppError())
	}

	siteInfoResult := s.getSiteInfo(ctx, siteId)
	if siteInfoResult.HasError() {
		return apperror.Fail[pushDeps](siteInfoResult.AppError())
	}

	passwordResult := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteId)
	if passwordResult.HasError() {
		return apperror.Fail[pushDeps](passwordResult.AppError())
	}

	siteInfo := siteInfoResult.Value()

	resolved := pushDeps{
		Plugin:   plugResult.Value(),
		Mapping:  mappingResult.Value(),
		SiteUrl:  siteInfo.Url,
		SiteUser: siteInfo.Username,
		Password: passwordResult.Value(),
	}

	return apperror.Ok(resolved)
}

// executePush builds sync files, pushes to remote, and returns the result.
func (s *serviceImpl) executePush(
	ctx context.Context,
	deps pushDeps,
	sr SyncResult,
	pluginId int64,
	siteId int64,
) apperror.Result[PushSyncResult] {
	buildInput := buildSyncFilesInput{
		PluginPath: deps.Plugin.Path,
		SyncResult: sr,
		PluginId:   pluginId,
		SiteId:     siteId,
	}
	syncFilesResult := s.buildSyncFiles(buildInput)
	if syncFilesResult.HasError() {
		return apperror.Fail[PushSyncResult](syncFilesResult.AppError())
	}

	syncFiles := syncFilesResult.Items()
	isEmpty := len(syncFiles) == 0

	if isEmpty {
		noPushProgress := SyncProgressInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Step:     syncstep.Complete.Value(),
			Progress: 100,
			Message:  "No pushable changes found",
		}
		s.broadcastProgress(noPushProgress)

		noPushResult := PushSyncResult{
			PluginId:  pluginId,
			SiteId:    siteId,
			IsSuccess: true,
		}

		return apperror.Ok(noPushResult)
	}

	return s.pushFilesToRemote(ctx, deps, syncFiles, pluginId, siteId)
}

// pushFilesToRemote sends files to the remote site and returns the result.
func (s *serviceImpl) pushFilesToRemote(
	ctx context.Context,
	deps pushDeps,
	syncFiles []wordpress.SyncFile,
	pluginId int64,
	siteId int64,
) apperror.Result[PushSyncResult] {
	pushProgress := SyncProgressInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Step:     syncstep.Pushing.Value(),
		Progress: 60,
		Message:  fmt.Sprintf("Pushing %d files to remote...", len(syncFiles)),
	}
	s.broadcastProgress(pushProgress)

	wpClient := s.wpClientFactory(deps.SiteUrl, deps.SiteUser, deps.Password)
	pushResult := wpClient.SyncPluginFilesViaUploader(deps.Mapping.RemoteSlug, syncFiles)
	if pushResult.HasError() {
		pushErr := pushResult.AppError()
		errorProgress := SyncProgressInput{
			PluginId: pluginId,
			SiteId:   siteId,
			Step:     syncstep.Error.Value(),
			Progress: 100,
			Message:  "Sync push failed: " + pushErr.Error(),
		}
		s.broadcastProgress(errorProgress)

		pushErrorResult := PushSyncResult{
			PluginId:     pluginId,
			SiteId:       siteId,
			TotalChanges: len(syncFiles),
			ErrorMessage: pushErr.Error(),
		}

		return apperror.Ok(pushErrorResult)
	}

	pushData := pushResult.Value()

	result := PushSyncResult{
		PluginId:     pluginId,
		SiteId:       siteId,
		TotalChanges: len(syncFiles),
		FilesUpdated: pushData.FilesUpdated,
		FilesDeleted: pushData.FilesDeleted,
		FilesIgnored: pushData.FilesIgnored,
		IsSuccess:    pushData.Success,
	}

	if result.IsSuccess {
		s.updateMappingSyncStatus(ctx, pluginId, siteId, true)
	}

	s.broadcastPushComplete(deps.Plugin.Name, result, pluginId, siteId)

	return apperror.Ok(result)
}

// broadcastPushComplete broadcasts and logs the push completion.
func (s *serviceImpl) broadcastPushComplete(
	pluginName string,
	result PushSyncResult,
	pluginId int64,
	siteId int64,
) {
	completeProgress := SyncProgressInput{
		PluginId: pluginId,
		SiteId:   siteId,
		Step:     syncstep.Complete.Value(),
		Progress: 100,
		Message: fmt.Sprintf("Sync complete: %d updated, %d deleted, %d ignored",
			result.FilesUpdated, result.FilesDeleted, result.FilesIgnored),
	}
	s.broadcastProgress(completeProgress)

	s.log.Info("Sync push completed",
		"pluginId", pluginId,
		"siteId", siteId,
		"updated", result.FilesUpdated,
		"deleted", result.FilesDeleted,
		"ignored", result.FilesIgnored,
	)
}

