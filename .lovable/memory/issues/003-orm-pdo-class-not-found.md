# Issue: ORM PDO Class Not Found on Remote Site

> **Severity:** Critical (admin page broken)
> **Date:** 2026-03-13
> **Status:** Open

## Summary

The Riseup Asia Uploader admin page crashes with `Class "RiseupAsia\Database\Traits\PDO" not found` at `OrmQueryTrait.php:145`. The `use PDO;` import exists in the local codebase (line 16) but the deployed version on `demoat.attoproperty.com.au` is missing it, indicating a stale deployment.

## Root Cause

The deployed `OrmQueryTrait.php` lacks the `use PDO;` import. Without it, PHP resolves bare `PDO` to the trait's namespace `RiseupAsia\Database\Traits\PDO`, which doesn't exist.

## Fix

Redeploy the Riseup Asia Uploader plugin. The local codebase already has the fix (line 16: `use PDO;`).

## Prevention

Add post-deployment file integrity verification to confirm critical imports are present in deployed files.

## Reference

- Full write-up: `spec/02-app-issues/25-orm-pdo-class-not-found.md`
