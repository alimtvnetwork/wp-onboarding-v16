package e2e

import (
	"context"
	"encoding/json"
	"time"

	teststatus "wp-plugin-publish/internal/enums/teststatustype"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// ListSuites returns all test suites with case counts.
func (s *serviceImpl) ListSuites(ctx context.Context) ([]TestSuite, *apperror.AppError) {
	rows, err := s.db.QueryContext(ctx, suiteListQuery)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list test suites")
	}
	defer rows.Close()

	return scanSuiteRows(rows)
}

// scanSuiteRows scans all suite rows from the result set.
func scanSuiteRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestSuite, *apperror.AppError) {
	var suites []TestSuite
	for rows.Next() {
		var suite TestSuite
		err := rows.Scan(
			&suite.Id, &suite.Name, &suite.Category, &suite.Enabled,
			&suite.TimeoutSeconds, &suite.CreatedAt, &suite.CaseCount,
		)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan test suite row")
		}
		suites = append(suites, suite)
	}

	return suites, nil
}

// GetSuite returns a single test suite.
func (s *serviceImpl) GetSuite(ctx context.Context, id string) (*TestSuite, *apperror.AppError) {
	var suite TestSuite
	err := s.db.QueryRowContext(ctx, suiteSelectQuery, id).Scan(
		&suite.Id, &suite.Name, &suite.Category, &suite.Enabled,
		&suite.TimeoutSeconds, &suite.CreatedAt, &suite.CaseCount,
	)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to get test suite")
	}

	return &suite, nil
}

// GetCases returns all test cases for a suite.
func (s *serviceImpl) GetCases(ctx context.Context, suiteId string) ([]TestCase, *apperror.AppError) {
	rows, err := s.db.QueryContext(ctx, caseListQuery, suiteId)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list test cases")
	}
	defer rows.Close()

	return scanCaseRows(rows)
}

// scanCaseRows scans all case rows from the result set.
func scanCaseRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestCase, *apperror.AppError) {
	var cases []TestCase
	for rows.Next() {
		tc, appErr := scanSingleCase(rows)

		if appErr != nil {
			return nil, appErr
		}
		cases = append(cases, tc)
	}

	return cases, nil
}

// scanSingleCase scans a single test case row.
func scanSingleCase(row interface{ Scan(dest ...any) error }) (TestCase, *apperror.AppError) {
	var tc TestCase
	var preJson, stepsJson string

	err := row.Scan(
		&tc.Id, &tc.SuiteId, &tc.Name, &tc.Description, &preJson, &stepsJson,
		&tc.ExpectedResult, &tc.TimeoutSeconds, &tc.OrderIndex, &tc.Enabled,
	)

	if err != nil {
		return tc, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan test case row")
	}

	json.Unmarshal([]byte(preJson), &tc.Preconditions)
	json.Unmarshal([]byte(stepsJson), &tc.Steps)

	return tc, nil
}

// AbortRun stops a running test.
func (s *serviceImpl) AbortRun(ctx context.Context, runId string) *apperror.AppError {
	s.mu.Lock()
	defer s.mu.Unlock()

	isIdle := s.activeRun == nil
	isActive := s.activeRun != nil
	isRunMismatch := isActive && s.activeRun.Id != runId
	isAbortInvalid := isIdle || isRunMismatch

	if isAbortInvalid {
		return apperror.New(apperror.ErrNotFound, "no active run with ID").WithRunId(runId)
	}

	s.abortActiveRun(ctx, runId)

	return nil
}

// abortActiveRun marks the active run as aborted. Must be called with mu held.
func (s *serviceImpl) abortActiveRun(ctx context.Context, runId string) {
	now := time.Now()
	s.activeRun.Status = teststatus.Aborted.String()
	s.activeRun.CompletedAt = &now

	s.db.ExecContext(ctx, runAbortQuery, now, runId)

	if s.broadcast != nil {
		s.broadcast("e2e:run:completed", ws.E2ERunCompletedData{
			RunId:  runId,
			Status: teststatus.Aborted.String(),
		})
	}

	s.activeRun = nil
}

// ListRuns returns past test runs.
func (s *serviceImpl) ListRuns(ctx context.Context, limit int) ([]TestRun, *apperror.AppError) {
	isLimitUnset := limit <= 0

	if isLimitUnset {
		limit = 20
	}

	rows, err := s.db.QueryContext(ctx, runListQuery, limit)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to list test runs")
	}
	defer rows.Close()

	return scanRunRows(rows)
}

// scanRunRows scans all run rows from the result set.
func scanRunRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestRun, *apperror.AppError) {
	var runs []TestRun
	for rows.Next() {
		var run TestRun
		err := rows.Scan(
			&run.Id, &run.StartedAt, &run.CompletedAt, &run.Status,
			&run.TotalTests, &run.PassedTests, &run.FailedTests, &run.SkippedTests, &run.DurationMs,
		)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan test run row")
		}
		runs = append(runs, run)
	}

	return runs, nil
}

// GetRun returns a test run with its results.
func (s *serviceImpl) GetRun(ctx context.Context, runId string) (*RunSummary, *apperror.AppError) {
	run, appErr := s.loadRun(ctx, runId)

	if appErr != nil {
		return nil, appErr
	}

	results, appErr := s.loadRunResults(ctx, runId)

	if appErr != nil {
		return nil, appErr
	}

	return &RunSummary{Run: run, Results: results}, nil
}

// loadRun fetches a single run record by ID.
func (s *serviceImpl) loadRun(ctx context.Context, runId string) (*TestRun, *apperror.AppError) {
	var run TestRun
	err := s.db.QueryRowContext(ctx, runSelectQuery, runId).Scan(
		&run.Id, &run.StartedAt, &run.CompletedAt, &run.Status,
		&run.TotalTests, &run.PassedTests, &run.FailedTests, &run.SkippedTests, &run.DurationMs,
	)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to load test run")
	}

	return &run, nil
}

// loadRunResults fetches all results for a given run.
func (s *serviceImpl) loadRunResults(ctx context.Context, runId string) ([]TestResult, *apperror.AppError) {
	rows, err := s.db.QueryContext(ctx, resultListQuery, runId)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "failed to load test run results")
	}
	defer rows.Close()

	return scanResultRows(rows)
}

// scanResultRows scans all result rows from the result set.
func scanResultRows(rows interface {
	Next() bool
	Scan(dest ...any) error
}) ([]TestResult, *apperror.AppError) {
	var results []TestResult
	for rows.Next() {
		var r TestResult
		err := rows.Scan(
			&r.Id, &r.RunId, &r.SuiteId, &r.CaseId, &r.CaseName, &r.Status,
			&r.StartedAt, &r.CompletedAt, &r.DurationMs, &r.ErrorMessage, &r.ErrorDetails,
			&r.RequestData, &r.ResponseData, &r.Logs,
		)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrDatabaseScan, "failed to scan test result row")
		}
		results = append(results, r)
	}

	return results, nil
}

// DeleteRun removes a test run and its results.
func (s *serviceImpl) DeleteRun(ctx context.Context, runId string) *apperror.AppError {
	s.db.ExecContext(ctx, resultDeleteQuery, runId)

	_, err := s.db.ExecContext(ctx, runDeleteQuery, runId)

	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabaseDelete, "failed to delete test run")
	}

	return nil
}
