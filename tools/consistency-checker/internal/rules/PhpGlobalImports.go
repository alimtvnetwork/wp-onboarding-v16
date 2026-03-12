// Package rules — PHP global class import checker.
package rules

import (
	"fmt"
	"regexp"
	"strings"

	"consistency-checker/internal/engine"
)

// PhpGlobalImports checks that namespaced PHP files import global classes
// they reference (e.g., SQLite3, PDO, Exception) via `use` statements.
type PhpGlobalImports struct{}

// Id returns the rule identifier.
func (r *PhpGlobalImports) Id() string { return "php-global-imports" }

// Name returns the rule display name.
func (r *PhpGlobalImports) Name() string { return "PHP Global Class Imports" }

// Languages returns the languages this rule applies to.
func (r *PhpGlobalImports) Languages() []string { return []string{"php"} }

// phpGlobalClasses lists global PHP/WordPress classes that require explicit import.
var phpGlobalClasses = []string{
	"Exception", "RuntimeException", "InvalidArgumentException", "LogicException",
	"BadMethodCallException", "OutOfRangeException", "OverflowException",
	"UnexpectedValueException", "LengthException", "DomainException",
	"Throwable", "Error", "TypeError", "ValueError",
	"PDO", "PDOException", "PDOStatement",
	"SQLite3", "SQLite3Result", "SQLite3Stmt",
	"DateTime", "DateTimeImmutable", "DateTimeInterface", "DateInterval", "DateTimeZone",
	"SplFileInfo", "SplFileObject", "SplTempFileObject",
	"ArrayObject", "ArrayIterator", "SplStack", "SplQueue", "SplPriorityQueue",
	"Generator", "Closure", "WeakMap", "WeakReference", "Fiber",
	"JsonSerializable", "Serializable", "Stringable", "Countable",
	"Iterator", "IteratorAggregate", "ArrayAccess",
	"ZipArchive", "CURLFile",
	"stdClass", "ReflectionClass", "ReflectionMethod",
	"WP_Error", "WP_REST_Response", "WP_REST_Request", "WP_REST_Server",
	"WP_Query", "WP_Post", "WP_User", "WP_Term", "WP_Comment",
	"WP_Filesystem_Base", "WP_Filesystem_Direct",
	"wpdb",
}

// namespacePattern matches a PHP namespace declaration.
var namespacePattern = regexp.MustCompile(`^\s*namespace\s+\S+`)

// Check analyzes a namespaced PHP file for unimported global class references.
func (r *PhpGlobalImports) Check(ctx engine.CheckContext) []engine.Finding {
	if !isNamespacedFile(ctx.Lines) {
		return nil
	}

	imports := collectUseImports(ctx.Lines)
	return scanForMissingImports(ctx, imports)
}

// isNamespacedFile returns true if the file contains a namespace declaration.
func isNamespacedFile(lines []string) bool {
	for _, line := range lines {
		if namespacePattern.MatchString(line) {
			return true
		}
	}
	return false
}

// collectUseImports gathers all imported class names from `use` statements.
func collectUseImports(lines []string) map[string]bool {
	imports := make(map[string]bool)

	for _, line := range lines {
		trimmed := strings.TrimSpace(line)
		if !strings.HasPrefix(trimmed, "use ") || !strings.HasSuffix(trimmed, ";") {
			continue
		}

		symbol := strings.TrimPrefix(trimmed, "use ")
		symbol = strings.TrimSuffix(symbol, ";")
		symbol = strings.TrimSpace(symbol)

		parts := strings.Split(symbol, "\\")
		className := parts[len(parts)-1]
		imports[className] = true
	}

	return imports
}

// scanForMissingImports checks each line for global class usage without import.
func scanForMissingImports(ctx engine.CheckContext, imports map[string]bool) []engine.Finding {
	var findings []engine.Finding
	reported := make(map[string]bool)

	for i, line := range ctx.Lines {
		trimmed := strings.TrimSpace(line)
		if isSkippableLine(trimmed) {
			continue
		}

		for _, cls := range phpGlobalClasses {
			if reported[cls] || imports[cls] {
				continue
			}
			if !referencesClass(trimmed, cls) {
				continue
			}

			reported[cls] = true
			findings = append(findings, buildGlobalImportFinding(ctx, cls, i+1))
		}
	}

	return findings
}

// isSkippableLine returns true for lines that should not be scanned.
func isSkippableLine(trimmed string) bool {
	return strings.HasPrefix(trimmed, "//") ||
		strings.HasPrefix(trimmed, "*") ||
		strings.HasPrefix(trimmed, "/*") ||
		strings.HasPrefix(trimmed, "use ") ||
		strings.HasPrefix(trimmed, "namespace ")
}

// referencesClass checks if a line uses the given class name in a meaningful context.
func referencesClass(line, cls string) bool {
	patterns := []string{
		"new " + cls,
		cls + "::",
		cls + " $",
		"catch (" + cls,
		"catch(" + cls,
		"instanceof " + cls,
		": " + cls,
		"(" + cls + " ",
		"?" + cls,
		"@param " + cls,
		"@return " + cls,
		"@throws " + cls,
		"@var " + cls,
	}

	for _, p := range patterns {
		if strings.Contains(line, p) {
			return true
		}
	}

	return false
}

// buildGlobalImportFinding constructs a finding for a missing global import.
func buildGlobalImportFinding(ctx engine.CheckContext, cls string, lineNum int) engine.Finding {
	return engine.Finding{
		RuleId:     "php-global-imports",
		RuleName:   "PHP Global Class Imports",
		Severity:   ctx.Spec.Severity,
		FilePath:   ctx.FilePath,
		Line:       lineNum,
		Message:    fmt.Sprintf("Global class %q used without a `use %s;` import", cls, cls),
		Suggestion: fmt.Sprintf("Add `use %s;` to the file-level imports", cls),
		Reference:  ctx.Spec.Reference,
	}
}
