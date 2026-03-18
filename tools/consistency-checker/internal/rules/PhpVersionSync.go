// Package rules — PHP version synchronization checker.
package rules

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"strings"

	"consistency-checker/internal/engine"
)

// PhpVersionSync checks that plugin header Version matches PluginConfigType::Version
// and that both match the corresponding field in public/version.json.
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

	var findings []engine.Finding

	if headerVersion != enumVersion {
		findings = append(findings, buildMismatchFindings(ctx, headerVersion, enumVersion, enumPath)...)
	}

	// Check version.json sync
	jsonFindings := checkVersionJsonSync(ctx, enumVersion)
	findings = append(findings, jsonFindings...)

	if len(findings) == 0 {
		return nil
	}
	return findings
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

// versionJsonData holds the relevant fields from public/version.json.
type versionJsonData struct {
	WpPluginVersion string `json:"wpPluginVersion"`
	QuploadVersion  string `json:"quploadVersion"`
}

// pluginDirToJsonKey maps plugin directory names to version.json field names.
var pluginDirToJsonKey = map[string]string{
	"riseup-asia-uploader": "wpPluginVersion",
	"qupload":              "quploadVersion",
}

// findVersionJson locates public/version.json by walking up from the plugin file path.
func findVersionJson(pluginFilePath string) string {
	dir := filepath.Dir(pluginFilePath)

	// Walk up at most 10 levels looking for public/version.json
	for i := 0; i < 10; i++ {
		candidate := filepath.Join(dir, "public", "version.json")
		if _, err := os.Stat(candidate); err == nil {
			return candidate
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			break
		}
		dir = parent
	}
	return ""
}

// resolvePluginDirName extracts the plugin directory name from a file path.
// e.g. "wp-plugins/qupload/qupload.php" → "qupload"
func resolvePluginDirName(filePath string) string {
	parts := strings.Split(filepath.ToSlash(filePath), "/")
	for i, part := range parts {
		if part == "wp-plugins" && i+1 < len(parts) {
			return parts[i+1]
		}
	}
	return ""
}

// readVersionJson parses public/version.json and returns the data.
func readVersionJson(jsonPath string) (*versionJsonData, error) {
	data, err := os.ReadFile(jsonPath)
	if err != nil {
		return nil, err
	}

	var vj versionJsonData
	if err := json.Unmarshal(data, &vj); err != nil {
		return nil, err
	}
	return &vj, nil
}

// checkVersionJsonSync validates that the enum version matches the version.json field.
func checkVersionJsonSync(ctx engine.CheckContext, enumVersion string) []engine.Finding {
	pluginDir := resolvePluginDirName(ctx.FilePath)
	if pluginDir == "" {
		return nil
	}

	jsonKey, ok := pluginDirToJsonKey[pluginDir]
	if !ok {
		return nil
	}

	jsonPath := findVersionJson(ctx.FilePath)
	if jsonPath == "" {
		return nil
	}

	vj, err := readVersionJson(jsonPath)
	if err != nil {
		return []engine.Finding{{
			RuleId:     "php-version-sync",
			RuleName:   "PHP Version Sync",
			Severity:   ctx.Spec.Severity,
			FilePath:   ctx.FilePath,
			Line:       1,
			Message:    fmt.Sprintf("Cannot read version.json at %s: %v", jsonPath, err),
			Suggestion: "Ensure public/version.json exists and is valid JSON",
			Reference:  ctx.Spec.Reference,
		}}
	}

	var jsonVersion string
	switch jsonKey {
	case "wpPluginVersion":
		jsonVersion = vj.WpPluginVersion
	case "quploadVersion":
		jsonVersion = vj.QuploadVersion
	}

	if jsonVersion == "" {
		return []engine.Finding{{
			RuleId:     "php-version-sync",
			RuleName:   "PHP Version Sync",
			Severity:   ctx.Spec.Severity,
			FilePath:   ctx.FilePath,
			Line:       1,
			Message:    fmt.Sprintf("version.json missing %q field", jsonKey),
			Suggestion: fmt.Sprintf("Add %q to public/version.json", jsonKey),
			Reference:  ctx.Spec.Reference,
		}}
	}

	if enumVersion == jsonVersion {
		return nil
	}

	return []engine.Finding{{
		RuleId:     "php-version-sync",
		RuleName:   "PHP Version Sync",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       findHeaderVersionLine(ctx.Lines),
		Message:    fmt.Sprintf("Plugin version %q != version.json %s %q", enumVersion, jsonKey, jsonVersion),
		Suggestion: fmt.Sprintf("Sync versions: update plugin or version.json %s field", jsonKey),
		Reference:  ctx.Spec.Reference,
	}}
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
