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
)

// WPEndpointName identifies a WordPress-delegated operation.
// Used in logs, error diagnostics, and endpoint validation.
type WPEndpointName string

const (
	EPListPlugins       WPEndpointName = "ListPlugins"
	EPPluginExists      WPEndpointName = "PluginExists"
	EPEnablePlugin      WPEndpointName = "EnablePlugin"
	EPDisablePlugin     WPEndpointName = "DisablePlugin"
	EPDeletePlugin      WPEndpointName = "DeletePlugin"
	EPUploadPlugin      WPEndpointName = "UploadPlugin"
	EPPluginFiles       WPEndpointName = "PluginFiles"
	EPPluginFileContent WPEndpointName = "PluginFileContent"
	EPPluginExport      WPEndpointName = "PluginExport"
	EPSyncManifest      WPEndpointName = "SyncManifest"
	EPSyncPush          WPEndpointName = "SyncPush"
	EPStatus            WPEndpointName = "Status"
	EPExportSelf        WPEndpointName = "ExportSelf"
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
	Endpoint string // relative to namespace, e.g. "/plugins/enable"
}

// GoEndpointMap maps each operation enum to the Go backend API route.
var GoEndpointMap = map[WPEndpointName]GoEndpointRoute{
	EPListPlugins:       {Method: "GET", Pattern: "/api/v1/sites/{id}/remote-plugins"},
	EPPluginExists:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/exists"},
	EPEnablePlugin:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/enable"},
	EPDisablePlugin:     {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/disable"},
	EPDeletePlugin:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/delete"},
	EPUploadPlugin:      {Method: "POST", Pattern: "/api/v1/sites/{id}/publish"},
	EPPluginFiles:       {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/files"},
	EPPluginFileContent: {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/file"},
	EPPluginExport:      {Method: "POST", Pattern: "/api/v1/sites/{id}/remote-plugins/export"},
	EPSyncManifest:      {Method: "POST", Pattern: "/api/v1/sites/{id}/sync/manifest"},
	EPSyncPush:          {Method: "POST", Pattern: "/api/v1/sites/{id}/sync/push"},
	EPStatus:            {Method: "GET", Pattern: "/api/v1/sites/{id}/status"},
	EPExportSelf:        {Method: "GET", Pattern: "/api/v1/sites/{id}/export-self"},
}

// WPEndpointMap maps each operation enum to the WordPress Riseup Asia Uploader REST endpoint.
// Endpoints are relative to the plugin namespace (riseup-asia-uploader/v1).
var WPEndpointMap = map[WPEndpointName]WPEndpointRoute{
	EPListPlugins:       {Method: "GET", Endpoint: EndpointPlugins},
	EPPluginExists:      {Method: "POST", Endpoint: EndpointPluginExists},
	EPEnablePlugin:      {Method: "POST", Endpoint: EndpointEnable},
	EPDisablePlugin:     {Method: "POST", Endpoint: EndpointDisable},
	EPDeletePlugin:      {Method: "POST", Endpoint: EndpointDelete},
	EPUploadPlugin:      {Method: "POST", Endpoint: EndpointUpload},
	EPPluginFiles:       {Method: "POST", Endpoint: EndpointFiles},
	EPPluginFileContent: {Method: "POST", Endpoint: EndpointFile},
	EPPluginExport:      {Method: "POST", Endpoint: EndpointExportPlugin},
	EPSyncManifest:      {Method: "POST", Endpoint: EndpointSyncManifest},
	EPSyncPush:          {Method: "POST", Endpoint: EndpointSync},
	EPStatus:            {Method: "GET", Endpoint: EndpointStatus},
	EPExportSelf:        {Method: "GET", Endpoint: EndpointExportSelf},
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
	return "/" + RiseupAsiaNamespace + route.Endpoint
}

func replaceID(pattern string, id int64) string {
	if id > 0 {
		return strings.ReplaceAll(pattern, "{id}", strconv.FormatInt(id, 10))
	}
	return pattern
}
