// Package rules — PHP enum required methods checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// PhpEnumMethods checks that PHP enum files include the required comparison
// methods: isEqual, isOtherThan, and isAnyOf.
type PhpEnumMethods struct{}

// Id returns the rule identifier.
func (r *PhpEnumMethods) Id() string { return "php-enum-methods" }

// Name returns the rule display name.
func (r *PhpEnumMethods) Name() string { return "PHP Enum Required Methods" }

// Languages returns the languages this rule applies to.
func (r *PhpEnumMethods) Languages() []string { return []string{"php"} }

// requiredEnumMethods lists the methods every PHP enum must implement.
var requiredEnumMethods = []string{"isEqual", "isOtherThan", "isAnyOf"}

// Check validates that all required methods are present in PHP enum files.
func (r *PhpEnumMethods) Check(ctx engine.CheckContext) []engine.Finding {
	enumName, enumLine := findPhpEnumDeclaration(ctx.Lines)
	if enumName == "" {
		return nil
	}

	present := collectDefinedMethods(ctx.Lines)

	var findings []engine.Finding
	for _, method := range requiredEnumMethods {
		if !present[method] {
			findings = append(findings, engine.Finding{
				RuleId:     "php-enum-methods",
				RuleName:   "PHP Enum Required Methods",
				Severity:   ctx.Spec.Severity,
				FilePath:   ctx.FilePath,
				Line:       enumLine,
				Message:    fmt.Sprintf("Enum %s is missing required method %s()", enumName, method),
				Suggestion: fmt.Sprintf("Add: public function %s(self $other): bool { ... }", method),
				Reference:  ctx.Spec.Reference,
			})
		}
	}
	return findings
}

// collectDefinedMethods scans lines for `function <name>` and returns a set.
func collectDefinedMethods(lines []string) map[string]bool {
	methods := make(map[string]bool)
	for _, line := range lines {
		trimmed := strings.TrimSpace(line)
		if idx := strings.Index(trimmed, "function "); idx >= 0 {
			rest := trimmed[idx+len("function "):]
			if paren := strings.Index(rest, "("); paren > 0 {
				methods[rest[:paren]] = true
			}
		}
	}
	return methods
}
