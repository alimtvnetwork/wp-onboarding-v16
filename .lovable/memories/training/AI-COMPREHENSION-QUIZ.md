# AI Comprehension Quiz: Architecture Patterns

**Version:** 1.0.0  
**Purpose:** Verify AI understanding of core architectural patterns before code generation  
**Pass Threshold:** 5/5 correct  

---

## Instructions for AI

Answer each question. Compare your answers to the Answer Key at the bottom. If you score below 5/5, re-read `CONTEXT-FOR-AI.md` before proceeding.

---

## Questions

### Q1: Database Isolation

You need to display a dashboard showing:
- User's global preferences (theme, language)
- List of all projects with their names
- Current project's specification count
- Current conversation's message history

**Question:** Which databases would you query, and can you JOIN them?

---

### Q2: Cross-Database Data Aggregation

A feature requires combining project metadata from `projects.db` with specification details from a specific `project.db`.

**Question:** Write pseudocode showing the CORRECT way to aggregate this data.

---

### Q3: Seedable Config Lifecycle

The application has these conditions:
- `SeedVersion` in JSON file: `2.1.0`
- `StoredVersion` in settings.db: `2.0.0`
- `IsUserModified` flag: `TRUE`

**Question:** Will the setting be updated when the app starts? Why or why not?

---

### Q4: User Preference Protection

A user manually changed `defaultModel` from `"gpt-4"` to `"claude-3"` via the Settings UI. A new version ships with `defaultModel` seed value `"gpt-4.5"`.

**Question:** What value will `defaultModel` have after the update, and why?

---

### Q5: Config Reset Scenario

An admin wants to force a config reset to get the latest seed values, overwriting user customizations.

**Question:** What database operation enables this, and what happens on next app start?

---

## Answer Key

### A1: Database Isolation
**Databases queried:**
- `settings.db` → global preferences
- `projects.db` → project list
- `project.db` (specific) → specification count
- `{conv-id}.db` → message history

**JOIN allowed?** NO. Cross-database JOINs are forbidden. Each database must be queried separately, and data aggregation happens at the **application layer** (Go service code).

---

### A2: Cross-Database Data Aggregation

```go
// CORRECT: Application-layer aggregation
func GetProjectWithSpecs(projectID string) (*ProjectDetails, error) {
    // Query 1: Get project metadata from projects.db
    project, err := projectsDB.GetProject(projectID)
    if err != nil {
        return nil, err
    }
    
    // Query 2: Open and query project-specific database
    projectDB, err := OpenProjectDB(project.DBPath)
    if err != nil {
        return nil, err
    }
    defer projectDB.Close()
    
    specs, err := projectDB.GetSpecifications()
    if err != nil {
        return nil, err
    }
    
    // Aggregate at application layer
    return &ProjectDetails{
        Project: project,
        Specs:   specs,
    }, nil
}
```

**WRONG approach:** `SELECT * FROM projects.db.Projects JOIN project.db.Specs...`

---

### A3: Seedable Config Lifecycle

**Answer:** NO, the setting will NOT be updated.

**Why:** The seeding condition is:
```
IF NOT EXISTS OR (SeedVersion > StoredVersion AND IsUserModified == FALSE)
```

Even though `SeedVersion (2.1.0) > StoredVersion (2.0.0)`, the `IsUserModified == TRUE` blocks the update. User customizations are protected.

---

### A4: User Preference Protection

**Answer:** The value remains `"claude-3"`.

**Why:** When the user changed the setting via UI, the system set `IsUserModified = TRUE`. This flag prevents any seed updates from overwriting the user's choice, regardless of version changes.

---

### A5: Config Reset Scenario

**Answer:** 
1. Execute: `UPDATE Settings SET IsUserModified = FALSE WHERE Key = 'targetConfig'`
2. On next app start, the seeding condition passes: `SeedVersion > StoredVersion AND IsUserModified == FALSE`
3. The config resets to the latest seed value from `/seeds/config/*.json`

---

## Scoring

| Score | Result |
|-------|--------|
| 5/5 | ✅ Ready to generate code |
| 4/5 | ⚠️ Review the missed concept |
| <4/5 | ❌ Re-read CONTEXT-FOR-AI.md |

---

## Related Documents

- [CONTEXT-FOR-AI.md](../../../CONTEXT-FOR-AI.md)
- [Split Database System](../architecture/split-database-system.md)
- [Seedable Configuration](../patterns/seedable-configuration.md)
