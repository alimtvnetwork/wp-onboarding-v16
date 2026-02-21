// Package handlers provides site-related HTTP request handlers
package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"

	"wp-plugin-publish/internal/wordpress"
)

// SiteCreateInput represents the request body for creating a site
type SiteCreateInput struct {
	Name     string `json:"name"`
	URL      string `json:"url"`
	Username string `json:"username"`
	// Accept both legacy "password" and frontend "applicationPassword"
	Password            string `json:"password,omitempty"`
	ApplicationPassword string `json:"applicationPassword,omitempty"`
}

// SiteUpdateInput represents the request body for updating a site
type SiteUpdateInput struct {
	Name     *string `json:"name,omitempty"`
	URL      *string `json:"url,omitempty"`
	Username *string `json:"username,omitempty"`
	// Accept both legacy "password" and frontend "applicationPassword"
	Password            *string `json:"password,omitempty"`
	ApplicationPassword *string `json:"applicationPassword,omitempty"`
}

// GetSites returns all registered WordPress sites
var GetSites = handleListNilSafe(siteService, "E2001",
	func(ctx context.Context) (any, error) {
		return Services.SiteService.List(ctx)
	},
)

// CreateSite creates a new WordPress site connection
func CreateSite(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	var input SiteCreateInput
	if !decodeJSON(w, r, &input) {
		return
	}

	// Normalize password field (frontend sends applicationPassword)
	if input.Password == "" && input.ApplicationPassword != "" {
		input.Password = input.ApplicationPassword
	}

	// Validate required fields
	if input.Name == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E9002",
			"Name is required",
		)

		return
	}

	if input.URL == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E9002",
			"URL is required",
		)

		return
	}

	if input.Username == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E9002",
			"Username is required",
		)

		return
	}

	if input.Password == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E9002",
			"Application password is required",
		)

		return
	}

	site, err := Services.SiteService.Create(r.Context(), input)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E2004",
			err.Error(),
		)

		return
	}

	respondCreated(w, site)
}

// GetSite returns a specific site by ID
var GetSite = handleActionByID(
	siteService,
	"Site service",
	"id",
	"site ID",
	"E9001",
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.GetByID(ctx, id)
	},
)

// UpdateSite updates an existing site
func UpdateSite(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	var input SiteUpdateInput
	if !decodeJSON(w, r, &input) {
		return
	}

	// Normalize password field (frontend may send applicationPassword)
	if input.Password == nil && input.ApplicationPassword != nil {
		input.Password = input.ApplicationPassword
	}

	site, err := Services.SiteService.Update(r.Context(), id, input)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E2005",
			err.Error(),
		)

		return
	}

	respondSuccess(w, site)
}

// DeleteSite removes a site
var DeleteSite = handleDeleteByID(
	siteService,
	"Site service",
	"id",
	"site ID",
	"E2006",
	func(ctx context.Context, id int64) error {
		return Services.SiteService.Delete(ctx, id)
	},
)

// TestSiteConnection tests the WordPress REST API connection
var TestSiteConnection = handleActionByID(
	siteService,
	"Site service",
	"id",
	"site ID",
	"E3001",
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.TestConnection(ctx, id)
	},
)

// TestSiteCredentials tests credentials without saving (for pre-create validation)
func TestSiteCredentials(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	var input struct {
		URL      string `json:"url"`
		Username string `json:"username"`
		Password string `json:"password"`
	}
	if !decodeJSON(w, r, &input) {
		return
	}

	result, err := Services.SiteService.TestConnectionWithCredentials(
		r.Context(),
		input.URL,
		input.Username,
		input.Password,
	)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3001",
			err.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// GetSiteCredentials returns decrypted credentials for API Explorer
var GetSiteCredentials = handleActionByID(
	siteService,
	"Site service",
	"id",
	"site ID",
	"E2002",
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.GetCredentials(ctx, id)
	},
)

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site
func BootstrapUploader(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	// Optional: allow specifying a custom uploader path in the request body
	var input struct {
		UploaderPath string `json:"uploaderPath,omitempty"`
	}
	_ = json.NewDecoder(r.Body).Decode(&input)

	result, err := Services.SiteService.BootstrapUploader(r.Context(), id, input.UploaderPath)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E2010",
			err.Error(),
		)

		return
	}

	respondSuccess(w, result)
}

// BulkBootstrapUploader deploys the Riseup Asia Uploader to multiple sites
func BulkBootstrapUploader(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	var input struct {
		SiteIds      []int64 `json:"siteIds"`
		UploaderPath string  `json:"uploaderPath,omitempty"`
	}
	if !decodeJSON(w, r, &input) {
		return
	}

	if len(input.SiteIds) == 0 {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"At least one site ID is required",
		)

		return
	}

	results := make([]BulkBootstrapSiteResult, 0, len(input.SiteIds))

	for _, siteId := range input.SiteIds {
		result, err := Services.SiteService.BootstrapUploader(r.Context(), siteId, input.UploaderPath)
		if err != nil {
			siteInfo, _ := Services.SiteService.GetByID(r.Context(), siteId)
			siteName := ""

			if siteInfo != nil {
				siteName = siteInfo.Name
			}

			results = append(results, BulkBootstrapSiteResult{
				SiteID:   siteId,
				SiteName: siteName,
				Success:  false,
				Message:  "Deployment failed",
				Error:    err.Error(),
			})
		} else {
			results = append(results, BulkBootstrapSiteResult{
				SiteID:    result.SiteId,
				SiteName:  result.SiteName,
				Success:   result.Success,
				Message:   result.Message,
				Activated: result.Activated,
			})
		}
	}

	respondSuccess(w, BulkBootstrapResponse{Results: results})
}

// --- Remote Plugin Management ---

// remotePluginInput is the JSON body struct for remote plugin actions
type remotePluginInput struct {
	Plugin string `json:"plugin"`
	Path   string `json:"path,omitempty"`
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

// GetRemotePlugins returns all plugins installed on a remote WordPress site
var GetRemotePlugins = handleSiteActionByID("E3004",
	func(ctx context.Context, siteID int64) (any, error) {
		return Services.SiteService.GetRemotePlugins(ctx, siteID)
	},
)

// ForceSyncRemotePlugins clears cache and fetches fresh plugin data
var ForceSyncRemotePlugins = handleSiteActionByID("E3004",
	func(ctx context.Context, siteID int64) (any, error) {
		return Services.SiteService.ForceSyncRemotePlugins(ctx, siteID)
	},
)

// ClearRemotePluginsCache invalidates the cache without fetching
func ClearRemotePluginsCache(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	if err := Services.SiteService.InvalidateRemotePluginsCache(r.Context(), id); err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3005",
			err.Error(),
		)

		return
	}

	respondSuccess(w, ActionResponse{Cleared: true, SiteID: id})
}

// CheckRemotePluginExists performs a lightweight pre-flight check to verify plugin existence
func CheckRemotePluginExists(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid request: "+err.Error(),
		)

		return
	}

	if pluginSlug == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Plugin slug is required in JSON body",
		)

		return
	}

	exists, status, pluginFile, err := Services.SiteService.CheckRemotePluginExists(
		r.Context(),
		id,
		pluginSlug,
	)
	if err != nil {
		statusCode := resolveHTTPStatus(err, wordpress.HttpStatusServerError)
		respondErrorWithSession(
			w,
			statusCode,
			"E3010",
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, PluginExistsResponse{
		Exists:     exists,
		Status:     status,
		PluginFile: pluginFile,
		Plugin:     pluginSlug,
	})
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func EnableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid request: "+err.Error(),
		)

		return
	}

	if pluginSlug == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Plugin slug is required in JSON body",
		)

		return
	}

	if err := Services.SiteService.EnableRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		status := resolveHTTPStatus(err, wordpress.HttpStatusServerError)
		respondErrorWithSession(
			w,
			status,
			"E3007",
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, ActionResponse{Enabled: true, Plugin: pluginSlug})
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
func DisableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid request: "+err.Error(),
		)

		return
	}

	if pluginSlug == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Plugin slug is required in JSON body",
		)

		return
	}

	if err := Services.SiteService.DisableRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		status := resolveHTTPStatus(err, wordpress.HttpStatusServerError)
		respondErrorWithSession(
			w,
			status,
			"E3007",
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, ActionResponse{Disabled: true, Plugin: pluginSlug})
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site (POST with JSON body)
func DeleteRemotePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid request: "+err.Error(),
		)

		return
	}

	if pluginSlug == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Plugin slug is required in JSON body",
		)

		return
	}

	if err := Services.SiteService.DeleteRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		status := resolveHTTPStatus(err, wordpress.HttpStatusServerError)
		respondErrorWithSession(
			w,
			status,
			"E3010",
			err.Error(),
			err,
		)

		return
	}

	respondSuccess(w, ActionResponse{Deleted: true, Plugin: pluginSlug})
}

// GetRemotePluginFiles returns the file list for a remote plugin
func GetRemotePluginFiles(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Invalid request: "+err.Error(),
		)

		return
	}

	if pluginSlug == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Plugin slug is required in JSON body",
		)

		return
	}

	files, err := Services.SiteService.GetRemotePluginFiles(r.Context(), id, pluginSlug)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3011",
			err.Error(),
		)

		return
	}

	respondSuccess(w, files)
}

// GetRemotePluginFileContent returns the content of a specific file from a remote plugin
func GetRemotePluginFileContent(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	var input struct {
		Plugin string `json:"plugin"`
		Path   string `json:"path"`
	}
	if !decodeJSON(w, r, &input) {
		return
	}

	if input.Plugin == "" || input.Path == "" {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			"Plugin and path are required",
		)

		return
	}

	content, err := Services.SiteService.GetRemotePluginFileContent(
		r.Context(),
		id,
		input.Plugin,
		input.Path,
	)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E3012",
			err.Error(),
		)

		return
	}

	respondSuccess(w, FileContentResponse{
		Content: content,
		Plugin:  input.Plugin,
		Path:    input.Path,
	})
}

// ClearErrorLogHashes resets the in-memory error deduplication map
func ClearErrorLogHashes(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	count := Services.SiteService.ClearErrorLogHashes()
	respondSuccess(w, ActionResponse{
		Cleared: true,
		Count:   count,
		Message: fmt.Sprintf("Cleared %d error deduplication hashes", count),
	})
}
