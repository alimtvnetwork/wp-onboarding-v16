// Package rules — file naming convention checker.
package rules

import (
	"fmt"
	"path/filepath"
	"strings"
	"unicode"

	"consistency-checker/internal/engine"
)

// FileNaming checks that files follow the configured naming convention.
type FileNaming struct{}

// ID returns the rule identifier.
func (r *FileNaming) ID() string { return "file-naming" }

// Name returns the rule display name.
func (r *FileNaming) Name() string { return "File Naming Convention" }

// Languages returns the languages this rule applies to.
func (r *FileNaming) Languages() []string { return []string{"go"} }

// Check validates the file name against the configured convention.
func (r *FileNaming) Check(ctx engine.CheckContext) []engine.Finding {
	convention := ctx.Spec.ParamString("convention", "PascalCase")
	baseName := stripExtension(filepath.Base(ctx.FilePath))

	if isValidName(baseName, convention) {
		return nil
	}

	return []engine.Finding{buildNamingFinding(ctx, baseName, convention)}
}

// stripExtension removes the file extension.
func stripExtension(name string) string {
	ext := filepath.Ext(name)
	return strings.TrimSuffix(name, ext)
}

// isValidName checks if a name matches the convention.
func isValidName(name, convention string) bool {
	switch convention {
	case "PascalCase":
		return isPascalCase(name)
	case "snake_case":
		return isSnakeCase(name)
	case "camelCase":
		return isCamelCase(name)
	default:
		return true
	}
}

// isPascalCase checks PascalCase: starts uppercase, no underscores.
func isPascalCase(name string) bool {
	if len(name) == 0 {
		return false
	}
	if !unicode.IsUpper(rune(name[0])) {
		return false
	}
	return !strings.Contains(name, "_")
}

// isSnakeCase checks snake_case: all lowercase with underscores.
func isSnakeCase(name string) bool {
	for _, ch := range name {
		if !unicode.IsLower(ch) && ch != '_' && !unicode.IsDigit(ch) {
			return false
		}
	}
	return true
}

// isCamelCase checks camelCase: starts lowercase, no underscores.
func isCamelCase(name string) bool {
	if len(name) == 0 {
		return false
	}
	if !unicode.IsLower(rune(name[0])) {
		return false
	}
	return !strings.Contains(name, "_")
}

// buildNamingFinding constructs a finding for a naming violation.
func buildNamingFinding(ctx engine.CheckContext, name, convention string) engine.Finding {
	return engine.Finding{
		RuleID:     "file-naming",
		RuleName:   "File Naming Convention",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       1,
		Message:    fmt.Sprintf("File %q does not follow %s convention", name, convention),
		Suggestion: fmt.Sprintf("Rename to follow %s naming", convention),
		Reference:  ctx.Spec.Reference,
	}
}
