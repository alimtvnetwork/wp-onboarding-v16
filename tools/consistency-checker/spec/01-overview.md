# Consistency Checker — Overview

## Purpose

A standalone Go CLI application that audits source code repositories for
compliance with project coding standards. It scans Go, PHP, HTML, and Markdown
files and reports violations with file paths, line numbers, severity, and
references to the governing rule.

## Key Capabilities

| Capability              | Description                                         |
|-------------------------|-----------------------------------------------------|
| Multi-language support  | Go, PHP, HTML, Markdown scanners                    |
| JSON-driven rules       | All rule parameters are configurable via JSON        |
| Glob exclusions         | `.gitignore`-style path exclusions (`*`, `**`)       |
| SQLite findings store   | Every finding is persisted with line number & context |
| Severity levels         | `error`, `warning`, `info`                           |
| Rule references         | Each finding links to the spec/guideline it enforces  |
| Exit codes              | `0` = clean, `1` = violations found                  |

## Usage

```bash
# Scan current directory with default config
consistency-checker

# Scan specific repo with custom config
consistency-checker --dir /path/to/repo --config config/rules.json

# Output to specific database
consistency-checker --dir . --db data/findings.db

# Dry run — print findings without persisting
consistency-checker --dir . --dry-run
```

## Project Structure

```
tools/consistency-checker/
├── cmd/checker/main.go          # CLI entry point
├── config/
│   └── rules.json               # Default rule configuration
├── data/                        # SQLite database (runtime)
├── internal/
│   ├── config/                  # Config loading & validation
│   ├── database/                # SQLite operations
│   ├── engine/                  # Rule execution engine
│   ├── rules/                   # Individual rule implementations
│   ├── scanner/                 # File discovery & filtering
│   └── report/                  # Output formatting
├── pkg/
│   └── apperror/                # Structured error handling
├── spec/                        # Specification documents
├── go.mod
└── go.sum
```

## Design Principles

1. **Each rule is a separate file** — logic is concrete and testable
2. **JSON config drives behavior** — no hardcoded thresholds
3. **SQLite persistence** — findings survive across runs for trending
4. **Spec-first** — specifications written before code
5. **Follows its own rules** — the checker itself must pass all Go checks
