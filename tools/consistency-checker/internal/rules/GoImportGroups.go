// Package rules — Go import grouping checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// GoImportGroups checks that Go imports are organized in 3 groups:
// stdlib, third-party, internal.
type GoImportGroups struct{}

// ID returns the rule identifier.
func (r *GoImportGroups) ID() string { return "go-import-groups" }

// Name returns the rule display name.
func (r *GoImportGroups) Name() string { return "Go Import Grouping" }

// Languages returns the languages this rule applies to.
func (r *GoImportGroups) Languages() []string { return []string{"go"} }

// Check verifies import blocks have proper group separation.
func (r *GoImportGroups) Check(ctx engine.CheckContext) []engine.Finding {
	importBlock := findImportBlock(ctx.Lines)
	if importBlock == nil {
		return nil
	}

	return validateImportGroups(ctx, importBlock)
}

// importBlock holds the location and content of an import block.
type importBlock struct {
	StartLine int
	Lines     []string
}

// findImportBlock locates the first multi-line import block.
func findImportBlock(lines []string) *importBlock {
	for i, line := range lines {
		trimmed := strings.TrimSpace(line)
		if trimmed == "import (" {
			return extractImportBlock(lines, i)
		}
	}
	return nil
}

// extractImportBlock reads from `import (` to the closing `)`.
func extractImportBlock(lines []string, start int) *importBlock {
	var blockLines []string
	for j := start + 1; j < len(lines); j++ {
		trimmed := strings.TrimSpace(lines[j])
		if trimmed == ")" {
			break
		}
		blockLines = append(blockLines, trimmed)
	}

	return &importBlock{StartLine: start + 1, Lines: blockLines}
}

// validateImportGroups checks that exactly 3 groups exist (separated by blanks).
func validateImportGroups(ctx engine.CheckContext, block *importBlock) []engine.Finding {
	groupCount := countImportGroups(block.Lines)
	expectedGroups := ctx.Spec.ParamInt("groups", 3)

	if groupCount == expectedGroups {
		return nil
	}

	return []engine.Finding{buildImportGroupFinding(ctx, block, groupCount, expectedGroups)}
}

// countImportGroups counts groups separated by blank lines.
func countImportGroups(lines []string) int {
	if len(lines) == 0 {
		return 0
	}
	groups := 1
	prevIsBlank := false

	for _, line := range lines {
		isBlank := line == ""
		if isBlank && !prevIsBlank {
			groups++
		}
		prevIsBlank = isBlank
	}
	return groups
}

// buildImportGroupFinding constructs a finding for wrong import grouping.
func buildImportGroupFinding(ctx engine.CheckContext, block *importBlock, actual, expected int) engine.Finding {
	return engine.Finding{
		RuleID:     "go-import-groups",
		RuleName:   "Go Import Grouping",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       block.StartLine,
		Message:    fmt.Sprintf("Import block has %d groups (expected %d: stdlib, third-party, internal)", actual, expected),
		Suggestion: "Organize imports into 3 groups separated by blank lines",
		Reference:  ctx.Spec.Reference,
	}
}
