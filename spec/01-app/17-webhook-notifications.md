# K-3: Webhook Notifications

> **Phase:** K — Platform Maturity
> **Status:** Specced — Not Implemented
> **Date:** 2026-04-08

---

## Overview

Fire HTTP webhooks on key publish-pipeline events so external systems (Slack, Discord, custom endpoints) receive real-time notifications without polling.

---

## 1. Supported Platforms

| Platform | Delivery Method | Auth |
|----------|----------------|------|
| **Slack** | Incoming Webhook URL (`https://hooks.slack.com/services/…`) | URL contains token |
| **Discord** | Webhook URL (`https://discord.com/api/webhooks/…`) | URL contains token |
| **Custom URL** | Any HTTPS endpoint | Optional `Authorization` header (Bearer / Basic) |

Platform detection is automatic based on URL pattern. Custom URLs must be HTTPS (HTTP rejected except `localhost` for dev).

---

## 2. Events

| Event Key | Fires When |
|-----------|-----------|
| `publish.started` | Publish pipeline begins |
| `publish.completed` | All sites successfully updated |
| `publish.failed` | One or more sites failed |
| `backup.completed` | Backup finished successfully |
| `backup.failed` | Backup failed |
| `rollback.completed` | Rollback restored a previous version |
| `rollback.failed` | Rollback failed |

Each webhook subscription selects one or more events.

---

## 3. Payload Schema

All payloads use a consistent envelope:

```json
{
  "event": "publish.completed",
  "timestamp": "2026-04-08T14:30:00Z",
  "delivery_id": "d-xxxxxxxx-xxxx",
  "data": {
    "plugin_slug": "qupload",
    "plugin_version": "2.31.0",
    "sites": [
      {
        "name": "Site A",
        "url": "https://site-a.com",
        "status": "success",
        "duration_ms": 1200
      },
      {
        "name": "Site B",
        "url": "https://site-b.com",
        "status": "failed",
        "error": "HTTP 502 from remote"
      }
    ],
    "summary": {
      "total_sites": 2,
      "succeeded": 1,
      "failed": 1,
      "total_duration_ms": 3400
    }
  }
}
```

### Platform-Specific Formatting

- **Slack**: Payload wrapped in `{ "text": "...", "blocks": [...] }` using Block Kit with color-coded status bars.
- **Discord**: Payload wrapped in `{ "embeds": [...] }` with color-coded embeds (green = success, red = failure, yellow = started).
- **Custom URL**: Raw JSON envelope above. `Content-Type: application/json`. Optional `X-Webhook-Signature` header (HMAC-SHA256 of body using a shared secret).

---

## 4. Webhook Configuration

### 4.1 Go Data Model

```
WebhookEndpoint:
  ID            string       (UUID)
  Name          string       (user label, max 100 chars)
  URL           string       (HTTPS endpoint)
  Platform      enum         (slack | discord | custom)
  Events        []string     (subscribed event keys)
  AuthHeader    string       (optional, encrypted at rest — custom only)
  SigningSecret  string      (optional, for HMAC — custom only)
  IsEnabled     bool
  CreatedAt     time.Time
  UpdatedAt     time.Time
```

### 4.2 Go Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/webhooks` | List all webhook endpoints |
| POST | `/webhooks` | Create new endpoint |
| PUT | `/webhooks/{id}` | Update endpoint |
| DELETE | `/webhooks/{id}` | Delete endpoint |
| POST | `/webhooks/{id}/test` | Send a test payload |
| GET | `/webhooks/{id}/deliveries` | List recent delivery attempts |

### 4.3 Delivery Log

```
WebhookDelivery:
  ID              string
  EndpointID      string
  Event           string
  DeliveryID      string   (matches payload delivery_id)
  RequestBody     string   (truncated to 4 KB)
  ResponseStatus  int
  ResponseBody    string   (truncated to 1 KB)
  DurationMs      int
  Attempt         int      (1-based)
  Status          enum     (success | failed | pending)
  CreatedAt       time.Time
```

Retain last 100 deliveries per endpoint. Older entries pruned on write.

---

## 5. Retry Policy

| Parameter | Value |
|-----------|-------|
| Max attempts | 3 (initial + 2 retries) |
| Backoff | Exponential: 10s → 30s → 90s |
| Timeout per request | 10 seconds |
| Retryable conditions | Network error, HTTP 5xx, HTTP 429 |
| Non-retryable | HTTP 4xx (except 429), invalid URL |

After all retries exhausted, delivery marked `failed`. No circuit breaker (endpoint stays enabled). Users can see failures in delivery log and manually re-test.

---

## 6. React UI

### 6.1 Webhook Settings Page

Location: Settings → Webhooks tab (new tab in existing settings layout).

**List View:**
- Table of configured endpoints: Name, Platform icon, URL (masked after domain), Events (chips), Enabled toggle, Actions (Edit / Delete / Test).
- "Add Webhook" button opens create dialog.

**Create / Edit Dialog:**
- Name (text input, required)
- URL (text input, required, validated HTTPS)
- Platform (auto-detected from URL, shown as read-only badge; overridable for custom)
- Events (multi-select checkboxes)
- Auth Header (text input, shown only for custom platform, stored encrypted)
- Signing Secret (text input, shown only for custom platform)
- Enabled toggle

**Test Button:**
- Sends a synthetic `publish.completed` event with sample data.
- Shows success/failure toast with response status.

### 6.2 Delivery Log Panel

- Accessible via "View Deliveries" action on each endpoint.
- Table: Timestamp, Event, Status badge (green/red/yellow), HTTP status, Duration, Attempt count.
- Click row to expand: request body (pretty-printed JSON), response body, response headers.

---

## 7. Security

1. **HTTPS only** — reject HTTP URLs (except localhost).
2. **Signing** — custom endpoints may configure a signing secret. The Go handler computes `HMAC-SHA256(body, secret)` and sends it as `X-Webhook-Signature: sha256=<hex>`.
3. **Auth header encrypted at rest** — stored using Go's AES-GCM with a server-side key.
4. **No secrets in logs** — delivery log truncates request body and never logs auth headers.
5. **Rate limit** — max 10 webhooks per installation. Max 50 deliveries per minute across all endpoints.

---

## 8. Implementation Order

| Step | Description | Depends On |
|------|-------------|-----------|
| K-3.1 | Go data model + CRUD endpoints + persistence | — |
| K-3.2 | Go delivery engine (dispatch, retry, logging) | K-3.1 |
| K-3.3 | Platform formatters (Slack Block Kit, Discord embeds) | K-3.2 |
| K-3.4 | Hook into publish/backup/rollback pipelines | K-3.2 |
| K-3.5 | React webhook settings UI + delivery log | K-3.1 |
| K-3.6 | Test endpoint + HMAC signing | K-3.2 |

---

## 9. Acceptance Criteria

- [ ] User can create a Slack webhook, subscribe to `publish.completed`, and receive a formatted Slack message on publish.
- [ ] User can create a Discord webhook and receive a color-coded embed on publish failure.
- [ ] Custom URL receives raw JSON with valid `X-Webhook-Signature` when signing secret is configured.
- [ ] Failed deliveries retry up to 3 times with exponential backoff.
- [ ] Delivery log shows all attempts with request/response details.
- [ ] Test button sends a synthetic event and shows result in a toast.
- [ ] HTTPS enforced; auth headers never appear in logs.
