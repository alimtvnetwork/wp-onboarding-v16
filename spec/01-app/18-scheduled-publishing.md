# K-1: Scheduled Publishing

> **Phase:** K — Platform Maturity
> **Status:** Specced — Not Implemented
> **Date:** 2026-04-08

---

## Overview

Allow users to queue a publish for a future time. The Go backend holds a persistent schedule queue, a ticker checks for due jobs, and the React UI provides scheduling controls in the publish dialog. Users can cancel or reschedule pending jobs at any time.

---

## 1. Concepts

| Term | Definition |
|------|-----------|
| **ScheduledJob** | A persisted record representing a future publish intent |
| **Scheduler** | A Go goroutine that ticks every 30 seconds, picks up due jobs, and feeds them into the existing publish pipeline |
| **Queue** | A flat JSON file (`data/scheduled-jobs.json`) storing all pending/completed jobs — no external dependencies |

---

## 2. Data Model

### ScheduledJob

```
ScheduledJob:
  ID              string            (UUID, generated on create)
  PluginSlug      string            (target plugin)
  PluginVersion   string            (version at time of scheduling)
  TargetSites     []string          (site keys — empty = all sites)
  ScheduledAt     time.Time         (UTC — when to publish)
  CreatedAt       time.Time         (UTC)
  Status          ScheduledJobStatus
  Error           string            (populated on failure)
  CompletedAt     *time.Time        (UTC, set on success or failure)
  CancelledBy     string            (optional — "user" or "system")
```

### ScheduledJobStatus Enum

| Value | Meaning |
|-------|---------|
| `pending` | Waiting for scheduled time |
| `running` | Publish pipeline currently executing |
| `completed` | Successfully published |
| `failed` | Pipeline returned error |
| `cancelled` | User or system cancelled before execution |

---

## 3. Go Scheduler

### 3.1 Lifecycle

- **Start:** Launched as a goroutine when the Go server boots (`StartScheduler(ctx)`).
- **Tick:** Every 30 seconds, query all jobs where `Status == pending` and `ScheduledAt <= now()`.
- **Execute:** For each due job (oldest first), set `Status = running`, invoke the existing publish pipeline (`ServicePublish`), then set `Status = completed` or `Status = failed` with error.
- **Shutdown:** Context cancellation stops the ticker gracefully. Any in-flight publish completes before exit.

### 3.2 Concurrency Rules

- **One job at a time.** The scheduler holds a mutex; if a publish is already running, due jobs wait for the next tick.
- **No overlapping plugins.** If two jobs target the same `PluginSlug`, the later one waits. Different plugins could theoretically run concurrently, but for v1 we keep it serial for simplicity.

### 3.3 Persistence

- Jobs stored in `data/scheduled-jobs.json` (same pattern as existing settings/config files).
- File read on boot, written after every state change (create, update, cancel, complete).
- Atomic write via temp-file + rename to prevent corruption.

### 3.4 Edge Cases

| Scenario | Behavior |
|----------|----------|
| Server was down when job was due | On boot, scheduler immediately picks up overdue `pending` jobs |
| Plugin source changed since scheduling | Publish uses current source files (latest), not a snapshot. Version field is informational. |
| Job scheduled < 1 minute in future | Accepted; fires on next tick (within 30s) |
| Job scheduled in the past | Rejected at creation time with error `E8010: Scheduled time must be in the future` |
| Duplicate schedule for same plugin+time | Allowed — user's responsibility. UI warns if another pending job exists for the same plugin. |

---

## 4. Go Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/scheduled-jobs` | List all jobs (optionally filter by `?status=pending`) |
| POST | `/scheduled-jobs` | Create a new scheduled job |
| GET | `/scheduled-jobs/{id}` | Get single job details |
| DELETE | `/scheduled-jobs/{id}` | Cancel a pending job |
| PUT | `/scheduled-jobs/{id}` | Reschedule (update `ScheduledAt` — only if `pending`) |

### POST `/scheduled-jobs` Request Body

```json
{
  "plugin_slug": "qupload",
  "target_sites": ["site-a", "site-b"],
  "scheduled_at": "2026-04-09T03:00:00Z"
}
```

### Validation Rules

- `scheduled_at` must be in the future (> `time.Now().UTC()`).
- `plugin_slug` must match a known plugin.
- `target_sites` entries must match known site keys (empty array = all sites).
- Cancel/reschedule only allowed when `Status == pending`.

### WebSocket Events

| Event | Payload |
|-------|---------|
| `scheduledJobCreated` | Full `ScheduledJob` |
| `scheduledJobStarted` | `{ id, plugin_slug }` |
| `scheduledJobCompleted` | `{ id, plugin_slug, duration_ms }` |
| `scheduledJobFailed` | `{ id, plugin_slug, error }` |
| `scheduledJobCancelled` | `{ id, plugin_slug, cancelled_by }` |

---

## 5. React UI

### 5.1 Publish Dialog Enhancement

The existing publish confirmation dialog gains a **"Schedule for later"** toggle:

- **Off (default):** "Publish Now" button — current behavior, unchanged.
- **On:** Reveals a date-time picker and changes the button to "Schedule Publish".
  - Date picker: calendar (using `react-day-picker`, already installed).
  - Time picker: hour + minute dropdowns in 15-minute increments, with timezone label (browser local, submitted as UTC).
  - Minimum time: now + 5 minutes (prevents near-instant schedules that confuse users).

On submit, calls `POST /scheduled-jobs` and shows a success toast: _"Publish scheduled for {formatted time}"_.

### 5.2 Scheduled Jobs Panel

Location: New tab **"Scheduled"** in the main dashboard tabs (next to Plugins / Sites).

**List View:**
- Table columns: Plugin (with version badge), Scheduled Time (relative + absolute), Target Sites (chips or "All"), Status badge, Actions.
- Status badges: `pending` (blue), `running` (yellow pulse), `completed` (green), `failed` (red), `cancelled` (gray).
- Sort: pending jobs first (by `ScheduledAt` ascending), then completed/failed/cancelled by `CompletedAt` descending.

**Actions per row:**
| Status | Available Actions |
|--------|------------------|
| `pending` | Cancel, Reschedule, Publish Now |
| `running` | — (no actions, show spinner) |
| `completed` | View Details |
| `failed` | View Error, Retry (creates new job for "now + 1 min") |
| `cancelled` | View Details |

**Cancel Confirmation:** Dialog with _"Cancel scheduled publish of {plugin} at {time}?"_ and Cancel / Confirm buttons.

**Reschedule Dialog:** Same date-time picker as publish dialog, pre-filled with current `ScheduledAt`.

### 5.3 Dashboard Indicator

When any `pending` jobs exist, show a small badge on the "Scheduled" tab (count of pending jobs) and optionally a banner: _"1 publish scheduled for today at 3:00 PM"_.

---

## 6. Webhook Integration (K-3)

If K-3 (Webhook Notifications) is implemented, the scheduler fires these webhook events:

- `publish.scheduled` — when a job is created
- `publish.started` — when the scheduler begins the job (reuses existing event)
- `publish.completed` / `publish.failed` — reuses existing events
- `publish.cancelled` — when a job is cancelled

---

## 7. Cancellation Flow

```
User clicks "Cancel" on pending job
  → Confirmation dialog
  → DELETE /scheduled-jobs/{id}
  → Go handler checks Status == pending
    → Yes: set Status = cancelled, CancelledBy = "user", persist
           broadcast scheduledJobCancelled via WebSocket
           respond 200
    → No:  respond 409 Conflict "Job is no longer pending"
  → UI updates row to cancelled state, shows toast
```

System cancellation (e.g., plugin deleted while job pending):
- Scheduler detects missing plugin on tick → sets `Status = cancelled`, `CancelledBy = "system"`, `Error = "Plugin no longer exists"`.

---

## 8. Implementation Order

| Step | Description | Depends On |
|------|-------------|-----------|
| K-1.1 | Go `ScheduledJob` model + enum + JSON persistence | — |
| K-1.2 | Go CRUD endpoints (create, list, get, cancel, reschedule) | K-1.1 |
| K-1.3 | Go scheduler goroutine (ticker, job execution, pipeline integration) | K-1.1, K-1.2 |
| K-1.4 | WebSocket events for job lifecycle | K-1.3 |
| K-1.5 | React: schedule toggle in publish dialog + date-time picker | K-1.2 |
| K-1.6 | React: Scheduled Jobs panel (list, cancel, reschedule, retry) | K-1.2, K-1.4 |
| K-1.7 | Dashboard badge + pending job banner | K-1.6 |

---

## 9. Acceptance Criteria

- [ ] User can schedule a publish for a future time from the publish dialog.
- [ ] Scheduled job executes automatically within 30 seconds of the scheduled time.
- [ ] User can cancel a pending job; cancelled jobs never execute.
- [ ] User can reschedule a pending job to a new time.
- [ ] Overdue jobs (server was down) execute immediately on boot.
- [ ] Only one publish runs at a time; concurrent due jobs queue serially.
- [ ] Scheduled Jobs panel shows all jobs with correct status badges.
- [ ] Failed jobs can be retried (creates a new near-future job).
- [ ] WebSocket events update the UI in real time without polling.
- [ ] Past times are rejected at creation with a clear error message.
