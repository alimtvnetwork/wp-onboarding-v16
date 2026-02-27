// Package database — schema migrations.
package database

import "consistency-checker/pkg/apperror"

const schema = `
CREATE TABLE IF NOT EXISTS Runs (
    Id            INTEGER PRIMARY KEY AUTOINCREMENT,
    StartedAt     TEXT    NOT NULL DEFAULT (datetime('now')),
    EndedAt       TEXT,
    Directory     TEXT    NOT NULL,
    Config        TEXT    NOT NULL,
    TotalFiles    INTEGER DEFAULT 0,
    TotalFindings INTEGER DEFAULT 0,
    ErrorCount    INTEGER DEFAULT 0,
    WarningCount  INTEGER DEFAULT 0,
    InfoCount     INTEGER DEFAULT 0,
    ExitCode      INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS Findings (
    Id         INTEGER PRIMARY KEY AUTOINCREMENT,
    RunId      INTEGER NOT NULL,
    RuleId     TEXT    NOT NULL,
    RuleName   TEXT    NOT NULL,
    Severity   TEXT    NOT NULL,
    FilePath   TEXT    NOT NULL,
    Line       INTEGER DEFAULT 0,
    EndLine    INTEGER DEFAULT 0,
    Message    TEXT    NOT NULL,
    Suggestion TEXT    DEFAULT '',
    Reference  TEXT    DEFAULT '',
    Context    TEXT    DEFAULT '',
    CreatedAt  TEXT    NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (RunId) REFERENCES Runs(Id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_findings_run      ON Findings(RunId);
CREATE INDEX IF NOT EXISTS idx_findings_rule     ON Findings(RuleId);
CREATE INDEX IF NOT EXISTS idx_findings_severity ON Findings(Severity);
CREATE INDEX IF NOT EXISTS idx_findings_file     ON Findings(FilePath);
`

// migrate applies the schema to the database.
func (db *DB) migrate() *apperror.AppError {
	_, err := db.conn.Exec(schema)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrDatabase, "failed to apply schema")
	}
	return nil
}
