# Consistency Checker — Database Schema

## Overview

Findings are stored in a SQLite database for historical tracking, trend
analysis, and CI integration. The database is created automatically on first
run.

## Schema

### Runs Table

Tracks each execution of the checker.

```sql
CREATE TABLE IF NOT EXISTS Runs (
    Id        INTEGER PRIMARY KEY AUTOINCREMENT,
    StartedAt TEXT    NOT NULL DEFAULT (datetime('now')),
    EndedAt   TEXT,
    Directory TEXT    NOT NULL,
    Config    TEXT    NOT NULL,
    TotalFiles    INTEGER DEFAULT 0,
    TotalFindings INTEGER DEFAULT 0,
    ErrorCount    INTEGER DEFAULT 0,
    WarningCount  INTEGER DEFAULT 0,
    InfoCount     INTEGER DEFAULT 0,
    ExitCode      INTEGER DEFAULT 0
);
```

### Findings Table

Stores individual violations found during a run.

```sql
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

CREATE INDEX IF NOT EXISTS idx_findings_run    ON Findings(RunId);
CREATE INDEX IF NOT EXISTS idx_findings_rule   ON Findings(RuleId);
CREATE INDEX IF NOT EXISTS idx_findings_severity ON Findings(Severity);
CREATE INDEX IF NOT EXISTS idx_findings_file   ON Findings(FilePath);
```

## Queries

### Insert a run

```sql
INSERT INTO Runs (Directory, Config) VALUES (?, ?);
```

### Complete a run

```sql
UPDATE Runs SET
    EndedAt = datetime('now'),
    TotalFiles = ?, TotalFindings = ?,
    ErrorCount = ?, WarningCount = ?, InfoCount = ?,
    ExitCode = ?
WHERE Id = ?;
```

### Insert a finding

```sql
INSERT INTO Findings (RunId, RuleId, RuleName, Severity, FilePath, Line, EndLine, Message, Suggestion, Reference, Context)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
```

### Summary by severity

```sql
SELECT Severity, COUNT(*) as Count
FROM Findings WHERE RunId = ?
GROUP BY Severity;
```

## Data Retention

- No automatic cleanup — users manage the database
- Database path defaults to `data/findings.db`
- `--db` flag allows custom path
- `--dry-run` skips all database writes
