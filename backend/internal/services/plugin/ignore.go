// Package plugin provides the upload ignore parser
package plugin

import (
	"bufio"
	"os"
	"path/filepath"
	"regexp"
	"strings"
)

// UploadIgnoreFilename is the name of the ignore file
const UploadIgnoreFilename = ".uploadignore"

// UploadIgnore handles .uploadignore pattern matching
type UploadIgnore struct {
	patterns  []compiledPattern
	negations []compiledPattern
	loaded    bool
}

// compiledPattern represents a compiled ignore pattern
type compiledPattern struct {
	original  string
	anchored  bool
	directory bool
	regex     *regexp.Regexp
}

// LoadUploadIgnore loads and parses an .uploadignore file from a plugin directory
func LoadUploadIgnore(pluginDir string) (*UploadIgnore, error) {
	ui := &UploadIgnore{
		patterns:  make([]compiledPattern, 0),
		negations: make([]compiledPattern, 0),
		loaded:    false,
	}

	ignorePath := filepath.Join(pluginDir, UploadIgnoreFilename)
	file, err := os.Open(ignorePath)
	if err != nil {
		if os.IsNotExist(err) {
			return ui, nil // Not an error, just no ignore file
		}
		return nil, err
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())

		// Skip empty lines and comments
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}

		// Handle negation patterns
		if strings.HasPrefix(line, "!") {
			pattern := strings.TrimPrefix(line, "!")
			compiled, err := compilePattern(pattern)
			if err != nil {
				continue // Skip invalid patterns
			}
			ui.negations = append(ui.negations, compiled)
		} else {
			compiled, err := compilePattern(line)
			if err != nil {
				continue // Skip invalid patterns
			}
			ui.patterns = append(ui.patterns, compiled)
		}
	}

	if err := scanner.Err(); err != nil {
		return nil, err
	}

	ui.loaded = true
	return ui, nil
}

// ShouldIgnore checks if a relative path should be ignored
func (u *UploadIgnore) ShouldIgnore(relPath string) bool {
	// Normalize path separators
	path := filepath.ToSlash(relPath)
	path = strings.TrimPrefix(path, "/")

	// Check if any pattern matches
	ignored := false
	for _, pattern := range u.patterns {
		if matchPattern(pattern, path) {
			ignored = true
			break
		}
	}

	// If ignored, check for negation patterns
	if ignored {
		for _, pattern := range u.negations {
			if matchPattern(pattern, path) {
				return false // Negated, don't ignore
			}
		}
	}

	return ignored
}

// GetPatterns returns all include patterns
func (u *UploadIgnore) GetPatterns() []string {
	result := make([]string, len(u.patterns))
	for i, p := range u.patterns {
		result[i] = p.original
	}
	return result
}

// GetNegations returns all negation patterns
func (u *UploadIgnore) GetNegations() []string {
	result := make([]string, len(u.negations))
	for i, p := range u.negations {
		result[i] = p.original
	}
	return result
}

// IsLoaded returns whether an ignore file was successfully loaded
func (u *UploadIgnore) IsLoaded() bool {
	return u.loaded
}

// compilePattern compiles a gitignore-style pattern to regex
func compilePattern(pattern string) (compiledPattern, error) {
	cp := compiledPattern{
		original:  pattern,
		anchored:  false,
		directory: false,
	}

	// Check if pattern is anchored to root
	if strings.HasPrefix(pattern, "/") {
		cp.anchored = true
		pattern = strings.TrimPrefix(pattern, "/")
	}

	// Check if pattern is directory-only
	if strings.HasSuffix(pattern, "/") {
		cp.directory = true
		pattern = strings.TrimSuffix(pattern, "/")
	}

	// Escape regex special characters
	regexStr := regexp.QuoteMeta(pattern)

	// Handle ** (match any path segments)
	regexStr = strings.ReplaceAll(regexStr, `\*\*`, ".*")

	// Handle * (match any characters except /)
	regexStr = strings.ReplaceAll(regexStr, `\*`, "[^/]*")

	// Handle ? (match single character except /)
	regexStr = strings.ReplaceAll(regexStr, `\?`, "[^/]")

	if cp.anchored {
		regexStr = "^" + regexStr
	} else {
		// Pattern can match anywhere in path
		regexStr = "(^|/)" + regexStr
	}

	// Match end of path or directory separator
	regexStr = regexStr + "(/|$)"

	regex, err := regexp.Compile("(?i)" + regexStr)
	if err != nil {
		return cp, err
	}

	cp.regex = regex
	return cp, nil
}

// matchPattern matches a compiled pattern against a path
func matchPattern(pattern compiledPattern, path string) bool {
	if pattern.regex == nil {
		return false
	}
	return pattern.regex.MatchString(path)
}

// FilterFiles filters a list of file paths, returning only those not ignored
func (u *UploadIgnore) FilterFiles(files []string) []string {
	result := make([]string, 0, len(files))
	for _, f := range files {
		if !u.ShouldIgnore(f) {
			result = append(result, f)
		}
	}
	return result
}

// PartitionFiles partitions files into included and ignored lists
func (u *UploadIgnore) PartitionFiles(files []string) (included []string, ignored []string) {
	included = make([]string, 0, len(files))
	ignored = make([]string, 0)
	for _, f := range files {
		if u.ShouldIgnore(f) {
			ignored = append(ignored, f)
		} else {
			included = append(included, f)
		}
	}
	return
}
