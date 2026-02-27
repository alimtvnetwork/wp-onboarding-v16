# Consistency Checker — Rules Specification

## Rule Categories

### Go Rules

| Rule ID          | Name                | Default          | Severity | Description                                |
|------------------|---------------------|------------------|----------|--------------------------------------------|
| `go-file-size`   | File Size Limit     | max: 300 lines   | error    | Go files must not exceed line limit         |
| `go-func-size`   | Function Body Size  | max: 15 lines    | error    | Function bodies must not exceed line limit  |
| `go-single-return` | Single Return Value | —              | error    | Functions must return exactly one value     |
| `go-import-groups` | Import Grouping    | 3 groups         | warning  | Imports: stdlib, third-party, internal      |
| `go-file-naming` | File Naming         | PascalCase       | warning  | Go files must follow naming convention      |
| `go-param-count` | Parameter Count     | max: 3           | warning  | Max params excluding context.Context        |

### PHP Rules

| Rule ID          | Name                | Default          | Severity | Description                                |
|------------------|---------------------|------------------|----------|--------------------------------------------|
| `php-file-size`  | File Size Limit     | max: 500 lines   | warning  | PHP files must not exceed line limit        |

### Markdown Rules

| Rule ID          | Name                | Default          | Severity | Description                                |
|------------------|---------------------|------------------|----------|--------------------------------------------|
| `md-heading`     | Single H1           | —                | info     | Only one H1 heading per document            |

### Universal Rules

| Rule ID          | Name                | Default          | Severity | Description                                |
|------------------|---------------------|------------------|----------|--------------------------------------------|
| `file-naming`    | File Naming         | configurable     | warning  | File names follow configured convention     |

## Rule Configuration (rules.json)

Each rule entry in `rules.json`:

```json
{
  "id": "go-file-size",
  "name": "Go File Size Limit",
  "enabled": true,
  "severity": "error",
  "languages": ["go"],
  "params": {
    "max_lines": 300
  },
  "exclude": ["*_test.go", "vendor/**"],
  "reference": "spec/03-rules.md#go-file-size"
}
```

### Field Descriptions

| Field       | Type     | Required | Description                              |
|-------------|----------|----------|------------------------------------------|
| `id`        | string   | yes      | Unique rule identifier                   |
| `name`      | string   | yes      | Human-readable name                      |
| `enabled`   | bool     | yes      | Whether rule is active                   |
| `severity`  | string   | yes      | `error`, `warning`, or `info`            |
| `languages` | []string | yes      | File types: `go`, `php`, `html`, `md`    |
| `params`    | object   | no       | Rule-specific parameters                 |
| `exclude`   | []string | no       | Additional glob exclusions for this rule |
| `reference` | string   | no       | Path to governing spec/guideline         |

## Finding Output

Each finding includes:

```json
{
  "rule_id": "go-file-size",
  "file": "backend/internal/services/publish/Service.go",
  "line": 1,
  "message": "File has 423 lines (max 300)",
  "severity": "error",
  "suggestion": "Split into smaller files: Service.go, ServiceHelpers.go",
  "reference": "spec/03-rules.md#go-file-size"
}
```
