package e2e

import "encoding/json"

// seedTestSuites populates default test suites and cases if empty.
func (s *serviceImpl) seedTestSuites() {
	var count int
	s.db.QueryRow(suiteCountQuery).Scan(&count)
	if count > 0 {
		return
	}

	s.insertDefaultSuites()
	s.insertDefaultCases()
}

// insertDefaultSuites inserts the standard test suites.
func (s *serviceImpl) insertDefaultSuites() {
	for _, suite := range defaultSuites() {
		s.db.Exec(suiteInsertQuery, suite.ID, suite.Name, suite.Category, suite.Enabled, suite.TimeoutSeconds)
	}
}

// insertDefaultCases inserts the standard test cases.
func (s *serviceImpl) insertDefaultCases() {
	for _, tc := range defaultCases() {
		stepsJSON, _ := json.Marshal(tc.Steps)
		preJSON, _ := json.Marshal(tc.Preconditions)
		s.db.Exec(caseInsertQuery, tc.ID, tc.SuiteID, tc.Name, tc.Description, string(preJSON), string(stepsJSON), tc.ExpectedResult, tc.OrderIndex, true)
	}
}

// defaultSuites returns the built-in test suites.
func defaultSuites() []TestSuite {
	return []TestSuite{
		{ID: "plugin-crud", Name: "Plugin CRUD", Category: "plugin-crud", Enabled: true, TimeoutSeconds: 30},
		{ID: "site-connections", Name: "Site Connections", Category: "site-connections", Enabled: true, TimeoutSeconds: 30},
		{ID: "sync-operations", Name: "Sync Operations", Category: "sync-operations", Enabled: true, TimeoutSeconds: 60},
		{ID: "publish-flow", Name: "Publish Flow", Category: "publish-flow", Enabled: true, TimeoutSeconds: 120},
	}
}

// defaultCases returns the built-in test cases.
func defaultCases() []TestCase {
	return []TestCase{
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
}
