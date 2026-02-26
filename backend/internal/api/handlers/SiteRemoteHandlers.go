// Package handlers provides site remote plugin HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// --- Remote Plugin Management ---

// remotePluginInput is the JSON body struct for remote plugin actions
type remotePluginInput struct {
	Plugin string
	Path   string `json:",omitempty"`
}

// remotePluginParsed holds the parsed site ID and plugin slug from a request.
type remotePluginParsed struct {
	SiteID     int64
	PluginSlug string
}

// parseRemotePluginInput reads and validates the plugin slug from JSON body
func parseRemotePluginInput(r *http.Request) (*remotePluginParsed, error) {
	id, err := getIDParam(r, "id")
	if err != nil {
		return nil, err
	}

	var input remotePluginInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		return nil, err
	}

	return &remotePluginParsed{SiteID: id, PluginSlug: input.Plugin}, nil
}

// parseRemotePluginInputOrFail parses site ID + plugin slug, writing error responses on failure.
// Returns the parsed input and ok=true, or writes an error and returns ok=false.
func parseRemotePluginInputOrFail(w http.ResponseWriter, r *http.Request) (*remotePluginParsed, bool) {
	if !requireService(w, Services.SiteService, "Site service") {
		return nil, false
	}

	parsed, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrConfigParse,
			"Invalid request: "+err.Error(),
		)

		return nil, false
	}

	if parsed.PluginSlug == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrConfigParse,
			"Plugin slug is required in JSON body",
		)

		return nil, false
	}

	return parsed, true
}

// GetRemotePlugins returns all plugins installed on a remote WordPress site
var GetRemotePlugins = handleSiteActionByID(
	apperror.ErrWPPluginList,
	func(ctx context.Context, siteId int64) (any, error) {
		return Services.SiteService.GetRemotePlugins(ctx, siteId)
	},
)

// ForceSyncRemotePlugins clears cache and fetches fresh plugin data
var ForceSyncRemotePlugins = handleSiteActionByID(
	apperror.ErrWPPluginList,
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
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrWPPluginGet,
			err.Error(),
		)

		return
	}

	respondSuccess(w, ActionResponse{IsCleared: true, SiteId: id})
}

// CheckRemotePluginExists performs a lightweight pre-flight check to verify plugin existence
func CheckRemotePluginExists(w http.ResponseWriter, r *http.Request) {
	parsed, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}

	result, err := Services.SiteService.CheckRemotePluginExists(r.Context(), parsed.SiteID, parsed.PluginSlug)
	if err != nil {
		respondErrorWithSession(
			w,
			resolveHTTPStatus(err, wordpress.HttpStatusServerError),
			apperror.ErrWPPluginDelete,
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, PluginExistsResponse{
		IsExists:   result.Exists,
		Status:     result.Status,
		PluginFile: result.PluginFile,
		Plugin:     parsed.PluginSlug,
	})
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func EnableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	parsed, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}

	if err := Services.SiteService.EnableRemotePlugin(r.Context(), parsed.SiteID, parsed.PluginSlug); err != nil {
		respondErrorWithSession(
			w,
			resolveHTTPStatus(err, wordpress.HttpStatusServerError),
			apperror.ErrWPPluginActivate,
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, ActionResponse{IsEnabled: true, Plugin: parsed.PluginSlug})
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
func DisableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	parsed, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}

	if err := Services.SiteService.DisableRemotePlugin(r.Context(), parsed.SiteID, parsed.PluginSlug); err != nil {
		respondErrorWithSession(
			w,
			resolveHTTPStatus(err, wordpress.HttpStatusServerError),
			apperror.ErrWPPluginActivate,
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, ActionResponse{IsDisabled: true, Plugin: parsed.PluginSlug})
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site (POST with JSON body)
func DeleteRemotePlugin(w http.ResponseWriter, r *http.Request) {
	parsed, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}

	if err := Services.SiteService.DeleteRemotePlugin(r.Context(), parsed.SiteID, parsed.PluginSlug); err != nil {
		respondErrorWithSession(
			w,
			resolveHTTPStatus(err, wordpress.HttpStatusServerError),
			apperror.ErrWPPluginDelete,
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, ActionResponse{IsDeleted: true, Plugin: parsed.PluginSlug})
}

// GetRemotePluginFiles returns the file list for a remote plugin
func GetRemotePluginFiles(w http.ResponseWriter, r *http.Request) {
	parsed, ok := parseRemotePluginInputOrFail(w, r)
	if !ok {
		return
	}

	files, err := Services.SiteService.GetRemotePluginFiles(r.Context(), parsed.SiteID, parsed.PluginSlug)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrWPPluginFiles,
			err.Error(),
		)

		return
	}

	respondSuccess(w, files)
}

// pluginFileInput is the JSON body for GetRemotePluginFileContent.
type pluginFileInput struct {
	Plugin string
	Path   string
}

// pluginFileParsed holds the parsed site ID and file input from a request.
type pluginFileParsed struct {
	SiteID int64
	Input  pluginFileInput
}

// parseRemotePluginFileInputOrFail parses site ID + plugin slug + file path, writing error responses on failure.
func parseRemotePluginFileInputOrFail(w http.ResponseWriter, r *http.Request) (*pluginFileParsed, bool) {
	if !requireService(w, Services.SiteService, "Site service") {
		return nil, false
	}

	id, ok := parseID(w, r, "id")
	if !ok {
		return nil, false
	}

	var input pluginFileInput
	if !decodeJSON(w, r, &input) {
		return nil, false
	}

	if input.Plugin == "" || input.Path == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			apperror.ErrConfigParse,
			"Plugin and path are required",
		)

		return nil, false
	}

	return &pluginFileParsed{SiteID: id, Input: input}, true
}

// GetRemotePluginFileContent returns the content of a specific file from a remote plugin
func GetRemotePluginFileContent(w http.ResponseWriter, r *http.Request) {
	parsed, ok := parseRemotePluginFileInputOrFail(w, r)
	if !ok {
		return
	}

	content, err := Services.SiteService.GetRemotePluginFileContent(r.Context(), parsed.SiteID, parsed.Input.Plugin, parsed.Input.Path)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			apperror.ErrWPPluginContent,
			err.Error(),
		)

		return
	}

	respondSuccess(w, FileContentResponse{
		Content: content,
		Plugin:  parsed.Input.Plugin,
		Path:    parsed.Input.Path,
	})
}

// ClearErrorLogHashes resets the in-memory error deduplication map
func ClearErrorLogHashes(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	count := Services.SiteService.ClearErrorLogHashes()

	respondSuccess(w, ActionResponse{
		IsCleared: true,
		Count:     count,
		Message:   fmt.Sprintf("Cleared %d error deduplication hashes", count),
	})
}
