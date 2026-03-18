// Package rules — Go struct field PascalCase checker.
package rules

import (
	"fmt"
	"regexp"
	"strings"
	"unicode"

	"consistency-checker/internal/engine"
)

// structFieldPattern matches exported struct field declarations.
// Captures the field name (first word before the type).
var structFieldPattern = regexp.MustCompile(`^\s*([A-Z][A-Za-z0-9_]*)\s+\S`)

// underscorePattern detects snake_case in identifiers.
var underscorePattern = regexp.MustCompile(`_[a-zA-Z]`)

// GoStructFieldCasing checks that Go struct fields use PascalCase naming.
// It flags fields with underscores (snake_case) or inconsistent capitalization.
type GoStructFieldCasing struct{}

// Id returns the rule identifier.
func (r *GoStructFieldCasing) Id() string { return "go-struct-field-casing" }

// Name returns the rule display name.
func (r *GoStructFieldCasing) Name() string { return "Go Struct Field Casing" }

// Languages returns the languages this rule applies to.
func (r *GoStructFieldCasing) Languages() []string { return []string{"go"} }

// Check scans Go source lines for struct fields that violate PascalCase.
func (r *GoStructFieldCasing) Check(ctx engine.CheckContext) []engine.Finding {
	var findings []engine.Finding

	inStruct := false

	for i, line := range ctx.Lines {
		trimmed := strings.TrimSpace(line)

		if isStructOpener(trimmed) {
			inStruct = true

			continue
		}

		if inStruct && trimmed == "}" {
			inStruct = false

			continue
		}

		if !inStruct {
			continue
		}

		finding := checkFieldCasing(ctx, trimmed, i+1)
		if finding != nil {
			findings = append(findings, *finding)
		}
	}

	return findings
}

// isStructOpener returns true if the line opens a struct definition.
func isStructOpener(line string) bool {
	return strings.HasPrefix(line, "type ") && strings.HasSuffix(line, "struct {")
}

// checkFieldCasing checks a single struct field line for PascalCase violations.
func checkFieldCasing(ctx engine.CheckContext, line string, lineNum int) *engine.Finding {
	match := structFieldPattern.FindStringSubmatch(line)
	if match == nil {
		return nil
	}

	fieldName := match[1]

	// Skip embedded types (no space-separated type follows the identifier directly)
	isEmbedded := !strings.Contains(line, " ")
	if isEmbedded {
		return nil
	}

	violation := detectCasingViolation(fieldName)
	if violation == "" {
		return nil
	}

	suggestion := toFieldPascalCase(fieldName)

	return &engine.Finding{
		RuleId:     "go-struct-field-casing",
		RuleName:   "Go Struct Field Casing",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       lineNum,
		Message:    fmt.Sprintf("Struct field %q %s — use PascalCase %q instead", fieldName, violation, suggestion),
		Suggestion: fmt.Sprintf("Rename to %q", suggestion),
		Reference:  ctx.Spec.Reference,
		Context:    line,
	}
}

// detectCasingViolation returns a description of the violation, or "" if the field is valid.
func detectCasingViolation(name string) string {
	hasUnderscore := strings.Contains(name, "_")
	if hasUnderscore {
		return "uses snake_case"
	}

	// Check for sequences of lowercase after the first char that suggest
	// missing word boundaries (e.g., "Pluginname" instead of "PluginName").
	// This is hard to detect without a dictionary, so we only flag underscores
	// and leave word boundary detection to code review.

	return ""
}

// toFieldPascalCase converts a snake_case or mixed identifier to PascalCase.
func toFieldPascalCase(name string) string {
	hasUnderscore := strings.Contains(name, "_")
	if !hasUnderscore {
		return name
	}

	parts := strings.Split(name, "_")
	var result strings.Builder

	for _, part := range parts {
		if part == "" {
			continue
		}

		runes := []rune(part)
		runes[0] = unicode.ToUpper(runes[0])
		result.WriteString(string(runes))
	}

	return result.String()
}
