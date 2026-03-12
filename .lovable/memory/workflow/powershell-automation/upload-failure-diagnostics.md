# Memory: workflow/powershell-automation/upload-failure-diagnostics

**Rule:** Every HTTP failure in the deployment script (`upload-plugin-v2.ps1`) MUST print three pieces of diagnostic information:

1. **HTTP status code** — extracted via `Get-ErrorStatusCode` from the exception's Response object
2. **Parsed JSON error summary** — extracted via `Get-JsonErrorSummary` (message, code, status, rootCause fields)
3. **Response body preview** — extracted via `Get-ResponsePreview` (first 400-500 chars, single-line)

**Anti-pattern (prohibited):**
```powershell
# WRONG — silent failure, no actionable info
Write-Status "⚠ Upload failed on ${ns}, trying next namespace..." -Color Yellow
```

**Correct pattern:**
```powershell
# RIGHT — always show WHY it failed
$statusCode = Get-ErrorStatusCode $_
$jsonSummary = Get-JsonErrorSummary $attemptBody
Write-Status "⚠ Upload failed on ${ns}, trying next namespace..." -Color Yellow
Write-Status "  Detail: HTTP ${statusCode} | ${jsonSummary}" -Color DarkYellow
```

**Applies to:** `Invoke-SafeRestRequest` retry loop, namespace fallback loop (Step 7), and the final failure summary before throwing.

**Reference:** `spec/02-app-issues/22-auth-return-type-fatal-error.md`
