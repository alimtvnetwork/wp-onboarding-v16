# Error Code Registry - Cross-Project Utility

> **Version:** 1.0.0  
> **Created:** 2026-01-31  
> **Purpose:** Standardized error code ranges for all projects  
> **Scope:** Cross-project utility

---

## Overview

This specification defines a centralized error code registry that ensures:
- **No collisions** between projects
- **Consistent structure** for debugging
- **Machine-parseable** error codes
- **Human-readable** messages

---

## Error Code Format

```
[PROJECT]-[CATEGORY]-[NUMBER]
```

| Component | Format | Example |
|-----------|--------|---------|
| PROJECT | 2-4 uppercase letters | `SM`, `LM`, `PS` |
| CATEGORY | 3-digit number | `100`, `500` |
| NUMBER | 2-digit number | `01`, `99` |

**Full Example:** `SM-500-01` = Spec Management, Database category, error #01

---

## Reserved Project Prefixes

| Prefix | Project | Range Start |
|--------|---------|-------------|
| `GEN` | General/Shared | 1000-1999 |
| `SM` | Spec Management Software | 2000-2999 |
| `LM` | Link Manager | 3000-3999 |
| `PS` | PowerShell Integration | 9500-9599 |
| `CLI` | CLI Tools (gsearch, brun) | 4000-4999 |

> New projects should claim the next available 1000-range.

---

## Category Ranges (Per Project)

Within each project's 1000-range:

| Offset | Category | Description |
|--------|----------|-------------|
| +000-099 | General | Initialization, config |
| +100-199 | Authentication | Login, tokens, sessions |
| +200-299 | Authorization | Permissions, roles |
| +300-399 | Validation | Input validation |
| +400-499 | Business Logic | Domain-specific errors |
| +500-599 | Database | CRUD, migrations |
| +600-699 | External Services | APIs, integrations |
| +700-799 | File System | I/O operations |
| +800-899 | Network | HTTP, WebSocket |
| +900-999 | Reserved | Future use |

---

## Files in This Spec

| File | Purpose |
|------|---------|
| `00-overview.md` | This file - structure and conventions |
| `01-registry.md` | Master list of all registered codes |
| `02-integration-guide.md` | How to add codes to your project |
| `schemas/error-code.schema.json` | JSON schema for validation |
| `templates/error-codes.template.md` | Template for project error docs |

---

## Quick Reference

**To register new codes:**
1. Claim a project prefix in `01-registry.md`
2. Add your codes following category offsets
3. Document in your project's spec folder

**To use in code:**
```go
// Go example
return errors.New("SM-500-01: Database connection failed")
```

```typescript
// TypeScript example  
throw new AppError("SM-300-01", "Invalid email format");
```

---

## Related Specs

- `spec/powershell-integration/04-error-codes.md` - PowerShell error codes
- `spec/spec-management-software/05-features/` - SM project errors
