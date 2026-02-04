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
)

const pluginDetectedFile = "wp-plugin-detected.json"

// ScanDirectory scans a plugin directory and returns file information
func (s *Service) ScanDirectory(ctx context.Context, path string) (*ScanResult, error) {
	s.log.Debug("Scanning directory", "path", path)

	scan := &ScanResult{
		Path:    path,
		IsValid: false,
		Files:   []FileInfo{},
	}

	// Check if .plugin-detected.json exists first
	detectedPath := filepath.Join(path, pluginDetectedFile)
	if _, err := os.Stat(detectedPath); err == nil {
		detected, err := s.readPluginDetected(detectedPath)
		if err == nil {
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
			return scan, nil
		}
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
	pluginInfo, err := s.findMainPluginFile(path)
	if err != nil {
		scan.Error = err.Error()
		return scan, nil
	}

	scan.IsValid = true
	scan.MainFile = pluginInfo.MainFile
	scan.PluginName = pluginInfo.PluginName
	scan.Version = pluginInfo.Version
	scan.Description = pluginInfo.Description
	scan.Author = pluginInfo.Author
	scan.AuthorURI = pluginInfo.AuthorURI
	scan.PluginURI = pluginInfo.PluginURI
	scan.TextDomain = pluginInfo.TextDomain
	scan.RequiresPHP = pluginInfo.RequiresPHP
	scan.RequiresWP = pluginInfo.RequiresWP

	// Walk directory and collect files
	err = filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
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

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDirRead, "failed to scan directory")
	}

	s.log.Info("Directory scanned", "path", path, "pluginName", scan.PluginName, "files", scan.FileCount)

	return scan, nil
}

// WritePluginDetected creates .plugin-detected.json for a valid WordPress plugin
func (s *Service) WritePluginDetected(ctx context.Context, path string) error {
	scan, err := s.ScanDirectory(ctx, path)
	if err != nil {
		return err
	}
	if !scan.IsValid {
		return apperror.New(apperror.ErrPathInvalid, scan.Error)
	}

	detected := PluginDetected{
		PluginName:  scan.PluginName,
		Version:     scan.Version,
		Slug:        filepath.Base(path),
		MainFile:    scan.MainFile,
		Description: scan.Description,
		Author:      scan.Author,
		AuthorURI:   scan.AuthorURI,
		PluginURI:   scan.PluginURI,
		TextDomain:  scan.TextDomain,
		RequiresPHP: scan.RequiresPHP,
		RequiresWP:  scan.RequiresWP,
		DetectedAt:  time.Now().UTC().Format(time.RFC3339),
	}

	data, err := json.MarshalIndent(detected, "", "  ")
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to marshal plugin detected")
	}

	detectedPath := filepath.Join(path, pluginDetectedFile)
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
func (s *Service) ValidatePath(ctx context.Context, path string) error {
	scan, err := s.ScanDirectory(ctx, path)
	if err != nil {
		return err
	}
	if !scan.IsValid {
		return apperror.New(apperror.ErrPathInvalid, scan.Error).WithContext("path", path)
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

		filePath := filepath.Join(path, entry.Name())
		file, err := os.Open(filePath)
		if err != nil {
			continue
		}

		scanner := bufio.NewScanner(file)
		lineCount := 0
		info := &pluginHeaderInfo{MainFile: entry.Name()}

		for scanner.Scan() && lineCount < 50 {
			line := scanner.Text()
			lineCount++

			for key, pattern := range patterns {
				if matches := pattern.FindStringSubmatch(line); len(matches) > 1 {
					val := strings.TrimSpace(matches[1])
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
			}
		}
		file.Close()

		if info.PluginName != "" {
			return info, nil
		}
	}

	return nil, apperror.New(apperror.ErrPathInvalid, "no valid WordPress plugin file found (missing Plugin Name header)")
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
