# Memory: architecture/database/enum-storage-format
Updated: 2026-02-21

Enums stored in the database (SQLite tables and WordPress options) follow a PascalCase string format (e.g., 'PerTable', 'Pending'). This ensures consistency with the cross-language enum specification and is maintained via transactional migration routines that normalize legacy snake_case or uppercase values.

## Relationship to Database Naming

Enum values stored in PascalCase columns (per the PascalCase database naming convention in `spec/03-coding-guidelines/database-naming.md`) maintain PascalCase for both the column name and the stored value. For example: column `Status` stores values like `'Pending'`, `'Active'`, `'Error'`.
