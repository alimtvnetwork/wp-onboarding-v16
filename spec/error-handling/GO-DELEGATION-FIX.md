# Go Backend Fix: Missing Delegation Fields

> **Problem:** When the Go backend proxies a request to a downstream service (e.g., WordPress PHP plugin) and the downstream call fails, the response envelope is missing `Attributes.RequestDelegatedAt` and `Errors.DelegatedRequestServer`. This makes it impossible for the frontend error modal to show the full 3-hop request chain or the third-party's error details.

## What Must Change

When a handler in the Go backend makes an HTTP request to a downstream/delegated service and receives a **non-2xx response**, the envelope builder **MUST** populate:

### 1. `Attributes.RequestDelegatedAt` (string)

The **full URL** of the downstream endpoint that was called.

```go
// Example: in the handler that proxies to WordPress
envelope.Attributes.RequestDelegatedAt = delegatedURL
// e.g. "https://demoat.attoproperty.com.au/wp-json/riseup-asia-uploader/v1/snapshots/providers"
```

**Rule:** This field MUST be set for **every** delegated request — even successful ones. It tells the frontend that a 3rd-party hop occurred.

---

### 2. `Errors.DelegatedRequestServer` (object)

Structured error details from the downstream server. **Required when `IsFailed=true` AND `RequestDelegatedAt` is non-empty.**

```go
type DelegatedRequestServer struct {
    DelegatedEndpoint  string      `json:"DelegatedEndpoint"`   // Full URL
    Method             string      `json:"Method"`              // HTTP method used
    StatusCode         int         `json:"StatusCode"`          // Response status code
    RequestBody        interface{} `json:"RequestBody"`         // Request body sent (null for GET)
    Response           interface{} `json:"Response"`            // Full response body (parsed JSON if possible)
    StackTrace         []string    `json:"StackTrace"`          // PHP/Node/etc stack trace lines
    AdditionalMessages string      `json:"AdditionalMessages"`  // Human-readable diagnostic hint
}
```

---

## Implementation Pattern

In the handler or service layer that makes the delegated HTTP call:

```go
func (s *Service) fetchFromDelegatedServer(ctx context.Context, site *models.Site, path string) (*Envelope, error) {
    delegatedURL := fmt.Sprintf("%s/wp-json/%s", site.URL, path)
    
    req, err := http.NewRequestWithContext(ctx, http.MethodGet, delegatedURL, nil)
    if err != nil {
        return nil, apperror.Wrap(err, "E3001", "failed to build delegated request")
    }

    resp, err := s.httpClient.Do(req)
    if err != nil {
        return nil, apperror.Wrap(err, "E3001", "failed to reach delegated server").
            WithContext("delegatedURL", delegatedURL)
    }
    defer resp.Body.Close()

    bodyBytes, _ := io.ReadAll(resp.Body)

    // ALWAYS set RequestDelegatedAt
    envelope := NewEnvelope()
    envelope.Attributes.RequestDelegatedAt = delegatedURL

    if resp.StatusCode >= 400 {
        // Parse response body as JSON if possible
        var responseBody interface{}
        if err := json.Unmarshal(bodyBytes, &responseBody); err != nil {
            responseBody = map[string]string{"raw": string(bodyBytes)}
        }

        // Extract PHP stack trace if present in response
        phpStack := extractPHPStackTrace(responseBody)

        // Extract additional messages / log hints
        additionalMsg := extractLogHint(responseBody)

        envelope.Errors = &EnvelopeErrors{
            BackendMessage: fmt.Sprintf("[E3001] failed to fetch %s: %s (GET %s): status %d",
                path, path, path, resp.StatusCode),
            DelegatedRequestServer: &DelegatedRequestServer{
                DelegatedEndpoint:  delegatedURL,
                Method:             http.MethodGet,
                StatusCode:         resp.StatusCode,
                RequestBody:        nil, // GET request
                Response:           responseBody,
                StackTrace:         phpStack,
                AdditionalMessages: additionalMsg,
            },
        }

        return envelope, apperror.New("E3001", fmt.Sprintf("delegated request failed with status %d", resp.StatusCode))
    }

    // Success path — RequestDelegatedAt is still set so frontend knows a hop occurred
    // ... parse Results ...
    return envelope, nil
}
```

---

## Helper: Extract PHP Stack Trace

```go
// extractPHPStackTrace attempts to pull stack trace lines from a WordPress-style error response.
func extractPHPStackTrace(body interface{}) []string {
    m, ok := body.(map[string]interface{})
    if !ok {
        return nil
    }

    // WordPress REST API error format: { "data": { "stack_trace": [...] } }
    data, _ := m["data"].(map[string]interface{})
    if data == nil {
        return nil
    }

    rawStack, _ := data["stack_trace"].([]interface{})
    if len(rawStack) == 0 {
        return nil
    }

    stack := make([]string, 0, len(rawStack))
    for _, line := range rawStack {
        if s, ok := line.(string); ok {
            stack = append(stack, s)
        }
    }
    return stack
}
```

---

## Helper: Extract Log Hint

```go
func extractLogHint(body interface{}) string {
    m, ok := body.(map[string]interface{})
    if !ok {
        return ""
    }
    data, _ := m["data"].(map[string]interface{})
    if data == nil {
        return ""
    }
    hint, _ := data["log_hint"].(string)
    return hint
}
```

---

## Checklist

- [ ] `Attributes.RequestDelegatedAt` is set for **all** delegated calls (success and failure)
- [ ] `Errors.DelegatedRequestServer` is populated for **all** failed delegated calls (status ≥ 400)
- [ ] `DelegatedRequestServer.Response` includes the full parsed response body
- [ ] `DelegatedRequestServer.StackTrace` includes PHP stack trace when available
- [ ] `DelegatedRequestServer.AdditionalMessages` includes the `log_hint` from WordPress error responses
- [ ] `DelegatedRequestServer.Method` matches the actual HTTP method used
- [ ] `DelegatedRequestServer.RequestBody` is populated for POST/PUT/DELETE (null for GET)

---

## Frontend Detection

The React frontend now shows a **"Missing Delegation Data"** warning (amber banner) in the error modal Overview tab when:
1. The error message contains a third-party endpoint pattern (e.g., `(GET /riseup-asia-uploader/v1/...)`)
2. But `RequestDelegatedAt` is empty/missing
3. And `DelegatedRequestServer` is null/missing

This warning explicitly states it's a backend bug to help developers identify the issue quickly.
