# Issue: QUpload Activate Endpoint Should Use PUT Not POST

> **ID:** 26-qupload-activate-should-use-put
> **Date:** 2026-03-13
> **Category:** API/REST Convention
> **Status:** Resolved

---

## Issue Summary

1. **What happened:** The QUpload plugin's `/activate` REST endpoint uses `POST` method, but it should use `PUT` per REST conventions — activation is an idempotent state change on an existing resource, not resource creation.
2. **Where it happened:** Multiple layers — PHP route registration, Go backend client, PowerShell scripts, frontend API calls, and specs.
3. **Symptoms and impact:** Semantic API inconsistency. No functional breakage currently, but violates REST best practices and could cause issues with caching proxies or API gateways that treat PUT and POST differently.
4. **How it was discovered:** User review of API design.

## Root Cause Analysis

1. **Direct cause:** The endpoint was originally implemented as POST without REST semantics review.
2. **Contributing factors:** No API design review process for HTTP method selection.
3. **Triggering conditions:** N/A — design issue, not a runtime bug.
4. **Why the existing spec did not prevent it:** No spec rule mandating PUT for idempotent state mutations on existing resources.

## Affected Files

### PHP (QUpload Plugin)
- `wp-plugins/qupload/includes/Traits/Route/RouteRegistrationTrait.php` — line 60: `HttpMethodType::Post->value` → `HttpMethodType::Put->value`
- `wp-plugins/qupload/includes/Enums/HttpMethodType.php` — ensure `Put` case exists

### PHP (Riseup Asia Uploader — plugins-onboard)
- `wp-plugins/plugins-onboard/api/Api.php` — line 179: `'methods' => 'POST'` for enable endpoint → `'PUT'`

### Go Backend
- `backend/internal/wordpress/QUploader.go` — line 76: `httpmethod.Post` → `httpmethod.Put`
- `backend/internal/wordpress/EndpointMap.go` — lines 101, 162: EPEnablePlugin method → `httpmethod.Put`

### Frontend (TypeScript)
- Any API client methods calling the enable/activate endpoint — update method from POST to PUT

### PowerShell
- Any scripts calling the activate endpoint directly — update `-Method` from `POST` to `PUT`

## Fix Description

1. **PHP:** Change the `methods` parameter in `register_rest_route` for the activate endpoint from `POST` to `PUT` (use `WP_REST_Server::EDITABLE` or `HttpMethodType::Put`).
2. **Go:** Update the HTTP method constant for the enable/activate operation.
3. **Frontend:** Update API client method.
4. **Spec:** Add rule: "Idempotent state mutations (activate, deactivate, enable, disable) MUST use PUT. Resource creation uses POST. Resource deletion uses DELETE."

## Prevention and Non-Regression

1. **Prevention rule:** All REST endpoints that perform idempotent state changes on existing resources MUST use PUT. POST is reserved for resource creation or non-idempotent operations.
2. **Acceptance criteria:** Activating a plugin via the API succeeds with PUT method; POST returns 405 Method Not Allowed.
3. **Guardrails:** API spec review checklist must include HTTP method validation.

## TODO and Follow-Ups

1. Update PHP route registration (QUpload + plugins-onboard)
2. Update Go backend client and endpoint map
3. Update frontend API calls
4. Update PowerShell scripts
5. Update specs and memory docs
6. Deploy and verify end-to-end

## Done Checklist

- [ ] Spec updated under `../01-app/`
- [x] Issue write-up created under `./`
- [ ] Memory updated with summary and prevention rule
- [ ] Acceptance criteria updated or added
- [ ] Iterations recorded if applicable
