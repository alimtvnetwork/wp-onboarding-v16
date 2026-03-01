// Package rules — Go raw error return detector.
package rules

import (
	"fmt"
	"regexp"
	"strings"

	"consistency-checker/internal/engine"
)

// funcReturningError matches func signatures returning raw `error`.
// Captures: `func Name(...) error {` or `func Name(...) (T, error) {`
var funcReturningError = regexp.MustCompile(`^func\s`)
var rawErrorReturn = regexp.MustCompile(`\)\s+(error\s*\{|\([^)]*\berror\b[^)]*\)\s*\{)`)

// interfaceMethodPatterns are signatures that MUST return raw error (stdlib interfaces).
var interfaceMethodPatterns = []string{
	"UnmarshalJSON(",
	"MarshalJSON(",
	"MarshalText(",
	"UnmarshalText(",
	"MarshalBinary(",
	"UnmarshalBinary(",
	") Error()",
	") String()",
	"ServeHTTP(",
	") Start()",
	") Shutdown(",
	") Close() error",
}

// walkCallbackPattern matches filepath.Walk callback signatures.
var walkCallbackPattern = regexp.MustCompile(`func\(.*os\.FileInfo.*error\)\s*error`)

// scanFuncPattern matches generic scan helper signatures.
var scanFuncPattern = regexp.MustCompile(`func\(.*\.Scan\(`)

// GoRawError detects functions returning raw error instead of *apperror.AppError.
type GoRawError struct{}

// Id returns the rule identifier.
func (r *GoRawError) Id() string { return "go-raw-error" }

// Name returns the rule display name.
func (r *GoRawError) Name() string { return "Go Raw Error Return" }

// Languages returns the languages this rule applies to.
func (r *GoRawError) Languages() []string { return []string{"go"} }

// Check scans for functions returning raw error.
func (r *GoRawError) Check(ctx engine.CheckContext) []engine.Finding {
	var findings []engine.Finding

	isExemptPackage := isPackageExempt(ctx.FilePath)
	if isExemptPackage {
		return findings
	}

	for i, line := range ctx.Lines {
		trimmed := strings.TrimSpace(line)

		isNotFunc := !funcReturningError.MatchString(trimmed)
		if isNotFunc {
			continue
		}

		hasRawError := rawErrorReturn.MatchString(trimmed)
		if !hasRawError {
			continue
		}

		isExempt := isExemptSignature(trimmed)
		if isExempt {
			continue
		}

		lineNum := i + 1
		funcName := extractFuncName(trimmed)
		finding := engine.Finding{
			RuleId:     "go-raw-error",
			RuleName:   "Go Raw Error Return",
			Severity:   ctx.Spec.Severity,
			FilePath:   ctx.FilePath,
			Line:       lineNum,
			Message:    fmt.Sprintf("Function %q returns raw error; use *apperror.AppError or apperror.Result[T]", funcName),
			Suggestion: "Replace error return with *apperror.AppError and wrap errors with apperror.Wrap()",
			Reference:  ctx.Spec.Reference,
			Context:    trimmed,
		}
		findings = append(findings, finding)
	}

	return findings
}

// isPackageExempt returns true for packages that legitimately use raw error.
func isPackageExempt(filePath string) bool {
	exemptPaths := []string{
		"pkg/apperror/",
		"pkg/pathutil/",
		"internal/database/dbops/",
		"internal/enums/",
		"cmd/",
	}

	for _, exempt := range exemptPaths {
		if strings.Contains(filePath, exempt) {
			return true
		}
	}

	return false
}

// isExemptSignature checks if the function signature is an interface implementation
// or Walk callback that must return raw error.
func isExemptSignature(line string) bool {
	for _, pattern := range interfaceMethodPatterns {
		if strings.Contains(line, pattern) {
			return true
		}
	}

	isWalkCallback := walkCallbackPattern.MatchString(line)
	if isWalkCallback {
		return true
	}

	isScanHelper := scanFuncPattern.MatchString(line)
	if isScanHelper {
		return true
	}

	// Exempt: Parse() functions in enum packages (return Variant, error)
	isParseFunc := strings.Contains(line, "func Parse(")
	if isParseFunc {
		return true
	}

	// Exempt: functions returning filepath.SkipDir
	isSkipDirHelper := strings.Contains(line, "SkipDir") || strings.Contains(line, "skipDir")
	if isSkipDirHelper {
		return true
	}

	return false
}
