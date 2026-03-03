package publish

import (
	"os"
	"strings"

	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// getLocalPluginVersion extracts the version from a WordPress plugin's main PHP file header
func (s *Service) getLocalPluginVersion(pluginPath string) string {
	absPath, err := pathutil.ToAbsolute(pluginPath)
	if err != nil {
		return ""
	}

	entries, readErr := os.ReadDir(absPath)
	if readErr != nil {
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
	content, appErr := readPluginPhpFile(dirPath, fileName)
	if appErr != nil {
		return ""
	}

	return scanVersionFromContent(string(content))
}

// readPluginPhpFile reads a PHP file and validates it contains a Plugin Name header.
func readPluginPhpFile(dirPath, fileName string) ([]byte, *apperror.AppError) {
	filePath, err := pathutil.Join(dirPath, fileName)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to resolve plugin PHP file path")
	}

	content, readErr := os.ReadFile(filePath)
	if readErr != nil {
		return nil, apperror.Wrap(readErr, apperror.ErrFSRead, "failed to read plugin PHP file")
	}

	isPluginFile := strings.Contains(string(content), "Plugin Name:")
	isMissingHeader := !isPluginFile

	if isMissingHeader {
		return nil, apperror.New(apperror.ErrValidation, "not a plugin file").WithFilePath(filePath)
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
