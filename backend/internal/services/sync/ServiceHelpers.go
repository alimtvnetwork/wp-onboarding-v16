// Package sync — helper functions for scanning, hashing, and comparing files.
package sync

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"strings"

	syncstep "wp-plugin-publish/internal/enums/syncsteptype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// scanLocalFiles scans the plugin directory and returns file entries with hashes and timestamps
func (s *serviceImpl) scanLocalFiles(pluginPath string, excludePatterns []string) (map[string]FileEntry, error) {
	files := make(map[string]FileEntry)

	absPluginPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin path").
			WithPath(pluginPath)
	}

	err = filepath.Walk(absPluginPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // Skip files we can't access
		}

		if info.IsDir() {
			for _, pattern := range excludePatterns {
				if strings.Contains(path, pattern) {
					return filepath.SkipDir
				}
			}
			return nil
		}

		return s.processLocalFile(files, absPluginPath, path, info, excludePatterns)
	})

	return files, err
}

// processLocalFile handles a single file during local scan.
func (s *serviceImpl) processLocalFile(files map[string]FileEntry, basePath, path string, info os.FileInfo, excludePatterns []string) error {
	relPath, err := filepath.Rel(basePath, path)
	if err != nil {
		return nil
	}

	if isFileExcluded(path, excludePatterns) {
		return nil
	}

	if strings.HasPrefix(filepath.Base(path), ".") {
		return nil
	}

	hash, err := s.calculateFileHash(path)
	if err != nil {
		return nil
	}

	key := filepath.ToSlash(relPath)
	files[key] = FileEntry{
		Path:       key,
		Hash:       hash,
		ModifiedAt: info.ModTime().UTC(),
		Size:       info.Size(),
	}
	return nil
}

// isFileExcluded checks if a file matches any exclude pattern.
func isFileExcluded(path string, excludePatterns []string) bool {
	for _, pattern := range excludePatterns {
		matched, _ := filepath.Match(pattern, filepath.Base(path))
		if matched {
			return true
		}
	}
	return false
}

// calculateFileHash calculates MD5 hash of a file
func (s *serviceImpl) calculateFileHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to open file for hashing").
			WithFilePath(path)
	}
	defer file.Close()

	hash := md5.New()
	_, err = io.Copy(hash, file)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrFSRead, "failed to read file for hashing").
			WithFilePath(path)
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}

// compareFiles compares local and remote file entries with timestamp-based conflict resolution.
func (s *serviceImpl) compareFiles(local, remote map[string]FileEntry) []models.FileChange {
	changes := s.detectLocalChanges(local, remote)
	deleted := s.detectDeletedFiles(local, remote)
	return append(changes, deleted...)
}

// detectLocalChanges finds files that are added or modified locally vs remote.
func (s *serviceImpl) detectLocalChanges(local, remote map[string]FileEntry) []models.FileChange {
	var changes []models.FileChange

	for path, localEntry := range local {
		remoteEntry, isFound := remote[path]
		if isFound {
			change := buildModifiedChange(path, localEntry, remoteEntry)
			if change != nil {
				changes = append(changes, *change)
			}
		} else {
			changes = append(changes, buildAddedChange(path, localEntry))
		}
	}

	return changes
}

// detectDeletedFiles finds files present remotely but missing locally.
func (s *serviceImpl) detectDeletedFiles(local, remote map[string]FileEntry) []models.FileChange {
	var changes []models.FileChange

	for path, remoteEntry := range remote {
		_, isFound := local[path]
		isLocalMissing := !isFound

		if isLocalMissing {
			changes = append(changes, buildDeletedChange(path, remoteEntry))
		}
	}

	return changes
}

// fetchRemoteManifest retrieves the remote file manifest for comparison.
func (s *serviceImpl) fetchRemoteManifest(ctx context.Context, pluginID, siteID int64) (map[string]FileEntry, string) {
	mappingResult := s.getMapping(ctx, pluginID, siteID)
	if mappingResult.HasError() {
		return nil, "No site mapping found: " + mappingResult.AppError().Error()
	}
	mapping := mappingResult.Value()

	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 40, Message: "Retrieving site credentials..."})
	siteInfoResult := s.getSiteInfo(ctx, siteID)
	if siteInfoResult.HasError() {
		return nil, "Failed to get site info: " + siteInfoResult.AppError().Error()
	}

	passwordResult := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if passwordResult.HasError() {
		return nil, "Failed to decrypt credentials: " + passwordResult.AppError().Error()
	}
	password := passwordResult.Value()

	return s.fetchAndParseManifest(ctx, siteInfoResult.Value(), mapping, pluginID, siteID, password)
}

// fetchAndParseManifest calls the remote API and converts the manifest to FileEntry map.
func (s *serviceImpl) fetchAndParseManifest(ctx context.Context, info models.Site, mapping models.PluginMapping, pluginID, siteID int64, password string) (map[string]FileEntry, string) {
	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 50, Message: "Fetching remote file manifest..."})

	wpClient := s.wpClientFactory(info.Url, info.Username, password)
	manifestResult := wpClient.GetPluginSyncManifest(ctx, mapping.RemoteSlug)

	remoteFiles := make(map[string]FileEntry)
	if manifestResult.HasError() {
		s.log.Warn("Failed to fetch remote sync manifest, comparing local only",
			"pluginId", pluginID, "siteId", siteID, "slug", mapping.RemoteSlug, "error", manifestResult.AppError())
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 60, Message: "Remote manifest unavailable, comparing local only..."})
	} else {
		for _, rf := range manifestResult.Value() {
			remoteFiles[rf.Path] = FileEntry{Path: rf.Path, Hash: rf.Hash, ModifiedAt: rf.ModifiedAt, Size: rf.Size}
		}
	}

	return remoteFiles, ""
}

// SyncProgressInput bundles parameters for broadcastProgress.
type SyncProgressInput struct {
	PluginID int64
	SiteID   int64
	Step     string
	Progress int
	Message  string
}

// broadcastProgress sends sync progress via WebSocket with detailed step info
func (s *serviceImpl) broadcastProgress(input SyncProgressInput) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventSyncProgress, ws.SyncStepProgressData{
		PluginID: input.PluginID,
		SiteID:   input.SiteID,
		Step:     input.Step,
		Progress: input.Progress,
		Total:    100,
		Message:  input.Message,
	})

	// Also broadcast detailed log entry for frontend live log display
	s.wsHub.BroadcastSyncLog(ws.OperationLogInput{
		PluginID: input.PluginID, SiteID: input.SiteID,
		Entry: ws.OperationLogEntry{Level: "info", Step: input.Step, Message: input.Message},
	})

	s.log.Debug("Sync progress", "pluginId", input.PluginID, "siteId", input.SiteID, "step", input.Step, "progress", input.Progress, "message", input.Message)
}

// countByType counts changes by type
func countByType(changes []models.FileChange, changeType string) int {
	count := 0
	for _, c := range changes {
		if c.ChangeType == changeType {
			count++
		}
	}
	return count
}
