# Memory: coding-standards/go-database-scan-formatting
Updated: 2026-02-28

## Rule: One Field Per Line in Scan Calls

All `rows.Scan()` and `row.Scan()` calls MUST place each field pointer on its own line.

```go
// ❌ FORBIDDEN — inline scan
scanErr := rows.Scan(&m.Id, &m.PluginId, &m.PluginName, &m.SiteId, &m.Status, &m.CreatedAt)

// ✅ REQUIRED — one field per line
scanErr := rows.Scan(
    &m.Id,
    &m.PluginId,
    &m.PluginName,
    &m.SiteId,
    &m.Status,
    &m.CreatedAt,
)
```

## Rule: Extract Scan Logic into Dedicated Scanner Functions

Row scanning MUST be extracted into a dedicated `scanXxxRow(rows *sql.Rows)` function. The scanned variable MUST use the short alias `m` (for "model").

```go
// ❌ FORBIDDEN — inline scan in loop
for rows.Next() {
    var e models.PublishHistory
    scanErr := rows.Scan(&e.Id, &e.PluginId, ...)
    entries = append(entries, e)
}

// ✅ REQUIRED — dedicated scanner with `m` alias
func scanHistoryRow(rows *sql.Rows) (models.PublishHistory, error) {
    var m models.PublishHistory

    err := rows.Scan(
        &m.Id,
        &m.PluginId,
        &m.PluginName,
    )

    return m, err
}
```

## Rule: Group Nullable Fields into a Struct

When scanning nullable columns (`sql.NullString`, `sql.NullInt64`, etc.), group them into a dedicated `xxxNullFields` struct and apply them in a separate `applyXxxNullFields` function.

```go
type versionNullFields struct {
    SiteName sql.NullString
    Version  sql.NullString
}

func scanVersionRow(rows *sql.Rows) (PluginVersionRow, error) {
    var m PluginVersionRow
    var nf versionNullFields

    err := rows.Scan(
        &m.ID,
        &nf.SiteName,
        &nf.Version,
    )
    if err != nil {

        return m, err
    }

    applyVersionNullFields(&m, &nf)

    return m, nil
}

func applyVersionNullFields(m *PluginVersionRow, nf *versionNullFields) {
    m.SiteName = nf.SiteName.String
    m.Version = nf.Version.String
}
```

## Rule: Extract SQL into Named Constants

Long SQL queries (3+ lines or 3+ columns) MUST be extracted into `const` blocks in a dedicated `Queries.go` file within the same package.

```go
// ❌ FORBIDDEN — inline SQL
rows, err := s.db.Query("SELECT Id, PluginId, PluginName, ... FROM PublishHistory WHERE ...")

// ✅ REQUIRED — named constant in Queries.go
const selectHistorySQL = `SELECT
    Id,
    PluginId,
    PluginName
FROM PublishHistory`

rows, err := s.db.Query(selectHistorySQL + where, args...)
```

## File Organization Pattern

For any package with database operations:
- `Service.go` — business logic and public API
- `Scanner.go` — `scanXxxRow`, `scanXxxRows`, null field structs
- `Queries.go` — SQL constants and query builders
