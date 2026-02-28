// Package watcher — scan and compare logic.
package watcher

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/internal/enums/scantriggertype"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// performScan executes the actual directory scan
func (s *Service) performScan(ctx context.Context, pluginID int64, triggerType string) apperror.Result[ScanResult] {
	startTime := time.Now()

	s.log.Debug("Scanning plugin", "pluginId", pluginID, "trigger", triggerType)

	// Get or create cache
	s.mu.Lock()
	cache, isFound := s.cache[pluginID]
	isCacheMissing := !isFound

	if isCacheMissing {
		s.mu.Unlock()

		err := s.InitializeCache(ctx, pluginID)
		if err != nil {

			return apperror.FailWrap[ScanResult](err, apperror.ErrInternal, "failed to initialize watcher cache")
		}

		s.mu.Lock()
		cache = s.cache[pluginID]
	}
	s.mu.Unlock()

	// Perform scan and detect changes
	changes := s.scanAndCompare(cache)

	result := ScanResult{
		PluginID:     pluginID,
		Path:         cache.path,
		ScanTime:     startTime,
		DurationMs:   time.Since(startTime).Milliseconds(),
		FilesScanned: len(cache.lastScan),
		Changes:      changes,
		TriggerType:  triggerType,
	}

	// Broadcast changes if any
	if len(changes) > 0 {
		s.broadcastChanges(pluginID, changes, triggerType)

		// Record changes in database
		for _, c := range changes {
			s.db.ExecContext(ctx, `
				INSERT INTO FileChanges (PluginId, FilePath, ChangeType, LocalHash, DetectedAt)
				VALUES (?, ?, ?, ?, datetime('now'))
			`, pluginID, c.Path, c.ChangeType, c.Hash)
		}

		// Trigger auto-publish if enabled
		go s.triggerAutoPublish(ctx, pluginID, changes)
	}

	s.log.Info("Scan complete", "pluginId", pluginID, "changes", len(changes), "duration", result.DurationMs)
	return apperror.Ok(result)
}

// populateCache performs initial scan without change detection
func (s *Service) populateCache(cache *pluginScanCache) {
	filepath.Walk(cache.path, func(path string, info os.FileInfo, err error) error {
		if err != nil || info.IsDir() {
			if info != nil && info.IsDir() && s.isExcluded(filepath.Base(path), cache.excludes) {
				return filepath.SkipDir
			}
			return nil
		}

		if s.isExcluded(filepath.Base(path), cache.excludes) {
			return nil
		}

		relPath, _ := filepath.Rel(cache.path, path)
		absPath, _ := pathutil.ToAbsolute(path)
		hash, _ := s.calculateHash(absPath)

		cache.lastScan[relPath] = fileInfo{
			ModTime: info.ModTime().Unix(),
			Size:    info.Size(),
			Hash:    hash,
		}
		return nil
	})
}

// scanAndCompare scans directory and returns detected changes
func (s *Service) scanAndCompare(cache *pluginScanCache) []FileChange {
	var changes []FileChange
	currentFiles := make(map[string]fileInfo)

	filepath.Walk(cache.path, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}

		if info.IsDir() {
			base := filepath.Base(path)
			if s.isExcluded(base, cache.excludes) {
				return filepath.SkipDir
			}
			return nil
		}

		base := filepath.Base(path)
		if s.isExcluded(base, cache.excludes) {
			return nil
		}

		relPath, _ := filepath.Rel(cache.path, path)
		absPath, _ := pathutil.ToAbsolute(path)
		hash, _ := s.calculateHash(absPath)

		fi := fileInfo{
			ModTime: info.ModTime().Unix(),
			Size:    info.Size(),
			Hash:    hash,
		}
		currentFiles[relPath] = fi

		lastInfo, isFound := cache.lastScan[relPath]
		if isFound {
			if lastInfo.Hash != fi.Hash {
				changes = append(changes, FileChange{
					Path:       relPath,
					ChangeType: "modified",
					Hash:       fi.Hash,
					Size:       fi.Size,
					ModTime:    info.ModTime(),
				})
			}
		} else {
			changes = append(changes, FileChange{
				Path:       relPath,
				ChangeType: "created",
				Hash:       fi.Hash,
				Size:       fi.Size,
				ModTime:    info.ModTime(),
			})
		}

		return nil
	})

	// Check for deleted files
	for path := range cache.lastScan {
		_, isFound := currentFiles[path]
		isDeleted := !isFound

		if isDeleted {
			changes = append(changes, FileChange{
				Path:       path,
				ChangeType: "deleted",
			})
		}
	}

	// Update cache
	cache.lastScan = currentFiles

	return changes
}

// isExcluded checks if a file/directory should be excluded
func (s *Service) isExcluded(name string, excludes []string) bool {
	if strings.HasPrefix(name, ".") {
		return true
	}

	defaultExcludes := []string{"node_modules", "vendor", ".git", ".svn", ".idea", ".vscode"}
	for _, ex := range defaultExcludes {
		if name == ex {
			return true
		}
	}

	for _, pattern := range excludes {
		matched, _ := filepath.Match(pattern, name)
		if matched {
			return true
		}
	}

	return false
}

// calculateHash computes MD5 hash of a file
func (s *Service) calculateHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	hash := md5.New()
	_, err = io.Copy(hash, file)
	if err != nil {
		return "", err
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}
