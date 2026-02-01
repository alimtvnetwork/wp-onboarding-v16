# Spec Management Software

**Version:** 0.7.0  
**Updated:** 2026-01-29  

---

## Quick Navigation

→ **[03-project-overview/](./03-project-overview/00-overview.md)** — Full project overview and navigation index  
→ **[05-features/](./05-features/00-overview.md)** — All feature specifications (22 feature folders)  
→ **[11-skipped-features/](./11-skipped-features/00-overview.md)** — Deferred features (for simplicity)

---

## Design Philosophy

**Keep it simple:** Go backend + React frontend running locally. No complex infrastructure required.

---

## Folder Structure

```
spec-management-software/
├── 00-overview.md                 # This file
├── 01-ideas/                      # Raw ideas, verbatim transcriptions
├── 02-instructions/               # Refined instructions
├── 03-project-overview/           # Project overview & navigation
├── 04-coding-guidelines/          # Project coding standards
├── 05-features/                   # Feature-based specifications (22 folders)
│   ├── 01-authentication/
│   ├── 02-file-management/
│   ├── 03-project-management/
│   ├── 04-spec-editor/
│   ├── 05-voice-input/
│   ├── 06-ai-integration/
│   ├── 07-history-system/
│   ├── 08-consistency-checker/
│   ├── 09-knowledge-memory/
│   ├── 10-theme-system/
│   ├── 11-dashboard/
│   ├── 12-routing-navigation/
│   ├── 13-error-ui/
│   ├── 14-mobile-responsive/
│   ├── 15-api-client/
│   ├── 16-state-management/
│   ├── 17-monitoring/
│   ├── 18-realtime/
│   ├── 19-performance/
│   ├── 20-testing/
│   ├── 21-i18n/
│   └── 22-golang-search-cli/
├── 06-error-management/           # Error handling (frontend/backend)
├── 07-database-design/            # Schema, migrations, ERD
├── 08-roadmap-overview/           # Roadmap, glossary, guidelines
├── 09-diagrams/                   # Workflow diagrams
├── 10-research/                   # Research notes
├── 11-skipped-features/           # Deferred for simplicity
│   ├── 00-overview.md             # Why features are skipped
│   ├── 01-grafana-monitoring.md   # Complex monitoring (skipped)
│   ├── 02-kubernetes-deployment.md # Container orchestration (skipped)
│   └── 03-centralized-logging.md  # ELK stack (skipped)
└── 99-consistency-report.md       # Auto-generated report
```

---

## See Also

- [Folder Structure Guideline](../00-folder-structure-guideline.md)
- [General Spec Overview](../general-spec/00-overview.md)
- [Skipped Features](./11-skipped-features/00-overview.md)
