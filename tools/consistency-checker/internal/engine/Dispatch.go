// Package engine — file dispatch and rule execution.
package engine

import (
	"os"
	"strings"

	"consistency-checker/internal/config"
	"consistency-checker/internal/scanner"
)

// Run executes all matching rules against the scanned files.
func (e *Engine) Run(input RunInput) RunResult {
	var allFindings []Finding

	for i := range input.Files {
		fileFindings := e.checkFile(&input.Files[i], input.EnabledRules)
		allFindings = append(allFindings, fileFindings...)
	}

	return RunResult{Findings: allFindings, FilesCount: len(input.Files)}
}

// checkFile runs all matching rules against a single file.
func (e *Engine) checkFile(file *scanner.ScannedFile, specs []config.RuleSpec) []Finding {
	content, lines := readFileContent(file.Path)
	file.Lines = lines

	var findings []Finding
	for _, spec := range specs {
		ruleFindings := e.checkRule(file, spec, content, lines)
		findings = append(findings, ruleFindings...)
	}
	return findings
}

// checkRule runs a single rule against a file if it matches.
func (e *Engine) checkRule(file *scanner.ScannedFile, spec config.RuleSpec, content []byte, lines []string) []Finding {
	rule := e.findRule(spec.ID)
	if rule == nil || !matchesLanguage(rule, file.Language) {
		return nil
	}
	if isFileExcluded(file.Path, spec.Exclude) {
		return nil
	}

	ctx := buildCheckContext(file, content, lines, spec)
	return rule.Check(ctx)
}

// findRule looks up a registered rule by ID.
func (e *Engine) findRule(id string) Rule {
	for _, r := range e.rules {
		if r.ID() == id {
			return r
		}
	}
	return nil
}

// matchesLanguage checks if a rule supports the file's language.
func matchesLanguage(rule Rule, language string) bool {
	for _, lang := range rule.Languages() {
		if lang == language || lang == "all" {
			return true
		}
	}
	return false
}

// isFileExcluded checks rule-specific exclusions.
func isFileExcluded(path string, excludes []string) bool {
	for _, pattern := range excludes {
		if scanner.IsExcluded(path, []string{pattern}) {
			return true
		}
	}
	return false
}

// readFileContent reads a file and splits into lines.
func readFileContent(path string) ([]byte, []string) {
	content, err := os.ReadFile(path)
	if err != nil {
		return nil, nil
	}
	return content, strings.Split(string(content), "\n")
}

// buildCheckContext constructs a CheckContext for rule execution.
func buildCheckContext(file *scanner.ScannedFile, content []byte, lines []string, spec config.RuleSpec) CheckContext {
	return CheckContext{
		FilePath: file.Path,
		Language: file.Language,
		Lines:    lines,
		Content:  content,
		Spec:     spec,
	}
}
