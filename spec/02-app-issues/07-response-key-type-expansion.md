# 07 — ResponseKeyType Enum Expansion

> **Created:** 2026-02-23

## Issue Summary

1. **What happened:** Magic string keys (`'settings'`, `'providers'`, `'dependencies'`, `'slug'`, `'title'`, `'type'`, `'plugin_file'`) were used across ~15 PHP files instead of `ResponseKeyType` enum references, violating the zero-magic-string policy.
2. **Where:** Upload traits (`UploadInstallExtractTrait`, `UploadPipelineTrait`, `UploadZipTrait`, `UploadParserTrait`), Snapshot traits (`SnapshotSettingsHandlerTrait`, `SnapshotBackupOpsTrait`, `SnapshotBackupExecTrait`, `WorkerSetupTrait`, `AnalyzerQueryTrait`, `DetectorProviderTrait`), Database traits (`RootDbSchemaTrait`, `RootDbRegistrationTrait`), Plugin lifecycle traits (`PluginLifecycleHelpersTrait`, `PluginLifecycleDeleteTrait`, `PluginLifecycleEnableTrait`), and `ErrorLogHandlerTrait`.
3. **Symptoms:** Inconsistent key usage (mix of enum and raw strings in same arrays), risk of typo-induced silent failures, and desynchronization across PHP/Go/TS.
4. **How discovered:** Full codebase audit scanning for snake_case and raw string array keys.

## Root Cause Analysis

1. **Direct cause:** The `ResponseKeyType` enum was missing common domain keys that were added incrementally without being standardized.
2. **Contributing factors:** No automated enforcement; existing code predated the enum expansion effort.
3. **Triggering conditions:** Any array literal using a key not yet covered by the enum.
4. **Why the spec didn't prevent it:** The enum-consumer-checklist existed but only covered adding NEW cases, not auditing for missing ones.

## Fix Description

1. **Enum additions:** Added 7 new cases to PHP, Go, and TypeScript: `Settings`, `Providers`, `Dependencies`, `Slug`, `Title`, `Type`, `PluginFile`.
2. **Consumer migration:** All ~15 PHP consumer files updated to use `ResponseKeyType::X->value` instead of raw strings.
3. **Bug fix:** `WorkerSetupTrait` accessed `$analysis['seed_order']` (snake_case) but the actual key was `ResponseKeyType::SeedOrder->value` = `'seedOrder'` — fixed to use the enum reference.
4. **Bug fix:** `WorkerSetupTrait` used `'pool_size'` (snake_case) in log contexts instead of `ResponseKeyType::PoolSize->value` = `'poolSize'` — fixed.
5. **Key rename:** `'plugin_file'` (snake_case) → `ResponseKeyType::PluginFile->value` = `'pluginFile'` (camelCase). All consumers of `$resolved['plugin_file']` updated.

## Prevention and Non-Regression

1. **Prevention rule:** Any new structured array key in PHP must first be added to `ResponseKeyType` before use. No raw string keys in response arrays, service results, or log contexts.
2. **Acceptance criteria:** `grep -rn "'[a-z_]\{3,\}'" --include="*.php" includes/` should return zero hits for domain-level keys not covered by the enum (excluding API input reads, SQL heredocs, and Autoloader).
3. **Guardrails:** Periodic audit; future linting automation recommended.
4. **Spec references:** `/spec/01-app/enum-consumer-checklist.md`, `/spec/04-php-standards/response-key-type-inventory.md`.

## Done Checklist

- [x] Spec updated — enum inventory expanded with 7 new cases
- [x] Issue write-up created at `/spec/02-app-issues/07-response-key-type-expansion.md`
- [x] Memory updated with prevention rule
- [x] Acceptance criteria documented above
- [x] Iterations: not applicable (single-pass fix)
