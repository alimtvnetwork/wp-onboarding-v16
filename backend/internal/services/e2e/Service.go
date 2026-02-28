// Package e2e implements end-to-end testing against real WordPress sites
package e2e

import (
	"database/sql"
	"sync"
	"time"
)

// ---------------------------------------------------------------------------
// SQL constants
// ---------------------------------------------------------------------------

const schemaSql = `
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

const migrationCheckQuery = "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='test_suites'"

const suiteCountQuery = "SELECT COUNT(*) FROM TestSuites"

const suiteInsertQuery = `INSERT INTO TestSuites (Id, Name, Category, Enabled, TimeoutSeconds) VALUES (?, ?, ?, ?, ?)`

const caseInsertQuery = `INSERT INTO TestCases (Id, SuiteId, Name, Description, Preconditions, Steps, ExpectedResult, OrderIndex, Enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`

const suiteListQuery = `
	SELECT s.Id, s.Name, s.Category, s.Enabled, s.TimeoutSeconds, s.CreatedAt,
	       (SELECT COUNT(*) FROM TestCases WHERE SuiteId = s.Id) as CaseCount
	FROM TestSuites s
	ORDER BY s.Category
`

const suiteSelectQuery = `
	SELECT s.Id, s.Name, s.Category, s.Enabled, s.TimeoutSeconds, s.CreatedAt,
	       (SELECT COUNT(*) FROM TestCases WHERE SuiteId = s.Id) as CaseCount
	FROM TestSuites s WHERE s.Id = ?
`

const caseListQuery = `
	SELECT Id, SuiteId, Name, Description, Preconditions, Steps, ExpectedResult,
	       TimeoutSeconds, OrderIndex, Enabled
	FROM TestCases WHERE SuiteId = ? ORDER BY OrderIndex
`

const runInsertQuery = `INSERT INTO TestRuns (Id, StartedAt, Status, TotalTests) VALUES (?, ?, ?, ?)`

const resultInsertQuery = `
	INSERT INTO TestResults
		(Id, RunId, SuiteId, CaseId, CaseName, Status, StartedAt, CompletedAt,
		 DurationMs, ErrorMessage, ErrorDetails, RequestData, ResponseData, Logs)
	VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
`

const runCompleteQuery = `
	UPDATE TestRuns
	SET CompletedAt = ?, Status = ?, PassedTests = ?, FailedTests = ?, SkippedTests = ?, DurationMs = ?
	WHERE Id = ?
`

const runAbortQuery = `UPDATE TestRuns SET Status = 'Aborted', CompletedAt = ? WHERE Id = ?`

const runListQuery = `
	SELECT Id, StartedAt, CompletedAt, Status, TotalTests, PassedTests, FailedTests, SkippedTests, DurationMs
	FROM TestRuns ORDER BY StartedAt DESC LIMIT ?
`

const runSelectQuery = `
	SELECT Id, StartedAt, CompletedAt, Status, TotalTests, PassedTests, FailedTests, SkippedTests, DurationMs
	FROM TestRuns WHERE Id = ?
`

const resultListQuery = `
	SELECT Id, RunId, SuiteId, CaseId, CaseName, Status, StartedAt, CompletedAt, DurationMs,
	       ErrorMessage, ErrorDetails, RequestData, ResponseData, Logs
	FROM TestResults WHERE RunId = ? ORDER BY StartedAt
`

const resultDeleteQuery = "DELETE FROM TestResults WHERE RunId = ?"

const runDeleteQuery = "DELETE FROM TestRuns WHERE Id = ?"

// ---------------------------------------------------------------------------
// Types & constructor
// ---------------------------------------------------------------------------

// Config holds configuration for the E2E test service
type Config struct {
	DB               *sql.DB
	Broadcast        func(event string, data any)
	BaseUrl          string // Backend API base URL (e.g. "http://localhost:8080")
	TestPluginPath   string // Local path to a test plugin directory
	TestSiteUrl      string // WordPress test site URL
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
	testSiteUrl      string
	testSiteUsername  string
	testSitePassword string
	cleanupIds       map[string][]int64
}

// New creates a new E2E test service
func New(cfg Config) Service {
	svc := &serviceImpl{
		db:               cfg.DB,
		broadcast:        cfg.Broadcast,
		api:              newApiClient(cfg.BaseUrl),
		testPluginPath:   cfg.TestPluginPath,
		testSiteUrl:      cfg.TestSiteUrl,
		testSiteUsername:  cfg.TestSiteUsername,
		testSitePassword: cfg.TestSitePassword,
	}
	svc.initSchema()
	svc.seedTestSuites()
	return svc
}

func (s *serviceImpl) initSchema() error {
	s.migrateToPascalCase()
	_, err := s.db.Exec(schemaSql)
	return err
}
