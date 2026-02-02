// Package e2e provides end-to-end testing functionality
package e2e

import (
	"context"
	"time"
)

// TestSuite represents a collection of related test cases
type TestSuite struct {
	ID             string    `json:"id"`
	Name           string    `json:"name"`
	Category       string    `json:"category"` // plugin-crud, site-connections, sync-operations, publish-flow
	Enabled        bool      `json:"enabled"`
	TimeoutSeconds int       `json:"timeoutSeconds"`
	CaseCount      int       `json:"caseCount"`
	CreatedAt      time.Time `json:"createdAt"`
}

// TestCase represents a single test case
type TestCase struct {
	ID              string   `json:"id"`
	SuiteID         string   `json:"suiteId"`
	Name            string   `json:"name"`
	Description     string   `json:"description"`
	Preconditions   []string `json:"preconditions"`
	Steps           []string `json:"steps"`
	ExpectedResult  string   `json:"expectedResult"`
	TimeoutSeconds  int      `json:"timeoutSeconds"`
	OrderIndex      int      `json:"orderIndex"`
	Enabled         bool     `json:"enabled"`
}

// TestRun represents a test execution session
type TestRun struct {
	ID           string     `json:"id"`
	StartedAt    time.Time  `json:"startedAt"`
	CompletedAt  *time.Time `json:"completedAt,omitempty"`
	Status       string     `json:"status"` // running, passed, failed, aborted
	TotalTests   int        `json:"totalTests"`
	PassedTests  int        `json:"passedTests"`
	FailedTests  int        `json:"failedTests"`
	SkippedTests int        `json:"skippedTests"`
	DurationMs   int64      `json:"durationMs"`
}

// TestResult represents the result of a single test case execution
type TestResult struct {
	ID           string     `json:"id"`
	RunID        string     `json:"runId"`
	SuiteID      string     `json:"suiteId"`
	CaseID       string     `json:"caseId"`
	CaseName     string     `json:"caseName"`
	Status       string     `json:"status"` // passed, failed, skipped, error
	StartedAt    time.Time  `json:"startedAt"`
	CompletedAt  *time.Time `json:"completedAt,omitempty"`
	DurationMs   int64      `json:"durationMs"`
	ErrorMessage string     `json:"errorMessage,omitempty"`
	ErrorDetails string     `json:"errorDetails,omitempty"`
	RequestData  string     `json:"requestData,omitempty"`
	ResponseData string     `json:"responseData,omitempty"`
	Logs         string     `json:"logs,omitempty"`
}

// RunOptions configures a test run
type RunOptions struct {
	Suites        []string `json:"suites"`        // Empty = run all
	Parallel      bool     `json:"parallel"`      // Run suites in parallel
	StopOnFailure bool     `json:"stopOnFailure"` // Stop on first failure
}

// RunSummary provides summary of a completed test run
type RunSummary struct {
	Run     *TestRun      `json:"run"`
	Results []TestResult  `json:"results"`
}

// Service defines the E2E test service interface
type Service interface {
	// Suite management
	ListSuites(ctx context.Context) ([]TestSuite, error)
	GetSuite(ctx context.Context, id string) (*TestSuite, error)
	GetCases(ctx context.Context, suiteID string) ([]TestCase, error)
	
	// Test execution
	StartRun(ctx context.Context, opts RunOptions) (*TestRun, error)
	AbortRun(ctx context.Context, runID string) error
	
	// Results
	ListRuns(ctx context.Context, limit int) ([]TestRun, error)
	GetRun(ctx context.Context, runID string) (*RunSummary, error)
	DeleteRun(ctx context.Context, runID string) error
}
