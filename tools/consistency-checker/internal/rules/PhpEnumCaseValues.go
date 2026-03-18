// Package rules — PHP enum case value checker.
package rules

import (
	"fmt"
	"regexp"

	"consistency-checker/internal/engine"
)

// PhpEnumCaseValues checks that PHP enum case values are PascalCase strings.
// Example valid:   case Success = 'Success';
// Example invalid: case success = 'success';  or  case SUCCESS = 'SUCCESS';
type PhpEnumCaseValues struct{}

// Id returns the rule identifier.
func (r *PhpEnumCaseValues) Id() string { return "php-enum-case-values" }

// Name returns the rule display name.
func (r *PhpEnumCaseValues) Name() string { return "PHP Enum Case Values" }

// Languages returns the languages this rule applies to.
func (r *PhpEnumCaseValues) Languages() []string { return []string{"php"} }

// phpEnumCasePattern matches `case Foo = 'Bar'` or `case Foo = "Bar"`.
var phpEnumCasePattern = regexp.MustCompile(`^\s*case\s+(\w+)\s*=\s*['"](\w+)['"]\s*;`)

// Check validates that all PHP enum case values are PascalCase.
func (r *PhpEnumCaseValues) Check(ctx engine.CheckContext) []engine.Finding {
	if !fileContainsEnum(ctx.Lines) {
		return nil
	}

	var findings []engine.Finding
	for i, line := range ctx.Lines {
		f := checkCaseValue(ctx, line, i+1)
		if f != nil {
			findings = append(findings, *f)
		}
	}
	return findings
}

// fileContainsEnum checks whether the file has an enum declaration.
func fileContainsEnum(lines []string) bool {
	for _, line := range lines {
		if phpEnumDeclPattern.MatchString(line) {
			return true
		}
	}
	return false
}

// checkCaseValue validates a single enum case line.
func checkCaseValue(ctx engine.CheckContext, line string, lineNum int) *engine.Finding {
	matches := phpEnumCasePattern.FindStringSubmatch(line)
	if matches == nil {
		return nil
	}

	caseName := matches[1]
	caseValue := matches[2]

	if isPascalCase(caseValue) {
		return nil
	}

	return &engine.Finding{
		RuleId:     "php-enum-case-values",
		RuleName:   "PHP Enum Case Values",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       lineNum,
		Message:    fmt.Sprintf("Enum case %s has non-PascalCase value %q", caseName, caseValue),
		Suggestion: fmt.Sprintf("Change value to PascalCase (e.g., '%s')", toPascalSuggestion(caseValue)),
		Reference:  ctx.Spec.Reference,
	}
}

// toPascalSuggestion returns a simple PascalCase suggestion by uppercasing the first letter.
func toPascalSuggestion(s string) string {
	if len(s) == 0 {
		return s
	}
	first := s[0]
	if first >= 'a' && first <= 'z' {
		return string(first-32) + s[1:]
	}
	return s
}
