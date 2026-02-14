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
	schema := `
		CREATE TABLE IF NOT EXISTS test_suites (
			id TEXT PRIMARY KEY,
			name TEXT NOT NULL,
			category TEXT NOT NULL,
			enabled INTEGER DEFAULT 1,
			timeout_seconds INTEGER DEFAULT 30,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		);
		
		CREATE TABLE IF NOT EXISTS test_cases (
			id TEXT PRIMARY KEY,
			suite_id TEXT NOT NULL,
			name TEXT NOT NULL,
			description TEXT,
			preconditions TEXT,
			steps TEXT NOT NULL,
			expected_result TEXT NOT NULL,
			timeout_seconds INTEGER DEFAULT 10,
			order_index INTEGER DEFAULT 0,
			enabled INTEGER DEFAULT 1,
			FOREIGN KEY (suite_id) REFERENCES test_suites(id)
		);
		
		CREATE TABLE IF NOT EXISTS test_runs (
			id TEXT PRIMARY KEY,
			started_at DATETIME NOT NULL,
			completed_at DATETIME,
			status TEXT DEFAULT 'running',
			total_tests INTEGER DEFAULT 0,
			passed_tests INTEGER DEFAULT 0,
			failed_tests INTEGER DEFAULT 0,
			skipped_tests INTEGER DEFAULT 0,
			duration_ms INTEGER DEFAULT 0
		);
		
		CREATE TABLE IF NOT EXISTS test_results (
			id TEXT PRIMARY KEY,
			run_id TEXT NOT NULL,
			suite_id TEXT NOT NULL,
			case_id TEXT NOT NULL,
			case_name TEXT NOT NULL,
			status TEXT NOT NULL,
			started_at DATETIME NOT NULL,
			completed_at DATETIME,
			duration_ms INTEGER DEFAULT 0,
			error_message TEXT,
			error_details TEXT,
			request_data TEXT,
			response_data TEXT,
			logs TEXT,
			FOREIGN KEY (run_id) REFERENCES test_runs(id)
		);
		
		CREATE INDEX IF NOT EXISTS idx_results_run ON test_results(run_id);
		CREATE INDEX IF NOT EXISTS idx_cases_suite ON test_cases(suite_id);
	`
	_, err := s.db.Exec(schema)
	return err
}

func (s *serviceImpl) seedTestSuites() {
	var count int
	s.db.QueryRow("SELECT COUNT(*) FROM test_suites").Scan(&count)
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
		s.db.Exec(`INSERT INTO test_suites (id, name, category, enabled, timeout_seconds) VALUES (?, ?, ?, ?, ?)`,
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
		s.db.Exec(`INSERT INTO test_cases (id, suite_id, name, description, preconditions, steps, expected_result, order_index, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
			tc.ID, tc.SuiteID, tc.Name, tc.Description, string(preJSON), string(stepsJSON), tc.ExpectedResult, tc.OrderIndex, true)
	}
}

// ListSuites returns all test suites with case counts
func (s *serviceImpl) ListSuites(ctx context.Context) ([]TestSuite, error) {
	rows, err := s.db.QueryContext(ctx, `
		SELECT s.id, s.name, s.category, s.enabled, s.timeout_seconds, s.created_at,
		       (SELECT COUNT(*) FROM test_cases WHERE suite_id = s.id) as case_count
		FROM test_suites s
		ORDER BY s.category
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
		SELECT s.id, s.name, s.category, s.enabled, s.timeout_seconds, s.created_at,
		       (SELECT COUNT(*) FROM test_cases WHERE suite_id = s.id) as case_count
		FROM test_suites s WHERE s.id = ?
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
		SELECT id, suite_id, name, description, preconditions, steps, expected_result, 
		       timeout_seconds, order_index, enabled
		FROM test_cases WHERE suite_id = ? ORDER BY order_index
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
	if s.activeRun != nil && s.activeRun.Status == "running" {
		s.mu.Unlock()
		return nil, apperror.New(apperror.ErrE2ERunning, "test run already in progress").
			WithContext("runId", s.activeRun.ID)
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
		Status:     "running",
		TotalTests: totalTests,
	}

	_, err = s.db.ExecContext(ctx, `
		INSERT INTO test_runs (id, started_at, status, total_tests)
		VALUES (?, ?, ?, ?)
	`, run.ID, run.StartedAt, run.Status, run.TotalTests)
	if err != nil {
		return nil, err
	}

	s.mu.Lock()
	s.activeRun = run
	s.mu.Unlock()

	if s.broadcast != nil {
		s.broadcast("e2e:run:started", map[string]any{
			"runId":      run.ID,
			"totalTests": run.TotalTests,
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
			aborted := s.activeRun == nil || s.activeRun.Status == "aborted"
			s.mu.RUnlock()
			if aborted {
				return
			}

			result := s.executeTest(ctx, run, suite, tc)

			s.db.Exec(`
				INSERT INTO test_results (id, run_id, suite_id, case_id, case_name, status, started_at, completed_at, duration_ms, error_message, error_details, request_data, response_data, logs)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			`, result.ID, result.RunID, result.SuiteID, result.CaseID, result.CaseName, result.Status,
				result.StartedAt, result.CompletedAt, result.DurationMs, result.ErrorMessage, result.ErrorDetails,
				result.RequestData, result.ResponseData, result.Logs)

			switch result.Status {
			case "passed":
				run.PassedTests++
			case "failed":
				run.FailedTests++
			case "skipped":
				run.SkippedTests++
			}

			if s.broadcast != nil {
				s.broadcast("e2e:test:completed", map[string]any{
					"runId":      run.ID,
					"caseId":     tc.ID,
					"status":     result.Status,
					"durationMs": result.DurationMs,
				})
			}

			if opts.StopOnFailure && result.Status == "failed" {
				break
			}
		}
	}

	now := time.Now()
	run.CompletedAt = &now
	run.DurationMs = now.Sub(run.StartedAt).Milliseconds()

	if run.FailedTests > 0 {
		run.Status = "failed"
	} else {
		run.Status = "passed"
	}

	s.db.Exec(`
		UPDATE test_runs SET completed_at = ?, status = ?, passed_tests = ?, failed_tests = ?, skipped_tests = ?, duration_ms = ?
		WHERE id = ?
	`, run.CompletedAt, run.Status, run.PassedTests, run.FailedTests, run.SkippedTests, run.DurationMs, run.ID)

	if s.broadcast != nil {
		s.broadcast("e2e:run:completed", map[string]any{
			"runId":  run.ID,
			"status": run.Status,
			"passed": run.PassedTests,
			"failed": run.FailedTests,
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
		s.broadcast("e2e:test:started", map[string]any{
			"runId":    run.ID,
			"caseId":   tc.ID,
			"caseName": tc.Name,
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
		result.Status = "skipped"
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
		result.Status = "failed"
		result.ErrorMessage = testErr.Error()
	} else {
		result.Status = "passed"
	}

	return result
}

// AbortRun stops a running test
func (s *serviceImpl) AbortRun(ctx context.Context, runID string) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	if s.activeRun != nil && s.activeRun.ID == runID {
		now := time.Now()
		s.activeRun.Status = "aborted"
		s.activeRun.CompletedAt = &now

		s.db.ExecContext(ctx, `UPDATE test_runs SET status = 'aborted', completed_at = ? WHERE id = ?`,
			now, runID)

		if s.broadcast != nil {
			s.broadcast("e2e:run:completed", map[string]any{
				"runId":  runID,
				"status": "aborted",
			})
		}

		s.activeRun = nil
		return nil
	}

	return apperror.New(apperror.ErrNotFound, "no active run with ID").
		WithContext("runId", runID)
}

// ListRuns returns past test runs
func (s *serviceImpl) ListRuns(ctx context.Context, limit int) ([]TestRun, error) {
	if limit <= 0 {
		limit = 20
	}

	rows, err := s.db.QueryContext(ctx, `
		SELECT id, started_at, completed_at, status, total_tests, passed_tests, failed_tests, skipped_tests, duration_ms
		FROM test_runs ORDER BY started_at DESC LIMIT ?
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
		SELECT id, started_at, completed_at, status, total_tests, passed_tests, failed_tests, skipped_tests, duration_ms
		FROM test_runs WHERE id = ?
	`, runID).Scan(&run.ID, &run.StartedAt, &run.CompletedAt, &run.Status,
		&run.TotalTests, &run.PassedTests, &run.FailedTests, &run.SkippedTests, &run.DurationMs)
	if err != nil {
		return nil, err
	}

	rows, err := s.db.QueryContext(ctx, `
		SELECT id, run_id, suite_id, case_id, case_name, status, started_at, completed_at, duration_ms,
		       error_message, error_details, request_data, response_data, logs
		FROM test_results WHERE run_id = ? ORDER BY started_at
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
	s.db.ExecContext(ctx, "DELETE FROM test_results WHERE run_id = ?", runID)
	_, err := s.db.ExecContext(ctx, "DELETE FROM test_runs WHERE id = ?", runID)
	return err
}
