# Memory: architecture/php/core-enum-inventory
Updated: 2026-02-21

Core enums in the `RiseupAsia\Enums` namespace include:

- **PluginConfigType**: Identity (Slug, Name, Version, LogPrefix, SettingsGroup).
- **OptionNameType** & **HookType**: WordPress keys and hooks.
- **HttpStatusType**: HTTP status codes including redirect (301–308) with helpers `isSuccess()`, `isClientError()`, `isServerError()`, `isRetryable()`, `isRedirect()`.
- **HttpHeaderType**: HTTP header name constants (Location, ContentType). All `wp_remote_retrieve_header()` calls must use this enum.
- **WpErrorCodeType**: Custom error codes with domain helpers (isAuthError, isDatabaseError).
- **SnapshotConfigType**: System limits (MaxSizeMb, LockTimeoutSeconds, DefaultTitle, RootDbFilename).
- **UpdateConfigType**: Cache and redirect limits (MaxRedirects, CacheDaysDefault).
- **PaginationConfigType**: API result limits (LogRetrievalMaxLines, DefaultLimit).
- **LogCategoryType**: Context for logPluginAction (Snapshot, Agent, Sync, Plugin, Update, Post).
- **StorageModeType** & **SnapshotWorkerModeType**: Storage and worker strategies (PerTable, Single, Legacy).
- **FilterKeyType**: Keys for array-based query filters (Status, Plugin, Action, User, TriggeredBy, UploadSource, From, To, SourceMachine).
- **ResponseKeyType**: Standardized envelope and data keys (Success, Error, Message, Data, Code, Valid, Total, Agents, Sql, Actions, Sets, Logs, Snapshots).
- **ResponseMessageType**: Centralized repeated API messages (ConnectionSuccessful, SnapshotNotFound, SnapshotProviderMissing, ProviderMissing, SnapshotFileMissing, UploadedFileMissing, ZipCreateFailed, TempDirCreateFailed, InvalidFileTypeZip).
- **AgentFieldType**: Input and model field keys for agent management (Name, Url, Username, AppPassword, RedirectUrl, Status).
- **HttpConfigType**: HTTP timeout and configuration constants with static factory methods for `wp_remote_*` option arrays. Backed int enum with cases `TimeoutDefault` (30s) and `TimeoutShort` (15s). Static factories:
  - `headRedirectOptions()` → timeout=15, redirection=0, sslverify=true (for HEAD-based redirect following).
  - `defaultGetOptions()` → timeout=30, sslverify=true (for standard GET requests).
  - `authenticatedOptions(string $method, string $authHeader)` → method, Authorization header, Content-Type=application/json, timeout=30, sslverify=true (for authenticated API requests).
  All `wp_remote_get`, `wp_remote_post`, `wp_remote_head`, and `wp_remote_request` calls must use these factories — inline magic arrays are prohibited.
- **ActionType**: Administrative actions (Enable, Disable, Delete, SnapshotSettingsUpdate).
- **AgentStatusType**, **PostStatusType**, **StatusType**, **SnapshotTriggerType**, **SyncEntryStatusType**: Domain-specific statuses and triggers.
