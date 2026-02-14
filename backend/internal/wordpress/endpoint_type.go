// Package wordpress provides typed endpoint path constants.
//
// EndpointType replaces untyped string constants for REST API endpoint paths.
// Each constant stores the path fragment (e.g., "/status") and provides
// IsEqual() for type-safe comparison and String() for value access.
package wordpress

import "strings"

// EndpointType is a typed string representing a REST API endpoint path.
type EndpointType string

// =============================================================================
// CORE ENDPOINTS
// =============================================================================

const (
	// EndpointStatus checks plugin availability and version.
	EndpointStatus EndpointType = "/status"

	// EndpointUpload handles Base64 ZIP plugin uploads.
	EndpointUpload EndpointType = "/upload"

	// EndpointUploadActive uploads and activates a plugin in one call.
	EndpointUploadActive EndpointType = "/upload-active"

	// EndpointPlugins lists all installed plugins.
	EndpointPlugins EndpointType = "/plugins"

	// EndpointPluginInfo gets info for a specific plugin (fixed URL, slug in JSON body).
	EndpointPluginInfo EndpointType = "/plugins/info"

	// EndpointPluginExists checks if a plugin is installed (lightweight pre-flight, slug in JSON body).
	EndpointPluginExists EndpointType = "/plugins/exists"

	// EndpointEnable activates a plugin (fixed URL, slug in JSON body).
	EndpointEnable EndpointType = "/plugins/enable"

	// EndpointDisable deactivates a plugin (fixed URL, slug in JSON body).
	EndpointDisable EndpointType = "/plugins/disable"

	// EndpointDelete removes a plugin (fixed URL, slug in JSON body).
	EndpointDelete EndpointType = "/plugins/delete"

	// EndpointFiles handles file listing (fixed URL, slug in JSON body).
	EndpointFiles EndpointType = "/plugins/files"

	// EndpointFile handles single file content retrieval (fixed URL, slug+path in JSON body).
	EndpointFile EndpointType = "/plugins/file"

	// EndpointSync handles delta file sync (fixed URL, slug in JSON body).
	EndpointSync EndpointType = "/plugins/sync"

	// EndpointLogs queries transaction logs.
	EndpointLogs EndpointType = "/logs"

	// EndpointLogsStats gets log statistics.
	EndpointLogsStats EndpointType = "/logs/stats"

	// EndpointPosts handles blog post operations.
	EndpointPosts EndpointType = "/posts"

	// EndpointPostsById handles single post operations (format: /posts/%d).
	EndpointPostsById EndpointType = "/posts/%d"

	// EndpointCategories handles category operations.
	EndpointCategories EndpointType = "/categories"

	// EndpointMedia handles media library uploads.
	EndpointMedia EndpointType = "/media"

	// EndpointExportSelf exports the Rise Up Asia plugin as a ZIP.
	EndpointExportSelf EndpointType = "/export-self"

	// EndpointExportPlugin exports any plugin as a base64-encoded ZIP (fixed URL, slug in JSON body).
	EndpointExportPlugin EndpointType = "/plugins/export"

	// EndpointSyncManifest fetches the cached file manifest for sync comparison (fixed URL, slug in JSON body).
	EndpointSyncManifest EndpointType = "/plugins/sync-manifest"

	// EndpointErrorLogs retrieves PHP error and log files as JSON.
	EndpointErrorLogs EndpointType = "/error-logs"

	// EndpointErrorSessions retrieves structured error entries from the plugin's SQLite DB.
	EndpointErrorSessions EndpointType = "/error-sessions"
)

// =============================================================================
// SNAPSHOT ENDPOINTS (fixed URLs, IDs in JSON body)
// =============================================================================

const (
	// EndpointSnapshotsList lists all snapshots.
	EndpointSnapshotsList EndpointType = "/snapshots/list"

	// EndpointSnapshotsSchedule creates/schedules a snapshot.
	EndpointSnapshotsSchedule EndpointType = "/snapshots/schedule"

	// EndpointSnapshotsInfo gets details for a specific snapshot (ID in JSON body).
	EndpointSnapshotsInfo EndpointType = "/snapshots/info"

	// EndpointSnapshotsDelete deletes a snapshot (ID in JSON body).
	EndpointSnapshotsDelete EndpointType = "/snapshots/delete"

	// EndpointSnapshotsRestore restores a snapshot (ID in JSON body).
	EndpointSnapshotsRestore EndpointType = "/snapshots/restore"

	// EndpointSnapshotsExport exports a snapshot as ZIP (ID in JSON body).
	EndpointSnapshotsExport EndpointType = "/snapshots/export"

	// EndpointSnapshotsSettings manages snapshot settings.
	EndpointSnapshotsSettings EndpointType = "/snapshots/settings"

	// EndpointSnapshotsProviders lists available snapshot providers.
	EndpointSnapshotsProviders EndpointType = "/snapshots/providers"

	// EndpointSnapshotsTables lists available database tables.
	EndpointSnapshotsTables EndpointType = "/snapshots/tables"

	// EndpointSnapshotsFullBackup triggers end-to-end full backup orchestration.
	EndpointSnapshotsFullBackup EndpointType = "/snapshots/full-backup"

	// EndpointSnapshotsIncremental triggers an incremental backup against the latest master.
	EndpointSnapshotsIncremental EndpointType = "/snapshots/incremental"

	// EndpointSnapshotsImport imports a snapshot from an uploaded ZIP file.
	EndpointSnapshotsImport EndpointType = "/snapshots/import"

	// EndpointSnapshotsCleanup triggers cleanup of old/orphan/stuck snapshots.
	EndpointSnapshotsCleanup EndpointType = "/snapshots/cleanup"

	// EndpointSnapshotsDownload requests a cached ZIP download URL for a snapshot.
	EndpointSnapshotsDownload EndpointType = "/snapshots/download"

	// EndpointSnapshotsDownloadFile serves the actual ZIP file with nonce validation.
	EndpointSnapshotsDownloadFile EndpointType = "/snapshots/download-file"
)

// IsEqual checks if this endpoint equals the given endpoint.
func (e EndpointType) IsEqual(other EndpointType) bool {
	return e == other
}

// String returns the raw string value of the endpoint path.
func (e EndpointType) String() string {
	return string(e)
}

// IsSnapshot checks if this endpoint belongs to the snapshot domain.
func (e EndpointType) IsSnapshot() bool {
	return strings.HasPrefix(string(e), "/snapshots/")
}

// IsAgent checks if this endpoint belongs to the agent domain.
func (e EndpointType) IsAgent() bool {
	return strings.HasPrefix(string(e), "/agents")
}

// IsPlugin checks if this endpoint belongs to the plugin domain.
func (e EndpointType) IsPlugin() bool {
	return strings.HasPrefix(string(e), "/plugins/")
}
