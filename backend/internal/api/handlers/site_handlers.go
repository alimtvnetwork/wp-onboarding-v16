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
	Name     string
	Url      string
	Username string
	// Accept both legacy "password" and frontend "applicationPassword"
	Password            string `json:",omitempty"`
	ApplicationPassword string `json:",omitempty"`
}

// SiteUpdateInput represents the request body for updating a site
type SiteUpdateInput struct {
	Name     *string `json:",omitempty"`
	Url      *string `json:",omitempty"`
	Username *string `json:",omitempty"`
	// Accept both legacy "password" and frontend "applicationPassword"
	Password            *string `json:",omitempty"`
	ApplicationPassword *string `json:",omitempty"`
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

	if input.Password == "" && input.ApplicationPassword != "" {
		input.Password = input.ApplicationPassword
	}

	if err := validateCreateSiteInput(input); err != "" {
		respondError(w, wordpress.HttpStatusBadRequest, "E9002", err)
		return
	}

	site, err := Services.SiteService.Create(r.Context(), input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E2004", err.Error())
		return
	}

	respondCreated(w, site)
}

// validateCreateSiteInput returns an error message if any required field is missing.
func validateCreateSiteInput(input SiteCreateInput) string {
	if input.Name == "" {
		return "Name is required"
	}
	if input.Url == "" {
		return "URL is required"
	}
	if input.Username == "" {
		return "Username is required"
	}
	if input.Password == "" {
		return "Application password is required"
	}
	return ""
}

// GetSite returns a specific site by ID
var GetSite = handleActionByID(
	siteService, "Site service", "id", "site ID", "E9001",
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.GetById(ctx, id)
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
	normalizeUpdateSitePassword(&input)

	site, err := Services.SiteService.Update(r.Context(), id, input)
	if err != nil {
		respondError(w, wordpress.HttpStatusBadRequest, "E2005", err.Error())
		return
	}
	respondSuccess(w, site)
}

// normalizeUpdateSitePassword maps applicationPassword to password if needed.
func normalizeUpdateSitePassword(input *SiteUpdateInput) {
	if input.Password == nil && input.ApplicationPassword != nil {
		input.Password = input.ApplicationPassword
	}
}

// DeleteSite removes a site
var DeleteSite = handleDeleteByID(
	siteService, "Site service", "id", "site ID", "E2006",
	func(ctx context.Context, id int64) error {
		return Services.SiteService.Delete(ctx, id)
	},
)

// TestSiteConnection tests the WordPress REST API connection
var TestSiteConnection = handleActionByID(
	siteService, "Site service", "id", "site ID", "E3001",
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.TestConnection(ctx, id)
	},
)

// credentialsInput is the JSON body for TestSiteCredentials.
type credentialsInput struct {
	Url      string
	Username string
	Password string
}

// TestSiteCredentials tests credentials without saving (for pre-create validation)
func TestSiteCredentials(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}
	var input credentialsInput
	if !decodeJSON(w, r, &input) {
		return
	}

	result, err := Services.SiteService.TestConnectionWithCredentials(r.Context(), input.Url, input.Username, input.Password)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E3001", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GetSiteCredentials returns decrypted credentials for API Explorer
var GetSiteCredentials = handleActionByID(
	siteService, "Site service", "id", "site ID", "E2002",
	func(ctx context.Context, id int64) (any, error) {
		return Services.SiteService.GetCredentials(ctx, id)
	},
)

// bootstrapInput is the optional JSON body for BootstrapUploader.
type bootstrapInput struct {
	UploaderPath string `json:",omitempty"`
}

// BootstrapUploader deploys the Riseup Asia Uploader plugin to a site
func BootstrapUploader(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}
	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}
	var input bootstrapInput
	_ = json.NewDecoder(r.Body).Decode(&input)

	result, err := Services.SiteService.BootstrapUploader(r.Context(), id, input.UploaderPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E2010", err.Error())
		return
	}
	respondSuccess(w, result)
}

// bulkBootstrapInput is the JSON body for BulkBootstrapUploader.
type bulkBootstrapInput struct {
	SiteIds      []int64
	UploaderPath string `json:",omitempty"`
}

// BulkBootstrapUploader deploys the Riseup Asia Uploader to multiple sites
func BulkBootstrapUploader(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}
	var input bulkBootstrapInput
	if !decodeJSON(w, r, &input) {
		return
	}
	if len(input.SiteIds) == 0 {
		respondError(w, wordpress.HttpStatusBadRequest, "E1002", "At least one site ID is required")
		return
	}

	results := make([]BulkBootstrapSiteResult, 0, len(input.SiteIds))
	for _, siteId := range input.SiteIds {
		results = append(results, bootstrapSingleSite(r, siteId, input.UploaderPath))
	}
	respondSuccess(w, BulkBootstrapResponse{Results: results})
}

// bootstrapSingleSite deploys the uploader to one site, returning a result entry.
func bootstrapSingleSite(r *http.Request, siteId int64, uploaderPath string) BulkBootstrapSiteResult {
	result, err := Services.SiteService.BootstrapUploader(r.Context(), siteId, uploaderPath)
	if err != nil {
		return buildBootstrapFailure(r, siteId, err)
	}
	return BulkBootstrapSiteResult{
		SiteId: result.SiteId, SiteName: result.SiteName,
		IsSuccess: result.IsSuccess, Message: result.Message, IsActivated: result.IsActivated,
	}
}

// buildBootstrapFailure constructs a failure result for a single bootstrap attempt.
func buildBootstrapFailure(r *http.Request, siteId int64, err error) BulkBootstrapSiteResult {
	siteInfo, _ := Services.SiteService.GetById(r.Context(), siteId)
	siteName := ""
	if siteInfo != nil {
		siteName = siteInfo.Name
	}
	return BulkBootstrapSiteResult{
		SiteId: siteId, SiteName: siteName,
		IsSuccess: false, Message: "Deployment failed", Error: err.Error(),
	}
}

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
	id, ok := parseID(w, r, "id", "site ID")
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
	exists, status, pluginFile, err := Services.SiteService.CheckRemotePluginExists(r.Context(), id, pluginSlug)
	if err != nil {
		respondErrorWithSession(w, resolveHTTPStatus(err, wordpress.HttpStatusServerError), "E3010", err.Error(), err)
		return
	}
	respondSuccess(w, PluginExistsResponse{IsExists: exists, Status: status, PluginFile: pluginFile, Plugin: pluginSlug})
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
	id, ok := parseID(w, r, "id", "site ID")
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
