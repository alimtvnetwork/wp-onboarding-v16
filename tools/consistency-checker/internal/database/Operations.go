// Package database — run and finding persistence operations.
package database

import "consistency-checker/pkg/apperror"

// Run represents a checker execution.
type Run struct {
	Id            int64
	Directory     string
	Config        string
	TotalFiles    int
	TotalFindings int
	ErrorCount    int
	WarningCount  int
	InfoCount     int
	ExitCode      int
}

// Finding represents a single violation.
type Finding struct {
	RuleId     string
	RuleName   string
	Severity   string
	FilePath   string
	Line       int
	EndLine    int
	Message    string
	Suggestion string
	Reference  string
	Context    string
}

// StartRun inserts a new run record and returns its ID.
func (db *DB) StartRun(directory, configPath string) apperror.Result[int64] {
	result, err := db.conn.Exec(
		"INSERT INTO Runs (Directory, Config) VALUES (?, ?)",
		directory, configPath,
	)
	if err != nil {
		return apperror.Fail[int64](apperror.Wrap(err, apperror.ErrDatabase, "failed to start run"))
	}

	id, _ := result.LastInsertId()
	return apperror.Ok(id)
}

// CompleteRun updates a run with final counts.
func (db *DB) CompleteRun(run Run) *apperror.AppError {
	_, err := db.conn.Exec(
		`UPDATE Runs SET EndedAt = datetime('now'),
		 TotalFiles = ?, TotalFindings = ?,
		 ErrorCount = ?, WarningCount = ?, InfoCount = ?,
		 ExitCode = ? WHERE Id = ?`,
		run.TotalFiles, run.TotalFindings,
		run.ErrorCount, run.WarningCount, run.InfoCount,
		run.ExitCode, run.Id,
	)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabase, "failed to complete run")
	}
	return nil
}

// InsertFinding persists a single finding.
func (db *DB) InsertFinding(runId int64, f Finding) *apperror.AppError {
	_, err := db.conn.Exec(
		`INSERT INTO Findings (RunId, RuleId, RuleName, Severity, FilePath, Line, EndLine, Message, Suggestion, Reference, Context)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		runId, f.RuleId, f.RuleName, f.Severity,
		f.FilePath, f.Line, f.EndLine,
		f.Message, f.Suggestion, f.Reference, f.Context,
	)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabase, "failed to insert finding")
	}
	return nil
}
