// Package handlers provides site remote plugin file HTTP handlers
package handlers

import (
	"fmt"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

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
