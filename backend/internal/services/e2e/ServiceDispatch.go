package e2e

import (
	"context"
	"time"

	"github.com/google/uuid"

	"wp-plugin-publish/internal/enums/teststatustype"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// executeTest runs a single test case and returns the result.
func (s *serviceImpl) executeTest(ctx context.Context, run *TestRun, suite TestSuite, tc TestCase) *TestResult {
	result := s.initTestResult(run, suite, tc)
	s.broadcastTestStarted(run.Id, tc)

	testErr := s.dispatchTest(ctx, tc.Id, result)
	s.completeTestResult(result, testErr)

	return result
}

// initTestResult creates a new TestResult for the given case.
func (s *serviceImpl) initTestResult(run *TestRun, suite TestSuite, tc TestCase) *TestResult {
	return &TestResult{
		Id:        uuid.New().String(),
		RunId:     run.Id,
		SuiteId:   suite.Id,
		CaseId:    tc.Id,
		CaseName:  tc.Name,
		StartedAt: time.Now(),
	}
}

// broadcastTestStarted sends a test start event.
func (s *serviceImpl) broadcastTestStarted(runId string, tc TestCase) {
	if s.broadcast != nil {
		s.broadcast("e2e:test:started", ws.E2ETestStartedData{
			RunId:    runId,
			CaseId:   tc.Id,
			CaseName: tc.Name,
		})
	}
}

// dispatchTest routes a test case ID to its implementation.
func (s *serviceImpl) dispatchTest(ctx context.Context, caseId string, result *TestResult) *apperror.AppError {
	switch caseId {
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
		return s.skipUnimplemented(result, caseId)
	}
}

// skipUnimplemented marks a test as skipped (no implementation).
func (s *serviceImpl) skipUnimplemented(result *TestResult, caseId string) *apperror.AppError {
	result.Status = teststatustype.Skipped.String()
	result.Logs = "No test implementation for " + caseId
	now := time.Now()
	result.CompletedAt = &now
	result.DurationMs = now.Sub(result.StartedAt).Milliseconds()

	// Return nil so the caller knows it's already finalized
	return nil
}

// completeTestResult finalizes timing and status on a result.
func (s *serviceImpl) completeTestResult(result *TestResult, testErr *apperror.AppError) {
	// Already finalized by skipUnimplemented
	if result.Status == teststatustype.Skipped.String() {
		return
	}

	now := time.Now()
	result.CompletedAt = &now
	result.DurationMs = now.Sub(result.StartedAt).Milliseconds()

	if testErr != nil {
		result.Status = teststatustype.Failed.String()
		result.ErrorMessage = testErr.Error()
	} else {
		result.Status = teststatustype.Passed.String()
	}
}
