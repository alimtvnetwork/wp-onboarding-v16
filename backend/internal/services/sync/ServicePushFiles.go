// Package sync — file packaging for push sync operations.
package sync

import (
	"encoding/base64"
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

// buildSyncFilesInput bundles parameters for buildSyncFiles.
type buildSyncFilesInput struct {
	PluginPath string
	SyncResult SyncResult
	PluginId   int64
	SiteId     int64
}

// buildSyncFiles constructs SyncFile array from changes.
func (s *serviceImpl) buildSyncFiles(input buildSyncFilesInput) apperror.ResultSlice[wordpress.SyncFile] {
	packageProgress := SyncProgressInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Step:     syncstep.Packaging.Value(),
		Progress: 40,
		Message:  "Packaging file changes...",
	}
	s.broadcastProgress(packageProgress)

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
		isRemoteNewer := change.Direction == syncdirection.RemoteNewer.Value()

		if isRemoteNewer {
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
