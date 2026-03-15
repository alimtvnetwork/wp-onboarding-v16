# Issue: `-cla` Fails Across Sites (`rest_disabled` + `machine_not_approved`)

> **ID:** 31-cla-failure-endpoint-gating-and-machine-approval  
> **Date:** 2026-03-15  
> **Category:** PowerShell / WordPress REST / Security Settings  
> **Status:** Open

---

## Issue Summary

1. **What happened:** Running `./run.ps1 -cla` failed on all target sites for both plugins.
2. **Where it happened:**
   - PowerShell orchestrator: `wp-plugins/scripts/modules/mode-clear-logs.ps1`
   - Riseup endpoint auth/enable gating: `includes/Admin/*`, `includes/Traits/Auth/AuthPermissionTrait.php`
   - QUpload machine validation: `includes/Traits/Log/LogClearingTrait.php`
3. **Symptoms and impact:**
   - Riseup Asia returned `403 rest_disabled - This endpoint is disabled` on Step 1 (`DELETE /logs/clear`).
   - QUpload returned `403 machine_not_approved` on Step 1.
   - No logs were cleared on any site (`Total: 6 | Success: 0 | Failed: 6`).
4. **How it was discovered:** Repeated real CLI runs with full response body output after PS 7+ diagnostics improvements.

## Root Cause Analysis

1. **Direct cause:**
   - **Riseup:** endpoint keys for remote log management (`logs_status`, `logs_clear`, `logs_confirm`, `logs_email`) were not fully represented in settings defaults/config metadata, so endpoint gating resolved to disabled in existing installs with older saved settings.
   - **QUpload:** machine approval is fail-closed and target sites had no matching machine (`ALIM-DESKTOP`) in `approved_machines`.
2. **Contributing factors:**
   - Existing settings payloads on remote sites were persisted before new endpoint keys were introduced.
   - Endpoint settings retrieval used shallow merge behavior, which does not safely backfill missing nested endpoint keys.
   - QUpload approval source was not explicit enough operationally (JSON vs option source mismatch perception).
3. **Triggering conditions:**
   - Running `-cla` against sites on mixed plugin versions (`2.13.x`, `2.14.x`) with old settings state.
   - Using a machine name not present in approved lists.
4. **Why existing spec did not prevent it:**
   - The two-step log-clear security spec documented fail-closed machine policy but did not enforce migration-safe endpoint defaults and nested settings merge requirements for newly added endpoint keys.

## Fix Description

1. **What was changed in implementation:**
   - Riseup endpoint settings now use deep merge (`array_replace_recursive`) for migration-safe defaults.
   - Missing endpoint keys in permission checks now default to secure-enabled behavior (`enabled=true`, `auth_required=true`).
   - Riseup admin endpoint metadata now explicitly includes remote log endpoints (`logs_status`, `logs_clear`, `logs_confirm`, `logs_email`, `error_sessions`).
   - QUpload machine approval now reads `settings.json` first, then falls back to WP option storage.
   - PowerShell troubleshooting text now explicitly distinguishes `rest_disabled` from `machine_not_approved`.
2. **New rules or constraints added:**
   - New endpoint keys must be backward-safe for existing saved settings.
   - Endpoint gating must never default to disabled solely because a key is missing after upgrade.
3. **Why this resolves the root cause:**
   - Riseup no longer fails closed due to missing migrated endpoint keys.
   - QUpload approval source is now deterministic and aligned with deployed plugin settings files.
4. **Config changes or defaults affected:**
   - Remote machine still must be explicitly approved (`approved_machines`) by design.
   - Remote sites must run updated plugin versions for the gating fix to take effect.
5. **Logging or diagnostics required:**
   - Keep response body previews enabled in `-cla` output.
   - Continue classifying Step 1 failures by `code` (`rest_disabled`, `machine_not_approved`, `rest_forbidden`, etc.).

## Iterations History

1. **Iteration 1 (v2.15):** Added `-cla` and targeting support → failures still opaque.
2. **Iteration 2 (v2.16):** Added PS 7+ error extraction and response previews → exposed true server-side causes.
3. **Iteration 3 (v2.17):** Fixed Riseup endpoint default/merge behavior, aligned QUpload machine approval source, improved troubleshooting text.

## Prevention and Non-Regression

1. **Prevention rule:** Every new REST endpoint key must be added with migration-safe defaults and validated against pre-existing saved options.
2. **Acceptance criteria / test scenarios:**
   - Given old saved settings (without new keys), `DELETE /logs/clear` must not return `rest_disabled` by default.
   - `-cla` must clearly surface `machine_not_approved` when machine is not whitelisted.
   - After approving machine + updated plugin deploy, `-cla` should succeed for both plugins.
3. **Guardrails or linting policies:**
   - Add a review checklist item: “new endpoint key added to defaults + admin metadata + migration-safe settings merge verified.”
4. **References to updated sections/files:**
   - `wp-plugins/riseup-asia-uploader/includes/Admin/Admin.php`
   - `wp-plugins/riseup-asia-uploader/includes/Admin/Traits/AdminSettingsTrait.php`
   - `wp-plugins/riseup-asia-uploader/includes/Admin/Traits/AdminPagesTrait.php`
   - `wp-plugins/qupload/includes/Traits/Log/LogClearingTrait.php`
   - `wp-plugins/scripts/modules/mode-clear-logs.ps1`

## Follow-Up: Remote Machine Approval (`-am` command)

**Added in v2.17.0** — A new `.\run.ps1 -am` command that remotely approves a machine via REST API on all sites, eliminating the need for redeployment.

### Architecture

1. **New REST endpoint:** `PUT /machines/approve` on both plugins (Riseup Asia + QUpload).
   - Accepts JSON body: `{ "machine": "MACHINE-NAME" }`
   - Requires `activate_plugins` capability (same as upload/log-clear).
   - Stores the machine in the WP option (`approved_machines` array) — persists without redeployment.
   - Idempotent: returns success with `already_approved: true` if machine is already in the list.

2. **New PowerShell module:** `wp-plugins/scripts/modules/mode-approve-machine.ps1`
   - `.\run.ps1 -am` — approves current machine (`$env:COMPUTERNAME`) on all enabled sites.
   - `.\run.ps1 -am 'CI-SERVER'` — approves a specific machine name on all sites.
   - Iterates all enabled sites × both plugins, calls `PUT /machines/approve` for each.
   - Summary table with per-site/plugin status.

3. **Files added/modified:**
   - `wp-plugins/riseup-asia-uploader/includes/Traits/Machine/MachineApprovalTrait.php` (new)
   - `wp-plugins/qupload/includes/Traits/Machine/MachineApprovalTrait.php` (new)
   - `wp-plugins/scripts/modules/mode-approve-machine.ps1` (new)
   - Both `EndpointType.php` enums: added `MachinesApprove` case
   - Both `RouteRegistrationTrait.php`: registered new route
   - Both `Plugin.php`: added `use MachineApprovalTrait`
   - `run.ps1`: added `-am` parameter, module dot-source, help text, early-exit handler

### Recommended Workflow

```
.\run.ps1 -uas          # Deploy v2.17.0 with the new endpoint to all sites
.\run.ps1 -am           # Remotely approve your machine on all sites (no redeploy needed)
.\run.ps1 -cla          # Now log clearing should succeed 6/6
```

## TODO and Follow-Ups

1. Deploy v2.17.0 to all target WordPress sites.
2. Run `.\run.ps1 -am` to approve `ALIM-DESKTOP` on all sites via REST API.
3. Re-run `.\run.ps1 -cla` and verify 6/6 success.

## Done Checklist

- [x] Spec updated under `../01-app/`
- [x] Issue write-up created under `./`
- [x] Memory updated with summary and prevention rule (this issue doc)
- [x] Acceptance criteria updated or added
- [x] Iterations recorded
- [x] Remote machine approval endpoint and CLI command added
