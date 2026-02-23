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

	ep "wp-plugin-publish/internal/enums/endpoint"
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

	// Error log endpoints
	EPErrorLogs     WPEndpointName = "ErrorLogs"
	EPErrorSessions WPEndpointName = "ErrorSessions"

	// Snapshot endpoints
	EPSnapshotList         WPEndpointName = "SnapshotList"
	EPSnapshotSchedule     WPEndpointName = "SnapshotSchedule"
	EPSnapshotInfo         WPEndpointName = "SnapshotInfo"
	EPSnapshotDelete       WPEndpointName = "SnapshotDelete"
	EPSnapshotRestore      WPEndpointName = "SnapshotRestore"
	EPSnapshotExport       WPEndpointName = "SnapshotExport"
	EPSnapshotSettings       WPEndpointName = "SnapshotSettings"
	EPSnapshotSettingsUpdate WPEndpointName = "SnapshotSettingsUpdate"
	EPSnapshotSettingsPut    WPEndpointName = "SnapshotSettingsPut"
	EPSnapshotProviders    WPEndpointName = "SnapshotProviders"
	EPSnapshotTables       WPEndpointName = "SnapshotTables"
	EPSnapshotFullBackup   WPEndpointName = "SnapshotFullBackup"
	EPSnapshotIncremental  WPEndpointName = "SnapshotIncremental"
	EPSnapshotImport       WPEndpointName = "SnapshotImport"
	EPSnapshotCleanup      WPEndpointName = "SnapshotCleanup"
	EPSnapshotDownload     WPEndpointName = "SnapshotDownload"
	EPSnapshotDownloadFile WPEndpointName = "SnapshotDownloadFile"
)

// GoEndpointRoute describes the Go backend API route for a delegated operation.
// The {id} placeholder represents the site ID.
type GoEndpointRoute struct {
	Method  string
	Pattern string // e.g. "/api/v1/sites/{id}/remote-plugins/enable"
}

// WPEndpointRoute describes the WordPress REST API endpoint that receives the delegated request.
type WPEndpointRoute struct {
	Method   string
	Endpoint ep.Variant // typed endpoint path
}

// GoEndpointMap maps each operation enum to the Go backend API route.
var GoEndpointMap = map[WPEndpointName]GoEndpointRoute{
	// Plugin operations
	EPListPlugins:       {Method: "GET", Pattern: "/api/v1/sites/{id}/remote-plugins"},
	EPPluginExists:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/exists"},
	EPPluginInfo:        {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/info"},
	EPEnablePlugin:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/enable"},
	EPDisablePlugin:     {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/disable"},
	EPDeletePlugin:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/delete"},
	EPUploadPlugin:      {Method: "POST", Pattern: "/api/v1/sites/{id}/publish"},
	EPUploadActive:      {Method: "POST", Pattern: "/api/v1/sites/{id}/publish-active"},
	EPPluginFiles:       {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/files"},
	EPPluginFileContent: {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/file"},
	EPPluginExport:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/export"},
	EPSyncManifest:      {Method: "POST", Pattern: "/api/v1/sites/{id}/sync/manifest"},
	EPSyncPush:          {Method: "POST", Pattern: "/api/v1/sites/{id}/sync/push"},
	EPStatus:            {Method: "GET", Pattern: "/api/v1/sites/{id}/status"},
	EPExportSelf:        {Method: "GET", Pattern: "/api/v1/sites/{id}/export-self"},

	// Error log operations
	EPErrorLogs:     {Method: "GET", Pattern: "/api/v1/sites/{id}/error-logs"},
	EPErrorSessions: {Method: "GET", Pattern: "/api/v1/sites/{id}/error-sessions"},

	// Snapshot operations
	EPSnapshotList:         {Method: "GET", Pattern: "/api/v1/sites/{id}/snapshots"},
	EPSnapshotSchedule:     {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots"},
	EPSnapshotInfo:         {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/info"},
	EPSnapshotDelete:       {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/delete"},
	EPSnapshotRestore:      {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/restore"},
	EPSnapshotExport:       {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/export"},
	EPSnapshotSettings:       {Method: "GET", Pattern: "/api/v1/sites/{id}/snapshots/settings"},
	EPSnapshotSettingsUpdate: {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/settings"},
	EPSnapshotSettingsPut:    {Method: "PUT", Pattern: "/api/v1/sites/{id}/snapshots/settings"},
	EPSnapshotProviders:    {Method: "GET", Pattern: "/api/v1/sites/{id}/snapshots/providers"},
	EPSnapshotTables:       {Method: "GET", Pattern: "/api/v1/sites/{id}/snapshots/tables"},
	EPSnapshotFullBackup:   {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/full-backup"},
	EPSnapshotIncremental:  {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/incremental"},
	EPSnapshotImport:       {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/import"},
	EPSnapshotCleanup:      {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/cleanup"},
	EPSnapshotDownload:     {Method: "POST", Pattern: "/api/v1/sites/{id}/snapshots/download"},
	EPSnapshotDownloadFile: {Method: "GET", Pattern: "/api/v1/sites/{id}/snapshots/download-file"},
}

// WPEndpointMap maps each operation enum to the WordPress Riseup Asia Uploader REST endpoint.
// Endpoints are relative to the plugin namespace (riseup-asia-uploader/v1).
var WPEndpointMap = map[WPEndpointName]WPEndpointRoute{
	// Plugin operations
	EPListPlugins:       {Method: "GET", Endpoint: ep.Plugins},
	EPPluginExists:      {Method: "POST", Endpoint: ep.PluginExists},
	EPPluginInfo:        {Method: "POST", Endpoint: ep.PluginInfo},
	EPEnablePlugin:      {Method: "POST", Endpoint: ep.Enable},
	EPDisablePlugin:     {Method: "POST", Endpoint: ep.Disable},
	EPDeletePlugin:      {Method: "POST", Endpoint: ep.Delete},
	EPUploadPlugin:      {Method: "POST", Endpoint: ep.Upload},
	EPUploadActive:      {Method: "POST", Endpoint: ep.UploadActive},
	EPPluginFiles:       {Method: "POST", Endpoint: ep.Files},
	EPPluginFileContent: {Method: "POST", Endpoint: ep.File},
	EPPluginExport:      {Method: "POST", Endpoint: ep.ExportPlugin},
	EPSyncManifest:      {Method: "POST", Endpoint: ep.SyncManifest},
	EPSyncPush:          {Method: "POST", Endpoint: ep.Sync},
	EPStatus:            {Method: "GET", Endpoint: ep.Status},
	EPExportSelf:        {Method: "GET", Endpoint: ep.ExportSelf},

	// Error log operations
	EPErrorLogs:     {Method: "GET", Endpoint: ep.ErrorLogs},
	EPErrorSessions: {Method: "GET", Endpoint: ep.ErrorSessions},

	// Snapshot operations
	EPSnapshotList:         {Method: "GET", Endpoint: ep.SnapshotsList},
	EPSnapshotSchedule:     {Method: "POST", Endpoint: ep.SnapshotsSchedule},
	EPSnapshotInfo:         {Method: "POST", Endpoint: ep.SnapshotsInfo},
	EPSnapshotDelete:       {Method: "POST", Endpoint: ep.SnapshotsDelete},
	EPSnapshotRestore:      {Method: "POST", Endpoint: ep.SnapshotsRestore},
	EPSnapshotExport:       {Method: "POST", Endpoint: ep.SnapshotsExport},
	EPSnapshotSettings:       {Method: "GET", Endpoint: ep.SnapshotsSettings},
	EPSnapshotSettingsUpdate: {Method: "POST", Endpoint: ep.SnapshotsSettings},
	EPSnapshotSettingsPut:    {Method: "PUT", Endpoint: ep.SnapshotsSettings},
	EPSnapshotProviders:    {Method: "GET", Endpoint: ep.SnapshotsProviders},
	EPSnapshotTables:       {Method: "GET", Endpoint: ep.SnapshotsTables},
	EPSnapshotFullBackup:   {Method: "POST", Endpoint: ep.SnapshotsFullBackup},
	EPSnapshotIncremental:  {Method: "POST", Endpoint: ep.SnapshotsIncremental},
	EPSnapshotImport:       {Method: "POST", Endpoint: ep.SnapshotsImport},
	EPSnapshotCleanup:      {Method: "POST", Endpoint: ep.SnapshotsCleanup},
	EPSnapshotDownload:     {Method: "POST", Endpoint: ep.SnapshotsDownload},
	EPSnapshotDownloadFile: {Method: "GET", Endpoint: ep.SnapshotsDownloadFile},
}

// ResolveGoEndpoint returns the Go backend route for a given operation,
// replacing {id} with the actual site ID.
func ResolveGoEndpoint(name WPEndpointName, siteID int64) string {
	route, ok := GoEndpointMap[name]
	if !ok {
		return "UNKNOWN"
	}
	return replaceID(route.Pattern, siteID)
}

// ResolveWPEndpoint returns the full WordPress REST API path for a given operation.
func ResolveWPEndpoint(name WPEndpointName) string {
	route, ok := WPEndpointMap[name]
	if !ok {
		return "UNKNOWN"
	}
	return "/" + RiseupAsiaNamespace + route.Endpoint.String()
}

func replaceID(pattern string, id int64) string {
	if id > 0 {
		return strings.ReplaceAll(pattern, "{id}", strconv.FormatInt(id, 10))
	}
	return pattern
}
