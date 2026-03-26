// Package apperror — AI-friendly clipboard formatting for structured errors.
package apperror

import (
	"fmt"
	"strings"
)

// ToClipboard formats the error for AI-friendly copy-paste.
func (e *AppError) ToClipboard() string {
	var b strings.Builder

	b.WriteString("## Error Report\n\n")
	writeCodeAndMessage(&b, e)
	writeDetailsSection(&b, e)
	writeValuesSection(&b, e)
	writeLocationSection(&b, e)
	writeDiagnosticSection(&b, e)
	writeStackSection(&b, e)

	return b.String()
}

// writeCodeAndMessage writes the code and message header.
func writeCodeAndMessage(b *strings.Builder, e *AppError) {
	b.WriteString(fmt.Sprintf("**Code:** %s\n", e.Code.String()))
	b.WriteString(fmt.Sprintf("**Message:** %s\n", e.Message))
}

// writeDetailsSection writes the details line if present.
func writeDetailsSection(b *strings.Builder, e *AppError) {
	if e.Details == "" {
		return
	}

	b.WriteString(fmt.Sprintf("**Details:** %s\n", e.Details))
}

// writeValuesSection writes injected variable values if present.
func writeValuesSection(b *strings.Builder, e *AppError) {
	hasValues := e.HasValues()

	if !hasValues {
		return
	}

	b.WriteString("\n**Values:**\n```\n")
	for k, v := range e.Values {
		b.WriteString(fmt.Sprintf("  %s: %s\n", k, v))
	}

	b.WriteString("```\n")
}

// writeLocationSection writes the caller location from the stack.
func writeLocationSection(b *strings.Builder, e *AppError) {
	if e.Stack.IsEmpty() {
		return
	}

	top := e.Stack.Frames[0]
	b.WriteString(fmt.Sprintf("**Location:** %s:%d (%s)\n", top.File, top.Line, top.Function))
}

// writeDiagnosticSection writes diagnostic fields if present.
func writeDiagnosticSection(b *strings.Builder, e *AppError) {
	hasDiagnostics := e.Diagnostic.HasFields()

	if !hasDiagnostics {
		return
	}

	b.WriteString("\n**Diagnostic:**\n```\n")
	b.WriteString(formatDiagnostic(e.Diagnostic))
	b.WriteString("```\n")
}

// writeStackSection writes the full stack trace if present.
func writeStackSection(b *strings.Builder, e *AppError) {
	if e.Stack.IsEmpty() {
		return
	}

	b.WriteString("\n**Stack Trace:**\n```\n")
	b.WriteString(e.Stack.String())
	b.WriteString("```\n")
}
