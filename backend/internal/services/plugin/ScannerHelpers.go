package plugin

import (
	"bufio"
	"crypto/md5"
	"encoding/hex"
	"io"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

type pluginHeaderInfo struct {
	MainFile    string
	PluginName  string
	Version     string
	Description string
	Author      string
	AuthorUri   string
	PluginUri   string
	TextDomain  string
	RequiresPhp string
	RequiresWP  string
}

// walkState holds shared state for a directory walk.
type walkState struct {
	BasePath string
	Scan     *ScanResult
}

// walkDirectory walks the directory tree and populates the scan result.
func (s *Service) walkDirectory(path string, scan *ScanResult) error {
	ws := &walkState{BasePath: path, Scan: scan}
	return filepath.Walk(path, func(filePath string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		return s.processWalkEntry(ws, filePath, info)
	})
}

// processWalkEntry handles a single file system entry during directory walk.
func (s *Service) processWalkEntry(ws *walkState, filePath string, info os.FileInfo) error {
	relPath, _ := filepath.Rel(ws.BasePath, filePath)
	isRootEntry := relPath == "."

	if isRootEntry {
		return nil
	}

	skipDir := shouldSkipEntry(filePath, info)
	if skipDir != nil {
		return *skipDir
	}

	fileInfo := buildFileInfo(relPath, info)

	isFile := !info.IsDir()

	if isFile {
		hash, hashErr := s.calculateFileHash(filePath)
		if hashErr != nil {
			fileInfo.Hash = ""
		} else {
			fileInfo.Hash = hash
		}
		ws.Scan.TotalSize += info.Size()
		ws.Scan.FileCount++
	}

	ws.Scan.Files = append(ws.Scan.Files, fileInfo)
	return nil
}

// shouldSkipEntry returns SkipDir for hidden/vendor dirs, nil otherwise.
func shouldSkipEntry(filePath string, info os.FileInfo) *error {
	base := filepath.Base(filePath)
	isHidden := strings.HasPrefix(base, ".")
	isNodeModules := base == "node_modules"
	isVendor := base == "vendor"
	isSkippable :=
		isHidden ||
		isNodeModules ||
		isVendor
	isNormalEntry := !isSkippable

	if isNormalEntry {
		return nil
	}
	if info.IsDir() {
		skip := filepath.SkipDir
		return &skip
	}
	var noErr error
	return &noErr
}

// applyPluginInfo copies plugin header metadata into the scan result.
func applyPluginInfo(scan *ScanResult, info *pluginHeaderInfo) {
	scan.IsValid = true
	scan.PluginName = info.PluginName
	scan.Version = info.Version
	scan.MainFile = info.MainFile
	scan.Description = info.Description
	scan.Author = info.Author
	scan.AuthorUri = info.AuthorUri
	scan.PluginUri = info.PluginUri
	scan.TextDomain = info.TextDomain
	scan.RequiresPhp = info.RequiresPhp
	scan.RequiresWP = info.RequiresWP
}

// buildFileInfo creates a FileInfo from path and os.FileInfo.
func buildFileInfo(relPath string, info os.FileInfo) FileInfo {
	return FileInfo{
		Path:        relPath,
		Size:        info.Size(),
		ModifiedAt:  info.ModTime(),
		IsDirectory: info.IsDir(),
	}
}

// headerPatterns returns compiled regex patterns for WP plugin headers.
func headerPatterns() map[string]*regexp.Regexp {
	return map[string]*regexp.Regexp{
		"PluginName":  regexp.MustCompile(`Plugin Name:\s*(.+)`),
		"Version":     regexp.MustCompile(`Version:\s*(.+)`),
		"Description": regexp.MustCompile(`Description:\s*(.+)`),
		"Author":      regexp.MustCompile(`Author:\s*(.+)`),
		"AuthorUri":   regexp.MustCompile(`Author URI:\s*(.+)`),
		"PluginUri":   regexp.MustCompile(`Plugin URI:\s*(.+)`),
		"TextDomain":  regexp.MustCompile(`Text Domain:\s*(.+)`),
		"RequiresPhp": regexp.MustCompile(`Requires PHP:\s*(.+)`),
		"RequiresWP":  regexp.MustCompile(`Requires at least:\s*(.+)`),
	}
}

func (s *Service) findMainPluginFile(path string) (*pluginHeaderInfo, error) {
	entries, err := os.ReadDir(path)
	if err != nil {
		return nil, err
	}

	patterns := headerPatterns()

	for _, entry := range entries {
		isSkippable := entry.IsDir() || !strings.HasSuffix(entry.Name(), ".php")
		if isSkippable {
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

	info := scanHeaderLines(file, filename, patterns)
	if info != nil && info.PluginName != "" {
		return info
	}
	return nil
}

// scanHeaderLines reads up to 50 lines and extracts header fields.
func scanHeaderLines(file *os.File, filename string, patterns map[string]*regexp.Regexp) *pluginHeaderInfo {
	scanner := bufio.NewScanner(file)
	lineCount := 0
	info := &pluginHeaderInfo{MainFile: filename}

	for scanner.Scan() && lineCount < 50 {
		line := scanner.Text()
		lineCount++
		matchHeaderLine(info, line, patterns)
	}

	if info.PluginName == "" {
		return nil
	}
	return info
}

// matchHeaderLine matches a single line against all header patterns.
func matchHeaderLine(info *pluginHeaderInfo, line string, patterns map[string]*regexp.Regexp) {
	for key, pattern := range patterns {
		matches := pattern.FindStringSubmatch(line)
		if len(matches) > 1 {
			setHeaderField(info, key, strings.TrimSpace(matches[1]))
		}
	}
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
	case "AuthorUri":
		info.AuthorUri = val
	case "PluginUri":
		info.PluginUri = val
	case "TextDomain":
		info.TextDomain = val
	case "RequiresPhp":
		info.RequiresPhp = val
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
	_, err = io.Copy(hash, file)
	if err != nil {
		return "", err
	}
	return hex.EncodeToString(hash.Sum(nil)), nil
}
