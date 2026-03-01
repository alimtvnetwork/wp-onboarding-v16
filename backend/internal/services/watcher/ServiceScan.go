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

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// performScan executes the actual directory scan.
func (s *Service) performScan(ctx context.Context, pluginId int64, triggerType string) apperror.Result[ScanResult] {
	startTime := time.Now()
	s.log.Debug("Scanning plugin", "pluginId", pluginId, "trigger", triggerType)

	cache, cacheErr := s.ensureScanCache(ctx, pluginId)
	if cacheErr != nil {
		return apperror.Fail[ScanResult](cacheErr)
	}

	changes := s.scanAndCompare(cache)
	result := s.buildScanResult(pluginId, cache, changes, triggerType, startTime)

	s.handleScanChanges(ctx, pluginId, changes, triggerType)
	s.log.Info("Scan complete", "pluginId", pluginId, "changes", len(changes), "duration", result.DurationMs)
	return apperror.Ok(result)
}

// ensureScanCache returns the existing cache or initializes a new one.
func (s *Service) ensureScanCache(ctx context.Context, pluginId int64) (*pluginScanCache, *apperror.AppError) {
	s.mu.Lock()
	cache, isFound := s.cache[pluginId]
	s.mu.Unlock()

	if isFound {
		return cache, nil
	}

	appErr := s.InitializeCache(ctx, pluginId)
	if appErr != nil {
		return nil, appErr
	}

	s.mu.Lock()
	cache = s.cache[pluginId]
	s.mu.Unlock()
	return cache, nil
}

// buildScanResult constructs a ScanResult from scan output.
func (s *Service) buildScanResult(pluginId int64, cache *pluginScanCache, changes []FileChange, triggerType string, startTime time.Time) ScanResult {
	return ScanResult{
		PluginId:     pluginId,
		Path:         cache.path,
		ScanTime:     startTime,
		DurationMs:   time.Since(startTime).Milliseconds(),
		FilesScanned: len(cache.lastScan),
		Changes:      changes,
		TriggerType:  triggerType,
	}
}

// handleScanChanges broadcasts, records, and triggers auto-publish for detected changes.
func (s *Service) handleScanChanges(ctx context.Context, pluginId int64, changes []FileChange, triggerType string) {
	if len(changes) == 0 {
		return
	}

	s.broadcastChanges(pluginId, changes, triggerType)
	s.recordChangesToDB(ctx, pluginId, changes)
	go s.triggerAutoPublish(ctx, pluginId, changes)
}

// recordChangesToDB persists each file change to the database.
func (s *Service) recordChangesToDB(ctx context.Context, pluginId int64, changes []FileChange) {
	for _, c := range changes {
		s.db.ExecContext(ctx, `
			INSERT INTO FileChanges (PluginId, FilePath, ChangeType, LocalHash, DetectedAt)
			VALUES (?, ?, ?, ?, datetime('now'))
		`, pluginId, c.Path, c.ChangeType, c.Hash)
	}
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

// scanAndCompare scans directory and returns detected changes.
func (s *Service) scanAndCompare(cache *pluginScanCache) []FileChange {
	currentFiles := make(map[string]fileInfo)
	changes := s.walkAndDetectChanges(cache, currentFiles)
	deleted := findDeletedFiles(cache.lastScan, currentFiles)
	cache.lastScan = currentFiles

	return append(changes, deleted...)
}

// walkAndDetectChanges walks the directory tree and detects created/modified files.
func (s *Service) walkAndDetectChanges(cache *pluginScanCache, currentFiles map[string]fileInfo) []FileChange {
	var changes []FileChange

	filepath.Walk(cache.path, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		if info.IsDir() {
			if s.isExcluded(filepath.Base(path), cache.excludes) {
				return filepath.SkipDir
			}
			return nil
		}
		if s.isExcluded(filepath.Base(path), cache.excludes) {
			return nil
		}

		change := s.processScannedFile(cache, currentFiles, path, info)
		if change != nil {
			changes = append(changes, *change)
		}
		return nil
	})

	return changes
}

// processScannedFile hashes a file and detects if it was created or modified.
func (s *Service) processScannedFile(cache *pluginScanCache, currentFiles map[string]fileInfo, path string, info os.FileInfo) *FileChange {
	relPath, _ := filepath.Rel(cache.path, path)
	absPath, _ := pathutil.ToAbsolute(path)
	hash, _ := s.calculateHash(absPath)

	fi := fileInfo{ModTime: info.ModTime().Unix(), Size: info.Size(), Hash: hash}
	currentFiles[relPath] = fi

	lastInfo, isFound := cache.lastScan[relPath]
	if isFound && lastInfo.Hash != fi.Hash {
		return &FileChange{Path: relPath, ChangeType: "modified", Hash: fi.Hash, Size: fi.Size, ModTime: info.ModTime()}
	}
	if !isFound {
		return &FileChange{Path: relPath, ChangeType: "created", Hash: fi.Hash, Size: fi.Size, ModTime: info.ModTime()}
	}

	return nil
}

// findDeletedFiles returns changes for files in lastScan but missing from currentFiles.
func findDeletedFiles(lastScan, currentFiles map[string]fileInfo) []FileChange {
	var changes []FileChange
	for path := range lastScan {
		_, isFound := currentFiles[path]
		if !isFound {
			changes = append(changes, FileChange{Path: path, ChangeType: "deleted"})
		}
	}
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
