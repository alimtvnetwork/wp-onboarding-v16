# Memory: guidelines/ai-output-standard

**Updated:** 2026-01-29  
**Purpose:** Standards for AI-generated specifications and documentation

---

## Output Requirements

### Structure

- Original user instructions included
- 4-level deep action item breakdown
- Separate BE/FE/Admin sections
- Acceptance criteria for every stage

---

## UI Field Documentation

| Section | Requirements |
|---------|--------------|
| Backend | API endpoints, data models |
| Frontend | Components, user flows |
| Admin Panel | Management interfaces |

---

## Filesystem Documentation

| Element | Format |
|---------|--------|
| Database Tables | PascalCase/camelCase fields |
| Upload Paths | Explicit path specifications |
| Log Paths | Logging directory structure |

---

## Diagrams

- Process flow diagrams required
- GORM-compatible markdown tables
- ORM/SQLite data flow diagrams

---

## AI Role

Act as prompt-crafter providing:
- Detailed proofreading
- Formatting as requested
- No missed critical steps
- Logically connected workflow steps
