// Package e2e provides end-to-end testing functionality
package e2e

import (
	"context"
	"time"

	"wp-plugin-publish/pkg/apperror"
)

// TestSuite represents a collection of related test cases
type TestSuite struct {
	Id             string
	Name           string
	Category       string
	Enabled        bool
	TimeoutSeconds int
	CaseCount      int
	CreatedAt      time.Time
}

// TestCase represents a single test case
type TestCase struct {
	Id             string
	SuiteId        string
	Name           string
	Description    string
	Preconditions  []string
	Steps          []string
	ExpectedResult string
	TimeoutSeconds int
	OrderIndex     int
	Enabled        bool
}

// TestRun represents a test execution session
type TestRun struct {
	Id           string
	StartedAt    time.Time
	CompletedAt  *time.Time `json:",omitempty"`
	Status       string
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
	Status       string
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
	Suites        []string
	IsParallel    bool
	StopOnFailure bool
}

// RunSummary provides summary of a completed test run
type RunSummary struct {
	Run     *TestRun
	Results []TestResult
}

// Service defines the E2E test service interface
type Service interface {
	ListSuites(ctx context.Context) ([]TestSuite, *apperror.AppError)
	GetSuite(ctx context.Context, id string) (*TestSuite, *apperror.AppError)
	GetCases(ctx context.Context, suiteId string) ([]TestCase, *apperror.AppError)
	StartRun(ctx context.Context, opts RunOptions) (*TestRun, *apperror.AppError)
	AbortRun(ctx context.Context, runId string) *apperror.AppError
	ListRuns(ctx context.Context, limit int) ([]TestRun, *apperror.AppError)
	GetRun(ctx context.Context, runId string) (*RunSummary, *apperror.AppError)
	DeleteRun(ctx context.Context, runId string) *apperror.AppError
}
