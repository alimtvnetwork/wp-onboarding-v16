// Package e2e provides end-to-end testing functionality
package e2e

import (
	"context"
	"time"
)

// TestSuite represents a collection of related test cases
type TestSuite struct {
	Id             string
	Name           string
	Category       string // plugin-crud, site-connections, sync-operations, publish-flow
	IsEnabled      bool
	TimeoutSeconds int
	CaseCount      int
	CreatedAt      time.Time
}

// TestCase represents a single test case
type TestCase struct {
	Id              string
	SuiteId         string
	Name            string
	Description     string
	Preconditions   []string
	Steps           []string
	ExpectedResult  string
	TimeoutSeconds  int
	OrderIndex      int
	IsEnabled       bool
}

// TestRun represents a test execution session
type TestRun struct {
	Id           string
	StartedAt    time.Time
	CompletedAt  *time.Time `json:",omitempty"`
	Status       string     // running, passed, failed, aborted
	TotalTests   int
	PassedTests  int
	FailedTests  int
	SkippedTests int
	DurationMs   int64
}

// TestResult represents the result of a single test case execution
type TestResult struct {
	Id           string
	RunId        string
	SuiteId      string
	CaseId       string
	CaseName     string
	Status       string     // passed, failed, skipped, error
	StartedAt    time.Time
	CompletedAt  *time.Time `json:",omitempty"`
	DurationMs   int64
	ErrorMessage string     `json:",omitempty"`
	ErrorDetails string     `json:",omitempty"`
	RequestData  string     `json:",omitempty"`
	ResponseData string     `json:",omitempty"`
	Logs         string     `json:",omitempty"`
}

// RunOptions configures a test run
type RunOptions struct {
	Suites        []string // Empty = run all
	IsParallel    bool     // Run suites in parallel
	StopOnFailure bool     // Stop on first failure
}

// RunSummary provides summary of a completed test run
type RunSummary struct {
	Run     *TestRun
	Results []TestResult
}

// Service defines the E2E test service interface
type Service interface {
	// Suite management
	ListSuites(ctx context.Context) ([]TestSuite, error)
	GetSuite(ctx context.Context, id string) (*TestSuite, error)
	GetCases(ctx context.Context, suiteId string) ([]TestCase, error)
	
	// Test execution
	StartRun(ctx context.Context, opts RunOptions) (*TestRun, error)
	AbortRun(ctx context.Context, runId string) error
	
	// Results
	ListRuns(ctx context.Context, limit int) ([]TestRun, error)
	GetRun(ctx context.Context, runId string) (*RunSummary, error)
	DeleteRun(ctx context.Context, runId string) error
}
