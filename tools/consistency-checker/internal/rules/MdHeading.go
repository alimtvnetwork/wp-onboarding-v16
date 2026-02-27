// Package rules — Markdown single H1 heading checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// MdHeading checks that Markdown files contain exactly one H1 heading.
type MdHeading struct{}

// ID returns the rule identifier.
func (r *MdHeading) ID() string { return "md-heading" }

// Name returns the rule display name.
func (r *MdHeading) Name() string { return "Single H1 Heading" }

// Languages returns the languages this rule applies to.
func (r *MdHeading) Languages() []string { return []string{"md"} }

// Check scans Markdown lines for multiple H1 headings.
func (r *MdHeading) Check(ctx engine.CheckContext) []engine.Finding {
	h1Lines := findH1Lines(ctx.Lines)

	if len(h1Lines) <= 1 {
		return nil
	}

	return buildH1Findings(ctx, h1Lines)
}

// findH1Lines returns line numbers (1-indexed) of all H1 headings.
func findH1Lines(lines []string) []int {
	var h1s []int
	for i, line := range lines {
		if isH1Heading(line) {
			h1s = append(h1s, i+1)
		}
	}
	return h1s
}

// isH1Heading checks if a line is an ATX-style H1 (# Title).
func isH1Heading(line string) bool {
	trimmed := strings.TrimSpace(line)
	return strings.HasPrefix(trimmed, "# ") && !strings.HasPrefix(trimmed, "##")
}

// buildH1Findings creates findings for each extra H1 beyond the first.
func buildH1Findings(ctx engine.CheckContext, h1Lines []int) []engine.Finding {
	findings := make([]engine.Finding, 0, len(h1Lines)-1)
	for _, lineNum := range h1Lines[1:] {
		findings = append(findings, buildH1Finding(ctx, lineNum, len(h1Lines)))
	}
	return findings
}

// buildH1Finding constructs a finding for a duplicate H1.
func buildH1Finding(ctx engine.CheckContext, lineNum, total int) engine.Finding {
	return engine.Finding{
		RuleID:     "md-heading",
		RuleName:   "Single H1 Heading",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       lineNum,
		Message:    fmt.Sprintf("Document has %d H1 headings (expected 1)", total),
		Suggestion: "Use a single H1 for the document title; demote others to H2",
		Reference:  ctx.Spec.Reference,
	}
}
