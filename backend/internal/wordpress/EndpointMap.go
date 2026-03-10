// Package wordpress provides WordPress endpoint mapping constants.
//
// This file defines a centralized enum→route mapping for all
// WordPress-delegated operations. When the Go backend forwards a
// request to a remote WordPress site, this map identifies both the
// Go API route that initiated the call and the WordPress REST
// endpoint that receives it.
//
// See spec/wp-plugin-publish/05-endpoint-mapping.md for full documentation.
package wordpress

import (
	"strconv"
	"strings"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
)

// WPEndpointName identifies a WordPress-delegated operation.
// Used in logs, error diagnostics, and endpoint validation.
type WPEndpointName string

const (
	EPListPlugins       WPEndpointName = "ListPlugins"
	EPPluginExists      WPEndpointName = "PluginExists"
	EPPluginInfo        WPEndpointName = "PluginInfo"
	EPEnablePlugin      WPEndpointName = "EnablePlugin"
	EPDisablePlugin     WPEndpointName = "DisablePlugin"
	EPDeletePlugin      WPEndpointName = "DeletePlugin"
	EPUploadPlugin      WPEndpointName = "UploadPlugin"
	EPUploadActive      WPEndpointName = "UploadActive"
	EPPluginFiles       WPEndpointName = "PluginFiles"
	EPPluginFileContent WPEndpointName = "PluginFileContent"
	EPPluginExport      WPEndpointName = "PluginExport"
	EPSyncManifest      WPEndpointName = "SyncManifest"
	EPSyncPush          WPEndpointName = "SyncPush"
	EPStatus            WPEndpointName = "Status"
	EPExportSelf        WPEndpointName = "ExportSelf"
	EPPluginBackup      WPEndpointName = "PluginBackup"
	// System endpoints
	EPOpenapi       WPEndpointName = "Openapi"
	EPOpcacheReset  WPEndpointName = "OpcacheReset"

	// Error log endpoints
	EPErrorLogs     WPEndpointName = "ErrorLogs"
	EPErrorSessions WPEndpointName = "ErrorSessions"

	// Snapshot endpoints
	EPSnapshotList           WPEndpointName = "SnapshotList"
	EPSnapshotSchedule       WPEndpointName = "SnapshotSchedule"
	EPSnapshotInfo           WPEndpointName = "SnapshotInfo"
	EPSnapshotDelete         WPEndpointName = "SnapshotDelete"
	EPSnapshotRestore        WPEndpointName = "SnapshotRestore"
	EPSnapshotExport         WPEndpointName = "SnapshotExport"
	EPSnapshotSettings       WPEndpointName = "SnapshotSettings"
	EPSnapshotSettingsUpdate WPEndpointName = "SnapshotSettingsUpdate"
	EPSnapshotSettingsPut    WPEndpointName = "SnapshotSettingsPut"
	EPSnapshotProviders      WPEndpointName = "SnapshotProviders"
	EPSnapshotTables         WPEndpointName = "SnapshotTables"
	EPSnapshotDependencies   WPEndpointName = "SnapshotDependencies"
	EPSnapshotExportPertable WPEndpointName = "SnapshotExportPertable"
	EPSnapshotFullBackup     WPEndpointName = "SnapshotFullBackup"
	EPSnapshotIncremental    WPEndpointName = "SnapshotIncremental"
	EPSnapshotImport         WPEndpointName = "SnapshotImport"
	EPSnapshotCleanup        WPEndpointName = "SnapshotCleanup"
	EPSnapshotProgress       WPEndpointName = "SnapshotProgress"
	EPSnapshotDownload       WPEndpointName = "SnapshotDownload"
	EPSnapshotDownloadFile   WPEndpointName = "SnapshotDownloadFile"

	// Agent endpoints
	EPAgents        WPEndpointName = "Agents"
	EPAgentsAdd     WPEndpointName = "AgentsAdd"
	EPAgentsRemove  WPEndpointName = "AgentsRemove"
	EPAgentsTest    WPEndpointName = "AgentsTest"
	EPAgentsSync    WPEndpointName = "AgentsSync"
	EPAgentsPlugins WPEndpointName = "AgentsPlugins"
	EPAgentAction   WPEndpointName = "AgentAction"
	EPAgentHistory  WPEndpointName = "AgentHistory"
)

// GoEndpointRoute describes the Go backend API route for a delegated operation.
// The {id} placeholder represents the site ID.
type GoEndpointRoute struct {
	Method  httpmethod.Variant
	Pattern string // e.g. "/api/v1/sites/{id}/remote-plugins/enable"
}

// WPEndpointRoute describes the WordPress REST API endpoint that receives the delegated request.
type WPEndpointRoute struct {
	Method   httpmethod.Variant
	Endpoint ep.Variant // typed endpoint path
}

// GoEndpointMap maps each operation enum to the Go backend API route.
var GoEndpointMap = map[WPEndpointName]GoEndpointRoute{
	// Plugin operations
	EPListPlugins:       {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/remote-plugins"},
	EPPluginExists:      {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/exists"},
	EPPluginInfo:        {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/info"},
	EPEnablePlugin:      {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/enable"},
	EPDisablePlugin:     {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/disable"},
	EPDeletePlugin:      {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/delete"},
	EPUploadPlugin:      {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/publish"},
	EPUploadActive:      {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/publish-active"},
	EPPluginFiles:       {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/files"},
	EPPluginFileContent: {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/file"},
	EPPluginExport:      {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/remote-plugins/export"},
	EPSyncManifest:      {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/sync/manifest"},
	EPSyncPush:          {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/sync/push"},
	EPStatus:            {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/status"},
	EPExportSelf:        {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/export-self"},

	// System operations
	EPOpenapi:      {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/openapi"},
	EPOpcacheReset: {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/opcache-reset"},

	// Error log operations
	EPErrorLogs:     {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/error-logs"},
	EPErrorSessions: {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/error-sessions"},

	// Snapshot operations
	EPSnapshotList:           {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/snapshots"},
	EPSnapshotSchedule:       {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots"},
	EPSnapshotInfo:           {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/info"},
	EPSnapshotDelete:         {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/delete"},
	EPSnapshotRestore:        {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/restore"},
	EPSnapshotExport:         {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/export"},
	EPSnapshotSettings:       {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/snapshots/settings"},
	EPSnapshotSettingsUpdate: {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/settings"},
	EPSnapshotSettingsPut:    {Method: httpmethod.Put, Pattern: "/api/v1/sites/{id}/snapshots/settings"},
	EPSnapshotProviders:      {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/snapshots/providers"},
	EPSnapshotTables:         {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/snapshots/tables"},
	EPSnapshotDependencies:   {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/dependencies"},
	EPSnapshotExportPertable: {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/export-pertable"},
	EPSnapshotFullBackup:     {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/full-backup"},
	EPSnapshotIncremental:    {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/incremental"},
	EPSnapshotImport:         {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/import"},
	EPSnapshotCleanup:        {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/cleanup"},
	EPSnapshotProgress:       {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/progress"},
	EPSnapshotDownload:       {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/snapshots/download"},
	EPSnapshotDownloadFile:   {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/snapshots/download-file"},

	// Agent operations
	EPAgents:        {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/agents"},
	EPAgentsAdd:     {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/agents"},
	EPAgentsRemove:  {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/agents/remove"},
	EPAgentsTest:    {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/agents/test"},
	EPAgentsSync:    {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/agents/sync"},
	EPAgentsPlugins: {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/agents/plugins"},
	EPAgentAction:   {Method: httpmethod.Post, Pattern: "/api/v1/sites/{id}/agents/action"},
	EPAgentHistory:  {Method: httpmethod.Get, Pattern: "/api/v1/sites/{id}/agents/history"},
}

// WPEndpointMap maps each operation enum to the WordPress Riseup Asia Uploader REST endpoint.
// Endpoints are relative to the plugin namespace (riseup-asia-uploader/v1).
var WPEndpointMap = map[WPEndpointName]WPEndpointRoute{
	// Plugin operations
	EPListPlugins:       {Method: httpmethod.Get, Endpoint: ep.Plugins},
	EPPluginExists:      {Method: httpmethod.Post, Endpoint: ep.PluginExists},
	EPPluginInfo:        {Method: httpmethod.Post, Endpoint: ep.PluginInfo},
	EPEnablePlugin:      {Method: httpmethod.Post, Endpoint: ep.Enable},
	EPDisablePlugin:     {Method: httpmethod.Post, Endpoint: ep.Disable},
	EPDeletePlugin:      {Method: httpmethod.Post, Endpoint: ep.Delete},
	EPUploadPlugin:      {Method: httpmethod.Post, Endpoint: ep.Upload},
	EPUploadActive:      {Method: httpmethod.Post, Endpoint: ep.UploadActive},
	EPPluginFiles:       {Method: httpmethod.Post, Endpoint: ep.Files},
	EPPluginFileContent: {Method: httpmethod.Post, Endpoint: ep.File},
	EPPluginExport:      {Method: httpmethod.Post, Endpoint: ep.ExportPlugin},
	EPSyncManifest:      {Method: httpmethod.Post, Endpoint: ep.SyncManifest},
	EPSyncPush:          {Method: httpmethod.Post, Endpoint: ep.Sync},
	EPStatus:            {Method: httpmethod.Get, Endpoint: ep.Status},
	EPExportSelf:        {Method: httpmethod.Get, Endpoint: ep.ExportSelf},

	// System operations
	EPOpenapi:      {Method: httpmethod.Get, Endpoint: ep.Openapi},
	EPOpcacheReset: {Method: httpmethod.Post, Endpoint: ep.OpcacheReset},

	// Error log operations
	EPErrorLogs:     {Method: httpmethod.Get, Endpoint: ep.ErrorLogs},
	EPErrorSessions: {Method: httpmethod.Get, Endpoint: ep.ErrorSessions},

	// Snapshot operations
	EPSnapshotList:           {Method: httpmethod.Get, Endpoint: ep.SnapshotsList},
	EPSnapshotSchedule:       {Method: httpmethod.Post, Endpoint: ep.SnapshotsSchedule},
	EPSnapshotInfo:           {Method: httpmethod.Post, Endpoint: ep.SnapshotsInfo},
	EPSnapshotDelete:         {Method: httpmethod.Post, Endpoint: ep.SnapshotsDelete},
	EPSnapshotRestore:        {Method: httpmethod.Post, Endpoint: ep.SnapshotsRestore},
	EPSnapshotExport:         {Method: httpmethod.Post, Endpoint: ep.SnapshotsExport},
	EPSnapshotSettings:       {Method: httpmethod.Get, Endpoint: ep.SnapshotsSettings},
	EPSnapshotSettingsUpdate: {Method: httpmethod.Post, Endpoint: ep.SnapshotsSettings},
	EPSnapshotSettingsPut:    {Method: httpmethod.Put, Endpoint: ep.SnapshotsSettings},
	EPSnapshotProviders:      {Method: httpmethod.Get, Endpoint: ep.SnapshotsProviders},
	EPSnapshotTables:         {Method: httpmethod.Get, Endpoint: ep.SnapshotsTables},
	EPSnapshotDependencies:   {Method: httpmethod.Post, Endpoint: ep.SnapshotsDependencies},
	EPSnapshotExportPertable: {Method: httpmethod.Post, Endpoint: ep.SnapshotsExportPertable},
	EPSnapshotFullBackup:     {Method: httpmethod.Post, Endpoint: ep.SnapshotsFullBackup},
	EPSnapshotIncremental:    {Method: httpmethod.Post, Endpoint: ep.SnapshotsIncremental},
	EPSnapshotImport:         {Method: httpmethod.Post, Endpoint: ep.SnapshotsImport},
	EPSnapshotCleanup:        {Method: httpmethod.Post, Endpoint: ep.SnapshotsCleanup},
	EPSnapshotProgress:       {Method: httpmethod.Post, Endpoint: ep.SnapshotsProgress},
	EPSnapshotDownload:       {Method: httpmethod.Post, Endpoint: ep.SnapshotsDownload},
	EPSnapshotDownloadFile:   {Method: httpmethod.Get, Endpoint: ep.SnapshotsDownloadFile},

	// Agent operations
	EPAgents:        {Method: httpmethod.Get, Endpoint: ep.Agents},
	EPAgentsAdd:     {Method: httpmethod.Post, Endpoint: ep.AgentsAdd},
	EPAgentsRemove:  {Method: httpmethod.Post, Endpoint: ep.AgentsRemove},
	EPAgentsTest:    {Method: httpmethod.Post, Endpoint: ep.AgentsTest},
	EPAgentsSync:    {Method: httpmethod.Post, Endpoint: ep.AgentsSync},
	EPAgentsPlugins: {Method: httpmethod.Post, Endpoint: ep.AgentsPlugins},
	EPAgentAction:   {Method: httpmethod.Post, Endpoint: ep.AgentAction},
	EPAgentHistory:  {Method: httpmethod.Get, Endpoint: ep.AgentHistory},
}

// ResolveGoEndpoint returns the Go backend route for a given operation,
// replacing {id} with the actual site Id.
func ResolveGoEndpoint(name WPEndpointName, siteId int64) string {
	route, ok := GoEndpointMap[name]
	if !ok {
		return "UNKNOWN"
	}
	return replaceId(route.Pattern, siteId)
}

// ResolveWPEndpoint returns the full WordPress REST API path for a given operation.
func ResolveWPEndpoint(name WPEndpointName) string {
	route, ok := WPEndpointMap[name]
	if !ok {
		return "UNKNOWN"
	}
	return "/" + RiseupAsiaNamespace + route.Endpoint.String()
}

func replaceId(pattern string, id int64) string {
	hasId := id > 0

	if hasId {
		return strings.ReplaceAll(pattern, "{id}", strconv.FormatInt(id, 10))
	}
	return pattern
}
