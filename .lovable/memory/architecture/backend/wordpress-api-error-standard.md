 # WordPress API Error Standard
 
 **Updated:** 2026-02-05
 
 ## Mandatory Error Structure
 
 All WordPress REST API errors MUST use the `APIError` struct defined in `backend/internal/wordpress/client.go`:
 
 ```go
 type APIError struct {
     Operation     string // What action was being performed
     Method        string // HTTP method (GET, POST, PUT, DELETE)
     Endpoint      string // REST API endpoint path (e.g., /wp/v2/plugins)
     URL           string // Fully resolved URL
     StatusCode    int    // HTTP response status
     ResponseBody  string // Server response (truncated to 8KB)
     PluginSlugIn  string // Original slug provided (if applicable)
     PluginIDUsed  string // Resolved plugin identifier (if applicable)
     StackTrace    string // Call stack at error time
 }
 ```
 
 ## Why This Matters
 
 When debugging WordPress integration issues, the endpoint URL is **critical** for:
 - Verifying the correct API route is being called
 - Checking if the endpoint exists on the remote WordPress site
 - Diagnosing authentication or permission issues
 - Comparing expected vs actual API behavior
 
 ## Implementation Rules
 
 1. **Never use `fmt.Errorf()`** for WordPress API failures
 2. **Always capture the endpoint path** separate from the full URL
 3. **Include response body** (truncated to 8KB) for server error context
 4. **Capture stack trace** using `captureStackTrace()` for critical failures
5. **Error strings MUST include method + endpoint** (at minimum) so failures like "status 500" are actionable
6. **Log the full URL** in error messages/details, not just status codes
7. **Always resolve file paths to absolute** before passing to upload functions
8. **Check endpoint status** before attempting uploads (call `/status` first)

### Required `Error()` Output

`APIError.Error()` MUST include the method + endpoint when present:

```
upload plugin via RiseupAsia Uploader (POST /riseup-asia-uploader/v1/upload): status 500
```

## Pre-Upload Validation

Before any upload operation:

1. Resolve ZIP path to absolute using `pathutil.ToAbsolute()`
2. Call the `/status` endpoint to verify the uploader is available
3. Log the full absolute URL being called
4. Only then proceed with the upload

This prevents cryptic errors and ensures all paths in logs are actionable.
 
 ## Example Usage
 
 ```go
 // WRONG - Missing endpoint context
 return fmt.Errorf("upload failed: status 500")
 
 // CORRECT - Full diagnostic context
 return &APIError{
     Operation:    "upload plugin via RiseupAsia Uploader",
     Method:       "POST",
     Endpoint:     "/riseup-asia-uploader/v1/upload",
     URL:          "https://example.com/wp-json/riseup-asia-uploader/v1/upload",
     StatusCode:   500,
     ResponseBody: `{"error": "Internal server error", "details": "..."}`,
     StackTrace:   captureStackTrace(2),
 }
 ```
 
 ## Frontend Display
 
 The Global Error Modal and Publish Progress Dialog parse `APIError` to display:
 - Request method and endpoint
 - Full resolved URL
 - Response status and body
 - Stack trace (in dedicated tab)
 
 This enables users to immediately understand what API call failed and why.

## Prohibited Patterns

The following patterns are **PROHIBITED** in the wordpress package:

| Pattern | Replacement |
|---------|-------------|
| `fmt.Errorf()` | `apperror.Wrap()` or `apperror.New()` |
| `filepath.Join()` | `pathutil.MustJoin()` or `pathutil.Join()` |
| `filepath.Abs()` | `pathutil.ToAbsolute()` or `pathutil.MustAbsolute()` |

## PowerShell Integration

The `powershell.go` file uses structured errors for platform validation:
- `ErrPublishPlatform` (E11001) for Windows-only operations
- `ErrPublishConfig` (E11002) for configuration marshaling failures

All script paths are resolved via `pathutil.MustJoin` and `pathutil.MustAbsolute`.