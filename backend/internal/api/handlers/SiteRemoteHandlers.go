// Package handlers provides site remote plugin HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
)

// --- Remote Plugin Management ---

// remotePluginInput is the JSON body struct for remote plugin actions
type remotePluginInput struct {
	Plugin string
	Path   string `json:",omitempty"`
}

// parseRemotePluginInput reads and validates the plugin slug from JSON body
func parseRemotePluginInput(r *http.Request) (int64, string, error) {
	id, err := getIDParam(r, "id")
	if err != nil {
		return 0, "", err
	}
	var input remotePluginInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		return id, "", err
	}
	return id, input.Plugin, nil
}

// parseRemotePluginInputOrFail parses site ID + plugin slug, writing error responses on failure.
// Returns (siteID, pluginSlug, ok). Callers should return immediately when ok is false.
func parseRemotePluginInputOrFail(w http.ResponseWriter, r *http.Request) (int64, string, bool) {
	if !requireService(w, Services.SiteService, "Site service") {
		return 0, "", false
	}
	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Invalid request: "+err.Error())
		return 0, "", false
	}
	if pluginSlug == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Plugin slug is required in JSON body")
		return 0, "", false
	}
	return id, pluginSlug, true
}

// GetRemotePlugins returns all plugins installed on a remote WordPress site
var GetRemotePlugins = handleSiteActionByID("E3004",
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.GetRemotePlugins(ctx, siteId)
	},
)

// ForceSyncRemotePlugins clears cache and fetches fresh plugin data
var ForceSyncRemotePlugins = handleSiteActionByID("E3004",
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.ForceSyncRemotePlugins(ctx, siteId)
	},
)

// ClearRemotePluginsCache invalidates the cache without fetching
func ClearRemotePluginsCache(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}
	id, ok := parseID(w, r, "id")
	if !ok {
		return
	}
	if err := Services.SiteService.InvalidateRemotePluginsCache(r.Context(), id); err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3005", err.Error())
		return
	}
	respondSuccess(w, ActionResponse{IsCleared: true, SiteId: id})
}

// CheckRemotePluginExists performs a lightweight pre-flight check to verify plugin existence
func CheckRemotePluginExists(w http.ResponseWriter, r *http.Request) {
	id, pluginSlug, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}
	result, err := Services.SiteService.CheckRemotePluginExists(r.Context(), id, pluginSlug)
	if err != nil {
		respondErrorWithSession(w, resolveHTTPStatus(err, wordpress.HttpStatusServerError), "E3010", err.Error(), err)
		return
	}
	respondSuccess(w, PluginExistsResponse{IsExists: result.Exists, Status: result.Status, PluginFile: result.PluginFile, Plugin: pluginSlug})
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func EnableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	id, pluginSlug, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}
	if err := Services.SiteService.EnableRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondErrorWithSession(w, resolveHTTPStatus(err, wordpress.HttpStatusServerError), "E3007", err.Error(), err)
		return
	}
	respondSuccess(w, ActionResponse{IsEnabled: true, Plugin: pluginSlug})
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
func DisableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	id, pluginSlug, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}
	if err := Services.SiteService.DisableRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondErrorWithSession(w, resolveHTTPStatus(err, wordpress.HttpStatusServerError), "E3007", err.Error(), err)
		return
	}
	respondSuccess(w, ActionResponse{IsDisabled: true, Plugin: pluginSlug})
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site (POST with JSON body)
func DeleteRemotePlugin(w http.ResponseWriter, r *http.Request) {
	id, pluginSlug, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}
	if err := Services.SiteService.DeleteRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondErrorWithSession(w, resolveHTTPStatus(err, wordpress.HttpStatusServerError), "E3010", err.Error(), err)
		return
	}
	respondSuccess(w, ActionResponse{IsDeleted: true, Plugin: pluginSlug})
}

// GetRemotePluginFiles returns the file list for a remote plugin
func GetRemotePluginFiles(w http.ResponseWriter, r *http.Request) {
	id, pluginSlug, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}
	files, err := Services.SiteService.GetRemotePluginFiles(r.Context(), id, pluginSlug)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3011", err.Error())
		return
	}
	respondSuccess(w, files)
}

// pluginFileInput is the JSON body for GetRemotePluginFileContent.
type pluginFileInput struct {
	Plugin string
	Path   string
}

// parseRemotePluginFileInputOrFail parses site ID + plugin slug + file path, writing error responses on failure.
func parseRemotePluginFileInputOrFail(w http.ResponseWriter, r *http.Request) (int64, pluginFileInput, bool) {
	if !requireService(w, Services.SiteService, "Site service") {
		return 0, pluginFileInput{}, false
	}
	id, ok := parseID(w, r, "id")
	if !ok {
		return 0, pluginFileInput{}, false
	}
	var input pluginFileInput
	if !decodeJSON(w, r, &input) {
		return 0, pluginFileInput{}, false
	}
	if input.Plugin == "" || input.Path == "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "Plugin and path are required")
		return 0, pluginFileInput{}, false
	}
	return id, input, true
}

// GetRemotePluginFileContent returns the content of a specific file from a remote plugin
func GetRemotePluginFileContent(w http.ResponseWriter, r *http.Request) {
	id, input, ok := parseRemotePluginFileInputOrFail(w, r)
	if !ok {
		return
	}
	content, err := Services.SiteService.GetRemotePluginFileContent(r.Context(), id, input.Plugin, input.Path)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3012", err.Error())
		return
	}
	respondSuccess(w, FileContentResponse{Content: content, Plugin: input.Plugin, Path: input.Path})
}

// ClearErrorLogHashes resets the in-memory error deduplication map
func ClearErrorLogHashes(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}
	count := Services.SiteService.ClearErrorLogHashes()
	respondSuccess(w, ActionResponse{
		IsCleared: true, Count: count,
		Message: fmt.Sprintf("Cleared %d error deduplication hashes", count),
	})
}
