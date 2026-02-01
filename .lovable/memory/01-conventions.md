# Conventions

> **Location:** `.lovable/memory/01-conventions.md`  
> **Updated:** 2026-02-01

---

## File Naming

### Numbered Prefix Pattern

All files in memory folders use numbered prefixes:

```
01-name-of-file.md
02-another-file.md
```

**Rules:**
- Two-digit prefix (01, 02, 03...)
- Hyphen-separated words
- Lowercase only
- `.md` extension for documentation

---

## Folder Organization

### Principle: Fewer Files, More Consolidation

- Keep folder file count low
- Consolidate related content into single files
- Use sections within files rather than creating new files
- Archive completed items by updating status, not moving files

---

## Spec Folder Structure

Each project spec follows this pattern:

```
spec/<project-name>/
├── 00-overview.md           # Project summary
├── 01-requirements.md       # Functional requirements
├── 02-data-model.md         # Data structures
├── 03-api.md                # API endpoints (if applicable)
└── 99-acceptance.md         # Acceptance criteria
```

**Rules:**
- Numbered prefixes indicate reading order
- `00-` for overview/entry point
- `99-` for acceptance criteria (read last)

---

## Code Conventions

### React/TypeScript

- Use functional components
- Prefer named exports
- Use semantic Tailwind tokens from design system
- Keep components focused and small

### File Organization

```
src/
├── components/    # Reusable UI components
├── pages/         # Route pages
├── hooks/         # Custom React hooks
├── lib/           # Utilities and helpers
└── types/         # TypeScript type definitions
```

---

*Keep this file updated when conventions change.*
