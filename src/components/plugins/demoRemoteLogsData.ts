/**
 * Demo/mock data for the Remote Logs panel.
 * Used to preview the UI without a live backend connection.
 */
import type {
  RemoteLogsStatusResponse,
  LogsRetrieveResult,
  LogRetrieveFileData,
} from "@/lib/api/types";

const DEMO_INFO_CONTENT = `[2026-03-25T08:12:01Z] INFO  Application started — version 1.62.3
[2026-03-25T08:12:02Z] INFO  Database connection established (PDO, SQLite)
[2026-03-25T08:12:02Z] INFO  Cron scheduler initialized — next run in 3600s
[2026-03-25T08:12:05Z] INFO  REST API routes registered: /logs/status, /logs/retrieve, /logs/clear
[2026-03-25T08:14:33Z] INFO  Incoming upload request — file: invoice_march.pdf (245 KB)
[2026-03-25T08:14:34Z] INFO  Upload validated — MIME: application/pdf, hash: a3f8c2…
[2026-03-25T08:14:35Z] INFO  File stored successfully — /uploads/2026/03/invoice_march.pdf
[2026-03-25T08:14:35Z] INFO  Transaction #4821 created — status: pending
[2026-03-25T08:30:00Z] INFO  Cron: processing 3 pending transactions
[2026-03-25T08:30:01Z] INFO  Transaction #4819 → completed
[2026-03-25T08:30:01Z] INFO  Transaction #4820 → completed
[2026-03-25T08:30:02Z] INFO  Transaction #4821 → completed
[2026-03-25T09:00:00Z] INFO  Health check passed — memory: 42 MB, DB rows: 1,204
[2026-03-25T09:15:22Z] INFO  REST request: GET /logs/status — 200 OK (18ms)
[2026-03-25T09:15:45Z] INFO  REST request: GET /logs/retrieve?max_lines=200 — 200 OK (34ms)
[2026-03-25T10:00:00Z] INFO  Cron: no pending transactions
[2026-03-25T10:45:11Z] INFO  Incoming upload request — file: receipt_007.jpg (89 KB)
[2026-03-25T10:45:12Z] INFO  Upload validated — MIME: image/jpeg, hash: b7e1d0…
[2026-03-25T10:45:12Z] INFO  File stored successfully — /uploads/2026/03/receipt_007.jpg
[2026-03-25T10:45:13Z] INFO  Transaction #4822 created — status: pending`;

const DEMO_ERROR_CONTENT = `[2026-03-24T14:02:11Z] ERROR  Failed to connect to remote API: cURL error 28 — Connection timed out after 30001ms
[2026-03-24T14:02:11Z] ERROR  Retry 1/3 scheduled in 5s
[2026-03-24T14:02:16Z] ERROR  Retry 1/3 — still failing: cURL error 28
[2026-03-24T14:02:21Z] ERROR  Retry 2/3 — still failing: cURL error 28
[2026-03-24T14:02:26Z] ERROR  Retry 3/3 — giving up. Transaction #4815 marked as failed.
[2026-03-24T16:33:45Z] ERROR  PDO exception: SQLSTATE[HY000] [14] unable to open database file
[2026-03-24T16:33:45Z] ERROR  Database recovery: attempting reconnect…
[2026-03-24T16:33:46Z] ERROR  Database recovered after 1 retry
[2026-03-25T03:00:01Z] WARNING  Log rotation skipped — archive directory not writable
[2026-03-25T03:00:01Z] WARNING  Permissions: drwxr-xr-x on /var/www/html/wp-content/uploads/riseup-logs/archive
[2026-03-25T08:14:33Z] WARNING  Upload file size (245 KB) approaching soft limit (256 KB)
[2026-03-25T11:22:07Z] ERROR  Webhook delivery failed — HTTP 502 from https://hooks.example.com/ingest
[2026-03-25T11:22:07Z] ERROR  Response body: <html><body>Bad Gateway</body></html>
[2026-03-25T11:22:08Z] WARNING  Webhook queued for retry — attempt 1/5 in 60s`;

const DEMO_STACKTRACE_CONTENT = `[2026-03-24T14:02:26Z] FATAL  Uncaught exception in TransactionProcessor::execute()

Exception: Remote API unreachable after 3 retries
  thrown in /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Services/TransactionProcessor.php on line 142

Stack trace:
#0 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Services/TransactionProcessor.php(142): RemoteApiClient->sendWithRetry()
#1 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Services/TransactionProcessor.php(89): TransactionProcessor->execute()
#2 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Cron/CronHandler.php(45): TransactionProcessor->processBatch()
#3 /var/www/html/wp-includes/class-wp-hook.php(324): CronHandler->handleScheduledRun()
#4 /var/www/html/wp-includes/class-wp-hook.php(348): WP_Hook->apply_filters()
#5 /var/www/html/wp-includes/plugin.php(517): WP_Hook->do_action()
#6 /var/www/html/wp-cron.php(84): do_action_ref_array()
#7 {main}

---

[2026-03-24T16:33:45Z] FATAL  PDOException in Database::getPdo()

PDOException: SQLSTATE[HY000] [14] unable to open database file
  thrown in /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Database/Database.php on line 67

Stack trace:
#0 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Database/Database.php(67): PDO->__construct()
#1 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Database/Database.php(42): Database->createConnection()
#2 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Traits/Log/LogStatusTrait.php(139): Database::getInstance()
#3 /var/www/html/wp-content/plugins/riseup-asia-uploader/includes/Controllers/LogController.php(28): LogController->handleLogsStatus()
#4 /var/www/html/wp-includes/rest-api/class-wp-rest-server.php(1225): {closure}()
#5 {main}`;

function makeDemoFile(content: string, fileName: string, path: string): LogRetrieveFileData {
  const lines = content.split("\n");
  return {
    Exists: true,
    File: fileName,
    Path: path,
    Content: content,
    Lines: lines.length,
    TotalLines: lines.length,
    TotalSize: new Blob([content]).size,
    Truncated: false,
  };
}

export function createDemoLogsStatus(): RemoteLogsStatusResponse {
  return {
    files: [
      { name: "log.txt", sizeBytes: 1842, lineCount: 20 },
      { name: "error.txt", sizeBytes: 1156, lineCount: 14 },
      { name: "stacktrace.txt", sizeBytes: 1780, lineCount: 38 },
    ],
    totalSizeBytes: 4778,
    archiveCount: 3,
  };
}

export function createDemoRetrieveResult(): LogsRetrieveResult {
  return {
    plugins: [
      {
        namespace: "riseup-asia",
        label: "Riseup Asia",
        available: true,
        infoLog: makeDemoFile(
          DEMO_INFO_CONTENT,
          "log.txt",
          "/var/www/html/wp-content/uploads/riseup-logs/log.txt"
        ),
        errorLog: makeDemoFile(
          DEMO_ERROR_CONTENT,
          "error.txt",
          "/var/www/html/wp-content/uploads/riseup-logs/error.txt"
        ),
        stacktrace: makeDemoFile(
          DEMO_STACKTRACE_CONTENT,
          "stacktrace.txt",
          "/var/www/html/wp-content/uploads/riseup-logs/stacktrace.txt"
        ),
      },
      {
        namespace: "flavor-flavor",
        label: "QUpload",
        available: true,
        infoLog: makeDemoFile(
          `[2026-03-25T09:00:00Z] INFO  QUpload health check — OK\n[2026-03-25T09:00:00Z] INFO  Queue depth: 0`,
          "log.txt",
          "/var/www/html/wp-content/uploads/flavor-logs/log.txt"
        ),
        errorLog: {
          Exists: false,
          File: "error.txt",
          Path: "/var/www/html/wp-content/uploads/flavor-logs/error.txt",
          Content: "",
          Lines: 0,
          TotalLines: 0,
          TotalSize: 0,
          Truncated: false,
        },
        stacktrace: {
          Exists: false,
          File: "stacktrace.txt",
          Path: "/var/www/html/wp-content/uploads/flavor-logs/stacktrace.txt",
          Content: "",
          Lines: 0,
          TotalLines: 0,
          TotalSize: 0,
          Truncated: false,
        },
      },
    ],
  };
}
