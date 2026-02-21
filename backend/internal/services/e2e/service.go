// Package e2e implements end-to-end testing against real WordPress sites
package e2e

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"sync"
	"time"

	"github.com/google/uuid"

	teststatus "wp-plugin-publish/internal/enums/test_status"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// Config holds configuration for the E2E test service
type Config struct {
	DB               *sql.DB
	Broadcast        func(event string, data any)
	BaseURL          string // Backend API base URL (e.g. "http://localhost:8080")
	TestPluginPath   string // Local path to a test plugin directory
	TestSiteURL      string // WordPress test site URL
	TestSiteUsername  string // WordPress test site username
	TestSitePassword string // WordPress test site password
}

// serviceImpl implements the E2E Service interface
type serviceImpl struct {
	db               *sql.DB
	mu               sync.RWMutex
	activeRun        *TestRun
	broadcast        func(event string, data any)
	api              *apiClient
	testPluginPath   string
	testSiteURL      string
	testSiteUsername  string
	testSitePassword string
	cleanupIDs       map[string][]int64
}

// New creates a new E2E test service
func New(cfg Config) Service {
	svc := &serviceImpl{
		db:               cfg.DB,
		broadcast:        cfg.Broadcast,
		api:              newAPIClient(cfg.BaseURL),
		testPluginPath:   cfg.TestPluginPath,
		testSiteURL:      cfg.TestSiteURL,
		testSiteUsername:  cfg.TestSiteUsername,
		testSitePassword: cfg.TestSitePassword,
	}
	svc.initSchema()
	svc.seedTestSuites()
	return svc
}

func (s *serviceImpl) initSchema() error {
	s.migrateToPascalCase()

	schema := `
		CREATE TABLE IF NOT EXISTS TestSuites (
			Id TEXT PRIMARY KEY,
			Name TEXT NOT NULL,
			Category TEXT NOT NULL,
			Enabled INTEGER DEFAULT 1,
			TimeoutSeconds INTEGER DEFAULT 30,
			CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
		);
		
		CREATE TABLE IF NOT EXISTS TestCases (
			Id TEXT PRIMARY KEY,
			SuiteId TEXT NOT NULL,
			Name TEXT NOT NULL,
			Description TEXT,
			Preconditions TEXT,
			Steps TEXT NOT NULL,
			ExpectedResult TEXT NOT NULL,
			TimeoutSeconds INTEGER DEFAULT 10,
			OrderIndex INTEGER DEFAULT 0,
			Enabled INTEGER DEFAULT 1,
			FOREIGN KEY (SuiteId) REFERENCES TestSuites(Id)
		);
		
		CREATE TABLE IF NOT EXISTS TestRuns (
			Id TEXT PRIMARY KEY,
			StartedAt DATETIME NOT NULL,
			CompletedAt DATETIME,
			Status TEXT DEFAULT 'Running',
			TotalTests INTEGER DEFAULT 0,
			PassedTests INTEGER DEFAULT 0,
			FailedTests INTEGER DEFAULT 0,
			SkippedTests INTEGER DEFAULT 0,
			DurationMs INTEGER DEFAULT 0
		);
		
		CREATE TABLE IF NOT EXISTS TestResults (
			Id TEXT PRIMARY KEY,
			RunId TEXT NOT NULL,
			SuiteId TEXT NOT NULL,
			CaseId TEXT NOT NULL,
			CaseName TEXT NOT NULL,
			Status TEXT NOT NULL,
			StartedAt DATETIME NOT NULL,
			CompletedAt DATETIME,
			DurationMs INTEGER DEFAULT 0,
			ErrorMessage TEXT,
			ErrorDetails TEXT,
			RequestData TEXT,
			ResponseData TEXT,
			Logs TEXT,
			FOREIGN KEY (RunId) REFERENCES TestRuns(Id)
		);
		
		CREATE INDEX IF NOT EXISTS IdxTestResults_RunId ON TestResults(RunId);
		CREATE INDEX IF NOT EXISTS IdxTestCases_SuiteId ON TestCases(SuiteId);
	`
	_, err := s.db.Exec(schema)
	return err
}

// migrateToPascalCase detects legacy snake_case tables and renames them.
func (s *serviceImpl) migrateToPascalCase() {
	var exists int
	err := s.db.QueryRow("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='test_suites'").Scan(&exists)
	if err != nil || exists == 0 {
		return
	}

	renames := []struct{ old, new string }{
		{"test_suites", "TestSuites"},
		{"test_cases", "TestCases"},
		{"test_runs", "TestRuns"},
		{"test_results", "TestResults"},
	}
	for _, r := range renames {
		s.db.Exec(fmt.Sprintf("ALTER TABLE %s RENAME TO %s", r.old, r.new))
	}

	columnRenames := []struct{ table, old, new string }{
		// TestSuites
		{"TestSuites", "id", "Id"},
		{"TestSuites", "name", "Name"},
		{"TestSuites", "category", "Category"},
		{"TestSuites", "enabled", "Enabled"},
		{"TestSuites", "timeout_seconds", "TimeoutSeconds"},
		{"TestSuites", "created_at", "CreatedAt"},
		// TestCases
		{"TestCases", "id", "Id"},
		{"TestCases", "suite_id", "SuiteId"},
		{"TestCases", "name", "Name"},
		{"TestCases", "description", "Description"},
		{"TestCases", "preconditions", "Preconditions"},
		{"TestCases", "steps", "Steps"},
		{"TestCases", "expected_result", "ExpectedResult"},
		{"TestCases", "timeout_seconds", "TimeoutSeconds"},
		{"TestCases", "order_index", "OrderIndex"},
		{"TestCases", "enabled", "Enabled"},
		// TestRuns
		{"TestRuns", "id", "Id"},
		{"TestRuns", "started_at", "StartedAt"},
		{"TestRuns", "completed_at", "CompletedAt"},
		{"TestRuns", "status", "Status"},
		{"TestRuns", "total_tests", "TotalTests"},
		{"TestRuns", "passed_tests", "PassedTests"},
		{"TestRuns", "failed_tests", "FailedTests"},
		{"TestRuns", "skipped_tests", "SkippedTests"},
		{"TestRuns", "duration_ms", "DurationMs"},
		// TestResults
		{"TestResults", "id", "Id"},
		{"TestResults", "run_id", "RunId"},
		{"TestResults", "suite_id", "SuiteId"},
		{"TestResults", "case_id", "CaseId"},
		{"TestResults", "case_name", "CaseName"},
		{"TestResults", "status", "Status"},
		{"TestResults", "started_at", "StartedAt"},
		{"TestResults", "completed_at", "CompletedAt"},
		{"TestResults", "duration_ms", "DurationMs"},
		{"TestResults", "error_message", "ErrorMessage"},
		{"TestResults", "error_details", "ErrorDetails"},
		{"TestResults", "request_data", "RequestData"},
		{"TestResults", "response_data", "ResponseData"},
		{"TestResults", "logs", "Logs"},
	}
	for _, c := range columnRenames {
		s.db.Exec(fmt.Sprintf("ALTER TABLE %s RENAME COLUMN %s TO %s", c.table, c.old, c.new))
	}

	// Drop old indexes and recreate with PascalCase names
	s.db.Exec("DROP INDEX IF EXISTS idx_results_run")
	s.db.Exec("DROP INDEX IF EXISTS idx_cases_suite")
}

func (s *serviceImpl) seedTestSuites() {
	var count int
	s.db.QueryRow("SELECT COUNT(*) FROM TestSuites").Scan(&count)
	if count > 0 {
		return
	}

	suites := []TestSuite{
		{ID: "plugin-crud", Name: "Plugin CRUD", Category: "plugin-crud", Enabled: true, TimeoutSeconds: 30},
		{ID: "site-connections", Name: "Site Connections", Category: "site-connections", Enabled: true, TimeoutSeconds: 30},
		{ID: "sync-operations", Name: "Sync Operations", Category: "sync-operations", Enabled: true, TimeoutSeconds: 60},
		{ID: "publish-flow", Name: "Publish Flow", Category: "publish-flow", Enabled: true, TimeoutSeconds: 120},
	}

	for _, suite := range suites {
		s.db.Exec(`INSERT INTO TestSuites (Id, Name, Category, Enabled, TimeoutSeconds) VALUES (?, ?, ?, ?, ?)`,
			suite.ID, suite.Name, suite.Category, suite.Enabled, suite.TimeoutSeconds)
	}

	cases := []TestCase{
		// Plugin CRUD
		{ID: "TC-PLUGIN-001", SuiteID: "plugin-crud", Name: "Register Plugin", Description: "Register a new plugin from local directory", Steps: []string{"POST /plugins", "Verify response", "GET /plugins"}, ExpectedResult: "Plugin created and visible in list", OrderIndex: 1},
		{ID: "TC-PLUGIN-002", SuiteID: "plugin-crud", Name: "Register Invalid Path", Description: "Attempt to register non-existent path", Steps: []string{"POST /plugins with invalid path"}, ExpectedResult: "Error response", OrderIndex: 2},
		{ID: "TC-PLUGIN-003", SuiteID: "plugin-crud", Name: "Update Plugin", Description: "Update plugin settings", Steps: []string{"Create plugin", "PUT /plugins/{id}"}, ExpectedResult: "Plugin updated", OrderIndex: 3},
		{ID: "TC-PLUGIN-004", SuiteID: "plugin-crud", Name: "Delete Plugin", Description: "Delete plugin registration", Steps: []string{"Create plugin", "DELETE /plugins/{id}"}, ExpectedResult: "Plugin deleted", OrderIndex: 4},
		{ID: "TC-PLUGIN-005", SuiteID: "plugin-crud", Name: "Scan Plugin Files", Description: "Scan local plugin directory", Steps: []string{"Create plugin", "POST /watcher/scan/{id}"}, ExpectedResult: "File count returned", OrderIndex: 5},

		// Site Connections
		{ID: "TC-SITE-001", SuiteID: "site-connections", Name: "Register Site", Description: "Register a WordPress site", Steps: []string{"POST /sites", "GET /sites"}, ExpectedResult: "Site created", OrderIndex: 1},
		{ID: "TC-SITE-002", SuiteID: "site-connections", Name: "Test Connection", Description: "Test WP REST API connectivity", Steps: []string{"Create site", "POST /sites/{id}/test"}, ExpectedResult: "Success with WP version", OrderIndex: 2},
		{ID: "TC-SITE-003", SuiteID: "site-connections", Name: "Invalid Credentials", Description: "Test with bad credentials", Steps: []string{"POST /sites/test with bad creds"}, ExpectedResult: "Error or auth failure", OrderIndex: 3},
		{ID: "TC-SITE-004", SuiteID: "site-connections", Name: "Create Plugin Mapping", Description: "Map plugin to site", Steps: []string{"Create plugin", "Create site", "POST /plugins/{id}/mappings"}, ExpectedResult: "Mapping created", OrderIndex: 4},

		// Sync Operations
		{ID: "TC-SYNC-001", SuiteID: "sync-operations", Name: "Detect New Files", Description: "Detect newly added files via scan", Steps: []string{"Create plugin", "Scan"}, ExpectedResult: "Scan completes successfully", OrderIndex: 1},
		{ID: "TC-SYNC-006", SuiteID: "sync-operations", Name: "Batch Scan All", Description: "Scan all plugins at once", Steps: []string{"POST /watcher/scan-all"}, ExpectedResult: "Results for each", OrderIndex: 6},

		// Publish Flow
		{ID: "TC-PUBLISH-001", SuiteID: "publish-flow", Name: "Preview Publish", Description: "Preview files to publish", Steps: []string{"Create mapping", "POST /publish/preview"}, ExpectedResult: "Preview data returned", OrderIndex: 1},
		{ID: "TC-PUBLISH-003", SuiteID: "publish-flow", Name: "List Backups", Description: "List backups for a plugin", Steps: []string{"Create plugin", "GET /backups/{id}"}, ExpectedResult: "Backup list returned", OrderIndex: 3},
	}

	for _, tc := range cases {
		stepsJSON, _ := json.Marshal(tc.Steps)
		preJSON, _ := json.Marshal(tc.Preconditions)
		s.db.Exec(`INSERT INTO TestCases (Id, SuiteId, Name, Description, Preconditions, Steps, ExpectedResult, OrderIndex, Enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
			tc.ID, tc.SuiteID, tc.Name, tc.Description, string(preJSON), string(stepsJSON), tc.ExpectedResult, tc.OrderIndex, true)
	}
}

// ListSuites returns all test suites with case counts
func (s *serviceImpl) ListSuites(ctx context.Context) ([]TestSuite, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT s.Id, s.Name, s.Category, s.Enabled, s.TimeoutSeconds, s.CreatedAt,
		       (SELECT COUNT(*) FROM TestCases WHERE SuiteId = s.Id) as CaseCount
		FROM TestSuites s
		ORDER BY s.Category
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var suites []TestSuite
	for rows.Next() {
		var suite TestSuite
		err := rows.Scan(&suite.ID, &suite.Name, &suite.Category, &suite.Enabled,
			&suite.TimeoutSeconds, &suite.CreatedAt, &suite.CaseCount)
		if err != nil {
			return nil, err
		}
		suites = append(suites, suite)
	}
	return suites, nil
}

// GetSuite returns a single test suite
func (s *serviceImpl) GetSuite(ctx context.Context, id string) (*TestSuite, error) {
	var suite TestSuite
	err := s.db.QueryRowContext(ctx, `
		SELECT s.Id, s.Name, s.Category, s.Enabled, s.TimeoutSeconds, s.CreatedAt,
		       (SELECT COUNT(*) FROM TestCases WHERE SuiteId = s.Id) as CaseCount
		FROM TestSuites s WHERE s.Id = ?
	`, id).Scan(&suite.ID, &suite.Name, &suite.Category, &suite.Enabled,
		&suite.TimeoutSeconds, &suite.CreatedAt, &suite.CaseCount)
	if err != nil {
		return nil, err
	}
	return &suite, nil
}

// GetCases returns all test cases for a suite
func (s *serviceImpl) GetCases(ctx context.Context, suiteID string) ([]TestCase, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT Id, SuiteId, Name, Description, Preconditions, Steps, ExpectedResult, 
		       TimeoutSeconds, OrderIndex, Enabled
		FROM TestCases WHERE SuiteId = ? ORDER BY OrderIndex
	`, suiteID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var cases []TestCase
	for rows.Next() {
		var tc TestCase
		var preJSON, stepsJSON string
		err := rows.Scan(&tc.ID, &tc.SuiteID, &tc.Name, &tc.Description, &preJSON, &stepsJSON,
			&tc.ExpectedResult, &tc.TimeoutSeconds, &tc.OrderIndex, &tc.Enabled)
		if err != nil {
			return nil, err
		}
		json.Unmarshal([]byte(preJSON), &tc.Preconditions)
		json.Unmarshal([]byte(stepsJSON), &tc.Steps)
		cases = append(cases, tc)
	}
	return cases, nil
}

// StartRun begins a new test run
func (s *serviceImpl) StartRun(ctx context.Context, opts RunOptions) (*TestRun, error) {
	s.mu.Lock()
	if s.activeRun != nil && s.activeRun.Status == teststatus.Running.String() {
		s.mu.Unlock()
		return nil, apperror.New(apperror.ErrE2ERunning, "test run already in progress").
			WithRunID(s.activeRun.ID)
	}
	s.mu.Unlock()

	suites, err := s.ListSuites(ctx)
	if err != nil {
		return nil, err
	}

	var suitesToRun []TestSuite
	if len(opts.Suites) > 0 {
		suiteMap := make(map[string]bool)
		for _, id := range opts.Suites {
			suiteMap[id] = true
		}
		for _, suite := range suites {
			if suiteMap[suite.ID] && suite.Enabled {
				suitesToRun = append(suitesToRun, suite)
			}
		}
	} else {
		for _, suite := range suites {
			if suite.Enabled {
				suitesToRun = append(suitesToRun, suite)
			}
		}
	}

	totalTests := 0
	for _, suite := range suitesToRun {
		totalTests += suite.CaseCount
	}

	run := &TestRun{
		ID:         fmt.Sprintf("run-%s", uuid.New().String()[:8]),
		StartedAt:  time.Now(),
		Status:     teststatus.Running.String(),
		TotalTests: totalTests,
	}

	_, err = s.db.ExecContext(ctx, `
		INSERT INTO TestRuns (Id, StartedAt, Status, TotalTests)
		VALUES (?, ?, ?, ?)
	`, run.ID, run.StartedAt, run.Status, run.TotalTests)
	if err != nil {
		return nil, err
	}

	s.mu.Lock()
	s.activeRun = run
	s.mu.Unlock()

	if s.broadcast != nil {
		s.broadcast("e2e:run:started", ws.E2ERunStartedData{
			RunID:      run.ID,
			TotalTests: run.TotalTests,
		})
	}

	go s.executeRun(run, suitesToRun, opts)

	return run, nil
}

func (s *serviceImpl) executeRun(run *TestRun, suites []TestSuite, opts RunOptions) {
	ctx := context.Background()
	defer s.runCleanup()

	for _, suite := range suites {
		cases, err := s.GetCases(ctx, suite.ID)
		if err != nil {
			continue
		}

		for _, tc := range cases {
			if !tc.Enabled {
				run.SkippedTests++
				continue
			}

			// Check if aborted
			s.mu.RLock()
			aborted := s.activeRun == nil || s.activeRun.Status == teststatus.Aborted.String()
			s.mu.RUnlock()
			if aborted {
				return
			}

			result := s.executeTest(ctx, run, suite, tc)

		s.db.Exec(`
				INSERT INTO TestResults (Id, RunId, SuiteId, CaseId, CaseName, Status, StartedAt, CompletedAt, DurationMs, ErrorMessage, ErrorDetails, RequestData, ResponseData, Logs)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			`, result.ID, result.RunID, result.SuiteID, result.CaseID, result.CaseName, result.Status,
				result.StartedAt, result.CompletedAt, result.DurationMs, result.ErrorMessage, result.ErrorDetails,
				result.RequestData, result.ResponseData, result.Logs)

			switch result.Status {
			case teststatus.Passed.String():
				run.PassedTests++
			case teststatus.Failed.String():
				run.FailedTests++
			case teststatus.Skipped.String():
				run.SkippedTests++
			}

			if s.broadcast != nil {
				s.broadcast("e2e:test:completed", ws.E2ETestCompletedData{
					RunID:      run.ID,
					CaseID:     tc.ID,
					Status:     result.Status,
					DurationMs: result.DurationMs,
				})
			}

			if opts.StopOnFailure && result.Status == teststatus.Failed.String() {
				break
			}
		}
	}

	now := time.Now()
	run.CompletedAt = &now
	run.DurationMs = now.Sub(run.StartedAt).Milliseconds()

	if run.FailedTests > 0 {
		run.Status = teststatus.Failed.String()
	} else {
		run.Status = teststatus.Passed.String()
	}

	s.db.Exec(`
		UPDATE TestRuns SET CompletedAt = ?, Status = ?, PassedTests = ?, FailedTests = ?, SkippedTests = ?, DurationMs = ?
		WHERE Id = ?
	`, run.CompletedAt, run.Status, run.PassedTests, run.FailedTests, run.SkippedTests, run.DurationMs, run.ID)

	if s.broadcast != nil {
		s.broadcast("e2e:run:completed", ws.E2ERunCompletedData{
			RunID:  run.ID,
			Status: run.Status,
			Passed: run.PassedTests,
			Failed: run.FailedTests,
		})
	}

	s.mu.Lock()
	s.activeRun = nil
	s.mu.Unlock()
}

func (s *serviceImpl) executeTest(ctx context.Context, run *TestRun, suite TestSuite, tc TestCase) *TestResult {
	result := &TestResult{
		ID:        uuid.New().String(),
		RunID:     run.ID,
		SuiteID:   suite.ID,
		CaseID:    tc.ID,
		CaseName:  tc.Name,
		StartedAt: time.Now(),
	}

	if s.broadcast != nil {
		s.broadcast("e2e:test:started", ws.E2ETestStartedData{
			RunID:    run.ID,
			CaseID:   tc.ID,
			CaseName: tc.Name,
		})
	}

	// Dispatch to real test implementation
	var testErr error
	switch tc.ID {
	// Plugin CRUD
	case "TC-PLUGIN-001":
		testErr = s.testRegisterPlugin(ctx, result)
	case "TC-PLUGIN-002":
		testErr = s.testRegisterInvalidPath(ctx, result)
	case "TC-PLUGIN-003":
		testErr = s.testUpdatePlugin(ctx, result)
	case "TC-PLUGIN-004":
		testErr = s.testDeletePlugin(ctx, result)
	case "TC-PLUGIN-005":
		testErr = s.testScanPluginFiles(ctx, result)

	// Site Connections
	case "TC-SITE-001":
		testErr = s.testRegisterSite(ctx, result)
	case "TC-SITE-002":
		testErr = s.testSiteConnection(ctx, result)
	case "TC-SITE-003":
		testErr = s.testInvalidCredentials(ctx, result)
	case "TC-SITE-004":
		testErr = s.testCreatePluginMapping(ctx, result)

	// Sync Operations
	case "TC-SYNC-001":
		testErr = s.testDetectNewFiles(ctx, result)
	case "TC-SYNC-006":
		testErr = s.testBatchScanAll(ctx, result)

	// Publish Flow
	case "TC-PUBLISH-001":
		testErr = s.testPreviewPublish(ctx, result)
	case "TC-PUBLISH-003":
		testErr = s.testBackupList(ctx, result)

	default:
		result.Status = teststatus.Skipped.String()
		result.Logs = "No test implementation for " + tc.ID
		now := time.Now()
		result.CompletedAt = &now
		result.DurationMs = now.Sub(result.StartedAt).Milliseconds()
		return result
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

	return result
}

// AbortRun stops a running test
func (s *serviceImpl) AbortRun(ctx context.Context, runID string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.activeRun != nil && s.activeRun.ID == runID {
		now := time.Now()
		s.activeRun.Status = teststatus.Aborted.String()
		s.activeRun.CompletedAt = &now

		s.db.ExecContext(ctx, `UPDATE TestRuns SET Status = 'Aborted', CompletedAt = ? WHERE Id = ?`,
			now, runID)

		if s.broadcast != nil {
			s.broadcast("e2e:run:completed", ws.E2ERunCompletedData{
				RunID:  runID,
				Status: teststatus.Aborted.String(),
			})
		}

		s.activeRun = nil
		return nil
	}

	return apperror.New(apperror.ErrNotFound, "no active run with ID").
		WithRunID(runID)
}

// ListRuns returns past test runs
func (s *serviceImpl) ListRuns(ctx context.Context, limit int) ([]TestRun, error) {
	if limit <= 0 {
		limit = 20
	}

	rows, err := s.db.QueryContext(ctx, `
		SELECT Id, StartedAt, CompletedAt, Status, TotalTests, PassedTests, FailedTests, SkippedTests, DurationMs
		FROM TestRuns ORDER BY StartedAt DESC LIMIT ?
	`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var runs []TestRun
	for rows.Next() {
		var run TestRun
		err := rows.Scan(&run.ID, &run.StartedAt, &run.CompletedAt, &run.Status,
			&run.TotalTests, &run.PassedTests, &run.FailedTests, &run.SkippedTests, &run.DurationMs)
		if err != nil {
			return nil, err
		}
		runs = append(runs, run)
	}
	return runs, nil
}

// GetRun returns a test run with its results
func (s *serviceImpl) GetRun(ctx context.Context, runID string) (*RunSummary, error) {
	var run TestRun
	err := s.db.QueryRowContext(ctx, `
		SELECT Id, StartedAt, CompletedAt, Status, TotalTests, PassedTests, FailedTests, SkippedTests, DurationMs
		FROM TestRuns WHERE Id = ?
	`, runID).Scan(&run.ID, &run.StartedAt, &run.CompletedAt, &run.Status,
		&run.TotalTests, &run.PassedTests, &run.FailedTests, &run.SkippedTests, &run.DurationMs)
	if err != nil {
		return nil, err
	}

	rows, err := s.db.QueryContext(ctx, `
		SELECT Id, RunId, SuiteId, CaseId, CaseName, Status, StartedAt, CompletedAt, DurationMs,
		       ErrorMessage, ErrorDetails, RequestData, ResponseData, Logs
		FROM TestResults WHERE RunId = ? ORDER BY StartedAt
	`, runID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var results []TestResult
	for rows.Next() {
		var r TestResult
		err := rows.Scan(&r.ID, &r.RunID, &r.SuiteID, &r.CaseID, &r.CaseName, &r.Status,
			&r.StartedAt, &r.CompletedAt, &r.DurationMs, &r.ErrorMessage, &r.ErrorDetails,
			&r.RequestData, &r.ResponseData, &r.Logs)
		if err != nil {
			return nil, err
		}
		results = append(results, r)
	}

	return &RunSummary{Run: &run, Results: results}, nil
}

// DeleteRun removes a test run and its results
func (s *serviceImpl) DeleteRun(ctx context.Context, runID string) error {
	s.db.ExecContext(ctx, "DELETE FROM TestResults WHERE RunId = ?", runID)
	_, err := s.db.ExecContext(ctx, "DELETE FROM TestRuns WHERE Id = ?", runID)
	return err
}
