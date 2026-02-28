package endpointtype

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
	SyncManifest:            "SyncManifest",
	ErrorLogs:               "ErrorLogs",
	ErrorSessions:           "ErrorSessions",
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
	SyncManifest:            "/plugins/sync-manifest",
	ErrorLogs:               "/error-logs",
	ErrorSessions:           "/error-sessions",
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

func (v Variant) String() string {
	return v.Value()
}

func (v Variant) Label() string {
	if v.IsInvalid() {
		return variantLabels[Invalid]
	}

	return variantLabels[v]
}

func (v Variant) Value() string {
	if v.IsInvalid() {
		return variantValues[Invalid]
	}

	return variantValues[v]
}

func (v Variant) IsValid() bool {
	return v > Invalid && v < Variant(len(variantLabels))
}

func (v Variant) IsInvalid() bool         { return v == Invalid }
func (v Variant) IsDefined() bool         { return v != Invalid }
func (v Variant) IsDefinedAndValid() bool { return v.IsDefined() && v.IsValid() }

func (v Variant) IsOther(other Variant) bool { return v != other }

func (v Variant) IsAnyOf(others ...Variant) bool {
	for _, o := range others {
		if v == o {
			return true
		}
	}

	return false
}

// IsSnapshot checks if this endpoint belongs to the snapshot domain.
func (v Variant) IsSnapshot() bool {
	return strings.HasPrefix(v.Value(), "/snapshots/")
}

// IsPlugin checks if this endpoint belongs to the plugin domain.
func (v Variant) IsPlugin() bool {
	return strings.HasPrefix(v.Value(), "/plugins/")
}

// IsAgent checks if this endpoint belongs to the agent domain.
func (v Variant) IsAgent() bool {
	return strings.HasPrefix(v.Value(), "/agents")
}

func All() []Variant {
	all := make([]Variant, 0, len(variantLabels)-1)

	for i := 1; i < len(variantLabels); i++ {
		all = append(all, Variant(i))
	}

	return all
}

func ByIndex(i int) Variant {
	if i < 0 || i >= len(variantLabels) {
		return Invalid
	}

	return Variant(i)
}

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)

	for i, str := range variantLabels {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	for i, str := range variantValues {
		if str == trimmed {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid endpoint: %q", s)
}

func Values() []string {
	result := make([]string, 0, len(variantLabels)-1)

	for _, s := range variantLabels[1:] {
		result = append(result, s)
	}

	return result
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.Value())
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
