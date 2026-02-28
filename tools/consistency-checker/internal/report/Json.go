// Package report — JSON output for CI/CD integration.
package report

import (
	"encoding/json"
	"fmt"
	"os"

	"consistency-checker/internal/engine"
)

// jsonOutput is the top-level JSON report structure.
type jsonOutput struct {
	Summary  jsonSummary  `json:"summary"`
	Findings []jsonFinding `json:"findings"`
}

// jsonSummary holds aggregate counts for JSON output.
type jsonSummary struct {
	TotalFiles    int `json:"total_files"`
	TotalFindings int `json:"total_findings"`
	ErrorCount    int `json:"error_count"`
	WarningCount  int `json:"warning_count"`
	InfoCount     int `json:"info_count"`
	ExitCode      int `json:"exit_code"`
}

// jsonFinding represents a single violation in JSON output.
type jsonFinding struct {
	RuleId     string `json:"rule_id"`
	Severity   string `json:"severity"`
	File       string `json:"file"`
	Line       int    `json:"line"`
	EndLine    int    `json:"end_line,omitempty"`
	Message    string `json:"message"`
	Suggestion string `json:"suggestion,omitempty"`
	Reference  string `json:"reference,omitempty"`
}

// PrintJSON writes findings and summary as a JSON object to stdout.
func PrintJSON(findings []engine.Finding, summary Summary) {
	output := buildJSONOutput(findings, summary)

	data, err := json.MarshalIndent(output, "", "  ")
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: failed to marshal JSON: %v\n", err)
		return
	}

	fmt.Println(string(data))
}

// buildJSONOutput assembles the full JSON report.
func buildJSONOutput(findings []engine.Finding, summary Summary) jsonOutput {
	return jsonOutput{
		Summary:  toJSONSummary(summary),
		Findings: toJSONFindings(findings),
	}
}

// toJSONSummary converts a Summary to jsonSummary.
func toJSONSummary(s Summary) jsonSummary {
	return jsonSummary{
		TotalFiles:    s.TotalFiles,
		TotalFindings: s.TotalFindings,
		ErrorCount:    s.ErrorCount,
		WarningCount:  s.WarningCount,
		InfoCount:     s.InfoCount,
		ExitCode:      exitCodeFromSummary(s),
	}
}

// exitCodeFromSummary returns 1 if errors exist, 0 otherwise.
func exitCodeFromSummary(s Summary) int {
	if s.ErrorCount > 0 {
		return 1
	}
	return 0
}

// toJSONFindings converts engine findings to JSON findings.
func toJSONFindings(findings []engine.Finding) []jsonFinding {
	result := make([]jsonFinding, 0, len(findings))
	for _, f := range findings {
		result = append(result, toJSONFinding(f))
	}
	return result
}

// toJSONFinding converts a single engine Finding to jsonFinding.
func toJSONFinding(f engine.Finding) jsonFinding {
	return jsonFinding{
		RuleId:     f.RuleId,
		Severity:   f.Severity,
		File:       f.FilePath,
		Line:       f.Line,
		EndLine:    f.EndLine,
		Message:    f.Message,
		Suggestion: f.Suggestion,
		Reference:  f.Reference,
	}
}
