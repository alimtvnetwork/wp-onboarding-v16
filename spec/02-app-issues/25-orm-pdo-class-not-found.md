# Issue: ORM PDO Class Not Found in Riseup Asia Uploader

> **ID:** 25-orm-pdo-class-not-found
> **Date:** 2026-03-13
> **Category:** WordPress/PHP
> **Status:** Open

---

## Issue Summary

1. **What happened:** The Riseup Asia Uploader admin page crashes with `Class "RiseupAsia\Database\Traits\PDO" not found` when rendering the Logs page.
2. **Where it happened:** `wp-plugins/riseup-asia-uploader/includes/Database/Traits/OrmQueryTrait.php:145` — the `count()` method references `self::$pdo` which resolves correctly, but `PDO::FETCH_ASSOC` on line 148 is interpreted as `RiseupAsia\Database\Traits\PDO` because the `use PDO;` import is scoped to the trait's namespace.
3. **Symptoms and impact:** The admin settings/logs page is completely broken. Users see a fatal error when navigating to the Riseup Asia Uploader admin page. The error occurs on every page load (confirmed twice: 10:21 and 23:32 on 2026-03-13).
4. **How it was discovered:** Production stack traces from `demoat.attoproperty.com.au`.

## Root Cause Analysis

1. **Direct cause:** The `use PDO;` import statement on line 16 of `OrmQueryTrait.php` is present in the current codebase, but the deployed version on the remote site is missing it. This indicates either: (a) the fix was applied locally but never deployed, or (b) a deployment overwrote the file with a stale version.
2. **Contributing factors:** PHP traits inherit the namespace of the file they're defined in (`RiseupAsia\Database\Traits`). Without an explicit `use PDO;`, any bare `PDO` reference resolves to `RiseupAsia\Database\Traits\PDO`, which doesn't exist.
3. **Triggering conditions:** Any call to `count()`, `findOne()`, or `findMany()` in the ORM — specifically the `PDO::FETCH_ASSOC` constant usage on lines 99, 122, and 148.
4. **Why the existing spec did not prevent it:** No deployment verification step confirms that the deployed PHP files match the local codebase.

## Stack Trace

```
#0 DatabaseQuerySearchTrait.php(65): RiseupAsia\Database\Orm->count()
#1 DatabaseQuerySearchTrait.php(44): RiseupAsia\Database\Database->executeTransactionQuery()
#2 AdminPagesTrait.php(38): RiseupAsia\Database\Database->queryTransactions()
#3 class-wp-hook.php(341): RiseupAsia\Admin\Admin->renderLogsPage()
#4 class-wp-hook.php(365): WP_Hook->apply_filters()
#5 plugin.php(522): WP_Hook->do_action()
#6 admin.php(264): do_action()
#7 {main}
```

## Fix Description

1. **Verify deployment:** Confirm the `use PDO;` import is present in the deployed `OrmQueryTrait.php` on the remote site. If not, redeploy the plugin.
2. **Add post-deploy verification:** After each deployment, verify critical PHP files contain expected `use` imports.
3. **Prevention:** Add a syntax/import check to the deployment pipeline that validates all PHP files resolve class references correctly.

## Prevention and Non-Regression

1. **Prevention rule:** All PHP files using native classes (PDO, DateTime, Throwable, etc.) inside namespaced code MUST have explicit `use` imports. The deployment pipeline should verify file checksums after upload.
2. **Acceptance criteria:** Navigate to Riseup Asia Uploader admin page → Logs page loads without fatal errors.
3. **Guardrails:** Post-deployment health check should hit the admin page and verify no 500 errors.

## TODO and Follow-Ups

1. Redeploy Riseup Asia Uploader plugin to remote site
2. Verify the deployed `OrmQueryTrait.php` contains `use PDO;`
3. Add post-deploy file integrity check to the deployment pipeline

## Done Checklist

- [ ] Spec updated under `../01-app/`
- [x] Issue write-up created under `./`
- [ ] Memory updated with summary and prevention rule
- [ ] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable
