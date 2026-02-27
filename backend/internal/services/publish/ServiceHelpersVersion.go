package publish

import (
	"fmt"
	"os"
	"strings"

	"wp-plugin-publish/pkg/pathutil"
)

// getLocalPluginVersion extracts the version from a WordPress plugin's main PHP file header
func (s *Service) getLocalPluginVersion(pluginPath string) string {
	absPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return ""
	}

	entries, err := os.ReadDir(absPath)
	if err != nil {
		return ""
	}

	return s.findVersionInPhpFiles(absPath, entries)
}

// findVersionInPhpFiles scans PHP files for a version header.
func (s *Service) findVersionInPhpFiles(absPath string, entries []os.DirEntry) string {
	for _, entry := range entries {
		isSkippable := entry.IsDir() || !strings.HasSuffix(entry.Name(), ".php")
		if isSkippable {
			continue
		}
		version := s.extractVersionFromPhpFile(absPath, entry.Name())
		if version != "" {
			return version
		}
	}

	return ""
}

// extractVersionFromPhpFile reads a PHP file and extracts the Version header.
func (s *Service) extractVersionFromPhpFile(dirPath, fileName string) string {
	content, err := readPluginPhpFile(dirPath, fileName)
	if err != nil {
		return ""
	}

	return scanVersionFromContent(string(content))
}

// readPluginPhpFile reads a PHP file and validates it contains a Plugin Name header.
func readPluginPhpFile(dirPath, fileName string) ([]byte, error) {
	filePath, err := pathutil.Join(dirPath, fileName)
	if err != nil {
		return nil, err
	}

	content, err := os.ReadFile(filePath)
	if err != nil {
		return nil, fmt.Errorf("not a plugin file")
	}

	isNotPlugin := !strings.Contains(string(content), "Plugin Name:")
	if isNotPlugin {
		return nil, fmt.Errorf("not a plugin file")
	}

	return content, nil
}

// scanVersionFromContent scans PHP content lines for a version header.
func scanVersionFromContent(content string) string {
	for _, line := range strings.Split(content, "\n") {
		version := parseVersionLine(strings.TrimSpace(line))
		if version != "" {
			return version
		}
	}

	return ""
}

// parseVersionLine extracts a version string from a PHP header comment line.
func parseVersionLine(trimmed string) string {
	prefixes := []string{"* Version:", "*Version:", "Version:"}
	for _, prefix := range prefixes {
		if strings.HasPrefix(trimmed, prefix) {
			version := strings.TrimSpace(strings.TrimPrefix(trimmed, prefix))
			if version != "" {
				return version
			}
		}
	}
	return ""
}
