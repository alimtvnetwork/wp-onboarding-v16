// Package handlers provides site-related HTTP request handlers
package handlers

import (
	"encoding/json"
	"net/http"
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
func GetSites(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondSuccess(w, []interface{}{})
		return
	}
	sites, err := Services.SiteService.List(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E2001", err.Error())
		return
	}
	respondSuccess(w, sites)
}

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
		respondError(w, http.StatusBadRequest, "E9002", "Name is required")
		return
	}
	if input.URL == "" {
		respondError(w, http.StatusBadRequest, "E9002", "URL is required")
		return
	}
	if input.Username == "" {
		respondError(w, http.StatusBadRequest, "E9002", "Username is required")
		return
	}
	if input.Password == "" {
		respondError(w, http.StatusBadRequest, "E9002", "Application password is required")
		return
	}

	site, err := Services.SiteService.Create(r.Context(), input)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E2004", err.Error())
		return
	}
	respondCreated(w, site)
}

// GetSite returns a specific site by ID
func GetSite(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	site, err := Services.SiteService.GetByID(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "E9001", err.Error())
		return
	}
	respondSuccess(w, site)
}

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
		respondError(w, http.StatusBadRequest, "E2005", err.Error())
		return
	}
	respondSuccess(w, site)
}

// DeleteSite removes a site
func DeleteSite(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	if err := Services.SiteService.Delete(r.Context(), id); err != nil {
		respondError(w, http.StatusBadRequest, "E2006", err.Error())
		return
	}
	respondDeleted(w)
}

// TestSiteConnection tests the WordPress REST API connection
func TestSiteConnection(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	result, err := Services.SiteService.TestConnection(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3001", err.Error())
		return
	}
	respondSuccess(w, result)
}

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

	result, err := Services.SiteService.TestConnectionWithCredentials(r.Context(), input.URL, input.Username, input.Password)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3001", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GetSiteCredentials returns decrypted credentials for API Explorer
func GetSiteCredentials(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	credentials, err := Services.SiteService.GetCredentials(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusNotFound, "E2002", err.Error())
		return
	}
	respondSuccess(w, credentials)
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

	// Optional: allow specifying a custom uploader path in the request body
	var input struct {
		UploaderPath string `json:"uploaderPath,omitempty"`
	}
	_ = json.NewDecoder(r.Body).Decode(&input)

	result, err := Services.SiteService.BootstrapUploader(r.Context(), id, input.UploaderPath)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E2010", err.Error())
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
		respondError(w, http.StatusBadRequest, "E1002", "At least one site ID is required")
		return
	}

	type siteResult struct {
		SiteId    int64  `json:"siteId"`
		SiteName  string `json:"siteName"`
		Success   bool   `json:"success"`
		Message   string `json:"message"`
		Activated bool   `json:"activated,omitempty"`
		Error     string `json:"error,omitempty"`
	}

	results := make([]siteResult, 0, len(input.SiteIds))

	for _, siteId := range input.SiteIds {
		result, err := Services.SiteService.BootstrapUploader(r.Context(), siteId, input.UploaderPath)
		if err != nil {
			site, _ := Services.SiteService.GetByID(r.Context(), siteId)
			siteName := ""
			if site != nil {
				if s, ok := site.(interface{ GetName() string }); ok {
					siteName = s.GetName()
				}
			}
			results = append(results, siteResult{
				SiteId:   siteId,
				SiteName: siteName,
				Success:  false,
				Message:  "Deployment failed",
				Error:    err.Error(),
			})
		} else {
			if r, ok := result.(*struct {
				Success   bool   `json:"success"`
				SiteId    int64  `json:"siteId"`
				SiteName  string `json:"siteName"`
				Message   string `json:"message"`
				Activated bool   `json:"activated"`
			}); ok {
				results = append(results, siteResult{
					SiteId:    r.SiteId,
					SiteName:  r.SiteName,
					Success:   r.Success,
					Message:   r.Message,
					Activated: r.Activated,
				})
			} else {
				results = append(results, siteResult{
					SiteId:  siteId,
					Success: true,
					Message: "Deployment completed",
				})
			}
		}
	}

	respondSuccess(w, map[string]interface{}{
		"results": results,
	})
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
func GetRemotePlugins(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	plugins, err := Services.SiteService.GetRemotePlugins(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3004", err.Error())
		return
	}
	respondSuccess(w, plugins)
}

// ForceSyncRemotePlugins clears cache and fetches fresh plugin data
func ForceSyncRemotePlugins(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, ok := parseID(w, r, "id", "site ID")
	if !ok {
		return
	}

	plugins, err := Services.SiteService.ForceSyncRemotePlugins(r.Context(), id)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3004", err.Error())
		return
	}
	respondSuccess(w, plugins)
}

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
		respondError(w, http.StatusInternalServerError, "E3005", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"cleared": true, "siteId": id})
}

// EnableRemotePlugin activates a plugin on a remote WordPress site
func EnableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid request: "+err.Error())
		return
	}
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required in JSON body")
		return
	}

	if err := Services.SiteService.EnableRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondError(w, http.StatusInternalServerError, "E3007", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"enabled": true, "plugin": pluginSlug})
}

// DisableRemotePlugin deactivates a plugin on a remote WordPress site
func DisableRemotePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid request: "+err.Error())
		return
	}
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required in JSON body")
		return
	}

	if err := Services.SiteService.DisableRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondError(w, http.StatusInternalServerError, "E3007", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"disabled": true, "plugin": pluginSlug})
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site (POST with JSON body)
func DeleteRemotePlugin(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid request: "+err.Error())
		return
	}
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required in JSON body")
		return
	}

	if err := Services.SiteService.DeleteRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondError(w, http.StatusInternalServerError, "E3010", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true, "plugin": pluginSlug})
}

// GetRemotePluginFiles returns the file list for a remote plugin
func GetRemotePluginFiles(w http.ResponseWriter, r *http.Request) {
	if !requireService(w, Services.SiteService, "Site service") {
		return
	}

	id, pluginSlug, err := parseRemotePluginInput(r)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid request: "+err.Error())
		return
	}
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required in JSON body")
		return
	}

	files, err := Services.SiteService.GetRemotePluginFiles(r.Context(), id, pluginSlug)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3011", err.Error())
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
	if input.Plugin == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required in JSON body")
		return
	}
	if input.Path == "" {
		respondError(w, http.StatusBadRequest, "E1002", "File path is required")
		return
	}

	content, err := Services.SiteService.GetRemotePluginFileContent(r.Context(), id, input.Plugin, input.Path)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3012", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{
		"path":    input.Path,
		"content": content,
	})
}
