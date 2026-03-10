package operationtype

import (
	"encoding/json"
	"fmt"
	"strings"
)

// Variant represents a WordPress client operation for type-safe API call identification.
type Variant byte

const (
	Invalid Variant = iota
	GetSnapshots
	GetSnapshot
	CreateSnapshot
	DeleteSnapshot
	RestoreSnapshot
	GetSnapshotSettings
	UpdateSnapshotSettings
	ExportSnapshot
	DownloadSnapshotZip
	StreamSnapshotZip
	GetSnapshotProviders
	GetAvailableTables
	FullBackup
	IncrementalBackup
	ImportSnapshot
	SnapshotCleanup
	ReplaceFile
	DeleteFile
	SyncFiles
	ExportPlugin
	ExportSelf
	FetchErrorLogs
	FetchErrorSessions
	CheckPluginExists
	ListPlugins
	ListPluginFiles
	EnablePlugin
	DisablePlugin
	DeletePlugin
	GetSyncManifest
	GetPluginFiles
	GetFileContent
	RequestMutationToken
	AuthenticateUser
	CheckPluginAccess
	TestWritePermissions
	DeleteTestPost
	GetPluginsList
	GetPlugin
	CheckNamespace
	GetUploaderStatus
	UploadPlugin
	GetSnapshotsFallback
	CheckUploaderNamespace
	RemotePluginBackup
)

var variantLabels = [...]string{
	Invalid:                "Invalid",
	GetSnapshots:           "GetSnapshots",
	GetSnapshot:            "GetSnapshot",
	CreateSnapshot:         "CreateSnapshot",
	DeleteSnapshot:         "DeleteSnapshot",
	RestoreSnapshot:        "RestoreSnapshot",
	GetSnapshotSettings:    "GetSnapshotSettings",
	UpdateSnapshotSettings: "UpdateSnapshotSettings",
	ExportSnapshot:         "ExportSnapshot",
	DownloadSnapshotZip:    "DownloadSnapshotZip",
	StreamSnapshotZip:      "StreamSnapshotZip",
	GetSnapshotProviders:   "GetSnapshotProviders",
	GetAvailableTables:     "GetAvailableTables",
	FullBackup:             "FullBackup",
	IncrementalBackup:      "IncrementalBackup",
	ImportSnapshot:         "ImportSnapshot",
	SnapshotCleanup:        "SnapshotCleanup",
	ReplaceFile:            "ReplaceFile",
	DeleteFile:             "DeleteFile",
	SyncFiles:              "SyncFiles",
	ExportPlugin:           "ExportPlugin",
	ExportSelf:             "ExportSelf",
	FetchErrorLogs:         "FetchErrorLogs",
	FetchErrorSessions:     "FetchErrorSessions",
	CheckPluginExists:      "CheckPluginExists",
	ListPlugins:            "ListPlugins",
	ListPluginFiles:        "ListPluginFiles",
	EnablePlugin:           "EnablePlugin",
	DisablePlugin:          "DisablePlugin",
	DeletePlugin:           "DeletePlugin",
	GetSyncManifest:        "GetSyncManifest",
	GetPluginFiles:         "GetPluginFiles",
	GetFileContent:         "GetFileContent",
	RequestMutationToken:   "RequestMutationToken",
	AuthenticateUser:       "AuthenticateUser",
	CheckPluginAccess:      "CheckPluginAccess",
	TestWritePermissions:   "TestWritePermissions",
	DeleteTestPost:         "DeleteTestPost",
	GetPluginsList:         "GetPluginsList",
	GetPlugin:              "GetPlugin",
	CheckNamespace:         "CheckNamespace",
	GetUploaderStatus:      "GetUploaderStatus",
	UploadPlugin:           "UploadPlugin",
	GetSnapshotsFallback:   "GetSnapshotsFallback",
	CheckUploaderNamespace: "CheckUploaderNamespace",
	RemotePluginBackup:     "RemotePluginBackup",
}

var variantValues = [...]string{
	Invalid:                "invalid",
	GetSnapshots:           "get snapshots",
	GetSnapshot:            "get snapshot",
	CreateSnapshot:         "create snapshot",
	DeleteSnapshot:         "delete snapshot",
	RestoreSnapshot:        "restore snapshot",
	GetSnapshotSettings:    "get snapshot settings",
	UpdateSnapshotSettings: "update snapshot settings",
	ExportSnapshot:         "export snapshot",
	DownloadSnapshotZip:    "download snapshot zip",
	StreamSnapshotZip:      "stream snapshot zip",
	GetSnapshotProviders:   "get snapshot providers",
	GetAvailableTables:     "get available tables",
	FullBackup:             "full backup",
	IncrementalBackup:      "incremental backup",
	ImportSnapshot:         "import snapshot",
	SnapshotCleanup:        "snapshot cleanup",
	ReplaceFile:            "replace file",
	DeleteFile:             "delete file",
	SyncFiles:              "sync files",
	ExportPlugin:           "export plugin",
	ExportSelf:             "export self",
	FetchErrorLogs:         "fetch error logs",
	FetchErrorSessions:     "fetch error sessions",
	CheckPluginExists:      "check plugin exists",
	ListPlugins:            "list plugins",
	ListPluginFiles:        "list plugin files",
	EnablePlugin:           "enable plugin",
	DisablePlugin:          "disable plugin",
	DeletePlugin:           "delete plugin",
	GetSyncManifest:        "get sync manifest",
	GetPluginFiles:         "get plugin files",
	GetFileContent:         "get file content",
	RequestMutationToken:   "request mutation token",
	AuthenticateUser:       "authenticate user",
	CheckPluginAccess:      "check plugin access",
	TestWritePermissions:   "test write permissions",
	DeleteTestPost:         "delete test post",
	GetPluginsList:         "get plugins list",
	GetPlugin:              "get plugin",
	CheckNamespace:         "check namespace",
	GetUploaderStatus:      "get uploader status",
	UploadPlugin:           "upload plugin",
	GetSnapshotsFallback:   "get snapshots (array fallback)",
	CheckUploaderNamespace: "check uploader namespace",
}

func (v Variant) String() string  { return v.Value() }
func (v Variant) Label() string   { return safeLabel(v) }
func (v Variant) Value() string   { return safeValue(v) }
func (v Variant) IsValid() bool   { return v > Invalid && v < Variant(len(variantLabels)) }
func (v Variant) IsInvalid() bool { return v == Invalid }
func (v Variant) IsDefined() bool { return v != Invalid }

func safeLabel(v Variant) string {
	if int(v) >= len(variantLabels) {
		return variantLabels[Invalid]
	}

	return variantLabels[v]
}

func safeValue(v Variant) string {
	if int(v) >= len(variantValues) {
		return variantValues[Invalid]
	}

	return variantValues[v]
}

func All() []Variant {
	result := make([]Variant, 0, len(variantLabels)-1)
	for i := 1; i < len(variantLabels); i++ {
		result = append(result, Variant(i))
	}

	return result
}

func Parse(s string) (Variant, error) {
	trimmed := strings.TrimSpace(s)
	for i, str := range variantLabels {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	for i, str := range variantValues {
		if strings.EqualFold(str, trimmed) {
			return Variant(i), nil
		}
	}

	return Invalid, fmt.Errorf("invalid operation: %q", s)
}

func (v Variant) MarshalJSON() ([]byte, error) {
	return json.Marshal(v.Value())
}

func (v *Variant) UnmarshalJSON(data []byte) error {
	var s string
	err := json.Unmarshal(data, &s)

	if err != nil {
		return err
	}

	parsed, err := Parse(s)
	if err != nil {
		return err
	}

	*v = parsed
	return nil
}
