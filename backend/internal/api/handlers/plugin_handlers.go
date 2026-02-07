// Package handlers provides plugin-related HTTP request handlers
package handlers

import (
	"net/http"
	"strconv"
)

// GetPlugins returns all registered plugins
func GetPlugins(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondSuccess(w, []interface{}{})
		return
	}
	plugins, err := Services.PluginService.List(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3001", err.Error())
		return
	}
	respondSuccess(w, plugins)
}

// CreatePlugin registers a new local plugin directory
func CreatePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	var input map[string]interface{}
	if !decodeJSON(w, r, &input) {
		return
	}

	plugin, err := Services.PluginService.Create(r.Context(), input)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E3002", err.Error())
		return
	}
	respondCreated(w, plugin)
}

// GetPlugin returns a specific plugin by ID
func GetPlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	plugin, err := Services.PluginService.GetByID(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "E3003", err.Error())
		return
	}
	respondSuccess(w, plugin)
}

// UpdatePlugin updates an existing plugin
func UpdatePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var input map[string]interface{}
	if !decodeJSON(w, r, &input) {
		return
	}

	plugin, err := Services.PluginService.Update(r.Context(), id, input)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E3004", err.Error())
		return
	}
	respondSuccess(w, plugin)
}

// DeletePlugin removes a plugin registration
func DeletePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	if err := Services.PluginService.Delete(r.Context(), id); err != nil {
		respondError(w, http.StatusBadRequest, "E3005", err.Error())
		return
	}
	respondDeleted(w)
}

// GetPluginMappings returns plugin-site mappings
func GetPluginMappings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	mappings, err := Services.PluginService.GetMappings(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3006", err.Error())
		return
	}
	respondSuccess(w, mappings)
}

// CreatePluginMapping creates a new plugin-site mapping
func CreatePluginMapping(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	var input map[string]interface{}
	if !decodeJSON(w, r, &input) {
		return
	}

	mapping, err := Services.PluginService.CreateMapping(r.Context(), id, input)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E3007", err.Error())
		return
	}
	respondCreated(w, mapping)
}

// DeletePluginMapping removes a plugin-site mapping
func DeletePluginMapping(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	id, ok := parseID(w, r, "id", "mapping ID")
	if !ok {
		return
	}

	if err := Services.PluginService.DeleteMapping(r.Context(), id); err != nil {
		respondError(w, http.StatusBadRequest, "E3008", err.Error())
		return
	}
	respondDeleted(w)
}

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
		respondError(w, http.StatusBadRequest, "E3009", err.Error())
		return
	}

	mappings, _ := Services.PluginService.GetMappings(r.Context(), id)
	respondSuccess(w, mappings)
}

// GetSiteMappings returns all plugin mappings for a site
func GetSiteMappings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	mappings, err := Services.PluginService.GetMappingsBySite(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3010", err.Error())
		return
	}
	respondSuccess(w, mappings)
}

// UpdateSiteMappings bulk-updates all plugin mappings for a site
func UpdateSiteMappings(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.PluginService, "Plugin service") {
		return
	}

	siteID, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	var raw struct {
		PluginIDs []interface{} `json:"pluginIds"`
	}
	if !decodeJSON(w, r, &raw) {
		return
	}

	// Convert JSON numbers (float64) to int64
	pluginIDs := make([]int64, 0, len(raw.PluginIDs))
	for _, v := range raw.PluginIDs {
		switch id := v.(type) {
		case float64:
			pluginIDs = append(pluginIDs, int64(id))
		case int64:
			pluginIDs = append(pluginIDs, id)
		case int:
			pluginIDs = append(pluginIDs, int64(id))
		}
	}

	if err := Services.PluginService.UpdateMappingsForSite(r.Context(), siteID, pluginIDs); err != nil {
		respondError(w, http.StatusBadRequest, "E3011", err.Error())
		return
	}

	mappings, _ := Services.PluginService.GetMappingsBySite(r.Context(), siteID)
	respondSuccess(w, mappings)
}

// --- Watcher/Scan Handlers ---

// ScanPlugin triggers a file scan for a specific plugin
func ScanPlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.WatcherService, "Watcher service") {
		return
	}

	pluginID, ok := parseID(w, r, "id", "plugin ID")
	if !ok {
		return
	}

	result, err := Services.WatcherService.TriggerScan(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E6001", err.Error())
		return
	}
	respondSuccess(w, result)
}

// ScanAllPlugins triggers a file scan for all plugins
func ScanAllPlugins(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.WatcherService, "Watcher service") {
		return
	}

	result, err := Services.WatcherService.ScanAll(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E6002", err.Error())
		return
	}
	respondSuccess(w, result)
}

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
		respondError(w, http.StatusBadRequest, "E1002", "Path is required")
		return
	}

	result, err := Services.PluginService.ScanDirectory(r.Context(), input.Path)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E6003", err.Error())
		return
	}

	if input.CreateDetection {
		if err := Services.PluginService.WritePluginDetected(r.Context(), input.Path); err != nil {
			respondSuccess(w, map[string]interface{}{
				"scan":           result,
				"detectionError": err.Error(),
			})
			return
		}
		respondSuccess(w, map[string]interface{}{
			"scan":             result,
			"detectionCreated": true,
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
		respondError(w, http.StatusBadRequest, "E1002", "At least one path is required")
		return
	}

	type scanResult struct {
		Path             string      `json:"path"`
		IsPlugin         bool        `json:"isPlugin"`
		Metadata         interface{} `json:"metadata,omitempty"`
		Error            string      `json:"error,omitempty"`
		DetectionCreated bool        `json:"detectionCreated,omitempty"`
	}

	results := make([]scanResult, 0, len(input.Paths))
	detected := 0

	for _, path := range input.Paths {
		result, err := Services.PluginService.ScanDirectory(r.Context(), path)
		if err != nil {
			results = append(results, scanResult{Path: path, IsPlugin: false, Error: err.Error()})
			continue
		}

		isPlugin := false
		if scanMap, ok := result.(map[string]interface{}); ok {
			if valid, ok := scanMap["isValid"].(bool); ok && valid {
				isPlugin = true
				detected++
			}
		}

		sr := scanResult{Path: path, IsPlugin: isPlugin, Metadata: result}

		if input.CreateDetection && isPlugin {
			if err := Services.PluginService.WritePluginDetected(r.Context(), path); err == nil {
				sr.DetectionCreated = true
			}
		}

		results = append(results, sr)
	}

	respondSuccess(w, map[string]interface{}{
		"scanned":  len(input.Paths),
		"detected": detected,
		"results":  results,
	})
}

// GetFileChanges returns detected file changes for a plugin
func GetFileChanges(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SyncService == nil {
		respondSuccess(w, []interface{}{})
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
		respondError(w, http.StatusInternalServerError, "E4001", err.Error())
		return
	}
	respondSuccess(w, changes)
}
