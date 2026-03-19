# Issue: Status Endpoint Returns PHP Noise Before JSON — Parsing Fails

**Date**: 2026-03-19  
**Severity**: Critical  
**Affected Commands**: `-am`, `-check`  
**Working Commands**: `-ps`, `-pas` (already fixed)  
**Version**: 2.27.0  

## Symptom

All sites report `NOT READY (no version in response)` even though the remote plugins are alive and responding with valid JSON.

```
[Atto Property Demo] Riseup Asia Uploader NOT READY (no version in response)
[Atto Property Demo] Quick Upload NOT READY (no version in response)
```

## Root Cause (Two Bugs)

### Bug 1: `Invoke-RestMethod` Cannot Parse Responses with PHP Noise

The remote WordPress sites have `WP_DEBUG_DISPLAY=On` (or a third-party plugin like UpdraftPlus triggers deprecation notices). This causes PHP warnings to be **prepended as HTML** before the JSON body:

```
\nDeprecated: Puc_v4p13_UpdateChecker::fixSupportedWordpressVersion()...
\n{\"Status\":{\"IsSuccess\":true,...},\"Results\":[{\"Version\":\"2.22.0\",...}]}
```

`Invoke-RestMethod` (used in `mode-approve-machine.ps1` and `mode-check.ps1`) tries to auto-parse the response as JSON. When the response starts with HTML, it **returns a raw string** instead of a parsed PSObject. Accessing `.version` on a string returns `$null`.

**`mode-plugin-status.ps1` already fixed this** by using `Invoke-WebRequest` + manual JSON extraction:
```powershell
$jsonStart = $rawStatusBody.IndexOf('{')
if ($jsonStart -gt 0) { $jsonBody = $rawStatusBody.Substring($jsonStart) }
$body = $jsonBody | ConvertFrom-Json
```

### Bug 2: Wrong Property Path for Version Extraction

Even if `Invoke-RestMethod` successfully parsed the JSON, the code checks the **wrong property path**:

```powershell
# WRONG — version is not at the top level
$hasVersion = ($statusResp.version)

# CORRECT — version is inside Results[0].Version in the envelope
$version = $body.Results[0].Version
```

The status response follows the standard envelope format:
```json
{
  "Status": { "IsSuccess": true, ... },
  "Attributes": { ... },
  "Results": [{ "Version": "2.22.0", "Plugin": "Riseup Asia Uploader", ... }]
}
```

## Fix

1. Replace `Invoke-RestMethod` with `Invoke-WebRequest` in all REST-calling modules
2. Strip PHP noise by finding the first `{` character before parsing JSON
3. Navigate the envelope: `$body.Results[0].Version` instead of `$body.version`
4. Both `mode-approve-machine.ps1` and `mode-check.ps1` must be updated

## Prevention Rules

1. **NEVER use `Invoke-RestMethod`** for WordPress REST API calls — always use `Invoke-WebRequest` + manual JSON parsing
2. **ALWAYS strip PHP noise** before parsing: find first `{` and substring from there
3. **ALWAYS use envelope-aware extraction**: access `Results[0].Version`, not top-level `.version`
4. **Test with `WP_DEBUG_DISPLAY=On`** sites to catch parsing issues early

## Files Changed

- `wp-plugins/scripts/modules/mode-approve-machine.ps1` — Switch to `Invoke-WebRequest`, strip noise, fix path
- `wp-plugins/scripts/modules/mode-check.ps1` — Same fixes
- `.lovable/memory/` — Updated with prevention rules
