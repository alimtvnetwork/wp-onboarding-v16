# Memory: architecture/php/core-enum-inventory
Updated: 2026-03-03

Core enums in the `RiseupAsia\Enums` namespace include:

- **PluginConfigType**: Identity (Slug, ShortName, Name, Version, LogPrefix, SettingsGroup). Must include `ShortName` and `LogPrefix` cases — see `plugin-identity-standard.md`.
- **OptionNameType** & **HookType**: WordPress keys and hooks.
- **HttpStatusType**: HTTP status codes including redirect (301–308) with helpers `isSuccess()`, `isClientError()`, `isServerError()`, `isRetryable()`, `isRedirect()`.
- **HttpHeaderType**: HTTP header name constants (Location, ContentType). All `wp_remote_retrieve_header()` calls must use this enum.
- **WpErrorCodeType**: Custom error codes with domain helpers (isAuthError, isDatabaseError).
- **SnapshotConfigType**: System limits (MaxSizeMb, LockTimeoutSeconds, DefaultTitle, RootDbFilename).
- **UpdateConfigType**: Cache and redirect limits (MaxRedirects, CacheDaysDefault).
- **PaginationConfigType**: API result limits (LogRetrievalMaxLines, DefaultLimit).
- **LogCategoryType**: Context for logPluginAction (Snapshot, Agent, Sync, Plugin, Update, Post).
- **LogColumnType**: PascalCase database column names for the Logs table (16 cases: Id, Action, PluginSlug, PluginFile, PluginVersion, PostId, Status, Details, ErrorMsg, UserLogin, UserId, IpAddress, TriggeredBy, UploadSource, SourceMachine, CreatedAt). Includes `isEqual()`, `isOtherThan()`, `isAnyOf()`.
- **StorageModeType** & **SnapshotWorkerModeType**: Storage and worker strategies (PerTable, Single, Legacy).
- **SnapshotFrequencyType**: Snapshot schedule frequency values (Manual, Hourly, Daily, Weekly, Monthly).
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
