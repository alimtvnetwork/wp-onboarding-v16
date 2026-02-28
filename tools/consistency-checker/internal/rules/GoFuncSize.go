// Package rules — Go function body size checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// GoFuncSize checks that Go function bodies do not exceed a maximum line count.
type GoFuncSize struct{}

// ID returns the rule identifier.
func (r *GoFuncSize) ID() string { return "go-func-size" }

// Name returns the rule display name.
func (r *GoFuncSize) Name() string { return "Go Function Body Size" }

// Languages returns the languages this rule applies to.
func (r *GoFuncSize) Languages() []string { return []string{"go"} }

// Check analyzes all functions in a file for body size violations.
func (r *GoFuncSize) Check(ctx engine.CheckContext) []engine.Finding {
	maxLines := ctx.Spec.ParamInt("max_lines", 15)
	functions := parseFunctions(ctx.Lines)

	var findings []engine.Finding
	for _, fn := range functions {
		if fn.BodyLines > maxLines {
			findings = append(findings, buildFuncSizeFinding(ctx, fn, maxLines))
		}
	}
	return findings
}

// parsedFunction holds metadata about a detected function.
type parsedFunction struct {
	Name      string
	StartLine int
	EndLine   int
	BodyLines int
}

// parseFunctions scans lines for func declarations and counts body lines.
func parseFunctions(lines []string) []parsedFunction {
	var funcs []parsedFunction
	var current *parsedFunction
	braceDepth := 0

	for i, line := range lines {
		trimmed := strings.TrimSpace(line)

		if current == nil {
			current = detectFuncStart(trimmed, i)
			if current != nil {
				braceDepth = 1
			}
			continue
		}

		braceDepth = updateBraceDepth(trimmed, braceDepth)
		if !isBlankOrComment(trimmed) {
			current.BodyLines++
		}

		if braceDepth == 0 {
			current.EndLine = i + 1
			funcs = append(funcs, *current)
			current = nil
		}
	}
	return funcs
}

// detectFuncStart checks if a line starts a function definition.
func detectFuncStart(trimmed string, lineIdx int) *parsedFunction {
	if !strings.HasPrefix(trimmed, "func ") && !strings.HasPrefix(trimmed, "func(") {
		return nil
	}

	if !strings.Contains(trimmed, "{") {
		return nil
	}

	name := extractFuncName(trimmed)
	return &parsedFunction{Name: name, StartLine: lineIdx + 1}
}

// extractFuncName extracts the function name from a declaration line.
func extractFuncName(line string) string {
	line = strings.TrimPrefix(line, "func ")
	if idx := strings.Index(line, "("); idx > 0 {
		candidate := line[:idx]
		if dotIdx := strings.LastIndex(candidate, ")"); dotIdx >= 0 {
			candidate = candidate[dotIdx+1:]
			candidate = strings.TrimSpace(candidate)
		}
		return candidate
	}
	return "anonymous"
}

// isBlankOrComment returns true if the trimmed line is empty or a comment.
func isBlankOrComment(trimmed string) bool {
	return trimmed == "" || strings.HasPrefix(trimmed, "//") || strings.HasPrefix(trimmed, "/*") || strings.HasPrefix(trimmed, "*")
}

// updateBraceDepth tracks brace nesting depth.
func updateBraceDepth(line string, depth int) int {
	for _, ch := range line {
		switch ch {
		case '{':
			depth++
		case '}':
			depth--
		}
	}
	return depth
}

// buildFuncSizeFinding constructs a finding for an oversized function.
func buildFuncSizeFinding(ctx engine.CheckContext, fn parsedFunction, maxLines int) engine.Finding {
	return engine.Finding{
		RuleID:     "go-func-size",
		RuleName:   "Go Function Body Size",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       fn.StartLine,
		EndLine:    fn.EndLine,
		Message:    fmt.Sprintf("Function %q has %d body lines (max %d)", fn.Name, fn.BodyLines, maxLines),
		Suggestion: "Extract helper functions or use input structs to reduce complexity",
		Reference:  ctx.Spec.Reference,
	}
}
