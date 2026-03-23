// Package handlers provides site remote plugin HTTP handlers
package handlers

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"

	"wp-plugin-publish/internal/envelope"
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
	SiteId     int64
	PluginSlug string
}

// parseRemotePluginInput reads and validates the plugin slug from JSON body
func parseRemotePluginInput(r *http.Request) (*remotePluginParsed, error) {
	id, err := getIdParam(r, "id")
	if err != nil {
		return nil, err
	}

	var input remotePluginInput
	decodeErr := json.NewDecoder(r.Body).Decode(&input)
	if decodeErr != nil {
		return nil, decodeErr
	}

	return &remotePluginParsed{SiteId: id, PluginSlug: input.Plugin}, nil
}

// parseRemotePluginInputOrFail parses site ID + plugin slug, writing error responses on failure.
// Returns the parsed input and ok=true, or writes an error and returns ok=false.
func parseRemotePluginInputOrFail(w http.ResponseWriter, r *http.Request) (*remotePluginParsed, bool) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return nil, false
	}

	parsed, err := parseRemotePluginInput(r)
	if err != nil {
		respondBadRequest(w, apperror.ErrConfigParse, "Invalid request: "+err.Error())

		return nil, false
	}

	return validateRemotePluginSlug(w, parsed)
}

// validateRemotePluginSlug ensures the plugin slug is non-empty.
func validateRemotePluginSlug(w http.ResponseWriter, parsed *remotePluginParsed) (*remotePluginParsed, bool) {
	if parsed.PluginSlug == "" {
		respondBadRequest(w, apperror.ErrConfigParse, "Plugin slug is required in JSON body")

		return nil, false
	}

	return parsed, true
}

// GetRemotePlugins returns all plugins installed on a remote WordPress site
var GetRemotePlugins = handleSiteActionById(
	apperror.ErrWPPluginList,
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.GetRemotePlugins(ctx, siteId)
	},
)

// ForceSyncRemotePlugins clears cache and fetches fresh plugin data
var ForceSyncRemotePlugins = handleSiteActionById(
	apperror.ErrWPPluginList,
	func(ctx context.Context, siteId int64) (any, *apperror.AppError) {
		return Services.SiteService.ForceSyncRemotePlugins(ctx, siteId)
	},
)

// ClearRemotePluginsCache invalidates the cache without fetching
func ClearRemotePluginsCache(w http.ResponseWriter, r *http.Request) {
	if isServiceMissing(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseId(w, r, "id")
	if !ok {
		return
	}

	clearCacheOrFail(w, r, id)
}

// clearCacheOrFail invalidates the remote plugins cache and writes the response.
func clearCacheOrFail(w http.ResponseWriter, r *http.Request, id int64) {
	appErr := Services.SiteService.InvalidateRemotePluginsCache(r.Context(), id)
	if appErr != nil {
		respondErrorWithDelegated(w, wordpress.HttpStatusServerError, apperror.ErrWPPluginGet, appErr.Error(), appErr)

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

	checkPluginExistsOrFail(w, r, parsed)
}

// checkPluginExistsOrFail queries plugin existence and writes the response.
func checkPluginExistsOrFail(w http.ResponseWriter, r *http.Request, parsed *remotePluginParsed) {
	result, appErr := Services.SiteService.CheckRemotePluginExists(r.Context(), parsed.SiteId, parsed.PluginSlug)

	if appErr != nil {
		respondErrorWithSession(w, resolveHttpStatus(appErr, wordpress.HttpStatusServerError), apperror.ErrWPPluginDelete, appErr.Error(), appErr)

		return
	}

	respondSuccess(w, PluginExistsResponse{
		IsExists:   result.Exists,
		Status:     result.Status,
		PluginFile: result.PluginFile,
		Plugin:     parsed.PluginSlug,
	})
}

// respondRemotePluginError writes an error response for remote plugin actions.
// Extracts the remote WordPress response body from the error chain if available.
func respondRemotePluginError(w http.ResponseWriter, errCode apperror.ErrorCode, appErr *apperror.AppError) {
	resp := envelope.ErrorWithStack(resolveHttpStatus(appErr, wordpress.HttpStatusServerError).Int(), errCode.String(), appErr.Error())

	if appErr != nil {
		hasSessionId := appErr.Diagnostic.SessionId != ""
		if hasSessionId {
			resp = resp.WithSessionId(appErr.Diagnostic.SessionId)
		}
	}

	remoteBody := extractRemoteResponseBody(appErr)
	hasRemoteBody := remoteBody != ""

	if hasRemoteBody {
		resp = resp.WithRemoteResponseBody(remoteBody)
	}

	envelope.Write(w, resp)
}

// extractRemoteResponseBody walks the error chain to find a wordpress.ApiError
// and returns its ResponseBody field.
func extractRemoteResponseBody(appErr *apperror.AppError) string {
	if appErr == nil {
		return ""
	}

	var apiErr *wordpress.ApiError

	// Walk cause chain: AppError.Cause may be another AppError or a *wordpress.ApiError
	var current error = appErr
	for current != nil {
		if ae, ok := current.(*wordpress.ApiError); ok {
			apiErr = ae
			break
		}
		current = errors.Unwrap(current)
	}

	if apiErr == nil {
		return ""
	}

	return apiErr.ResponseBody
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func EnableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	parsed, ok := parseRemotePluginInputOrFail(w, r)

	if !ok {

		return
	}

	appErr := Services.SiteService.EnableRemotePlugin(r.Context(), parsed.SiteId, parsed.PluginSlug)

	if appErr != nil {
		respondRemotePluginError(w, apperror.ErrWPPluginActivate, appErr)

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

	appErr := Services.SiteService.DisableRemotePlugin(r.Context(), parsed.SiteId, parsed.PluginSlug)

	if appErr != nil {
		respondRemotePluginError(w, apperror.ErrWPPluginActivate, appErr)

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

	appErr := Services.SiteService.DeleteRemotePlugin(r.Context(), parsed.SiteId, parsed.PluginSlug)

	if appErr != nil {
		respondRemotePluginError(w, apperror.ErrWPPluginDelete, appErr)

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

	fetchRemoteFilesOrFail(w, r, parsed)
}

// fetchRemoteFilesOrFail queries remote plugin files and writes the response.
func fetchRemoteFilesOrFail(w http.ResponseWriter, r *http.Request, parsed *remotePluginParsed) {
	files, appErr := Services.SiteService.GetRemotePluginFiles(r.Context(), parsed.SiteId, parsed.PluginSlug)
	if appErr != nil {
		respondError(w, wordpress.HttpStatusServerError, apperror.ErrWPPluginFiles, appErr.Error())

		return
	}

	respondSuccess(w, files)
}
