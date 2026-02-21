package endpoint

import (
	"encoding/json"
	"fmt"
	"strings"
)

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
	SyncManifest
	ErrorLogs
	ErrorSessions
	SnapshotsList
	SnapshotsSchedule
	SnapshotsInfo
	SnapshotsDelete
	SnapshotsRestore
	SnapshotsExport
	SnapshotsSettings
	SnapshotsProviders
	SnapshotsTables
	SnapshotsFullBackup
	SnapshotsIncremental
	SnapshotsImport
	SnapshotsCleanup
	SnapshotsDownload
	SnapshotsDownloadFile
)

var variantStrings = [...]string{
	Invalid:               "invalid",
	Status:                "/status",
	Upload:                "/upload",
	UploadActive:          "/upload-active",
	Plugins:               "/plugins",
	PluginInfo:            "/plugins/info",
	PluginExists:          "/plugins/exists",
	Enable:                "/plugins/enable",
	Disable:               "/plugins/disable",
	Delete:                "/plugins/delete",
	Files:                 "/plugins/files",
	File:                  "/plugins/file",
	Sync:                  "/plugins/sync",
	Logs:                  "/logs",
	LogsStats:             "/logs/stats",
	Posts:                 "/posts",
	PostsById:             "/posts/%d",
	Categories:            "/categories",
	Media:                 "/media",
	ExportSelf:            "/export-self",
	ExportPlugin:          "/plugins/export",
	SyncManifest:          "/plugins/sync-manifest",
	ErrorLogs:             "/error-logs",
	ErrorSessions:         "/error-sessions",
	SnapshotsList:         "/snapshots/list",
	SnapshotsSchedule:     "/snapshots/schedule",
	SnapshotsInfo:         "/snapshots/info",
	SnapshotsDelete:       "/snapshots/delete",
	SnapshotsRestore:      "/snapshots/restore",
	SnapshotsExport:       "/snapshots/export",
	SnapshotsSettings:     "/snapshots/settings",
	SnapshotsProviders:    "/snapshots/providers",
	SnapshotsTables:       "/snapshots/tables",
	SnapshotsFullBackup:   "/snapshots/full-backup",
	SnapshotsIncremental:  "/snapshots/incremental",
	SnapshotsImport:       "/snapshots/import",
	SnapshotsCleanup:      "/snapshots/cleanup",
	SnapshotsDownload:     "/snapshots/download",
	SnapshotsDownloadFile: "/snapshots/download-file",
}

var variantLabels = [...]string{
	Invalid:               "Invalid Endpoint",
	Status:                "Status",
	Upload:                "Upload",
	UploadActive:          "Upload & Activate",
	Plugins:               "Plugins",
	PluginInfo:            "Plugin Info",
	PluginExists:          "Plugin Exists",
	Enable:                "Enable",
	Disable:               "Disable",
	Delete:                "Delete",
	Files:                 "Files",
	File:                  "File",
	Sync:                  "Sync",
	Logs:                  "Logs",
	LogsStats:             "Logs Stats",
	Posts:                 "Posts",
	PostsById:             "Post By ID",
	Categories:            "Categories",
	Media:                 "Media",
	ExportSelf:            "Export Self",
	ExportPlugin:          "Export Plugin",
	SyncManifest:          "Sync Manifest",
	ErrorLogs:             "Error Logs",
	ErrorSessions:         "Error Sessions",
	SnapshotsList:         "Snapshots List",
	SnapshotsSchedule:     "Snapshots Schedule",
	SnapshotsInfo:         "Snapshots Info",
	SnapshotsDelete:       "Snapshots Delete",
	SnapshotsRestore:      "Snapshots Restore",
	SnapshotsExport:       "Snapshots Export",
	SnapshotsSettings:     "Snapshots Settings",
	SnapshotsProviders:    "Snapshots Providers",
	SnapshotsTables:       "Snapshots Tables",
	SnapshotsFullBackup:   "Snapshots Full Backup",
	SnapshotsIncremental:  "Snapshots Incremental",
	SnapshotsImport:       "Snapshots Import",
	SnapshotsCleanup:      "Snapshots Cleanup",
	SnapshotsDownload:     "Snapshots Download",
	SnapshotsDownloadFile: "Snapshots Download File",
}

func (v Variant) String() string {
	if !v.IsValid() {
		return variantStrings[Invalid]
	}
	return variantStrings[v]
}

func (v Variant) Label() string {
	if !v.IsValid() {
		return variantLabels[Invalid]
	}
	return variantLabels[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantStrings))
}

func (v Variant) IsInvalid() bool { return v == Invalid }

// IsSnapshot checks if this endpoint belongs to the snapshot domain.
func (v Variant) IsSnapshot() bool {
	return strings.HasPrefix(v.String(), "/snapshots/")
}

// IsPlugin checks if this endpoint belongs to the plugin domain.
func (v Variant) IsPlugin() bool {
	return strings.HasPrefix(v.String(), "/plugins/")
}

func All() []Variant {
	all := make([]Variant, 0, len(variantStrings)-1)
	for i := 1; i < len(variantStrings); i++ {
		all = append(all, Variant(i))
	}
	return all
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantStrings) {
		return Invalid
	}
	return Variant(i)
}

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)
	for i, str := range variantStrings {
		if str == trimmed {
			return Variant(i), nil
		}
	}
	return Invalid, fmt.Errorf("invalid endpoint: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantStrings)-1)
	for _, s := range variantStrings[1:] {
		result = append(result, s)
	}
	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.String())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string
	if err := json.Unmarshal(data, &s); err != nil {
		return err
	}
	parsed, err := Parse(s)
	if err != nil {
		return err
	}
	*v = parsed
	return nil
}
