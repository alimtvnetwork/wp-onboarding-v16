package e2e

import "fmt"

// tableRename holds old→new table name pairs for migration.
type tableRename struct{ old, new string }

// columnRename holds table, old→new column name triples for migration.
type columnRename struct{ table, old, new string }

// migrateToPascalCase detects legacy snake_case tables and renames them.
func (s *serviceImpl) migrateToPascalCase() {
	var exists int
	err := s.db.QueryRow(migrationCheckQuery).Scan(&exists)
	if err != nil || exists == 0 {
		return
	}

	s.renameTablesAndColumns()
	s.dropLegacyIndexes()
}

// renameTablesAndColumns renames all legacy snake_case tables and columns.
func (s *serviceImpl) renameTablesAndColumns() {
	for _, r := range tableRenames() {
		s.db.Exec(fmt.Sprintf("ALTER TABLE %s RENAME TO %s", r.old, r.new))
	}
	for _, c := range columnRenames() {
		s.db.Exec(fmt.Sprintf("ALTER TABLE %s RENAME COLUMN %s TO %s", c.table, c.old, c.new))
	}
}

// dropLegacyIndexes drops old snake_case indexes.
func (s *serviceImpl) dropLegacyIndexes() {
	s.db.Exec("DROP INDEX IF EXISTS idx_results_run")
	s.db.Exec("DROP INDEX IF EXISTS idx_cases_suite")
}

// tableRenames returns the table rename pairs.
func tableRenames() []tableRename {
	return []tableRename{
		{"test_suites", "TestSuites"},
		{"test_cases", "TestCases"},
		{"test_runs", "TestRuns"},
		{"test_results", "TestResults"},
	}
}

// columnRenames returns all column rename triples.
func columnRenames() []columnRename {
	return []columnRename{
		{"TestSuites", "id", "Id"},
		{"TestSuites", "name", "Name"},
		{"TestSuites", "category", "Category"},
		{"TestSuites", "enabled", "Enabled"},
		{"TestSuites", "timeout_seconds", "TimeoutSeconds"},
		{"TestSuites", "created_at", "CreatedAt"},
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
		{"TestRuns", "id", "Id"},
		{"TestRuns", "started_at", "StartedAt"},
		{"TestRuns", "completed_at", "CompletedAt"},
		{"TestRuns", "status", "Status"},
		{"TestRuns", "total_tests", "TotalTests"},
		{"TestRuns", "passed_tests", "PassedTests"},
		{"TestRuns", "failed_tests", "FailedTests"},
		{"TestRuns", "skipped_tests", "SkippedTests"},
		{"TestRuns", "duration_ms", "DurationMs"},
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
}
