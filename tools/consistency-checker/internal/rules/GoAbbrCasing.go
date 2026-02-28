// Package rules — Go abbreviation casing checker.
package rules

import (
	"fmt"
	"regexp"
	"strings"

	"consistency-checker/internal/engine"
)

// allCapsAbbreviations lists abbreviations that must use PascalCase, not ALL-CAPS.
var allCapsAbbreviations = []string{"ID", "URL", "HTTP", "JSON", "API", "PHP"}

// abbrPattern matches ALL-CAPS abbreviations in Go identifiers (struct fields, func names, vars).
// It looks for the abbreviation preceded or followed by another letter/digit boundary.
var abbrPattern = buildAbbrPattern()

// buildAbbrPattern creates a compiled regex that matches any ALL-CAPS abbreviation
// in a Go identifier context (type/func/var/field declarations and struct literal keys).
func buildAbbrPattern() *regexp.Regexp {
	parts := make([]string, len(allCapsAbbreviations))
	for i, abbr := range allCapsAbbreviations {
		// Match the abbreviation when:
		// - preceded by a lowercase letter or digit (e.g., pluginID, siteURL)
		// - followed by an uppercase letter, digit, or end of word (e.g., IDConfig, URLs)
		// - at the start of a word in a declaration (e.g., ID int64)
		parts[i] = abbr
	}

	// Match identifiers containing ALL-CAPS abbreviations in declarations and struct fields.
	// Captures: the full identifier and the violating abbreviation.
	pattern := fmt.Sprintf(
		`\b([A-Za-z]*(?:%s)[A-Za-z0-9]*)\b`,
		strings.Join(parts, "|"),
	)

	return regexp.MustCompile(pattern)
}

// GoAbbrCasing checks that Go identifiers use PascalCase for abbreviations (Id, Url, Http, etc.)
// instead of ALL-CAPS (ID, URL, HTTP, etc.).
type GoAbbrCasing struct{}

// ID returns the rule identifier.
func (r *GoAbbrCasing) ID() string { return "go-abbr-casing" }

// Name returns the rule display name.
func (r *GoAbbrCasing) Name() string { return "Go Abbreviation Casing" }

// Languages returns the languages this rule applies to.
func (r *GoAbbrCasing) Languages() []string { return []string{"go"} }

// declarationPrefixes are the Go keywords that introduce identifiers to check.
var declarationPrefixes = []string{
	"type ", "func ", "var ", "const ",
}

// Check scans Go source lines for ALL-CAPS abbreviation violations.
func (r *GoAbbrCasing) Check(ctx engine.CheckContext) []engine.Finding {
	var findings []engine.Finding

	for i, line := range ctx.Lines {
		trimmed := strings.TrimSpace(line)

		if !isCheckableLine(trimmed) {
			continue
		}

		lineFindings := checkLineForViolations(ctx, trimmed, i+1)
		findings = append(findings, lineFindings...)
	}

	return findings
}

// isCheckableLine returns true if the line could contain an identifier declaration.
func isCheckableLine(trimmed string) bool {
	// Skip comments and blank lines
	isComment := strings.HasPrefix(trimmed, "//") || strings.HasPrefix(trimmed, "/*")
	isBlank := trimmed == ""

	if isComment || isBlank {
		return false
	}

	// Check declarations
	for _, prefix := range declarationPrefixes {
		if strings.HasPrefix(trimmed, prefix) {
			return true
		}
	}

	// Check struct field lines (indented identifier followed by type)
	isStructField := isStructFieldLine(trimmed)

	return isStructField
}

// isStructFieldLine detects struct field declarations (e.g., "PluginID int64").
func isStructFieldLine(line string) bool {
	// A struct field line starts with an uppercase letter and contains a type
	if len(line) == 0 {
		return false
	}

	firstChar := rune(line[0])
	startsWithUpper := firstChar >= 'A' && firstChar <= 'Z'

	if !startsWithUpper {
		return false
	}

	// Must have a space separating field name from type
	return strings.Contains(line, " ")
}

// checkLineForViolations checks a single line for ALL-CAPS abbreviation usage.
func checkLineForViolations(
	ctx engine.CheckContext,
	line string,
	lineNum int,
) []engine.Finding {
	var findings []engine.Finding

	matches := abbrPattern.FindAllString(line, -1)
	for _, match := range matches {
		abbr := findViolatingAbbr(match)
		if abbr == "" {
			continue
		}

		// Skip if the match is inside a string literal or log key
		isInString := isInsideStringLiteral(line, match)

		if isInString {
			continue
		}

		suggestion := buildAbbrSuggestion(match, abbr)
		finding := engine.Finding{
			RuleID:   "go-abbr-casing",
			RuleName: "Go Abbreviation Casing",
			Severity: ctx.Spec.Severity,
			FilePath: ctx.FilePath,
			Line:     lineNum,
			Message: fmt.Sprintf(
				"Identifier %q uses ALL-CAPS %q — use %q instead",
				match, abbr, suggestion,
			),
			Suggestion: fmt.Sprintf("Rename to %q", suggestion),
			Reference:  ctx.Spec.Reference,
			Context:    line,
		}

		findings = append(findings, finding)
	}

	return findings
}

// findViolatingAbbr returns the first ALL-CAPS abbreviation found in an identifier, or "".
func findViolatingAbbr(identifier string) string {
	for _, abbr := range allCapsAbbreviations {
		if !strings.Contains(identifier, abbr) {
			continue
		}

		// Ensure it's truly ALL-CAPS usage, not already PascalCase.
		// e.g., "PluginId" should NOT match, "PluginID" should.
		pascalForm := pascalAbbr(abbr)
		isAlreadyPascal := strings.Contains(identifier, pascalForm) &&
			!strings.Contains(identifier, abbr)

		if isAlreadyPascal {
			continue
		}

		return abbr
	}

	return ""
}

// pascalAbbr converts an ALL-CAPS abbreviation to PascalCase.
func pascalAbbr(abbr string) string {
	if len(abbr) <= 1 {
		return abbr
	}

	return string(abbr[0]) + strings.ToLower(abbr[1:])
}

// buildAbbrSuggestion replaces the ALL-CAPS abbreviation with PascalCase in the identifier.
func buildAbbrSuggestion(identifier, abbr string) string {
	return strings.ReplaceAll(identifier, abbr, pascalAbbr(abbr))
}

// isInsideStringLiteral checks if a match appears inside a quoted string on the line.
func isInsideStringLiteral(line, match string) bool {
	idx := strings.Index(line, match)
	if idx < 0 {
		return false
	}

	// Count unescaped quotes before the match position
	quoteCount := 0
	for i := 0; i < idx; i++ {
		if line[i] == '"' && (i == 0 || line[i-1] != '\\') {
			quoteCount++
		}
	}

	// Odd number of quotes means we're inside a string
	isInsideQuotes := quoteCount%2 == 1

	return isInsideQuotes
}
