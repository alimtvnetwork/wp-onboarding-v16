// Package sync — helper functions for scanning, hashing, and comparing files.
package sync

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	changetype "wp-plugin-publish/internal/enums/changetype"
	syncdirection "wp-plugin-publish/internal/enums/syncdirectiontype"
	syncstep "wp-plugin-publish/internal/enums/syncsteptype"
	"wp-plugin-publish/internal/models"
	"wp-plugin-publish/internal/wordpress"
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

		relPath, err := filepath.Rel(absPluginPath, path)
		if err != nil {
			return nil
		}

		for _, pattern := range excludePatterns {
			matched, _ := filepath.Match(pattern, filepath.Base(path))
			if matched {
				return nil
			}
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
	})

	return files, err
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

// compareFiles compares local and remote file entries with timestamp-based conflict resolution
func (s *serviceImpl) compareFiles(local, remote map[string]FileEntry) []models.FileChange {
	var changes []models.FileChange

	for path, localEntry := range local {
		localMod := localEntry.ModifiedAt
		remoteEntry, isFound := remote[path]
		if isFound {
			if localEntry.Hash != remoteEntry.Hash {
				remoteMod := remoteEntry.ModifiedAt
				direction := syncdirection.LocalNewer.Value()
				if remoteMod.After(localMod) {
					direction = syncdirection.RemoteNewer.Value()
				}
				changes = append(changes, models.FileChange{
					FilePath:         path,
					ChangeType:       changetype.Modified.Value(),
					LocalHash:        localEntry.Hash,
					RemoteHash:       remoteEntry.Hash,
					LocalModifiedAt:  &localMod,
					RemoteModifiedAt: &remoteMod,
					LocalSize:        localEntry.Size,
					RemoteSize:       remoteEntry.Size,
					Direction:        direction,
				})
			}
		} else {
			changes = append(changes, models.FileChange{
				FilePath:        path,
				ChangeType:      changetype.Added.Value(),
				LocalHash:       localEntry.Hash,
				LocalModifiedAt: &localMod,
				LocalSize:       localEntry.Size,
				Direction:       syncdirection.LocalOnly.Value(),
			})
		}
	}

	for path, remoteEntry := range remote {
		_, isFound := local[path]
		isLocalMissing := !isFound

		if isLocalMissing {
			remoteMod := remoteEntry.ModifiedAt
			changes = append(changes, models.FileChange{
				FilePath:         path,
				ChangeType:       changetype.Deleted.Value(),
				RemoteHash:       remoteEntry.Hash,
				RemoteModifiedAt: &remoteMod,
				RemoteSize:       remoteEntry.Size,
				Direction:        syncdirection.RemoteOnly.Value(),
			})
		}
	}

	return changes
}

// fetchRemoteManifest retrieves the remote file manifest for comparison.
func (s *serviceImpl) fetchRemoteManifest(ctx context.Context, pluginID, siteID int64) (map[string]FileEntry, string) {
	mapping, err := s.getMapping(ctx, pluginID, siteID)
	if err != nil {
		return nil, "No site mapping found: " + err.Error()
	}

	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 40, Message: "Retrieving site credentials..."})
	siteInfo, err := s.getSiteInfo(ctx, siteID)
	if err != nil {
		return nil, "Failed to get site info: " + err.Error()
	}

	password, err := s.sitePasswordDecryptor.GetDecryptedPassword(ctx, siteID)
	if err != nil {
		return nil, "Failed to decrypt credentials: " + err.Error()
	}

	s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 50, Message: "Fetching remote file manifest..."})
	wpClient := s.wpClientFactory(siteInfo.URL, siteInfo.Username, password)
	remoteManifest, err := wpClient.GetPluginSyncManifest(ctx, mapping.RemoteSlug)

	remoteFiles := make(map[string]FileEntry)
	if err != nil {
		s.log.Warn("Failed to fetch remote sync manifest, comparing local only",
			"pluginId", pluginID, "siteId", siteID, "slug", mapping.RemoteSlug, "error", err)
		s.broadcastProgress(SyncProgressInput{PluginID: pluginID, SiteID: siteID, Step: syncstep.Comparing.Value(), Progress: 60, Message: "Remote manifest unavailable, comparing local only..."})
	} else {
		for _, rf := range remoteManifest {
			remoteFiles[rf.Path] = FileEntry{
				Path:       rf.Path,
				Hash:       rf.Hash,
				ModifiedAt: rf.ModifiedAt,
				Size:       rf.Size,
			}
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
