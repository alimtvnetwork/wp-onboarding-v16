// Package rules — PHP enum naming convention checker.
package rules

import (
	"fmt"
	"path/filepath"
	"regexp"
	"strings"

	"consistency-checker/internal/engine"
)

// PhpEnumNaming checks that PHP enum files follow the project naming conventions:
// 1. Enum name must be PascalCase with a "Type" suffix (e.g., StatusType).
// 2. File name must match the enum name (e.g., StatusType.php).
type PhpEnumNaming struct{}

// Id returns the rule identifier.
func (r *PhpEnumNaming) Id() string { return "php-enum-naming" }

// Name returns the rule display name.
func (r *PhpEnumNaming) Name() string { return "PHP Enum Naming Convention" }

// Languages returns the languages this rule applies to.
func (r *PhpEnumNaming) Languages() []string { return []string{"php"} }

// phpEnumDeclPattern matches `enum FooType: string {` or `enum FooType {`.
var phpEnumDeclPattern = regexp.MustCompile(`^\s*enum\s+([A-Za-z0-9_]+)`)

// Check validates that PHP enums use PascalCase + Type suffix and file name matches.
func (r *PhpEnumNaming) Check(ctx engine.CheckContext) []engine.Finding {
	enumName, enumLine := findPhpEnumDeclaration(ctx.Lines)
	if enumName == "" {
		return nil
	}

	var findings []engine.Finding

	if f := checkTypeSuffix(ctx, enumName, enumLine); f != nil {
		findings = append(findings, *f)
	}

	if f := checkEnumFileMatch(ctx, enumName, enumLine); f != nil {
		findings = append(findings, *f)
	}

	if f := checkEnumPascalCase(ctx, enumName, enumLine); f != nil {
		findings = append(findings, *f)
	}

	return findings
}

// findPhpEnumDeclaration locates the first enum declaration in the file.
func findPhpEnumDeclaration(lines []string) (string, int) {
	for i, line := range lines {
		matches := phpEnumDeclPattern.FindStringSubmatch(line)
		if matches != nil {
			return matches[1], i + 1
		}
	}
	return "", 0
}

// checkTypeSuffix verifies the enum name ends with "Type".
func checkTypeSuffix(ctx engine.CheckContext, name string, line int) *engine.Finding {
	if strings.HasSuffix(name, "Type") {
		return nil
	}
	return &engine.Finding{
		RuleId:     "php-enum-naming",
		RuleName:   "PHP Enum Naming Convention",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       line,
		Message:    fmt.Sprintf("Enum %q is missing the required \"Type\" suffix", name),
		Suggestion: fmt.Sprintf("Rename to %sType", name),
		Reference:  ctx.Spec.Reference,
	}
}

// checkEnumFileMatch verifies the file name matches the enum name.
func checkEnumFileMatch(ctx engine.CheckContext, name string, line int) *engine.Finding {
	baseName := strings.TrimSuffix(filepath.Base(ctx.FilePath), ".php")
	if baseName == name {
		return nil
	}
	return &engine.Finding{
		RuleId:     "php-enum-naming",
		RuleName:   "PHP Enum Naming Convention",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       line,
		Message:    fmt.Sprintf("File %q does not match enum name %q", baseName+".php", name+".php"),
		Suggestion: fmt.Sprintf("Rename file to %s.php", name),
		Reference:  ctx.Spec.Reference,
	}
}

// checkEnumPascalCase verifies the enum name is PascalCase (starts uppercase, no underscores).
func checkEnumPascalCase(ctx engine.CheckContext, name string, line int) *engine.Finding {
	if isPascalCase(name) {
		return nil
	}
	return &engine.Finding{
		RuleId:     "php-enum-naming",
		RuleName:   "PHP Enum Naming Convention",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       line,
		Message:    fmt.Sprintf("Enum %q does not follow PascalCase convention", name),
		Suggestion: "Rename enum to PascalCase (e.g., StatusType, ActionType)",
		Reference:  ctx.Spec.Reference,
	}
}
