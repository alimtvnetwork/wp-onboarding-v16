package e2e

import (
	"context"
	"time"

	"github.com/google/uuid"

	"wp-plugin-publish/internal/enums/test_status"
	"wp-plugin-publish/internal/ws"
)

// executeTest runs a single test case and returns the result.
func (s *serviceImpl) executeTest(ctx context.Context, run *TestRun, suite TestSuite, tc TestCase) *TestResult {
	result := s.initTestResult(run, suite, tc)
	s.broadcastTestStarted(run.ID, tc)

	testErr := s.dispatchTest(ctx, tc.ID, result)
	s.completeTestResult(result, testErr)

	return result
}

// initTestResult creates a new TestResult for the given case.
func (s *serviceImpl) initTestResult(run *TestRun, suite TestSuite, tc TestCase) *TestResult {
	return &TestResult{
		ID:        uuid.New().String(),
		RunID:     run.ID,
		SuiteID:   suite.ID,
		CaseID:    tc.ID,
		CaseName:  tc.Name,
		StartedAt: time.Now(),
	}
}

// broadcastTestStarted sends a test start event.
func (s *serviceImpl) broadcastTestStarted(runID string, tc TestCase) {
	if s.broadcast != nil {
		s.broadcast("e2e:test:started", ws.E2ETestStartedData{
			RunID:    runID,
			CaseID:   tc.ID,
			CaseName: tc.Name,
		})
	}
}

// dispatchTest routes a test case ID to its implementation.
func (s *serviceImpl) dispatchTest(ctx context.Context, caseID string, result *TestResult) error {
	switch caseID {
	case "TC-PLUGIN-001":
		return s.testRegisterPlugin(ctx, result)
	case "TC-PLUGIN-002":
		return s.testRegisterInvalidPath(ctx, result)
	case "TC-PLUGIN-003":
		return s.testUpdatePlugin(ctx, result)
	case "TC-PLUGIN-004":
		return s.testDeletePlugin(ctx, result)
	case "TC-PLUGIN-005":
		return s.testScanPluginFiles(ctx, result)
	case "TC-SITE-001":
		return s.testRegisterSite(ctx, result)
	case "TC-SITE-002":
		return s.testSiteConnection(ctx, result)
	case "TC-SITE-003":
		return s.testInvalidCredentials(ctx, result)
	case "TC-SITE-004":
		return s.testCreatePluginMapping(ctx, result)
	case "TC-SYNC-001":
		return s.testDetectNewFiles(ctx, result)
	case "TC-SYNC-006":
		return s.testBatchScanAll(ctx, result)
	case "TC-PUBLISH-001":
		return s.testPreviewPublish(ctx, result)
	case "TC-PUBLISH-003":
		return s.testBackupList(ctx, result)
	default:
		return s.skipUnimplemented(result, caseID)
	}
}

// skipUnimplemented marks a test as skipped (no implementation).
func (s *serviceImpl) skipUnimplemented(result *TestResult, caseID string) error {
	result.Status = teststatus.Skipped.String()
	result.Logs = "No test implementation for " + caseID
	now := time.Now()
	result.CompletedAt = &now
	result.DurationMs = now.Sub(result.StartedAt).Milliseconds()

	// Return a sentinel so the caller knows it's already finalized
	return nil
}

// completeTestResult finalizes timing and status on a result.
func (s *serviceImpl) completeTestResult(result *TestResult, testErr error) {
	// Already finalized by skipUnimplemented
	if result.Status == teststatus.Skipped.String() {
		return
	}

	now := time.Now()
	result.CompletedAt = &now
	result.DurationMs = now.Sub(result.StartedAt).Milliseconds()

	if testErr != nil {
		result.Status = teststatus.Failed.String()
		result.ErrorMessage = testErr.Error()
	} else {
		result.Status = teststatus.Passed.String()
	}
}
