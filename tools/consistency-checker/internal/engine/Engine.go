// Package engine dispatches files to matching rules and collects findings.
package engine

import (
	"consistency-checker/internal/config"
	"consistency-checker/internal/scanner"
)

// Finding represents a single violation found by a rule.
type Finding struct {
	RuleID     string
	RuleName   string
	Severity   string
	FilePath   string
	Line       int
	EndLine    int
	Message    string
	Suggestion string
	Reference  string
	Context    string
}

// CheckContext provides file content and config to a rule checker.
type CheckContext struct {
	FilePath string
	Language string
	Lines    []string
	Content  []byte
	Spec     config.RuleSpec
}

// Rule is the interface all rule checkers must implement.
type Rule interface {
	ID() string
	Name() string
	Languages() []string
	Check(ctx CheckContext) []Finding
}

// Engine holds registered rules and executes them against scanned files.
type Engine struct {
	rules []Rule
}

// New creates a new Engine.
func New() *Engine {
	return &Engine{rules: make([]Rule, 0)}
}

// Register adds a rule to the engine.
func (e *Engine) Register(r Rule) {
	e.rules = append(e.rules, r)
}

// RunInput bundles parameters for Run.
type RunInput struct {
	Files       []scanner.ScannedFile
	EnabledRules []config.RuleSpec
}

// RunResult holds the output of an engine run.
type RunResult struct {
	Findings   []Finding
	FilesCount int
}
