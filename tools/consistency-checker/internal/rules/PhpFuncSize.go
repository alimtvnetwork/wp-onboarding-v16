// Package rules — PHP function body size checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// PhpFuncSize checks that PHP function bodies do not exceed a maximum line count.
type PhpFuncSize struct{}

// Id returns the rule identifier.
func (r *PhpFuncSize) Id() string { return "php-func-size" }

// Name returns the rule display name.
func (r *PhpFuncSize) Name() string { return "PHP Function Body Size" }

// Languages returns the languages this rule applies to.
func (r *PhpFuncSize) Languages() []string { return []string{"php"} }

// Check analyzes all functions in a PHP file for body size violations.
func (r *PhpFuncSize) Check(ctx engine.CheckContext) []engine.Finding {
	maxLines := ctx.Spec.ParamInt("max_lines", 20)
	functions := parsePhpFunctions(ctx.Lines)

	var findings []engine.Finding
	for _, fn := range functions {
		if fn.BodyLines > maxLines {
			findings = append(findings, buildPhpFuncSizeFinding(ctx, fn, maxLines))
		}
	}
	return findings
}

// phpParsedFunction holds metadata about a detected PHP function.
type phpParsedFunction struct {
	Name      string
	StartLine int
	EndLine   int
	BodyLines int
}

// parsePhpFunctions scans PHP lines for function declarations and counts body lines.
func parsePhpFunctions(lines []string) []phpParsedFunction {
	var funcs []phpParsedFunction
	var current *phpParsedFunction
	braceDepth := 0

	for i, line := range lines {
		trimmed := strings.TrimSpace(line)

		if current == nil {
			current = detectPhpFuncStart(trimmed, i)
			if current != nil {
				braceDepth = countBraces(trimmed)
			}
			continue
		}

		braceDepth = updatePhpBraceDepth(trimmed, braceDepth)
		if !isPhpBlankOrComment(trimmed) {
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

// detectPhpFuncStart checks if a line starts a PHP function/method.
func detectPhpFuncStart(trimmed string, lineIdx int) *phpParsedFunction {
	if !strings.Contains(trimmed, "function ") && !strings.Contains(trimmed, "function(") {
		return nil
	}

	if !strings.Contains(trimmed, "{") {
		return nil
	}

	name := extractPhpFuncName(trimmed)
	return &phpParsedFunction{Name: name, StartLine: lineIdx + 1}
}

// extractPhpFuncName extracts the function name from a PHP declaration line.
func extractPhpFuncName(line string) string {
	idx := strings.Index(line, "function ")
	if idx < 0 {
		return "anonymous"
	}

	rest := line[idx+len("function "):]
	if parenIdx := strings.Index(rest, "("); parenIdx > 0 {
		return strings.TrimSpace(rest[:parenIdx])
	}
	return "anonymous"
}

// isPhpBlankOrComment returns true if the line is empty or a PHP comment.
func isPhpBlankOrComment(trimmed string) bool {
	return trimmed == "" ||
		trimmed == "{" ||
		trimmed == "}" ||
		strings.HasPrefix(trimmed, "//") ||
		strings.HasPrefix(trimmed, "#") ||
		strings.HasPrefix(trimmed, "/*") ||
		strings.HasPrefix(trimmed, "*") ||
		strings.HasPrefix(trimmed, "*/")
}

// countBraces counts the net brace depth for a line.
func countBraces(line string) int {
	depth := 0
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

// updatePhpBraceDepth tracks brace nesting depth for PHP.
func updatePhpBraceDepth(line string, depth int) int {
	return depth + countBraces(line)
}

// buildPhpFuncSizeFinding constructs a finding for an oversized PHP function.
func buildPhpFuncSizeFinding(ctx engine.CheckContext, fn phpParsedFunction, maxLines int) engine.Finding {
	return engine.Finding{
		RuleId:     "php-func-size",
		RuleName:   "PHP Function Body Size",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       fn.StartLine,
		EndLine:    fn.EndLine,
		Message:    fmt.Sprintf("Function %q has %d body lines (max %d)", fn.Name, fn.BodyLines, maxLines),
		Suggestion: "Extract helper methods or delegate to traits/utility classes",
		Reference:  ctx.Spec.Reference,
	}
}
