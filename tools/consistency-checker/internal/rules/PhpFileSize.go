// Package rules — PHP file size limit checker.
package rules

import (
	"fmt"

	"consistency-checker/internal/engine"
)

// PhpFileSize checks that PHP files do not exceed a maximum line count.
type PhpFileSize struct{}

// ID returns the rule identifier.
func (r *PhpFileSize) ID() string { return "php-file-size" }

// Name returns the rule display name.
func (r *PhpFileSize) Name() string { return "PHP File Size Limit" }

// Languages returns the languages this rule applies to.
func (r *PhpFileSize) Languages() []string { return []string{"php"} }

// Check analyzes a PHP file for line count violations.
func (r *PhpFileSize) Check(ctx engine.CheckContext) []engine.Finding {
	maxLines := ctx.Spec.ParamInt("max_lines", 500)
	lineCount := len(ctx.Lines)

	if lineCount <= maxLines {
		return nil
	}

	return []engine.Finding{buildPhpFileSizeFinding(ctx, lineCount, maxLines)}
}

// buildPhpFileSizeFinding constructs the finding for an oversized PHP file.
func buildPhpFileSizeFinding(ctx engine.CheckContext, lineCount, maxLines int) engine.Finding {
	return engine.Finding{
		RuleID:     "php-file-size",
		RuleName:   "PHP File Size Limit",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       1,
		EndLine:    lineCount,
		Message:    fmt.Sprintf("File has %d lines (max %d)", lineCount, maxLines),
		Suggestion: "Split into smaller, focused files or extract helper classes",
		Reference:  ctx.Spec.Reference,
	}
}
