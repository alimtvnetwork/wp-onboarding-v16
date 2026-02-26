package e2e

import (
	"context"
	"encoding/json"
	"time"

	"wp-plugin-publish/internal/enums/test_status"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// ListSuites returns all test suites with case counts.
func (s *serviceImpl) ListSuites(ctx context.Context) ([]TestSuite, error) {
	rows, err := s.db.QueryContext(ctx, suiteListQuery)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	return scanSuiteRows(rows)
}

// scanSuiteRows scans all suite rows from the result set.
func scanSuiteRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestSuite, error) {
	var suites []TestSuite
	for rows.Next() {
		var suite TestSuite
		err := rows.Scan(
			&suite.ID, &suite.Name, &suite.Category, &suite.Enabled,
			&suite.TimeoutSeconds, &suite.CreatedAt, &suite.CaseCount,
		)
		if err != nil {
			return nil, err
		}
		suites = append(suites, suite)
	}
	return suites, nil
}

// GetSuite returns a single test suite.
func (s *serviceImpl) GetSuite(ctx context.Context, id string) (*TestSuite, error) {
	var suite TestSuite
	err := s.db.QueryRowContext(ctx, suiteSelectQuery, id).Scan(
		&suite.ID, &suite.Name, &suite.Category, &suite.Enabled,
		&suite.TimeoutSeconds, &suite.CreatedAt, &suite.CaseCount,
	)
	if err != nil {
		return nil, err
	}
	return &suite, nil
}

// GetCases returns all test cases for a suite.
func (s *serviceImpl) GetCases(ctx context.Context, suiteID string) ([]TestCase, error) {
	rows, err := s.db.QueryContext(ctx, caseListQuery, suiteID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	return scanCaseRows(rows)
}

// scanCaseRows scans all case rows from the result set.
func scanCaseRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestCase, error) {
	var cases []TestCase
	for rows.Next() {
		tc, err := scanSingleCase(rows)
		if err != nil {
			return nil, err
		}
		cases = append(cases, tc)
	}
	return cases, nil
}

// scanSingleCase scans a single test case row.
func scanSingleCase(row interface{ Scan(dest ...any) error }) (TestCase, error) {
	var tc TestCase
	var preJSON, stepsJSON string

	err := row.Scan(
		&tc.ID, &tc.SuiteID, &tc.Name, &tc.Description, &preJSON, &stepsJSON,
		&tc.ExpectedResult, &tc.TimeoutSeconds, &tc.OrderIndex, &tc.Enabled,
	)
	if err != nil {
		return tc, err
	}

	json.Unmarshal([]byte(preJSON), &tc.Preconditions)
	json.Unmarshal([]byte(stepsJSON), &tc.Steps)
	return tc, nil
}

// AbortRun stops a running test.
func (s *serviceImpl) AbortRun(ctx context.Context, runID string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.activeRun == nil || s.activeRun.ID != runID {
		return apperror.New(apperror.ErrNotFound, "no active run with ID").WithRunId(runID)
	}

	s.abortActiveRun(ctx, runID)
	return nil
}

// abortActiveRun marks the active run as aborted. Must be called with mu held.
func (s *serviceImpl) abortActiveRun(ctx context.Context, runID string) {
	now := time.Now()
	s.activeRun.Status = teststatus.Aborted.String()
	s.activeRun.CompletedAt = &now

	s.db.ExecContext(ctx, runAbortQuery, now, runID)

	if s.broadcast != nil {
		s.broadcast("e2e:run:completed", ws.E2ERunCompletedData{
			RunID:  runID,
			Status: teststatus.Aborted.String(),
		})
	}

	s.activeRun = nil
}

// ListRuns returns past test runs.
func (s *serviceImpl) ListRuns(ctx context.Context, limit int) ([]TestRun, error) {
	if limit <= 0 {
		limit = 20
	}

	rows, err := s.db.QueryContext(ctx, runListQuery, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	return scanRunRows(rows)
}

// scanRunRows scans all run rows from the result set.
func scanRunRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestRun, error) {
	var runs []TestRun
	for rows.Next() {
		var run TestRun
		err := rows.Scan(
			&run.ID, &run.StartedAt, &run.CompletedAt, &run.Status,
			&run.TotalTests, &run.PassedTests, &run.FailedTests, &run.SkippedTests, &run.DurationMs,
		)
		if err != nil {
			return nil, err
		}
		runs = append(runs, run)
	}
	return runs, nil
}

// GetRun returns a test run with its results.
func (s *serviceImpl) GetRun(ctx context.Context, runID string) (*RunSummary, error) {
	run, err := s.loadRun(ctx, runID)
	if err != nil {
		return nil, err
	}

	results, err := s.loadRunResults(ctx, runID)
	if err != nil {
		return nil, err
	}

	return &RunSummary{Run: run, Results: results}, nil
}

// loadRun fetches a single run record by ID.
func (s *serviceImpl) loadRun(ctx context.Context, runID string) (*TestRun, error) {
	var run TestRun
	err := s.db.QueryRowContext(ctx, runSelectQuery, runID).Scan(
		&run.ID, &run.StartedAt, &run.CompletedAt, &run.Status,
		&run.TotalTests, &run.PassedTests, &run.FailedTests, &run.SkippedTests, &run.DurationMs,
	)
	if err != nil {
		return nil, err
	}
	return &run, nil
}

// loadRunResults fetches all results for a given run.
func (s *serviceImpl) loadRunResults(ctx context.Context, runID string) ([]TestResult, error) {
	rows, err := s.db.QueryContext(ctx, resultListQuery, runID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	return scanResultRows(rows)
}

// scanResultRows scans all result rows from the result set.
func scanResultRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestResult, error) {
	var results []TestResult
	for rows.Next() {
		var r TestResult
		err := rows.Scan(
			&r.ID, &r.RunID, &r.SuiteID, &r.CaseID, &r.CaseName, &r.Status,
			&r.StartedAt, &r.CompletedAt, &r.DurationMs, &r.ErrorMessage, &r.ErrorDetails,
			&r.RequestData, &r.ResponseData, &r.Logs,
		)
		if err != nil {
			return nil, err
		}
		results = append(results, r)
	}
	return results, nil
}

// DeleteRun removes a test run and its results.
func (s *serviceImpl) DeleteRun(ctx context.Context, runID string) error {
	s.db.ExecContext(ctx, resultDeleteQuery, runID)
	_, err := s.db.ExecContext(ctx, runDeleteQuery, runID)
	return err
}
