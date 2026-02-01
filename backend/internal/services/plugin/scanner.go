package plugin

import (
	"bufio"
	"context"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"wp-plugin-publish/pkg/apperror"
)

// ScanDirectory scans a plugin directory and returns file information
func (s *Service) ScanDirectory(ctx context.Context, path string) (*ScanResult, error) {
	s.log.Debug("Scanning directory", "path", path)

	scan := &ScanResult{
		Path:    path,
		IsValid: false,
		Files:   []FileInfo{},
	}

	// Check if directory exists
	info, err := os.Stat(path)
	if os.IsNotExist(err) {
		scan.Error = "directory does not exist"
		return scan, nil
	}
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDirRead, "failed to stat directory")
	}
	if !info.IsDir() {
		scan.Error = "path is not a directory"
		return scan, nil
	}

	// Find main plugin file
	mainFile, pluginName, version, err := s.findMainPluginFile(path)
	if err != nil {
		scan.Error = err.Error()
		return scan, nil
	}

	scan.IsValid = true
	scan.MainFile = mainFile
	scan.PluginName = pluginName
	scan.Version = version

	// Walk directory and collect files
	err = filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // Skip inaccessible files
		}

		// Get relative path
		relPath, _ := filepath.Rel(path, filePath)
		if relPath == "." {
			return nil
		}

		// Skip hidden files and common ignored directories
		base := filepath.Base(filePath)
		if strings.HasPrefix(base, ".") || base == "node_modules" || base == "vendor" {
			if info.IsDir() {
				return filepath.SkipDir
			}
			return nil
		}

		fileInfo := FileInfo{
			Path:        relPath,
			Size:        info.Size(),
			ModifiedAt:  info.ModTime(),
			IsDirectory: info.IsDir(),
		}

		if !info.IsDir() {
			fileInfo.Hash, _ = s.calculateFileHash(filePath)
			scan.TotalSize += info.Size()
			scan.FileCount++
		}

		scan.Files = append(scan.Files, fileInfo)
		return nil
	})

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDirRead, "failed to scan directory")
	}

	s.log.Info("Directory scanned",
		"path", path,
		"pluginName", pluginName,
		"files", scan.FileCount,
		"size", scan.TotalSize,
	)

	return scan, nil
}

// ValidatePath checks if a path is a valid WordPress plugin directory
func (s *Service) ValidatePath(ctx context.Context, path string) error {
	scan, err := s.ScanDirectory(ctx, path)
	if err != nil {
		return err
	}

	if !scan.IsValid {
		return apperror.New(apperror.ErrPathInvalid, scan.Error).
			WithContext("path", path)
	}

	return nil
}

// findMainPluginFile locates the main plugin PHP file with the plugin header
func (s *Service) findMainPluginFile(path string) (string, string, string, error) {
	entries, err := os.ReadDir(path)
	if err != nil {
		return "", "", "", err
	}

	pluginNameRegex := regexp.MustCompile(`Plugin Name:\s*(.+)`)
	versionRegex := regexp.MustCompile(`Version:\s*(.+)`)

	for _, entry := range entries {
		if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".php") {
			continue
		}

		filePath := filepath.Join(path, entry.Name())
		file, err := os.Open(filePath)
		if err != nil {
			continue
		}

		scanner := bufio.NewScanner(file)
		lineCount := 0
		var pluginName, version string

		for scanner.Scan() && lineCount < 30 {
			line := scanner.Text()
			lineCount++

			if matches := pluginNameRegex.FindStringSubmatch(line); len(matches) > 1 {
				pluginName = strings.TrimSpace(matches[1])
			}
			if matches := versionRegex.FindStringSubmatch(line); len(matches) > 1 {
				version = strings.TrimSpace(matches[1])
			}
		}
		file.Close()

		if pluginName != "" {
			return entry.Name(), pluginName, version, nil
		}
	}

	return "", "", "", apperror.New(apperror.ErrPathInvalid,
		"no valid WordPress plugin file found (missing Plugin Name header)")
}

// calculateFileHash computes MD5 hash of a file
func (s *Service) calculateFileHash(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()

	hash := md5.New()
	if _, err := io.Copy(hash, file); err != nil {
		return "", err
	}

	return hex.EncodeToString(hash.Sum(nil)), nil
}
