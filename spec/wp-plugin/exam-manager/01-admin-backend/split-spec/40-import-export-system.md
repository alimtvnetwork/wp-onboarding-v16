# 38. Import/Export System

## Overview
Comprehensive data import and export functionality for backups, migration, and data portability.

---

## 38.1 Full Plugin Export

### Included Data
- All exams with content and settings
- All participants with progress
- All checklists and completions
- Wiki pages and revisions
- Secret keys (without usage analytics)
- Plugin settings

### Export Format
- JSON file with structured data
- Version number for compatibility
- Timestamp of export
- Checksum for integrity verification

### Acceptance Criteria:
- [ ] Export creates single downloadable file
- [ ] Large exports don't timeout (background processing)
- [ ] Progress indicator during export
- [ ] File named with date: `eqm-export-YYYY-MM-DD.json`

---

## 38.2 Selective Export

### Export Options
- **Exams**: Select specific exams to export
- **Include Participants**: Toggle on/off
- **Include Progress**: Toggle on/off
- **Include Wiki**: Toggle on/off
- **Include Settings**: Toggle on/off

### Acceptance Criteria:
- [ ] Checkbox tree for granular selection
- [ ] Dependencies shown (e.g., progress requires participants)
- [ ] Export size estimate shown
- [ ] Preview of what will be exported

---

## 38.3 Import Process

### Import Steps
1. Upload export file
2. Validate file format and version
3. Analyze contents and show summary
4. Conflict resolution options
5. Confirm and execute import
6. Display results

### Conflict Handling
- **Duplicate Exams**: Skip / Rename / Overwrite
- **Duplicate Participants**: Skip / Update / Create new
- **Settings**: Keep current / Overwrite

### Acceptance Criteria:
- [ ] File validation before processing
- [ ] Version compatibility check
- [ ] Clear summary of what will be imported
- [ ] Conflict resolution per item or global
- [ ] Rollback on partial failure
- [ ] Detailed import log available

---

## 38.4 Exam Content Export

### Single Exam Package
- Exam metadata (JSON)
- Content file (Markdown)
- Prerequisites list
- Checklist items
- Participant template (CSV)

### Acceptance Criteria:
- [ ] Creates ZIP package with all files
- [ ] Markdown preserves all formatting
- [ ] Package can be re-imported to recreate exam
- [ ] Includes README with import instructions

---

## 38.5 Database Backup

### Backup Types
- **Full Backup**: Complete SQLite database file
- **Incremental**: Changes since last backup (future)

### Backup Settings
- Automatic backup schedule (daily/weekly)
- Retention count (number of backups to keep)
- Backup location (uploads directory)

### Acceptance Criteria:
- [ ] Backup creates .db file copy
- [ ] Timestamp in filename
- [ ] Old backups auto-deleted per retention
- [ ] Manual backup button available
- [ ] Restore from backup with confirmation

---

## 38.6 Migration Tools

### From Other Systems
- CSV import for basic exam data
- Mapping interface for field matching
- Data transformation rules

### To Other Systems
- Standard CSV export formats
- API for programmatic access
- Webhook for real-time sync (future)

### Acceptance Criteria:
- [ ] CSV import wizard with field mapping
- [ ] Preview transformation results
- [ ] Handle encoding issues (UTF-8)
- [ ] Support large files (chunked processing)

---

## 38.7 Scheduled Exports [OPTIONAL]

### Configuration
- Export frequency (daily/weekly/monthly)
- Export type (full/selective)
- Delivery method (email/download/external storage)

### Acceptance Criteria:
- [ ] Cron job for scheduled exports
- [ ] Email with download link
- [ ] Cleanup old scheduled exports
- [ ] Failure notifications to admin
