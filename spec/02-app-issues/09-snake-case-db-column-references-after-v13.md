# Issue #09 — snake_case DB Column References After V13 Migration

## Issue Summary

1. **What happened**: After the V13 PascalCase migration renamed all SQLite columns from snake_case to PascalCase, multiple PHP files continued referencing the old snake_case column names in array accesses and raw SQL queries.
2. **Where it happened**: Snapshot export/import subsystem and transaction logging.
   - `includes/Snapshot/Traits/ExporterPublicApiTrait.php`
   - `includes/Snapshot/Traits/ExporterHelpersTrait.php`
   - `includes/Traits/Snapshot/SnapshotExportHandlerTrait.php`
   - `includes/Traits/Snapshot/SnapshotImportStreamTrait.php`
   - `includes/Database/Traits/DatabaseQueryLogTrait.php`
   - `includes/Logging/Traits/LoggerContextTrait.php`
   - `includes/Traits/Upload/UploadPipelineTrait.php`
   - `includes/Traits/Upload/UploadZipTrait.php`
   - `includes/Traits/FileSystem/FileSystemPluginTrait.php`
3. **Symptoms and impact**: After V13 migration renames columns to PascalCase, `$export['zip_path']` returns `null` instead of the actual path. This silently breaks snapshot ZIP downloads, invalidation, and streaming. Internal array keys (`$enhanced['source_machine']`) violate the camelCase naming standard.
4. **How it was discovered**: Fresh codebase audit searching for snake_case patterns in PHP code outside migration files.

## Root Cause Analysis

1. **Direct cause**: V13 migration was implemented but the consumer code referencing DB column names was not updated simultaneously.
2. **Contributing factors**: No automated enforcement (lint rule) to verify that PHP array accesses on DB result arrays use PascalCase column names.
3. **Triggering conditions**: Any code path that reads from `SnapshotExports`, `Snapshots`, or `Transactions` tables after V13 migration has run.
4. **Why the existing spec did not prevent it**: The enum-consumer-checklist covered enum value propagation but did not cover raw SQL column references or array key access patterns on DB result sets.

## Fix Description

1. **ExporterPublicApiTrait.php**: Changed `$export['zip_path']` → `$export['ZipPath']`, `$export['id']` → `$export['Id']`, `$export['status']` → `$export['Status']`; fixed raw SQL to use `Status`, `ExpiresAt`, `Id`.
2. **ExporterHelpersTrait.php**: Fixed `WHERE id = ?` → `WHERE Id = ?`, `$snapshot['scope']` → `$snapshot['Scope']`, `$snapshot['status']` → `$snapshot['Status']`.
3. **SnapshotExportHandlerTrait.php**: Changed all `$export['snake_case']` to `$export['PascalCase']` for DB columns.
4. **SnapshotImportStreamTrait.php**: Changed `$export['zip_path']` → `$export['ZipPath']`, `$export['zip_filename']` → `$export['ZipFilename']`; also fixed internal `'export_id'` key to `'exportId'`.
5. **DatabaseQueryLogTrait.php**: Converted all `$enhanced` and `$params` internal array keys from snake_case to camelCase (e.g., `'plugin_file'` → `'pluginFile'`, `'agent_site_id'` → `'agentSiteId'`).
6. **LoggerContextTrait.php**: Changed `$enhanced['source_machine']` → `$enhanced['sourceMachine']`, `$enhanced['plugin_version']` → `$enhanced['pluginVersion']`.
7. **UploadPipelineTrait.php** and **UploadZipTrait.php**: Changed `'upload_source'` → `'uploadSource'` and `'plugin_version'` → `'pluginVersion'` in enhanced field arrays.
8. **FileSystemPluginTrait.php**: Changed `'plugin_file'` → `'pluginFile'` in log context arrays.

## Prevention and Non-Regression

1. **Prevention rule**: When a database migration renames columns, ALL consumer code reading from those tables must be updated in the same commit. Use `grep -r "old_column_name"` to find all references.
2. **Acceptance criteria**: Search for any snake_case string matching a known DB column name in PHP code outside of migration files and backward-compat maps — zero results expected.
3. **Guardrails**: Add a CI grep check: `grep -rn "'[a-z_]*_[a-z]'" --include="*.php" includes/ | grep -v "Migration\|RootDb\|V1[0-9]\|_snapshot_meta\|__(\|esc_html"` should return only persistence-exempt keys.
4. **Spec references**: `../01-app/enum-consumer-checklist.md` (updated to include DB column reference rule).

## TODO and Follow-Ups

1. [ ] Add `RULE-DB-COLUMN-SYNC` to enum-consumer-checklist.md
2. [ ] Consider creating a `SnapshotExportColumnType` enum for typed column access

## Done Checklist

- [x] Spec updated under ../01-app/
- [x] Issue write-up created under ./
- [x] Memory updated with summary and prevention rule
- [x] Acceptance criteria updated or added
- [x] Iterations recorded if applicable (N/A — single pass)
