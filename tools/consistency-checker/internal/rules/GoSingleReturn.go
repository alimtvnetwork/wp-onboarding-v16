// Package rules — Go single return value checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// GoSingleReturn checks that Go functions return exactly one value.
type GoSingleReturn struct{}

// Id returns the rule identifier.
func (r *GoSingleReturn) Id() string { return "go-single-return" }

// Name returns the rule display name.
func (r *GoSingleReturn) Name() string { return "Go Single Return Value" }

// Languages returns the languages this rule applies to.
func (r *GoSingleReturn) Languages() []string { return []string{"go"} }

// Check scans function signatures for multiple return values.
func (r *GoSingleReturn) Check(ctx engine.CheckContext) []engine.Finding {
	var findings []engine.Finding

	for i, line := range ctx.Lines {
		trimmed := strings.TrimSpace(line)
		if !isFuncDeclaration(trimmed) {
			continue
		}

		if hasMultipleReturns(trimmed) {
			findings = append(findings, buildMultiReturnFinding(ctx, trimmed, i+1))
		}
	}
	return findings
}

// isFuncDeclaration checks if a line declares a function.
func isFuncDeclaration(line string) bool {
	return strings.HasPrefix(line, "func ")
}

// hasMultipleReturns detects (T, error) style returns.
func hasMultipleReturns(line string) bool {
	afterParams := extractReturnPart(line)
	if afterParams == "" {
		return false
	}

	return isMultiReturnSignature(afterParams)
}

// extractReturnPart gets the portion after the last `)` that precedes `{`.
func extractReturnPart(line string) string {
	braceIdx := strings.LastIndex(line, "{")
	if braceIdx < 0 {
		return ""
	}

	beforeBrace := strings.TrimSpace(line[:braceIdx])
	return findReturnSection(beforeBrace)
}

// findReturnSection locates the return type section of a func signature.
func findReturnSection(sig string) string {
	parenCount := 0
	lastCloseParen := -1

	for i := len(sig) - 1; i >= 0; i-- {
		switch sig[i] {
		case ')':
			parenCount++
			if parenCount == 1 {
				lastCloseParen = i
			}
		case '(':
			parenCount--
			if parenCount == 0 && lastCloseParen > i {
				return strings.TrimSpace(sig[lastCloseParen+1:])
			}
		}
	}
	return ""
}

// isMultiReturnSignature checks if the return section has multiple values.
func isMultiReturnSignature(returnPart string) bool {
	if !strings.HasPrefix(returnPart, "(") {
		return false
	}

	inner := strings.Trim(returnPart, "()")
	parts := strings.Split(inner, ",")
	return len(parts) > 1
}

// buildMultiReturnFinding constructs a finding for a multi-return function.
func buildMultiReturnFinding(ctx engine.CheckContext, line string, lineNum int) engine.Finding {
	funcName := extractFuncName(line)
	return engine.Finding{
		RuleId:     "go-single-return",
		RuleName:   "Go Single Return Value",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       lineNum,
		Message:    fmt.Sprintf("Function %q has multiple return values; use apperror.Result[T] or a named struct", funcName),
		Suggestion: "Replace (T, error) with apperror.Result[T]",
		Reference:  ctx.Spec.Reference,
	}
}
