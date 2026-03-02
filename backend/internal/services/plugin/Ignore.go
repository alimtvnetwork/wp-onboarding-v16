// Package plugin provides the upload ignore parser
package plugin

import (
	"bufio"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"wp-plugin-publish/pkg/apperror"
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

// LoadUploadIgnore loads and parses an .uploadignore file from a plugin directory.
func LoadUploadIgnore(pluginDir string) (*UploadIgnore, *apperror.AppError) {
	ui := &UploadIgnore{
		patterns:  make([]compiledPattern, 0),
		negations: make([]compiledPattern, 0),
	}

	ignorePath := filepath.Join(pluginDir, UploadIgnoreFilename)
	file, err := os.Open(ignorePath)

	if err != nil {
		isNotFound := os.IsNotExist(err)

		if isNotFound {
			return ui, nil
		}

		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to open .uploadignore file")
	}
	defer file.Close()

	appErr := parseIgnoreFile(file, ui)

	if appErr != nil {
		return nil, appErr
	}

	ui.loaded = true

	return ui, nil
}

// parseIgnoreFile reads lines from the file and populates patterns.
func parseIgnoreFile(file *os.File, ui *UploadIgnore) *apperror.AppError {
	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		addIgnoreLine(ui, line)
	}

	scanErr := scanner.Err()

	if scanErr != nil {
		return apperror.Wrap(scanErr, apperror.ErrFSRead, "failed to parse .uploadignore file")
	}

	return nil
}

// addIgnoreLine compiles and appends a single ignore line.
func addIgnoreLine(ui *UploadIgnore, line string) {
	if strings.HasPrefix(line, "!") {
		compiled, err := compilePattern(strings.TrimPrefix(line, "!"))
		if err == nil {
			ui.negations = append(ui.negations, compiled)
		}
	} else {
		compiled, err := compilePattern(line)
		if err == nil {
			ui.patterns = append(ui.patterns, compiled)
		}
	}
}

// ShouldIgnore checks if a relative path should be ignored.
func (u *UploadIgnore) ShouldIgnore(relPath string) bool {
	path := filepath.ToSlash(relPath)
	path = strings.TrimPrefix(path, "/")

	ignored := matchesAny(u.patterns, path)

	if ignored {
		return !matchesAny(u.negations, path)
	}
	return false
}

// matchesAny returns true if any pattern matches the path.
func matchesAny(patterns []compiledPattern, path string) bool {
	for _, p := range patterns {
		if matchPattern(p, path) {
			return true
		}
	}
	return false
}

// GetPatterns returns all include patterns.
func (u *UploadIgnore) GetPatterns() []string {
	result := make([]string, len(u.patterns))
	for i, p := range u.patterns {
		result[i] = p.original
	}
	return result
}

// GetNegations returns all negation patterns.
func (u *UploadIgnore) GetNegations() []string {
	result := make([]string, len(u.negations))
	for i, p := range u.negations {
		result[i] = p.original
	}
	return result
}

// IsLoaded returns whether an ignore file was successfully loaded.
func (u *UploadIgnore) IsLoaded() bool {
	return u.loaded
}

// compilePattern compiles a gitignore-style pattern to regex.
func compilePattern(pattern string) (compiledPattern, error) {
	cp := compiledPattern{original: pattern}

	pattern = parsePatternFlags(&cp, pattern)
	regexStr := buildPatternRegex(cp, pattern)

	regex, err := regexp.Compile("(?i)" + regexStr)
	if err != nil {
		return cp, err
	}

	cp.regex = regex
	return cp, nil
}

// parsePatternFlags extracts anchored/directory flags and returns the cleaned pattern.
func parsePatternFlags(cp *compiledPattern, pattern string) string {
	if strings.HasPrefix(pattern, "/") {
		cp.anchored = true
		pattern = strings.TrimPrefix(pattern, "/")
	}
	if strings.HasSuffix(pattern, "/") {
		cp.directory = true
		pattern = strings.TrimSuffix(pattern, "/")
	}
	return pattern
}

// buildPatternRegex converts a gitignore pattern to a regex string.
func buildPatternRegex(cp compiledPattern, pattern string) string {
	regexStr := regexp.QuoteMeta(pattern)
	regexStr = strings.ReplaceAll(regexStr, `\*\*`, ".*")
	regexStr = strings.ReplaceAll(regexStr, `\*`, "[^/]*")
	regexStr = strings.ReplaceAll(regexStr, `\?`, "[^/]")

	if cp.anchored {
		regexStr = "^" + regexStr
	} else {
		regexStr = "(^|/)" + regexStr
	}
	return regexStr + "(/|$)"
}

// matchPattern matches a compiled pattern against a path.
func matchPattern(pattern compiledPattern, path string) bool {
	if pattern.regex == nil {
		return false
	}
	return pattern.regex.MatchString(path)
}

// FilterFiles filters a list of file paths, returning only those not ignored.
func (u *UploadIgnore) FilterFiles(files []string) []string {
	result := make([]string, 0, len(files))
	for _, f := range files {
		isIncluded := !u.ShouldIgnore(f)

		if isIncluded {
			result = append(result, f)
		}
	}
	return result
}

// PartitionFiles partitions files into included and ignored lists.
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
