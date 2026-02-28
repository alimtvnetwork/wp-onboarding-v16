// Package rules — Go inline if-init pattern detector.
package rules

import (
	"fmt"
	"regexp"

	"consistency-checker/internal/engine"
)

// inlineIfPattern matches 'if err := ...; err != nil' and 'if _, err := ...; err != nil'.
var inlineIfPattern = regexp.MustCompile(`^\s*if\s+.*:=.*;\s*`)

// GoInlineIf detects prohibited inline if-init patterns.
type GoInlineIf struct{}

// Id returns the rule identifier.
func (r *GoInlineIf) Id() string { return "go-inline-if" }

// Name returns the rule display name.
func (r *GoInlineIf) Name() string { return "Go Inline If-Init" }

// Languages returns the languages this rule applies to.
func (r *GoInlineIf) Languages() []string { return []string{"go"} }

// Check scans lines for inline if-init patterns.
func (r *GoInlineIf) Check(ctx engine.CheckContext) []engine.Finding {
	var findings []engine.Finding

	for i, line := range ctx.Lines {
		isViolation := inlineIfPattern.MatchString(line)

		if isViolation {
			lineNum := i + 1
			finding := engine.Finding{
				RuleId:     "go-inline-if",
				RuleName:   "Go Inline If-Init",
				Severity:   ctx.Spec.Severity,
				FilePath:   ctx.FilePath,
				Line:       lineNum,
				Message:    fmt.Sprintf("Inline if-init at line %d — separate assignment from condition", lineNum),
				Suggestion: "Split into: err := doThing() followed by if err != nil {",
				Reference:  ctx.Spec.Reference,
				Context:    line,
			}
			findings = append(findings, finding)
		}
	}

	return findings
}
