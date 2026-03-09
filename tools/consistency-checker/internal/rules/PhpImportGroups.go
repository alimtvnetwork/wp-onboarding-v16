// Package rules — PHP import (use) grouping checker.
package rules

import (
	"fmt"
	"strings"

	"consistency-checker/internal/engine"
)

// PhpImportGroups checks that PHP `use` statements are organized in two groups:
// 1. Global/PHP built-in types (no namespace separator)
// 2. Plugin-namespaced imports (contain `\`)
// separated by exactly one blank line.
type PhpImportGroups struct{}

// Id returns the rule identifier.
func (r *PhpImportGroups) Id() string { return "php-import-groups" }

// Name returns the rule display name.
func (r *PhpImportGroups) Name() string { return "PHP Import Grouping" }

// Languages returns the languages this rule applies to.
func (r *PhpImportGroups) Languages() []string { return []string{"php"} }

// Check verifies that PHP use-statement blocks are properly grouped.
func (r *PhpImportGroups) Check(ctx engine.CheckContext) []engine.Finding {
	block := findPhpUseBlock(ctx.Lines)
	if block == nil {
		return nil
	}

	return validatePhpImportGroups(ctx, block)
}

// phpUseBlock holds the location and parsed imports of a use-statement block.
type phpUseBlock struct {
	StartLine int
	EndLine   int
	Imports   []phpImportLine
}

// phpImportLine represents a single `use` statement with classification.
type phpImportLine struct {
	LineNum    int
	Raw        string
	IsGlobal   bool
	IsBlank    bool
}

// findPhpUseBlock locates the contiguous block of `use` statements.
func findPhpUseBlock(lines []string) *phpUseBlock {
	var imports []phpImportLine
	startLine := 0

	for i, line := range lines {
		trimmed := strings.TrimSpace(line)

		isUseStatement := strings.HasPrefix(trimmed, "use ") && strings.HasSuffix(trimmed, ";")
		isBlankLine := trimmed == ""
		isInBlock := len(imports) > 0

		if isUseStatement {
			if !isInBlock {
				startLine = i + 1
			}
			imports = append(imports, classifyPhpImport(trimmed, i+1))
			continue
		}

		if isBlankLine && isInBlock {
			imports = append(imports, phpImportLine{LineNum: i + 1, IsBlank: true})
			continue
		}

		if isInBlock {
			break
		}
	}

	hasImports := len(imports) > 0
	if !hasImports {
		return nil
	}

	return &phpUseBlock{
		StartLine: startLine,
		EndLine:   imports[len(imports)-1].LineNum,
		Imports:   imports,
	}
}

// classifyPhpImport determines if a use statement imports a global or namespaced type.
func classifyPhpImport(trimmed string, lineNum int) phpImportLine {
	// Extract the imported symbol: `use Foo\Bar;` → `Foo\Bar`
	symbol := strings.TrimPrefix(trimmed, "use ")
	symbol = strings.TrimSuffix(symbol, ";")
	symbol = strings.TrimSpace(symbol)

	isGlobal := !strings.Contains(symbol, "\\")

	return phpImportLine{
		LineNum:  lineNum,
		Raw:      trimmed,
		IsGlobal: isGlobal,
	}
}

// validatePhpImportGroups checks ordering and grouping of imports.
func validatePhpImportGroups(ctx engine.CheckContext, block *phpUseBlock) []engine.Finding {
	var findings []engine.Finding

	orderFinding := checkPhpImportOrder(ctx, block)
	if orderFinding != nil {
		findings = append(findings, *orderFinding)
	}

	separatorFinding := checkPhpImportSeparator(ctx, block)
	if separatorFinding != nil {
		findings = append(findings, *separatorFinding)
	}

	return findings
}

// checkPhpImportOrder ensures globals come before namespaced imports.
func checkPhpImportOrder(ctx engine.CheckContext, block *phpUseBlock) *engine.Finding {
	seenNamespaced := false

	for _, imp := range block.Imports {
		if imp.IsBlank {
			continue
		}

		if !imp.IsGlobal {
			seenNamespaced = true
			continue
		}

		if imp.IsGlobal && seenNamespaced {
			return &engine.Finding{
				RuleId:     "php-import-groups",
				RuleName:   "PHP Import Grouping",
				Severity:   ctx.Spec.Severity,
				FilePath:   ctx.FilePath,
				Line:       imp.LineNum,
				Message:    fmt.Sprintf("Global import %q appears after namespaced imports", imp.Raw),
				Suggestion: "Place all global PHP imports (Throwable, PDO, etc.) before namespaced imports",
				Reference:  ctx.Spec.Reference,
			}
		}
	}

	return nil
}

// checkPhpImportSeparator ensures a blank line separates globals from namespaced imports.
func checkPhpImportSeparator(ctx engine.CheckContext, block *phpUseBlock) *engine.Finding {
	hasGlobals := false
	hasNamespaced := false

	for _, imp := range block.Imports {
		if imp.IsBlank {
			continue
		}
		if imp.IsGlobal {
			hasGlobals = true
		} else {
			hasNamespaced = true
		}
	}

	hasBothGroups := hasGlobals && hasNamespaced
	if !hasBothGroups {
		return nil
	}

	return verifyBlankLineBetweenGroups(ctx, block)
}

// verifyBlankLineBetweenGroups checks for exactly one blank line between groups.
func verifyBlankLineBetweenGroups(ctx engine.CheckContext, block *phpUseBlock) *engine.Finding {
	lastGlobalLine := 0
	firstNamespacedLine := 0

	for _, imp := range block.Imports {
		if imp.IsBlank {
			continue
		}
		if imp.IsGlobal {
			lastGlobalLine = imp.LineNum
		}
		if !imp.IsGlobal && firstNamespacedLine == 0 {
			firstNamespacedLine = imp.LineNum
		}
	}

	blanksBetween := countBlanksBetween(block.Imports, lastGlobalLine, firstNamespacedLine)
	isCorrectSeparation := blanksBetween == 1

	if isCorrectSeparation {
		return nil
	}

	return &engine.Finding{
		RuleId:     "php-import-groups",
		RuleName:   "PHP Import Grouping",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       firstNamespacedLine,
		Message:    fmt.Sprintf("Expected 1 blank line between global and namespaced imports (found %d)", blanksBetween),
		Suggestion: "Separate global PHP types from namespaced imports with exactly one blank line",
		Reference:  ctx.Spec.Reference,
	}
}

// countBlanksBetween counts blank lines between two line numbers in the import block.
func countBlanksBetween(imports []phpImportLine, afterLine, beforeLine int) int {
	count := 0

	for _, imp := range imports {
		isInRange := imp.LineNum > afterLine && imp.LineNum < beforeLine
		if isInRange && imp.IsBlank {
			count++
		}
	}

	return count
}
