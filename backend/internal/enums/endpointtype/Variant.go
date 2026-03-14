package endpointtype

// Variant represents a REST API endpoint path.
type Variant byte

const (
	Invalid               Variant = iota
	Status
	Upload
	UploadActive
	Plugins
	PluginInfo
	PluginExists
	Enable
	Disable
	Delete
	Files
	File
	Sync
	Logs
	LogsStats
	Posts
	PostsById
	Categories
	Media
	ExportSelf
	ExportPlugin
	PluginBackup
	PluginBackupRestore
	PluginBackupList
	PluginBackupDelete
	SyncManifest
	ErrorLogs
	ErrorSessions
	LogsStatus
	LogsClear
	LogsConfirm
	LogsEmail
	Openapi
	OpcacheReset
	SnapshotsList
	SnapshotsSchedule
	SnapshotsInfo
	SnapshotsDelete
	SnapshotsRestore
	SnapshotsExport
	SnapshotsSettings
	SnapshotsProviders
	SnapshotsTables
	SnapshotsDependencies
	SnapshotsExportPertable
	SnapshotsFullBackup
	SnapshotsIncremental
	SnapshotsImport
	SnapshotsCleanup
	SnapshotsProgress
	SnapshotsDownload
	SnapshotsDownloadFile
	Agents
	AgentsAdd
	AgentsRemove
	AgentsTest
	AgentsSync
	AgentsPlugins
	AgentAction
	AgentHistory
	Users
	UserId
	UserAppPassword
	UsersExport
	UsersImport
	UsersExportSqlite
	UsersImportSqlite
)

var variantLabels = [...]string{
	Invalid:                 "Invalid",
	Status:                  "Status",
	Upload:                  "Upload",
	UploadActive:            "UploadActive",
	Plugins:                 "Plugins",
	PluginInfo:              "PluginInfo",
	PluginExists:            "PluginExists",
	Enable:                  "Enable",
	Disable:                 "Disable",
	Delete:                  "Delete",
	Files:                   "Files",
	File:                    "File",
	Sync:                    "Sync",
	Logs:                    "Logs",
	LogsStats:               "LogsStats",
	Posts:                    "Posts",
	PostsById:               "PostsById",
	Categories:              "Categories",
	Media:                   "Media",
	ExportSelf:              "ExportSelf",
	ExportPlugin:            "ExportPlugin",
	PluginBackup:            "PluginBackup",
	PluginBackupRestore:     "PluginBackupRestore",
	PluginBackupList:        "PluginBackupList",
	PluginBackupDelete:      "PluginBackupDelete",
	SyncManifest:            "SyncManifest",
	ErrorLogs:               "ErrorLogs",
	ErrorSessions:           "ErrorSessions",
	LogsStatus:              "LogsStatus",
	LogsClear:               "LogsClear",
	LogsConfirm:             "LogsConfirm",
	LogsEmail:               "LogsEmail",
	Openapi:                 "Openapi",
	OpcacheReset:            "OpcacheReset",
	SnapshotsList:           "SnapshotsList",
	SnapshotsSchedule:       "SnapshotsSchedule",
	SnapshotsInfo:           "SnapshotsInfo",
	SnapshotsDelete:         "SnapshotsDelete",
	SnapshotsRestore:        "SnapshotsRestore",
	SnapshotsExport:         "SnapshotsExport",
	SnapshotsSettings:       "SnapshotsSettings",
	SnapshotsProviders:      "SnapshotsProviders",
	SnapshotsTables:         "SnapshotsTables",
	SnapshotsDependencies:   "SnapshotsDependencies",
	SnapshotsExportPertable: "SnapshotsExportPertable",
	SnapshotsFullBackup:     "SnapshotsFullBackup",
	SnapshotsIncremental:    "SnapshotsIncremental",
	SnapshotsImport:         "SnapshotsImport",
	SnapshotsCleanup:        "SnapshotsCleanup",
	SnapshotsProgress:       "SnapshotsProgress",
	SnapshotsDownload:       "SnapshotsDownload",
	SnapshotsDownloadFile:   "SnapshotsDownloadFile",
	Agents:                  "Agents",
	AgentsAdd:               "AgentsAdd",
	AgentsRemove:            "AgentsRemove",
	AgentsTest:              "AgentsTest",
	AgentsSync:              "AgentsSync",
	AgentsPlugins:           "AgentsPlugins",
	AgentAction:             "AgentAction",
	AgentHistory:            "AgentHistory",
	Users:                   "Users",
	UserId:                  "UserId",
	UserAppPassword:         "UserAppPassword",
	UsersExport:             "UsersExport",
	UsersImport:             "UsersImport",
	UsersExportSqlite:       "UsersExportSqlite",
	UsersImportSqlite:       "UsersImportSqlite",
}

var variantValues = [...]string{
	Invalid:                 "invalid",
	Status:                  "/status",
	Upload:                  "/upload",
	UploadActive:            "/upload-active",
	Plugins:                 "/plugins",
	PluginInfo:              "/plugins/info",
	PluginExists:            "/plugins/exists",
	Enable:                  "/plugins/enable",
	Disable:                 "/plugins/disable",
	Delete:                  "/plugins/delete",
	Files:                   "/plugins/files",
	File:                    "/plugins/file",
	Sync:                    "/plugins/sync",
	Logs:                    "/logs",
	LogsStats:               "/logs/stats",
	Posts:                    "/posts",
	PostsById:               "/posts/%d",
	Categories:              "/categories",
	Media:                   "/media",
	ExportSelf:              "/export-self",
	ExportPlugin:            "/plugins/export",
	PluginBackup:            "/plugins/backup",
	PluginBackupRestore:     "/plugins/backup-restore",
	PluginBackupList:        "/plugins/backup-list",
	PluginBackupDelete:      "/plugins/backup-delete",
	SyncManifest:            "/plugins/sync-manifest",
	ErrorLogs:               "/error-logs",
	ErrorSessions:           "/error-sessions",
	LogsStatus:              "/logs/status",
	LogsClear:               "/logs/clear",
	LogsConfirm:             "/logs/clear/confirm",
	LogsEmail:               "/logs/email",
	Openapi:                 "/openapi",
	OpcacheReset:            "/opcache-reset",
	SnapshotsList:           "/snapshots/list",
	SnapshotsSchedule:       "/snapshots/schedule",
	SnapshotsInfo:           "/snapshots/info",
	SnapshotsDelete:         "/snapshots/delete",
	SnapshotsRestore:        "/snapshots/restore",
	SnapshotsExport:         "/snapshots/export",
	SnapshotsSettings:       "/snapshots/settings",
	SnapshotsProviders:      "/snapshots/providers",
	SnapshotsTables:         "/snapshots/tables",
	SnapshotsDependencies:   "/snapshots/dependencies",
	SnapshotsExportPertable: "/snapshots/export-pertable",
	SnapshotsFullBackup:     "/snapshots/full-backup",
	SnapshotsIncremental:    "/snapshots/incremental",
	SnapshotsImport:         "/snapshots/import",
	SnapshotsCleanup:        "/snapshots/cleanup",
	SnapshotsProgress:       "/snapshots/progress",
	SnapshotsDownload:       "/snapshots/download",
	SnapshotsDownloadFile:   "/snapshots/download-file",
	Agents:                  "/agents",
	AgentsAdd:               "/agents/add",
	AgentsRemove:            "/agents/remove",
	AgentsTest:              "/agents/test",
	AgentsSync:              "/agents/sync",
	AgentsPlugins:           "/agents/plugins",
	AgentAction:             "/agents/action",
	AgentHistory:            "/agents/history",
}
