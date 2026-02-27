// Package report formats and prints findings to stdout.
package report

import (
	"fmt"

	"consistency-checker/internal/engine"
)

// ANSI color codes.
const (
	colorRed    = "\033[0;31m"
	colorYellow = "\033[0;33m"
	colorGreen  = "\033[0;32m"
	colorCyan   = "\033[0;36m"
	colorReset  = "\033[0m"
)

// Summary holds aggregate counts for a run.
type Summary struct {
	TotalFiles    int
	TotalFindings int
	ErrorCount    int
	WarningCount  int
	InfoCount     int
}

// PrintFindings prints all findings to stdout with color.
func PrintFindings(findings []engine.Finding) {
	for _, f := range findings {
		printFinding(f)
	}
}

// printFinding prints a single finding with severity color.
func printFinding(f engine.Finding) {
	color := severityColor(f.Severity)
	fmt.Printf("  %s%s%s %s:%d — %s\n", color, f.Severity, colorReset, f.FilePath, f.Line, f.Message)

	if f.Suggestion != "" {
		fmt.Printf("    💡 %s\n", f.Suggestion)
	}
	if f.Reference != "" {
		fmt.Printf("    📖 %s\n", f.Reference)
	}
}

// severityColor returns the ANSI color for a severity level.
func severityColor(severity string) string {
	switch severity {
	case "error":
		return colorRed
	case "warning":
		return colorYellow
	case "info":
		return colorCyan
	default:
		return colorReset
	}
}
