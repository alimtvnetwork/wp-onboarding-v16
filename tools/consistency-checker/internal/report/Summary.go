// Package report — summary statistics.
package report

import (
	"fmt"

	"consistency-checker/internal/engine"
)

// BuildSummary computes aggregate counts from findings.
func BuildSummary(findings []engine.Finding, filesCount int) Summary {
	summary := Summary{TotalFiles: filesCount, TotalFindings: len(findings)}

	for _, f := range findings {
		countBySeverity(&summary, f.Severity)
	}
	return summary
}

// countBySeverity increments the appropriate counter.
func countBySeverity(s *Summary, severity string) {
	switch severity {
	case "error":
		s.ErrorCount++
	case "warning":
		s.WarningCount++
	case "info":
		s.InfoCount++
	}
}

// PrintSummary prints the final summary to stdout.
func PrintSummary(s Summary) {
	fmt.Println()
	if s.TotalFindings == 0 {
		fmt.Printf("%s✓ All %d files passed consistency checks%s\n", colorGreen, s.TotalFiles, colorReset)
		return
	}

	printViolationSummary(s)
}

// printViolationSummary prints the breakdown of violations.
func printViolationSummary(s Summary) {
	fmt.Printf("%s✗ %d finding(s) in %d files%s\n", colorRed, s.TotalFindings, s.TotalFiles, colorReset)

	if s.ErrorCount > 0 {
		fmt.Printf("  %s● %d error(s)%s\n", colorRed, s.ErrorCount, colorReset)
	}
	if s.WarningCount > 0 {
		fmt.Printf("  %s● %d warning(s)%s\n", colorYellow, s.WarningCount, colorReset)
	}
	if s.InfoCount > 0 {
		fmt.Printf("  %s● %d info%s\n", colorCyan, s.InfoCount, colorReset)
	}
}
