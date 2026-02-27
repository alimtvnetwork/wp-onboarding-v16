# Consistency Checker — Architecture

## Component Diagram

```
┌─────────────┐     ┌──────────┐     ┌─────────┐
│  CLI (main)  │────▶│  Config  │────▶│ Scanner │
└──────┬──────┘     └──────────┘     └────┬────┘
       │                                   │
       │         ┌──────────┐              │ file list
       │         │  Engine   │◀────────────┘
       │         └────┬─────┘
       │              │ runs each rule
       │         ┌────▼─────┐
       │         │  Rules    │  (file_size, func_size, naming, ...)
       │         └────┬─────┘
       │              │ findings
       │         ┌────▼─────┐     ┌──────────┐
       │         │ Database  │────▶│ SQLite   │
       │         └────┬─────┘     └──────────┘
       │              │
       │         ┌────▼─────┐
       └────────▶│  Report   │────▶ stdout / JSON
                 └──────────┘
```

## Data Flow

1. **CLI** parses flags (`--dir`, `--config`, `--db`, `--dry-run`)
2. **Config** loads `rules.json`, validates, merges with CLI overrides
3. **Scanner** walks the target directory, applies glob exclusions
4. **Engine** iterates scanned files, dispatches to matching rules
5. **Rules** analyze file content, return `[]Finding`
6. **Database** persists findings to SQLite (unless `--dry-run`)
7. **Report** formats and prints summary to stdout

## Rule Interface

Every rule implements:

```go
type Rule interface {
    ID() string
    Name() string
    Languages() []string
    Check(ctx CheckContext) []Finding
}
```

- `CheckContext` provides file path, content bytes, parsed lines, and config
- `Finding` contains file, line, message, severity, rule ID, and suggestion

## Package Responsibilities

| Package    | Responsibility                                    |
|------------|---------------------------------------------------|
| `config`   | Load JSON, validate, provide typed access          |
| `scanner`  | Walk dirs, apply exclusions, classify by language  |
| `engine`   | Register rules, dispatch files, collect findings   |
| `rules`    | Individual check implementations (one per file)    |
| `database` | SQLite schema, insert findings, query history      |
| `report`   | Console output, JSON export, summary stats         |
| `apperror` | Structured errors with codes and context           |

## Concurrency Model

- File scanning is sequential (I/O bound, simple)
- Rule execution is per-file sequential (rules may depend on full file content)
- Future: parallel file processing with `sync.WaitGroup` if needed
