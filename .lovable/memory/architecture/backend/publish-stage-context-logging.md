# Memory: architecture/backend/publish-stage-context-logging
Updated: 2026-02-05

The publish pipeline requires granular context logging for the 'Upload' and 'Activate' stages. Logs must explicitly document 'what' is being processed (plugin slug/ZIP path), 'why' (user intent/trigger), 'where' (target site/endpoint URL), and the 'inner results' (HTTP status codes, full response bodies, and multipart form-data metadata). This level of detail ensures that transient network failures or remote server errors are captured with sufficient diagnostic data in the session logs.

## StageContext Structure

```go
type StageContext struct {
    What       string                 // What is being done (e.g., "Uploading ZIP (143KB)")
    Why        string                 // Why it's being done (e.g., "Deploy plugin update")
    Where      string                 // Target URL/endpoint
    Result     string                 // Outcome description (e.g., "SUCCESS", "FAILED: 404")
    InnerData  map[string]interface{} // HTTP status, response body, timing, etc.
}
```

## Helper Functions

- `runStageWithSession()` - Executes stage with session LogStageStart/End
- `broadcastStageLog()` - Sends structured context to WebSocket and session
- `broadcastStageComplete()` - Emits `stage_complete` event with duration/details
- `formatBytes()` - Human-readable byte sizes
- `truncateString()` - Limits response body length in logs

## Log Format Example

```
───────────────────────────────────────────────────────────────────────────────
 STAGE: UPLOAD
───────────────────────────────────────────────────────────────────────────────
[2026-02-05 14:30:00] [INFO] [upload] Uploading ZIP (143KB) to WordPress → Plugin uploaded and activated
    {
      "What": "Upload ZIP (143KB)",
      "Why": "Deploy category-generator plugin update to production",
      "Where": "https://example.com/wp-json/riseup-asia-uploader/v1/upload",
      "Result": "SUCCESS - plugin uploaded and activated",
      "DurationMs": 4200,
      "UploadResponse": { "Success": true, "Overwritten": true }  // external key values
    }

✓ STAGE UPLOAD completed (completed) in 4200ms
```
