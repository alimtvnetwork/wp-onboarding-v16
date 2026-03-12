# Issue: checkAuthenticatedOnly() Return Type Fatal — WP_User Returned Instead of true|WP_Error

> **ID:** 22-auth-return-type-fatal-error
> **Date:** 2026-03-12
> **Category:** WordPress/PHP — Auth/REST
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** The `/status` and `/upload` REST endpoints returned HTTP 500 with fatal error: `RiseupAsia\Core\Plugin::checkAuthenticatedOnly(): Return value must be of type WP_Error|true, WP_User returned`.
2. **Where it happened:** `AuthCredentialTrait::checkAuthenticatedOnly()` and `AuthPermissionTrait::checkStatusPermission()` — used as permission callbacks for all REST routes.
3. **Symptoms and impact:** All REST API endpoints (status, upload, etc.) returned HTTP 500, making the plugin completely inoperable remotely. The deployment script could not check remote version (Step 3) or upload (Step 7), falling through all namespace retries silently.
4. **How it was discovered:** Deployment script output showed `⚠ Upload failed on {namespace}, trying next namespace...` for all three namespaces with no diagnostic detail about *why* each failed.

## Root Cause Analysis

1. **Direct cause:** `checkAuthenticatedOnly()` declared return type `true|WP_Error` but its `resolveAndAuthenticate()` helper returned `WP_User` on success. The method passed that `WP_User` straight through instead of converting it to `true`.
2. **Contributing factors:**
   - `resolveAndAuthenticate()` returns `WP_User|WP_Error` (correct for its purpose), but `checkAuthenticatedOnly()` must narrow `WP_User` → `true` for the permission callback contract.
   - `checkStatusPermission()` in `AuthPermissionTrait` called `checkAuthenticatedOnly()` and also did not defensively normalize the result.
   - PHP 8.x enforces return type declarations strictly — returning `WP_User` when `true|WP_Error` is declared triggers a fatal `TypeError`.
3. **Triggering conditions:** Any authenticated request to any endpoint using `checkAuthenticatedOnly()` or `checkStatusPermission()` as the permission callback.
4. **Why the existing spec did not prevent it:** No explicit rule existed requiring that auth helper return types be validated against the WordPress permission callback contract (`true|WP_Error`). No local PHP CLI was available to catch the fatal before upload.

## Fix Description

1. **What was changed:**
   - `AuthCredentialTrait::checkAuthenticatedOnly()` — Added explicit check: if `resolveAndAuthenticate()` returns a `WP_User` (not `WP_Error`), return `true` instead of the `WP_User` object.
   - `AuthPermissionTrait::checkStatusPermission()` — Added defensive normalization: check `is_wp_error($authResult)` and return `true` if no error, rather than passing through the raw result.
2. **New rules or constraints added:**
   - **RULE:** Any method with return type `true|WP_Error` used as a WordPress permission callback MUST explicitly convert successful auth results (`WP_User`) to `true`. Never pass through a `WP_User` object.
   - **RULE:** The deployment script must show HTTP status code, parsed JSON error summary, and response body preview on every failed namespace attempt — not just "trying next namespace."
3. **Why the fix resolves the root cause:** The return type now always satisfies the `true|WP_Error` contract. `WP_User` is never leaked to WordPress's permission callback dispatcher.
4. **Config changes or defaults affected:** None.
5. **Logging or diagnostics required:** Upload script now prints `Detail:` lines with HTTP status, JSON error summary (message/code/rootCause), and body preview for every failed attempt.

## Iterations History

1. **Iteration 1:** Fixed `checkAuthenticatedOnly()` to return `true` instead of `WP_User` → Upload still failed because the *old* code was cached on the server (OPcache) and the script gave no detail about *why* each namespace failed.
2. **Iteration 2:** Also fixed `checkStatusPermission()` defensively + added OPcache reset detection in the script → Still failed silently because the script's retry/fallback loop only printed generic "trying next namespace" with no HTTP status or error body.
3. **Iteration 3:** Added `Get-ErrorStatusCode`, `Get-ResponsePreview`, `Get-JsonErrorSummary` helper functions to the deployment script. Every retry and namespace fallback now prints HTTP status + parsed JSON error summary + body preview. Added per-namespace diagnostic summary before the final throw. → **Succeeded.** The combination of the PHP fix + detailed script diagnostics resolved the issue.

## Prevention and Non-Regression

1. **Prevention rule:**
   - **PHP:** Every permission callback method (`check*Permission`, `checkAuthenticatedOnly`) MUST have an explicit `true` return for the success path. Never return or pass through `WP_User` from a `true|WP_Error` method.
   - **Script:** Every HTTP failure in the deployment script MUST print: (a) HTTP status code, (b) parsed JSON error summary if available, (c) response body preview. Silent "trying next" messages are prohibited.
2. **Acceptance criteria / test scenarios:**
   - Authenticated request to `/status` returns 200 with version data.
   - Authenticated request to `/upload` with valid ZIP returns success envelope.
   - Failed upload attempt in script prints `Detail:` line with HTTP status and error message.
3. **Guardrails or linting policies:**
   - Enable local PHP CLI (`php -l`) before upload to catch fatal type errors pre-deployment.
   - The backed enum lint already runs; consider adding a static analysis pass for return type mismatches.
4. **References to updated spec sections:**
   - `spec/02-app-issues/22-auth-return-type-fatal-error.md` (this file)
   - `.lovable/memory/issues-fixed/00-index.md` (updated)
   - `.lovable/memory/coding-standards/` (new memory entry)

## TODO and Follow-Ups

1. Consider adding PHPStan or Psalm level-5 static analysis to the pre-upload lint step to catch return type mismatches automatically.

## Done Checklist

- [x] Issue write-up created under `spec/02-app-issues/`
- [x] Memory updated with summary and prevention rule
- [x] Index updated in `.lovable/memory/issues-fixed/00-index.md`
- [x] Iterations recorded
- [x] Deployment script enhanced with detailed failure diagnostics
