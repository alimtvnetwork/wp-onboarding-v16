// Package rules — PHP version synchronization checker.
package rules

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"consistency-checker/internal/engine"
)

// PhpVersionSync checks that plugin header Version matches PluginConfigType::Version.
type PhpVersionSync struct{}

// Id returns the rule identifier.
func (r *PhpVersionSync) Id() string { return "php-version-sync" }

// Name returns the rule display name.
func (r *PhpVersionSync) Name() string { return "PHP Version Sync" }

// Languages returns the languages this rule applies to.
func (r *PhpVersionSync) Languages() []string { return []string{"php"} }

// Check analyzes a plugin header file for version mismatches.
func (r *PhpVersionSync) Check(ctx engine.CheckContext) []engine.Finding {
	headerVersion := extractHeaderVersion(ctx.Lines)
	if headerVersion == "" {
		return nil
	}

	enumPath := findPluginConfigEnum(ctx.FilePath)
	if enumPath == "" {
		return nil
	}

	enumVersion := readEnumVersion(enumPath)
	if enumVersion == "" {
		return buildMissingEnumFindings(ctx, enumPath)
	}

	if headerVersion == enumVersion {
		return nil
	}

	return buildMismatchFindings(ctx, headerVersion, enumVersion, enumPath)
}

// headerVersionRe matches "Version: x.y.z" in plugin file headers.
var headerVersionRe = regexp.MustCompile(`^\s*\*?\s*Version:\s*(.+)$`)

// enumVersionRe matches "case Version = 'x.y.z';" in PluginConfigType.
var enumVersionRe = regexp.MustCompile(`case\s+Version\s*=\s*'([^']+)'`)

// extractHeaderVersion finds the Version: line in a PHP file header comment.
func extractHeaderVersion(lines []string) string {
	for _, line := range lines {
		match := headerVersionRe.FindStringSubmatch(line)
		if match == nil {
			continue
		}
		return strings.TrimSpace(match[1])
	}
	return ""
}

// findPluginConfigEnum locates PluginConfigType.php relative to the plugin root.
func findPluginConfigEnum(headerPath string) string {
	pluginDir := filepath.Dir(headerPath)
	enumPath := filepath.Join(pluginDir, "includes", "Enums", "PluginConfigType.php")

	if _, err := os.Stat(enumPath); err != nil {
		return ""
	}
	return enumPath
}

// readEnumVersion extracts the Version case value from PluginConfigType.php.
func readEnumVersion(enumPath string) string {
	data, err := os.ReadFile(enumPath)
	if err != nil {
		return ""
	}

	match := enumVersionRe.FindSubmatch(data)
	if match == nil {
		return ""
	}
	return string(match[1])
}

// buildMissingEnumFindings creates findings when the enum file cannot be read.
func buildMissingEnumFindings(ctx engine.CheckContext, enumPath string) []engine.Finding {
	return []engine.Finding{{
		RuleId:     "php-version-sync",
		RuleName:   "PHP Version Sync",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       1,
		Message:    fmt.Sprintf("Cannot read PluginConfigType at %s", enumPath),
		Suggestion: "Ensure PluginConfigType.php exists with a Version case",
		Reference:  ctx.Spec.Reference,
	}}
}

// buildMismatchFindings creates findings for version mismatches.
func buildMismatchFindings(ctx engine.CheckContext, headerVersion, enumVersion, enumPath string) []engine.Finding {
	return []engine.Finding{{
		RuleId:     "php-version-sync",
		RuleName:   "PHP Version Sync",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       findHeaderVersionLine(ctx.Lines),
		Message:    fmt.Sprintf("Header version %q != PluginConfigType version %q", headerVersion, enumVersion),
		Suggestion: fmt.Sprintf("Sync versions: update header or %s", filepath.Base(enumPath)),
		Reference:  ctx.Spec.Reference,
	}}
}

// findHeaderVersionLine returns the 1-based line number of the Version: header.
func findHeaderVersionLine(lines []string) int {
	for i, line := range lines {
		if headerVersionRe.MatchString(line) {
			return i + 1
		}
	}
	return 1
}
