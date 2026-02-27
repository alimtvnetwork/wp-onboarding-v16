// Package scanner — language classification and exclusion matching.
package scanner

import (
	"path/filepath"
	"strings"
)

// classifyLanguage returns the language for a file extension, or "" if unsupported.
func classifyLanguage(path string) string {
	ext := strings.ToLower(filepath.Ext(path))
	switch ext {
	case ".go":
		return "go"
	case ".php":
		return "php"
	case ".html", ".htm":
		return "html"
	case ".md", ".markdown":
		return "md"
	default:
		return ""
	}
}

// IsExcluded checks if a relative path matches any exclusion glob.
func IsExcluded(relPath string, patterns []string) bool {
	for _, pattern := range patterns {
		if matchGlob(relPath, pattern) {
			return true
		}
	}
	return false
}

// matchGlob matches a path against a glob pattern.
func matchGlob(path, pattern string) bool {
	if strings.Contains(pattern, "**") {
		return matchDoubleGlob(path, pattern)
	}

	matched, _ := filepath.Match(pattern, path)
	if matched {
		return true
	}

	return matchBaseName(path, pattern)
}

// matchDoubleGlob handles ** patterns.
func matchDoubleGlob(path, pattern string) bool {
	prefix := strings.Split(pattern, "**")[0]
	prefix = strings.TrimSuffix(prefix, "/")
	if prefix == "" {
		return true
	}
	return strings.HasPrefix(path, prefix)
}

// matchBaseName matches against the file's base name.
func matchBaseName(path, pattern string) bool {
	matched, _ := filepath.Match(pattern, filepath.Base(path))
	return matched
}
