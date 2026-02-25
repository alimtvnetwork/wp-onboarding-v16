package plugin

import (
	"bufio"
	"context"
	"crypto/md5"
	"encoding/hex"
	"encoding/json"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

const pluginDetectedFile = "wp-plugin-detected.json"

// ScanDirectory scans a plugin directory and returns file information
func (s *Service) ScanDirectory(ctx context.Context, path string) apperror.Result[ScanResult] {
	s.log.Debug("Scanning directory", "path", path)

	scan := ScanResult{
		Path:    path,
		IsValid: false,
		Files:   []FileInfo{},
	}

	// Check if .plugin-detected.json exists first
	if result, ok := s.tryLoadDetected(path, &scan); ok {
		return result
	}

	// Check if directory exists
	info, err := os.Stat(path)
	if os.IsNotExist(err) {
		scan.Error = "directory does not exist"
		return apperror.Ok(scan)
	}
	if err != nil {
		return apperror.FailWrap[ScanResult](err, apperror.ErrDirRead, "failed to stat directory")
	}
	if !info.IsDir() {
		scan.Error = "path is not a directory"
		return apperror.Ok(scan)
	}

	// Find main plugin file
	pluginInfo, err := s.findMainPluginFile(path)
	if err != nil {
		scan.Error = err.Error()
		return apperror.Ok(scan)
	}

	applyPluginInfo(&scan, pluginInfo)

	// Walk directory and collect files
	if err := s.walkDirectory(path, &scan); err != nil {
		return apperror.FailWrap[ScanResult](err, apperror.ErrDirRead, "failed to scan directory")
	}

	s.log.Info("Directory scanned", "path", path, "pluginName", scan.PluginName, "files", scan.FileCount)

	return apperror.Ok(scan)
}

// tryLoadDetected attempts to load plugin info from .plugin-detected.json.
func (s *Service) tryLoadDetected(path string, scan *ScanResult) (apperror.Result[ScanResult], bool) {
	detectedPath, err := pathutil.Join(path, pluginDetectedFile)
	if err != nil {
		return apperror.Result[ScanResult]{}, false
	}

	if _, err := os.Stat(detectedPath); err != nil {
		return apperror.Result[ScanResult]{}, false
	}

	detected, err := s.readPluginDetected(detectedPath)
	if err != nil {
		return apperror.Result[ScanResult]{}, false
	}

	scan.IsValid = true
	scan.PluginName = detected.PluginName
	scan.Version = detected.Version
	scan.MainFile = detected.MainFile
	scan.Description = detected.Description
	scan.Author = detected.Author
	scan.AuthorURI = detected.AuthorURI
	scan.PluginURI = detected.PluginURI
	scan.TextDomain = detected.TextDomain
	scan.RequiresPHP = detected.RequiresPHP
	scan.RequiresWP = detected.RequiresWP
	s.log.Info("Found .plugin-detected.json", "path", path, "pluginName", detected.PluginName)

	return apperror.Ok(*scan), true
}

// applyPluginInfo copies header info into the scan result.
func applyPluginInfo(scan *ScanResult, info *pluginHeaderInfo) {
	scan.IsValid = true
	scan.MainFile = info.MainFile
	scan.PluginName = info.PluginName
	scan.Version = info.Version
	scan.Description = info.Description
	scan.Author = info.Author
	scan.AuthorURI = info.AuthorURI
	scan.PluginURI = info.PluginURI
	scan.TextDomain = info.TextDomain
	scan.RequiresPHP = info.RequiresPHP
	scan.RequiresWP = info.RequiresWP
}

// walkDirectory walks the directory tree and populates the scan result.
func (s *Service) walkDirectory(path string, scan *ScanResult) error {
	return filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}

		relPath, _ := filepath.Rel(path, filePath)
		if relPath == "." {
			return nil
		}

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
}

// WritePluginDetected creates .plugin-detected.json for a valid WordPress plugin
func (s *Service) WritePluginDetected(ctx context.Context, path string) error {
	scan := s.ScanDirectory(ctx, path)
	if scan.HasError() {
		return scan.AppError()
	}

	scanVal := scan.Value()
	if !scanVal.IsValid {
		return apperror.New(apperror.ErrPathInvalid, scanVal.Error)
	}

	detected := PluginDetected{
		PluginName:  scanVal.PluginName,
		Version:     scanVal.Version,
		Slug:        filepath.Base(path),
		MainFile:    scanVal.MainFile,
		Description: scanVal.Description,
		Author:      scanVal.Author,
		AuthorURI:   scanVal.AuthorURI,
		PluginURI:   scanVal.PluginURI,
		TextDomain:  scanVal.TextDomain,
		RequiresPHP: scanVal.RequiresPHP,
		RequiresWP:  scanVal.RequiresWP,
		DetectedAt:  time.Now().UTC().Format(time.RFC3339),
	}

	data, err := json.MarshalIndent(detected, "", "  ")
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to marshal plugin detected")
	}

	detectedPath, err := pathutil.Join(path, pluginDetectedFile)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to resolve plugin detected path")
	}
	if err := os.WriteFile(detectedPath, data, 0644); err != nil {
		return apperror.Wrap(err, apperror.ErrFSWrite, "failed to write plugin detected file")
	}

	s.log.Info("Created .plugin-detected.json", "path", detectedPath)
	return nil
}

func (s *Service) readPluginDetected(path string) (*PluginDetected, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var detected PluginDetected
	if err := json.Unmarshal(data, &detected); err != nil {
		return nil, err
	}
	return &detected, nil
}

// ValidatePath checks if a path is a valid WordPress plugin directory
func (s *Service) ValidatePath(ctx context.Context, path string) *apperror.AppError {
	scan := s.ScanDirectory(ctx, path)
	if scan.HasError() {
		return scan.AppError()
	}

	scanVal := scan.Value()
	if !scanVal.IsValid {
		return apperror.New(apperror.ErrPathInvalid, scanVal.Error).WithPath(path)
	}
	return nil
}

type pluginHeaderInfo struct {
	MainFile    string
	PluginName  string
	Version     string
	Description string
	Author      string
	AuthorURI   string
	PluginURI   string
	TextDomain  string
	RequiresPHP string
	RequiresWP  string
}

func (s *Service) findMainPluginFile(path string) (*pluginHeaderInfo, error) {
	entries, err := os.ReadDir(path)
	if err != nil {
		return nil, err
	}

	patterns := map[string]*regexp.Regexp{
		"PluginName":  regexp.MustCompile(`Plugin Name:\s*(.+)`),
		"Version":     regexp.MustCompile(`Version:\s*(.+)`),
		"Description": regexp.MustCompile(`Description:\s*(.+)`),
		"Author":      regexp.MustCompile(`Author:\s*(.+)`),
		"AuthorURI":   regexp.MustCompile(`Author URI:\s*(.+)`),
		"PluginURI":   regexp.MustCompile(`Plugin URI:\s*(.+)`),
		"TextDomain":  regexp.MustCompile(`Text Domain:\s*(.+)`),
		"RequiresPHP": regexp.MustCompile(`Requires PHP:\s*(.+)`),
		"RequiresWP":  regexp.MustCompile(`Requires at least:\s*(.+)`),
	}

	for _, entry := range entries {
		if entry.IsDir() || !strings.HasSuffix(entry.Name(), ".php") {
			continue
		}

		info := s.parsePluginHeader(path, entry.Name(), patterns)
		if info != nil {
			return info, nil
		}
	}

	return nil, apperror.New(apperror.ErrPathInvalid, "no valid WordPress plugin file found (missing Plugin Name header)")
}

// parsePluginHeader reads a PHP file header and extracts plugin metadata.
func (s *Service) parsePluginHeader(dir, filename string, patterns map[string]*regexp.Regexp) *pluginHeaderInfo {
	filePath, err := pathutil.Join(dir, filename)
	if err != nil {
		return nil
	}
	file, err := os.Open(filePath)
	if err != nil {
		return nil
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	lineCount := 0
	info := &pluginHeaderInfo{MainFile: filename}

	for scanner.Scan() && lineCount < 50 {
		line := scanner.Text()
		lineCount++

		for key, pattern := range patterns {
			if matches := pattern.FindStringSubmatch(line); len(matches) > 1 {
				val := strings.TrimSpace(matches[1])
				setHeaderField(info, key, val)
			}
		}
	}

	if info.PluginName != "" {
		return info
	}
	return nil
}

// setHeaderField sets a pluginHeaderInfo field by key name.
func setHeaderField(info *pluginHeaderInfo, key, val string) {
	switch key {
	case "PluginName":
		info.PluginName = val
	case "Version":
		info.Version = val
	case "Description":
		info.Description = val
	case "Author":
		info.Author = val
	case "AuthorURI":
		info.AuthorURI = val
	case "PluginURI":
		info.PluginURI = val
	case "TextDomain":
		info.TextDomain = val
	case "RequiresPHP":
		info.RequiresPHP = val
	case "RequiresWP":
		info.RequiresWP = val
	}
}

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
