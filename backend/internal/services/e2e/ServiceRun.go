package e2e

import (
	"context"
	"fmt"
	"time"

	"github.com/google/uuid"

	teststatus "wp-plugin-publish/internal/enums/teststatustype"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// StartRun begins a new test run.
func (s *serviceImpl) StartRun(ctx context.Context, opts RunOptions) (*TestRun, error) {
	err := s.checkNoActiveRun()

	if err != nil {
		return nil, err
	}

	suitesToRun, err := s.resolveSuites(ctx, opts)
	if err != nil {
		return nil, err
	}

	run := s.createRunRecord(ctx, suitesToRun)
	isRunMissing := run == nil

	if isRunMissing {
		return nil, fmt.Errorf("failed to create run record")
	}

	s.activateRun(run)
	go s.executeRun(run, suitesToRun, opts)

	return run, nil
}

// checkNoActiveRun returns an error if a run is already in progress.
func (s *serviceImpl) checkNoActiveRun() error {
	s.mu.Lock()
	defer s.mu.Unlock()

	hasActiveRun := s.activeRun != nil
	isRunning := hasActiveRun && s.activeRun.Status == teststatus.Running.String()

	if isRunning {
		return apperror.New(apperror.ErrE2ERunning, "test run already in progress").
			WithRunId(s.activeRun.Id)
	}
	return nil
}

// resolveSuites filters enabled suites by opts.Suites (or all if empty).
func (s *serviceImpl) resolveSuites(ctx context.Context, opts RunOptions) ([]TestSuite, error) {
	suites, err := s.ListSuites(ctx)
	if err != nil {
		return nil, err
	}

	hasSuiteFilter := len(opts.Suites) > 0

	if hasSuiteFilter {
		return filterSuitesByIds(suites, opts.Suites), nil
	}
	return filterEnabledSuites(suites), nil
}

// filterSuitesByIds returns enabled suites matching the given IDs.
func filterSuitesByIds(suites []TestSuite, ids []string) []TestSuite {
	idMap := make(map[string]bool, len(ids))
	for _, id := range ids {
		idMap[id] = true
	}

	var out []TestSuite
	for _, s := range suites {
		if idMap[s.Id] && s.Enabled {
			out = append(out, s)
		}
	}
	return out
}

// filterEnabledSuites returns only enabled suites.
func filterEnabledSuites(suites []TestSuite) []TestSuite {
	var out []TestSuite
	for _, s := range suites {
		if s.Enabled {
			out = append(out, s)
		}
	}
	return out
}

// createRunRecord inserts a new TestRun and returns it.
func (s *serviceImpl) createRunRecord(ctx context.Context, suites []TestSuite) *TestRun {
	totalTests := 0
	for _, suite := range suites {
		totalTests += suite.CaseCount
	}

	run := &TestRun{
		Id:         fmt.Sprintf("run-%s", uuid.New().String()[:8]),
		StartedAt:  time.Now(),
		Status:     teststatus.Running.String(),
		TotalTests: totalTests,
	}

	_, err := s.db.ExecContext(ctx, runInsertQuery, run.Id, run.StartedAt, run.Status, run.TotalTests)
	if err != nil {
		return nil
	}
	return run
}

// activateRun sets the run as active and broadcasts the start event.
func (s *serviceImpl) activateRun(run *TestRun) {
	s.mu.Lock()
	s.activeRun = run
	s.mu.Unlock()

	if s.broadcast != nil {
		s.broadcast("e2e:run:started", ws.E2ERunStartedData{
			RunId:      run.Id,
			TotalTests: run.TotalTests,
		})
	}
}

// executeRun runs all test cases across suites.
func (s *serviceImpl) executeRun(run *TestRun, suites []TestSuite, opts RunOptions) {
	ctx := context.Background()
	defer s.runCleanup()

	s.runSuites(ctx, run, suites, opts)
	s.finalizeRun(run)
}

// runSuites iterates suites and cases, executing each test.
func (s *serviceImpl) runSuites(
	ctx context.Context,
	run *TestRun,
	suites []TestSuite,
	opts RunOptions,
) {
	for _, suite := range suites {
		if s.runSuiteCases(ctx, run, suite, opts) {
			return
		}
	}
}

// runSuiteCases executes cases in a suite. Returns true if run should stop.
func (s *serviceImpl) runSuiteCases(
	ctx context.Context,
	run *TestRun,
	suite TestSuite,
	opts RunOptions,
) bool {
	cases, err := s.GetCases(ctx, suite.Id)
	if err != nil {
		return false
	}

	for _, tc := range cases {
		shouldStop := s.runSingleCase(ctx, RunSingleCaseInput{Run: run, Suite: suite, Case: tc, Opts: opts})
		if shouldStop {
			return true
		}
	}
	return false
}

// RunSingleCaseInput bundles parameters for runSingleCase.
type RunSingleCaseInput struct {
	Run   *TestRun
	Suite TestSuite
	Case  TestCase
	Opts  RunOptions
}

// runSingleCase executes one test case. Returns true if run should stop.
func (s *serviceImpl) runSingleCase(ctx context.Context, input RunSingleCaseInput) bool {
	isDisabled := !input.Case.Enabled

	if isDisabled {
		input.Run.SkippedTests++
		return false
	}

	if s.isRunAborted() {
		return true
	}

	result := s.executeTest(ctx, input.Run, input.Suite, input.Case)
	s.persistResult(result)
	s.tallyResult(input.Run, result)
	s.broadcastTestResult(input.Run.Id, input.Case.Id, result)

	isFailed := result.Status == teststatus.Failed.String()
	shouldStop := input.Opts.StopOnFailure && isFailed

	return shouldStop
}

// isRunAborted checks whether the active run has been aborted.
func (s *serviceImpl) isRunAborted() bool {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.activeRun == nil || s.activeRun.Status == teststatus.Aborted.String()
}

// persistResult inserts a test result into the database.
func (s *serviceImpl) persistResult(r *TestResult) {
	s.db.Exec(
		resultInsertQuery,
		r.Id, r.RunId, r.SuiteId, r.CaseId, r.CaseName, r.Status,
		r.StartedAt, r.CompletedAt, r.DurationMs, r.ErrorMessage, r.ErrorDetails,
		r.RequestData, r.ResponseData, r.Logs,
	)
}

// tallyResult updates pass/fail/skip counters on the run.
func (s *serviceImpl) tallyResult(run *TestRun, r *TestResult) {
	switch r.Status {
	case teststatus.Passed.String():
		run.PassedTests++
	case teststatus.Failed.String():
		run.FailedTests++
	case teststatus.Skipped.String():
		run.SkippedTests++
	}
}

// broadcastTestResult sends a test completion event.
func (s *serviceImpl) broadcastTestResult(runId, caseId string, r *TestResult) {
	if s.broadcast != nil {
		s.broadcast("e2e:test:completed", ws.E2ETestCompletedData{
			RunId:      runId,
			CaseId:     caseId,
			Status:     r.Status,
			DurationMs: r.DurationMs,
		})
	}
}

// finalizeRun marks the run complete and broadcasts.
func (s *serviceImpl) finalizeRun(run *TestRun) {
	now := time.Now()
	run.CompletedAt = &now
	run.DurationMs = now.Sub(run.StartedAt).Milliseconds()
	run.Status = s.resolveRunStatus(run)

	s.db.Exec(runCompleteQuery, run.CompletedAt, run.Status, run.PassedTests, run.FailedTests, run.SkippedTests, run.DurationMs, run.Id)
	s.broadcastRunComplete(run)

	s.mu.Lock()
	s.activeRun = nil
	s.mu.Unlock()
}

// resolveRunStatus returns "Failed" if any test failed, else "Passed".
func (s *serviceImpl) resolveRunStatus(run *TestRun) string {
	hasFailedTests := run.FailedTests > 0

	if hasFailedTests {
		return teststatus.Failed.String()
	}
	return teststatus.Passed.String()
}

// broadcastRunComplete sends the run completion event.
func (s *serviceImpl) broadcastRunComplete(run *TestRun) {
	if s.broadcast != nil {
		s.broadcast("e2e:run:completed", ws.E2ERunCompletedData{
			RunId:  run.Id,
			Status: run.Status,
			Passed: run.PassedTests,
			Failed: run.FailedTests,
		})
	}
}
