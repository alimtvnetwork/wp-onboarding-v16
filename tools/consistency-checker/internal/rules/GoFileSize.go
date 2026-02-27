// Package rules — Go file size limit checker.
package rules

import (
	"fmt"

	"consistency-checker/internal/engine"
)

// GoFileSize checks that Go files do not exceed a maximum line count.
type GoFileSize struct{}

// ID returns the rule identifier.
func (r *GoFileSize) ID() string { return "go-file-size" }

// Name returns the rule display name.
func (r *GoFileSize) Name() string { return "Go File Size Limit" }

// Languages returns the languages this rule applies to.
func (r *GoFileSize) Languages() []string { return []string{"go"} }

// Check analyzes a file for line count violations.
func (r *GoFileSize) Check(ctx engine.CheckContext) []engine.Finding {
	maxLines := ctx.Spec.ParamInt("max_lines", 300)
	lineCount := len(ctx.Lines)

	if lineCount <= maxLines {
		return nil
	}

	return []engine.Finding{buildFileSizeFinding(ctx, lineCount, maxLines)}
}

// buildFileSizeFinding constructs the finding for an oversized file.
func buildFileSizeFinding(ctx engine.CheckContext, lineCount, maxLines int) engine.Finding {
	return engine.Finding{
		RuleID:     "go-file-size",
		RuleName:   "Go File Size Limit",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       1,
		EndLine:    lineCount,
		Message:    fmt.Sprintf("File has %d lines (max %d)", lineCount, maxLines),
		Suggestion: "Split into smaller, focused files (e.g., Service.go + ServiceHelpers.go)",
		Reference:  ctx.Spec.Reference,
	}
}
