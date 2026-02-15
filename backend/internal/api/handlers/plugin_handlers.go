// Package handlers provides plugin-related HTTP request handlers
package handlers

import (
	"context"
	"net/http"
	"strconv"

	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/wordpress"
)

// GetPlugins returns all registered plugins
var GetPlugins = handleListNilSafe(pluginService, "E3001",
	func(ctx context.Context) (any, error) {
		return Services.PluginService.List(ctx)
	},
)

// CreatePlugin registers a new local plugin directory
func CreatePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	var input plugin.CreateInput
	if !decodeJSON(w, r, &input) {
		return
	}

	p, err := Services.PluginService.Create(r.Context(), input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3002", err.Error())
		return
	}
	respondCreated(w, p)
}

// GetPlugin returns a specific plugin by ID
var GetPlugin = handleActionByID(pluginService, "Plugin service", "id", "plugin ID", "E3003",
	func(ctx context.Context, id int64) (any, error) {
		return Services.PluginService.GetByID(ctx, id)
	},
)

// UpdatePlugin updates an existing plugin
func UpdatePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var input plugin.UpdateInput
	if !decodeJSON(w, r, &input) {
		return
	}

	p, err := Services.PluginService.Update(r.Context(), id, input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3004", err.Error())
		return
	}
	respondSuccess(w, p)
}

// DeletePlugin removes a plugin registration
var DeletePlugin = handleDeleteByID(pluginService, "Plugin service", "id", "plugin ID", "E3005",
	func(ctx context.Context, id int64) error {
		return Services.PluginService.Delete(ctx, id)
	},
)

// GetPluginMappings returns plugin-site mappings
var GetPluginMappings = handleActionByID(pluginService, "Plugin service", "id", "plugin ID", "E3006",
	func(ctx context.Context, id int64) (any, error) {
		return Services.PluginService.GetMappings(ctx, id)
	},
)

// CreatePluginMapping creates a new plugin-site mapping
func CreatePluginMapping(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var input plugin.CreateMappingInput
	if !decodeJSON(w, r, &input) {
		return
	}
	input.PluginID = id

	mapping, err := Services.PluginService.CreateMapping(r.Context(), id, input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3007", err.Error())
		return
	}
	respondCreated(w, mapping)
}

// DeletePluginMapping removes a plugin-site mapping
var DeletePluginMapping = handleDeleteByID(pluginService, "Plugin service", "id", "mapping ID", "E3008",
	func(ctx context.Context, id int64) error {
		return Services.PluginService.DeleteMapping(ctx, id)
	},
)

// UpdatePluginMappings bulk-updates all site mappings for a plugin
func UpdatePluginMappings(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var input struct {
		SiteIDs    []int64 `json:"siteIds"`
		RemoteSlug string  `json:"remoteSlug"`
	}
	if !decodeJSON(w, r, &input) {
		return
	}

	if err := Services.PluginService.UpdateMappingsForPlugin(r.Context(), id, input.SiteIDs, input.RemoteSlug); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3009", err.Error())
		return
	}

	mappings, _ := Services.PluginService.GetMappings(r.Context(), id)
	respondSuccess(w, mappings)
}

// GetSiteMappings returns all plugin mappings for a site
var GetSiteMappings = handleActionByID(pluginService, "Plugin service", "id", "site ID", "E3010",
	func(ctx context.Context, id int64) (any, error) {
		return Services.PluginService.GetMappingsBySite(ctx, id)
	},
)

// UpdateSiteMappings bulk-updates all plugin mappings for a site
func UpdateSiteMappings(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	siteID, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	var input struct {
		PluginIDs []int64 `json:"pluginIds"`
	}
	if !decodeJSON(w, r, &input) {
		return
	}

	if err := Services.PluginService.UpdateMappingsForSite(r.Context(), siteID, input.PluginIDs); err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E3011", err.Error())
		return
	}

	mappings, _ := Services.PluginService.GetMappingsBySite(r.Context(), siteID)
	respondSuccess(w, mappings)
}

// --- Watcher/Scan Handlers ---

// ScanPlugin triggers a file scan for a specific plugin
var ScanPlugin = handleActionByID(watcherService, "Watcher service", "id", "plugin ID", "E6001",
	func(ctx context.Context, id int64) (any, error) {
		return Services.WatcherService.TriggerScan(ctx, id)
	},
)

// ScanAllPlugins triggers a file scan for all plugins
var ScanAllPlugins = handleNoArgs(watcherService, "Watcher service", "E6002",
	func(ctx context.Context) (any, error) {
		return Services.WatcherService.ScanAll(ctx)
	},
)

// ScanDirectoryPath scans a directory path for WordPress plugin and creates wp-plugin-detected.json
func ScanDirectoryPath(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	var input struct {
		Path            string `json:"path"`
		CreateDetection bool   `json:"createDetection"`
	}
	if !decodeJSON(w, r, &input) {
		return
	}

	if input.Path == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Path is required")
		return
	}

	result, err := Services.PluginService.ScanDirectory(r.Context(), input.Path)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E6003", err.Error())
		return
	}

	if input.CreateDetection {
		if err := Services.PluginService.WritePluginDetected(r.Context(), input.Path); err != nil {
			respondSuccess(w, ScanResultResponse{
				Scan:           result,
				DetectionError: err.Error(),
			})
			return
		}
		respondSuccess(w, ScanResultResponse{
			Scan:             result,
			DetectionCreated: true,
		})
		return
	}

	respondSuccess(w, result)
}

// ScanDirectoriesPath scans multiple directories for WordPress plugin info
func ScanDirectoriesPath(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	var input struct {
		Paths           []string `json:"paths"`
		CreateDetection bool     `json:"createDetection"`
	}
	if !decodeJSON(w, r, &input) {
		return
	}

	if len(input.Paths) == 0 {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "At least one path is required")
		return
	}

	results := make([]DirectoryScanResult, 0, len(input.Paths))
	detected := 0

	for _, path := range input.Paths {
		result, err := Services.PluginService.ScanDirectory(r.Context(), path)
		if err != nil {
			results = append(results, DirectoryScanResult{Path: path, IsPlugin: false, Error: err.Error()})
			continue
		}

		isPlugin := result != nil && result.IsValid
		if isPlugin {
			detected++
		}

		sr := DirectoryScanResult{Path: path, IsPlugin: isPlugin, Metadata: result}

		if input.CreateDetection && isPlugin {
			if err := Services.PluginService.WritePluginDetected(r.Context(), path); err == nil {
				sr.DetectionCreated = true
			}
		}

		results = append(results, sr)
	}

	respondSuccess(w, MultiScanResponse{
		Scanned:  len(input.Paths),
		Detected: detected,
		Results:  results,
	})
}

// GetFileChanges returns detected file changes for a plugin
func GetFileChanges(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SyncService == nil {
		respondSuccess(w, []any{})
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var siteID int64
	if siteIDStr := r.URL.Query().Get("siteId"); siteIDStr != "" {
		siteID, _ = strconv.ParseInt(siteIDStr, 10, 64)
	}

	changes, err := Services.SyncService.GetFileChanges(r.Context(), id, siteID)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E4001", err.Error())
		return
	}
	respondSuccess(w, changes)
}
