// Package rules — Go parameter count checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// GoParamCount checks that Go functions have at most N parameters
// (excluding context.Context).
type GoParamCount struct{}

// ID returns the rule identifier.
func (r *GoParamCount) ID() string { return "go-param-count" }

// Name returns the rule display name.
func (r *GoParamCount) Name() string { return "Go Parameter Count" }

// Languages returns the languages this rule applies to.
func (r *GoParamCount) Languages() []string { return []string{"go"} }

// Check scans function signatures for excessive parameter counts.
func (r *GoParamCount) Check(ctx engine.CheckContext) []engine.Finding {
	maxParams := ctx.Spec.ParamInt("max_params", 3)
	var findings []engine.Finding

	for i, line := range ctx.Lines {
		trimmed := strings.TrimSpace(line)
		if !isFuncDeclaration(trimmed) {
			continue
		}

		if finding := checkParamCount(ctx, trimmed, i+1, maxParams); finding != nil {
			findings = append(findings, *finding)
		}
	}
	return findings
}

// checkParamCount evaluates a single function's parameter count.
func checkParamCount(ctx engine.CheckContext, line string, lineNum, maxParams int) *engine.Finding {
	params := extractParams(line)
	filtered := filterContextParam(params)

	if len(filtered) <= maxParams {
		return nil
	}

	finding := buildParamCountFinding(ctx, line, lineNum, len(filtered), maxParams)
	return &finding
}

// extractParams extracts parameter names from a func signature.
func extractParams(line string) []string {
	openIdx := strings.Index(line, "(")
	if openIdx < 0 {
		return nil
	}

	inner := extractInnerParams(line, openIdx)
	if inner == "" {
		return nil
	}

	return splitParams(inner)
}

// extractInnerParams gets the content between the first pair of parens.
func extractInnerParams(line string, openIdx int) string {
	depth := 0
	for i := openIdx; i < len(line); i++ {
		switch line[i] {
		case '(':
			depth++
		case ')':
			depth--
			if depth == 0 {
				return strings.TrimSpace(line[openIdx+1 : i])
			}
		}
	}
	return ""
}

// splitParams splits a parameter string by commas.
func splitParams(inner string) []string {
	if inner == "" {
		return nil
	}
	parts := strings.Split(inner, ",")
	result := make([]string, 0, len(parts))

	for _, p := range parts {
		p = strings.TrimSpace(p)
		if p != "" {
			result = append(result, p)
		}
	}
	return result
}

// filterContextParam removes context.Context from the param list.
func filterContextParam(params []string) []string {
	filtered := make([]string, 0, len(params))
	for _, p := range params {
		if !strings.Contains(p, "context.Context") && !strings.Contains(p, "ctx context") {
			filtered = append(filtered, p)
		}
	}
	return filtered
}

// buildParamCountFinding constructs a finding for too many parameters.
func buildParamCountFinding(ctx engine.CheckContext, line string, lineNum, count, max int) engine.Finding {
	funcName := extractFuncName(line)
	return engine.Finding{
		RuleID:     "go-param-count",
		RuleName:   "Go Parameter Count",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       lineNum,
		Message:    fmt.Sprintf("Function %q has %d parameters (max %d, excluding context.Context)", funcName, count, max),
		Suggestion: "Bundle parameters into an input struct",
		Reference:  ctx.Spec.Reference,
	}
}
