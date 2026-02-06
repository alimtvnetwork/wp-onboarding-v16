// Package handlers provides HTTP request handlers
package handlers

import (
	"archive/zip"
	"context"
	"encoding/json"
	"io"
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	"github.com/gorilla/mux"
)

// ServiceRegistry holds references to all services
type ServiceRegistry struct {
	PluginService        PluginServiceInterface
	SiteService          SiteServiceInterface
	SyncService          SyncServiceInterface
	GitService           GitServiceInterface
	WatcherService       WatcherServiceInterface
	PublishService       PublishServiceInterface
	BackupService        BackupServiceInterface
	SessionService       SessionServiceInterface
	ErrorHistoryService  ErrorHistoryServiceInterface
}

// PluginServiceInterface defines plugin service methods needed by handlers
type PluginServiceInterface interface {
	List(ctx context.Context) (interface{}, error)
	GetByID(ctx context.Context, id int64) (interface{}, error)
	Create(ctx context.Context, input interface{}) (interface{}, error)
	Update(ctx context.Context, id int64, input interface{}) (interface{}, error)
	Delete(ctx context.Context, id int64) error
	GetMappings(ctx context.Context, pluginID int64) (interface{}, error)
	GetMappingsBySite(ctx context.Context, siteID int64) (interface{}, error)
	CreateMapping(ctx context.Context, pluginID int64, input interface{}) (interface{}, error)
	DeleteMapping(ctx context.Context, id int64) error
	UpdateMappingsForPlugin(ctx context.Context, pluginID int64, siteIDs []int64, remoteSlug string) error
	UpdateMappingsForSite(ctx context.Context, siteID int64, pluginIDs []int64) error
	ScanDirectory(ctx context.Context, path string) (interface{}, error)
	WritePluginDetected(ctx context.Context, path string) error
	RefreshFileCount(ctx context.Context, id int64) error
}

// SiteServiceInterface defines site service methods
type SiteServiceInterface interface {
	List(ctx context.Context) (interface{}, error)
	GetByID(ctx context.Context, id int64) (interface{}, error)
	Create(ctx context.Context, input interface{}) (interface{}, error)
	Update(ctx context.Context, id int64, input interface{}) (interface{}, error)
	Delete(ctx context.Context, id int64) error
	TestConnection(ctx context.Context, id int64) (interface{}, error)
	TestConnectionWithCredentials(ctx context.Context, url, username, password string) (interface{}, error)
	BootstrapUploader(ctx context.Context, id int64, uploaderPath string) (interface{}, error)
	// Remote plugin management
	GetRemotePlugins(ctx context.Context, siteID int64) (interface{}, error)
	ForceSyncRemotePlugins(ctx context.Context, siteID int64) (interface{}, error)
	InvalidateRemotePluginsCache(ctx context.Context, siteID int64) error
	EnableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	DisableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	DeleteRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	// Remote plugin file browser (Phase 10)
	GetRemotePluginFiles(ctx context.Context, siteID int64, pluginSlug string) (interface{}, error)
	GetRemotePluginFileContent(ctx context.Context, siteID int64, pluginSlug, filePath string) (string, error)
	// Credentials for API Explorer
	GetCredentials(ctx context.Context, siteID int64) (interface{}, error)
}

// SyncServiceInterface defines sync service methods
type SyncServiceInterface interface {
	CheckSync(ctx context.Context, pluginID, siteID int64) (interface{}, error)
	CheckAllSites(ctx context.Context, pluginID int64) (interface{}, error)
	CheckAllPlugins(ctx context.Context) (interface{}, error)
	GetFileChanges(ctx context.Context, pluginID, siteID int64) (interface{}, error)
}

// GitServiceInterface defines git service methods
type GitServiceInterface interface {
	Pull(ctx context.Context, pluginID int64) (interface{}, error)
	PullAll(ctx context.Context) (interface{}, error)
	Status(ctx context.Context, pluginID int64) (interface{}, error)
	Commit(ctx context.Context, pluginID int64, message string) (interface{}, error)
	Push(ctx context.Context, pluginID int64) (interface{}, error)
}

// WatcherServiceInterface defines watcher service methods
type WatcherServiceInterface interface {
	TriggerScan(ctx context.Context, pluginID int64) (interface{}, error)
	ScanAll(ctx context.Context) (interface{}, error)
}

// PublishServiceInterface defines publish service methods
type PublishServiceInterface interface {
	Publish(ctx context.Context, pluginID, siteID int64, opts interface{}) (interface{}, error)
	PublishFiles(ctx context.Context, pluginID, siteID int64, files []string) (interface{}, error)
	PreviewPublish(ctx context.Context, pluginID, siteID int64) (interface{}, error)
	GetFileDiff(ctx context.Context, pluginID, siteID int64, filePath string) (interface{}, error)
}

// BackupServiceInterface defines backup service methods
type BackupServiceInterface interface {
	List(ctx context.Context, pluginID int64) (interface{}, error)
	Create(ctx context.Context, pluginID, siteID int64) (interface{}, error)
	Restore(ctx context.Context, backupID int64) error
	Delete(ctx context.Context, backupID int64) error
}

// Global service registry - set during server initialization
var Services *ServiceRegistry

// Health returns server health status (standard envelope format)
func Health(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, map[string]interface{}{
		"status":    "ok",
		"timestamp": time.Now().Format(time.RFC3339),
	})
}

// APIIndex returns API metadata for the base /api/v1 endpoint
func APIIndex(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, map[string]interface{}{
		"name":    "WP Plugin Publish API",
		"version": "v1",
		"health":  "/api/v1/health",
		"ws":      "/ws",
	})
}

// --- Helper Functions ---

// respondJSON writes a JSON response
func respondJSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// respondSuccess writes a successful API response
func respondSuccess(w http.ResponseWriter, data interface{}) {
	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"data":    data,
	})
}

// respondError writes an error API response
func respondError(w http.ResponseWriter, status int, code, message string) {
	respondJSON(w, status, map[string]interface{}{
		"success": false,
		"error": map[string]interface{}{
			"code":      code,
			"message":   message,
			"timestamp": time.Now().Format(time.RFC3339),
		},
	})
}

// getIDParam extracts an ID parameter from the URL
func getIDParam(r *http.Request, name string) (int64, error) {
	vars := mux.Vars(r)
	return strconv.ParseInt(vars[name], 10, 64)
}

// --- Sites Handlers ---

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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	var input SiteCreateInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
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
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    site,
	})
}

// GetSite returns a specific site by ID
func GetSite(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	var input SiteUpdateInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	if err := Services.SiteService.Delete(r.Context(), id); err != nil {
		respondError(w, http.StatusBadRequest, "E2006", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true})
}

// TestSiteConnection tests the WordPress REST API connection
func TestSiteConnection(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	var input struct {
		URL      string `json:"url"`
		Username string `json:"username"`
		Password string `json:"password"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	var input struct {
		SiteIds      []int64 `json:"siteIds"`
		UploaderPath string  `json:"uploaderPath,omitempty"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
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
			// Get site name for error reporting
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

// --- Remote Plugin Management Handlers ---

// GetRemotePlugins returns all plugins installed on a remote WordPress site
func GetRemotePlugins(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	vars := mux.Vars(r)
	pluginSlug := vars["plugin"]
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required")
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
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	vars := mux.Vars(r)
	pluginSlug := vars["plugin"]
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required")
		return
	}

	if err := Services.SiteService.DisableRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondError(w, http.StatusInternalServerError, "E3007", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"disabled": true, "plugin": pluginSlug})
}

// DeleteRemotePlugin removes a plugin from a remote WordPress site
func DeleteRemotePlugin(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	vars := mux.Vars(r)
	pluginSlug := vars["plugin"]
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required")
		return
	}

	if err := Services.SiteService.DeleteRemotePlugin(r.Context(), id, pluginSlug); err != nil {
		respondError(w, http.StatusInternalServerError, "E3010", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true, "plugin": pluginSlug})
}

// GetRemotePluginFiles returns the file list for a remote plugin (Phase 10)
func GetRemotePluginFiles(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	vars := mux.Vars(r)
	pluginSlug := vars["plugin"]
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required")
		return
	}

	files, err := Services.SiteService.GetRemotePluginFiles(r.Context(), id, pluginSlug)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3011", err.Error())
		return
	}
	respondSuccess(w, files)
}

// GetRemotePluginFileContent returns the content of a specific file from a remote plugin (Phase 10)
func GetRemotePluginFileContent(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SiteService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Site service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	vars := mux.Vars(r)
	pluginSlug := vars["plugin"]
	if pluginSlug == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Plugin slug is required")
		return
	}

	// Parse request body for file path
	var input struct {
		Path string `json:"path"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}
	if input.Path == "" {
		respondError(w, http.StatusBadRequest, "E1002", "File path is required")
		return
	}

	content, err := Services.SiteService.GetRemotePluginFileContent(r.Context(), id, pluginSlug, input.Path)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E3012", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{
		"path":    input.Path,
		"content": content,
	})
}

// --- Plugins Handlers ---

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
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	var input map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	plugin, err := Services.PluginService.Create(r.Context(), input)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E3002", err.Error())
		return
	}
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    plugin,
	})
}

// GetPlugin returns a specific plugin by ID
func GetPlugin(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
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
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	var input map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
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
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	if err := Services.PluginService.Delete(r.Context(), id); err != nil {
		respondError(w, http.StatusBadRequest, "E3005", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true})
}

// GetPluginMappings returns plugin-site mappings
func GetPluginMappings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
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
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	var input map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	mapping, err := Services.PluginService.CreateMapping(r.Context(), id, input)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E3007", err.Error())
		return
	}
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    mapping,
	})
}

// DeletePluginMapping removes a plugin-site mapping
func DeletePluginMapping(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid mapping ID")
		return
	}

	if err := Services.PluginService.DeleteMapping(r.Context(), id); err != nil {
		respondError(w, http.StatusBadRequest, "E3008", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true})
}

// UpdatePluginMappings bulk-updates all site mappings for a plugin
func UpdatePluginMappings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	var input struct {
		SiteIDs    []int64 `json:"siteIds"`
		RemoteSlug string  `json:"remoteSlug"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	if err := Services.PluginService.UpdateMappingsForPlugin(r.Context(), id, input.SiteIDs, input.RemoteSlug); err != nil {
		respondError(w, http.StatusBadRequest, "E3009", err.Error())
		return
	}

	// Return updated mappings
	mappings, _ := Services.PluginService.GetMappings(r.Context(), id)
	respondSuccess(w, mappings)
}

// GetSiteMappings returns all plugin mappings for a site
func GetSiteMappings(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
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
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	siteID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	var raw struct {
		PluginIDs []interface{} `json:"pluginIds"`
	}
	if err := json.NewDecoder(r.Body).Decode(&raw); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
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

	// Return updated mappings for this site
	mappings, _ := Services.PluginService.GetMappingsBySite(r.Context(), siteID)
	respondSuccess(w, mappings)
}

// GetFileChanges returns detected file changes for a plugin
func GetFileChanges(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SyncService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	id, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	// Get siteId from query param if provided
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

// --- Sync Handlers ---

// CheckSync compares local vs remote plugin files
func CheckSync(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SyncService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Sync service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	siteID, err := getIDParam(r, "siteId")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	result, err := Services.SyncService.CheckSync(r.Context(), pluginID, siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4002", err.Error())
		return
	}
	respondSuccess(w, result)
}

// CheckAllSites checks sync status for all mapped sites
func CheckAllSites(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.SyncService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Sync service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	result, err := Services.SyncService.CheckAllSites(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4003", err.Error())
		return
	}
	respondSuccess(w, result)
}

// --- Git Handlers ---

// GitPull performs git pull for a specific plugin
func GitPull(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.GitService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Git service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	result, err := Services.GitService.Pull(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5001", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GitPullAll performs git pull for all plugins
func GitPullAll(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.GitService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Git service not available")
		return
	}

	result, err := Services.GitService.PullAll(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5002", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GitStatus returns git status for a specific plugin
func GitStatus(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.GitService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Git service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	result, err := Services.GitService.Status(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5003", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GitCommit commits changes for a specific plugin
func GitCommit(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.GitService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Git service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	var input struct {
		Message string `json:"message"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	if input.Message == "" {
		respondError(w, http.StatusBadRequest, "E1003", "Commit message is required")
		return
	}

	result, err := Services.GitService.Commit(r.Context(), pluginID, input.Message)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5004", err.Error())
		return
	}
	respondSuccess(w, result)
}

// GitPush pushes commits to remote for a specific plugin
func GitPush(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.GitService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Git service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	result, err := Services.GitService.Push(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5005", err.Error())
		return
	}
	respondSuccess(w, result)
}

// --- Watcher/Scan Handlers ---

// ScanPlugin triggers a file scan for a specific plugin
func ScanPlugin(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.WatcherService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Watcher service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
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
	if Services == nil || Services.WatcherService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Watcher service not available")
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
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	var input struct {
		Path            string `json:"path"`
		CreateDetection bool   `json:"createDetection"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	if input.Path == "" {
		respondError(w, http.StatusBadRequest, "E1002", "Path is required")
		return
	}

	// Scan the directory
	result, err := Services.PluginService.ScanDirectory(r.Context(), input.Path)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E6003", err.Error())
		return
	}

	// Optionally create wp-plugin-detected.json
	if input.CreateDetection {
		if err := Services.PluginService.WritePluginDetected(r.Context(), input.Path); err != nil {
			// Non-fatal - still return scan result but note the error
			respondSuccess(w, map[string]interface{}{
				"scan":           result,
				"detectionError": err.Error(),
			})
			return
		}
		respondSuccess(w, map[string]interface{}{
			"scan":              result,
			"detectionCreated": true,
		})
		return
	}

	respondSuccess(w, result)
}

// ScanDirectoriesPath scans multiple directories for WordPress plugin info
func ScanDirectoriesPath(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PluginService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Plugin service not available")
		return
	}

	var input struct {
		Paths           []string `json:"paths"`
		CreateDetection bool     `json:"createDetection"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
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
			results = append(results, scanResult{
				Path:     path,
				IsPlugin: false,
				Error:    err.Error(),
			})
			continue
		}

		// Check if it's a valid plugin from the result
		isPlugin := false
		if scanMap, ok := result.(map[string]interface{}); ok {
			if valid, ok := scanMap["isValid"].(bool); ok && valid {
				isPlugin = true
				detected++
			}
		}

		sr := scanResult{
			Path:     path,
			IsPlugin: isPlugin,
			Metadata: result,
		}

		// Optionally create wp-plugin-detected.json
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

// --- Publish Handlers ---

// PublishInput represents the request body for publishing
type PublishInput struct {
	Mode         string   `json:"mode"`         // "full" or "selected"
	Files        []string `json:"files"`        // files to publish (for "selected" mode)
	CreateBackup bool     `json:"createBackup"` // create backup before publish
	KeepZipFiles bool     `json:"keepZipFiles"` // keep ZIP files after publish (for debugging)
}

// PublishPlugin publishes plugin changes to a site
func PublishPlugin(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PublishService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Publish service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	siteID, err := getIDParam(r, "siteId")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	var input PublishInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	// Default to full publish mode
	if input.Mode == "" {
		input.Mode = "full"
	}

	result, err := Services.PublishService.Publish(r.Context(), pluginID, siteID, input)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5006", err.Error())
		return
	}
	respondSuccess(w, result)
}

// PreviewPublish returns a preview of files that will be published
func PreviewPublish(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.PublishService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Publish service not available")
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	siteID, err := getIDParam(r, "siteId")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid site ID")
		return
	}

	result, err := Services.PublishService.PreviewPublish(r.Context(), pluginID, siteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E5007", err.Error())
		return
	}
	respondSuccess(w, result)
}

// --- Backup Handlers ---

// GetBackups returns backup history for a plugin
func GetBackups(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.BackupService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	backups, err := Services.BackupService.List(r.Context(), pluginID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E6001", err.Error())
		return
	}
	respondSuccess(w, backups)
}

// RestoreBackup restores a plugin from backup
func RestoreBackup(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.BackupService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Backup service not available")
		return
	}

	backupID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid backup ID")
		return
	}

	if err := Services.BackupService.Restore(r.Context(), backupID); err != nil {
		respondError(w, http.StatusInternalServerError, "E6002", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"restored": true})
}

// DeleteBackup removes a backup file
func DeleteBackup(w http.ResponseWriter, r *http.Request) {
	if Services == nil || Services.BackupService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Backup service not available")
		return
	}

	backupID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid backup ID")
		return
	}

	if err := Services.BackupService.Delete(r.Context(), backupID); err != nil {
		respondError(w, http.StatusBadRequest, "E6003", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true})
}

// --- Plugin Version History Handlers ---

// VersionServiceInterface defines version history service methods
type VersionServiceInterface interface {
	GetVersions(ctx context.Context, pluginID int64, siteID *int64, limit int) (interface{}, error)
	GetVersion(ctx context.Context, versionID int64) (interface{}, error)
	Rollback(ctx context.Context, versionID int64) (interface{}, error)
	DeleteVersion(ctx context.Context, versionID int64) error
}

// VersionService holds the version service instance
var VersionService VersionServiceInterface

// GetPluginVersions returns version history for a plugin
func GetPluginVersions(w http.ResponseWriter, r *http.Request) {
	if VersionService == nil {
		respondSuccess(w, []interface{}{})
		return
	}

	pluginID, err := getIDParam(r, "id")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid plugin ID")
		return
	}

	// Optional site filter
	var siteID *int64
	if siteIDStr := r.URL.Query().Get("siteId"); siteIDStr != "" {
		if parsed, err := strconv.ParseInt(siteIDStr, 10, 64); err == nil {
			siteID = &parsed
		}
	}

	// Optional limit
	limit := 50
	if l := r.URL.Query().Get("limit"); l != "" {
		if parsed, err := strconv.Atoi(l); err == nil && parsed > 0 {
			limit = parsed
		}
	}

	versions, err := VersionService.GetVersions(r.Context(), pluginID, siteID, limit)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E8001", err.Error())
		return
	}
	respondSuccess(w, versions)
}

// GetPluginVersion returns a specific version entry
func GetPluginVersion(w http.ResponseWriter, r *http.Request) {
	if VersionService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Version service not available")
		return
	}

	versionID, err := getIDParam(r, "versionId")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid version ID")
		return
	}

	version, err := VersionService.GetVersion(r.Context(), versionID)
	if err != nil {
		respondError(w, http.StatusNotFound, "E8002", err.Error())
		return
	}
	respondSuccess(w, version)
}

// RollbackPluginVersion restores a plugin to a previous version
func RollbackPluginVersion(w http.ResponseWriter, r *http.Request) {
	if VersionService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Version service not available")
		return
	}

	versionID, err := getIDParam(r, "versionId")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid version ID")
		return
	}

	result, err := VersionService.Rollback(r.Context(), versionID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E8003", err.Error())
		return
	}
	respondSuccess(w, result)
}

// DeletePluginVersion removes a version entry
func DeletePluginVersion(w http.ResponseWriter, r *http.Request) {
	if VersionService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "Version service not available")
		return
	}

	versionID, err := getIDParam(r, "versionId")
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid version ID")
		return
	}

	if err := VersionService.DeleteVersion(r.Context(), versionID); err != nil {
		respondError(w, http.StatusBadRequest, "E8004", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true})
}

// --- Error Handlers ---

// GetErrors returns application error logs - streams the error log file
func GetErrors(w http.ResponseWriter, r *http.Request) {
	dataDir := "data/errors"

	// Check query params for which log to return
	logType := r.URL.Query().Get("type") // "all" or "errors", defaults to "errors"
	if logType == "" {
		logType = "errors"
	}

	var logPath string
	if logType == "all" {
		logPath = dataDir + "/log.txt"
	} else {
		logPath = dataDir + "/error.log.txt"
	}

	// Check if file exists
	if _, err := os.Stat(logPath); os.IsNotExist(err) {
		respondSuccess(w, map[string]interface{}{
			"content":  "",
			"path":     logPath,
			"exists":   false,
			"logType":  logType,
		})
		return
	}

	// Read file content
	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4001", "Failed to read log file: "+err.Error())
		return
	}

	// Get file info
	info, _ := os.Stat(logPath)
	
	respondSuccess(w, map[string]interface{}{
		"content":    string(content),
		"path":       logPath,
		"exists":     true,
		"logType":    logType,
		"size":       info.Size(),
		"modifiedAt": info.ModTime().Format(time.RFC3339),
	})
}

// GetError returns a specific error by ID
func GetError(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// ClearErrors removes all error logs
func ClearErrors(w http.ResponseWriter, r *http.Request) {
	dataDir := "data/errors"
	
	logPath := dataDir + "/log.txt"
	errorPath := dataDir + "/error.log.txt"
	
	cleared := []string{}
	
	// Truncate log.txt
	if err := os.Truncate(logPath, 0); err == nil {
		cleared = append(cleared, "log.txt")
	}
	
	// Truncate error.log.txt
	if err := os.Truncate(errorPath, 0); err == nil {
		cleared = append(cleared, "error.log.txt")
	}
	
	respondSuccess(w, map[string]interface{}{
		"cleared": cleared,
		"message": "Log files cleared",
	})
}

// StreamErrorLogs streams the error log file content for real-time viewing
func StreamErrorLogs(w http.ResponseWriter, r *http.Request) {
	dataDir := "data/errors"

	// Check query params
	logType := r.URL.Query().Get("type") // "all" or "errors"
	if logType == "" {
		logType = "all"
	}
	
	// Tail mode - get last N lines
	tailLines := 100
	if tailStr := r.URL.Query().Get("tail"); tailStr != "" {
		if n, err := strconv.Atoi(tailStr); err == nil && n > 0 && n <= 10000 {
			tailLines = n
		}
	}

	var logPath string
	if logType == "errors" {
		logPath = dataDir + "/error.log.txt"
	} else {
		logPath = dataDir + "/log.txt"
	}

	// Check if file exists
	if _, err := os.Stat(logPath); os.IsNotExist(err) {
		respondSuccess(w, map[string]interface{}{
			"lines":   []string{},
			"path":    logPath,
			"exists":  false,
			"logType": logType,
		})
		return
	}

	// Read file content
	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4001", "Failed to read log file: "+err.Error())
		return
	}

	// Split into lines and get tail
	allLines := splitLines(string(content))
	var lines []string
	if len(allLines) > tailLines {
		lines = allLines[len(allLines)-tailLines:]
	} else {
		lines = allLines
	}

	// Get file info
	info, _ := os.Stat(logPath)

	respondSuccess(w, map[string]interface{}{
		"lines":      lines,
		"totalLines": len(allLines),
		"path":       logPath,
		"exists":     true,
		"logType":    logType,
		"size":       info.Size(),
		"modifiedAt": info.ModTime().Format(time.RFC3339),
	})
}

// splitLines splits content into lines, filtering empty lines
func splitLines(content string) []string {
	rawLines := strings.Split(content, "\n")
	lines := make([]string, 0, len(rawLines))
	for _, line := range rawLines {
		if strings.TrimSpace(line) != "" {
			lines = append(lines, line)
		}
	}
	return lines
}

// --- Settings Handlers ---

// GetSettings returns application settings
func GetSettings(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, map[string]interface{}{
		"watcher": map[string]interface{}{
			"pollingEnabled":         false,
			"scanAfterGitPull":       true,
			"debounceMs":             500,
			"defaultExcludePatterns": []string{".git", "node_modules", ".DS_Store"},
		},
		"backup": map[string]interface{}{
			"autoBackupBeforePublish": true,
			"retentionDays":           30,
			"maxBackupsPerPlugin":     10,
			"location":                "backups",
		},
		"logging": map[string]interface{}{
			"level":         "info",
			"retentionDays": 7,
			"debugMode":     false,
		},
		"appearance": map[string]interface{}{
			"theme":       "system",
			"compactMode": false,
		},
		"server": map[string]interface{}{
			"port":               8080,
			"wsReconnectDelayMs": 3000,
		},
	})
}

// UpdateSettings updates application settings
func UpdateSettings(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// --- E2E Test Handlers ---

// E2EServiceInterface defines E2E test service methods
type E2EServiceInterface interface {
	ListSuites(ctx context.Context) (interface{}, error)
	GetCases(ctx context.Context, suiteID string) (interface{}, error)
	StartRun(ctx context.Context, opts interface{}) (interface{}, error)
	AbortRun(ctx context.Context, runID string) error
	ListRuns(ctx context.Context, limit int) (interface{}, error)
	GetRun(ctx context.Context, runID string) (interface{}, error)
	DeleteRun(ctx context.Context, runID string) error
}

// E2EService holds the E2E service instance
var E2EService E2EServiceInterface

// GetE2ESuites returns all test suites
func GetE2ESuites(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondSuccess(w, []interface{}{})
		return
	}
	suites, err := E2EService.ListSuites(r.Context())
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E7001", err.Error())
		return
	}
	respondSuccess(w, suites)
}

// GetE2ECases returns test cases for a suite
func GetE2ECases(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondSuccess(w, []interface{}{})
		return
	}
	vars := mux.Vars(r)
	suiteID := vars["id"]
	cases, err := E2EService.GetCases(r.Context(), suiteID)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E7002", err.Error())
		return
	}
	respondSuccess(w, cases)
}

// StartE2ERun begins a new test run
func StartE2ERun(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "E2E service not available")
		return
	}

	var opts map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&opts); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return
	}

	run, err := E2EService.StartRun(r.Context(), opts)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E7003", err.Error())
		return
	}
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    run,
	})
}

// GetE2ERuns returns past test runs
func GetE2ERuns(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondSuccess(w, []interface{}{})
		return
	}
	
	limit := 20
	if l := r.URL.Query().Get("limit"); l != "" {
		if parsed, err := strconv.Atoi(l); err == nil {
			limit = parsed
		}
	}

	runs, err := E2EService.ListRuns(r.Context(), limit)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E7001", err.Error())
		return
	}
	respondSuccess(w, runs)
}

// GetE2ERun returns a specific test run with results
func GetE2ERun(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "E2E service not available")
		return
	}
	vars := mux.Vars(r)
	runID := vars["id"]
	
	run, err := E2EService.GetRun(r.Context(), runID)
	if err != nil {
		respondError(w, http.StatusNotFound, "E7001", err.Error())
		return
	}
	respondSuccess(w, run)
}

// AbortE2ERun stops a running test
func AbortE2ERun(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "E2E service not available")
		return
	}
	vars := mux.Vars(r)
	runID := vars["id"]
	
	if err := E2EService.AbortRun(r.Context(), runID); err != nil {
		respondError(w, http.StatusBadRequest, "E7003", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"aborted": true})
}

// DeleteE2ERun removes a test run
func DeleteE2ERun(w http.ResponseWriter, r *http.Request) {
	if E2EService == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", "E2E service not available")
		return
	}
	vars := mux.Vars(r)
	runID := vars["id"]
	
	if err := E2EService.DeleteRun(r.Context(), runID); err != nil {
		respondError(w, http.StatusBadRequest, "E7001", err.Error())
		return
	}
	respondSuccess(w, map[string]interface{}{"deleted": true})
}

// DownloadErrorBundle creates and serves a ZIP bundle of error logs for debugging
func DownloadErrorBundle(w http.ResponseWriter, r *http.Request) {
	dataDir := "data/errors"

	// Optional: include a frontend-provided error report inside the bundle.
	// This allows the UI to bundle both on-disk logs + the human-readable report.
	report := ""
	if r.Method == http.MethodPost {
		var payload struct {
			Report string `json:"report"`
		}
		bodyBytes, _ := io.ReadAll(io.LimitReader(r.Body, 2*1024*1024))
		if len(bodyBytes) > 0 {
			_ = json.Unmarshal(bodyBytes, &payload)
			report = payload.Report
		}
	}

	logFile := dataDir + "/log.txt"
	errorFile := dataDir + "/error.log.txt"

	// Check if files exist
	logExists := fileExists(logFile)
	errorExists := fileExists(errorFile)

	if !logExists && !errorExists {
		respondError(w, http.StatusNotFound, "E9001", "No error log files found")
		return
	}

	// Create in-memory zip
	w.Header().Set("Content-Type", "application/zip")
	w.Header().Set("Content-Disposition", "attachment; filename=error-bundle-"+time.Now().Format("20060102-150405")+".zip")

	zipWriter := zip.NewWriter(w)
	defer zipWriter.Close()

	if logExists {
		if err := addFileToZip(zipWriter, logFile, "log.txt"); err != nil {
			// Already started writing, can't change headers
			return
		}
	}

	if errorExists {
		if err := addFileToZip(zipWriter, errorFile, "error.log.txt"); err != nil {
			return
		}
	}

	if report != "" {
		reportWriter, err := zipWriter.Create("report.md")
		if err == nil {
			_, _ = io.WriteString(reportWriter, report)
		}
	}

	// Include a manifest with timestamp
	manifest := struct {
		GeneratedAt string   `json:"generatedAt"`
		Files       []string `json:"files"`
	}{
		GeneratedAt: time.Now().Format(time.RFC3339),
		Files:       []string{},
	}
	if logExists {
		manifest.Files = append(manifest.Files, "log.txt")
	}
	if errorExists {
		manifest.Files = append(manifest.Files, "error.log.txt")
	}
	if report != "" {
		manifest.Files = append(manifest.Files, "report.md")
	}

	manifestWriter, err := zipWriter.Create("manifest.json")
	if err == nil {
		json.NewEncoder(manifestWriter).Encode(manifest)
	}
}

func fileExists(path string) bool {
	_, err := os.Stat(path)
	return err == nil
}

func addFileToZip(zipWriter *zip.Writer, srcPath, destName string) error {
	file, err := os.Open(srcPath)
	if err != nil {
		return err
	}
	defer file.Close()

	writer, err := zipWriter.Create(destName)
	if err != nil {
		return err
	}

	_, err = io.Copy(writer, file)
	return err
}

// GetBackendErrorLog returns the content of error.log.txt
func GetBackendErrorLog(w http.ResponseWriter, r *http.Request) {
	readLogFile(w, "data/errors/error.log.txt", "error.log.txt")
}

// GetBackendFullLog returns the content of the full log.txt
func GetBackendFullLog(w http.ResponseWriter, r *http.Request) {
	readLogFile(w, "data/errors/log.txt", "log.txt")
}

// readLogFile is a helper to read and return log file contents
func readLogFile(w http.ResponseWriter, path string, filename string) {
	info, err := os.Stat(path)
	if err != nil {
		if os.IsNotExist(err) {
			respondError(w, http.StatusNotFound, "E9001", "Log file not found: "+filename)
			return
		}
		respondError(w, http.StatusInternalServerError, "E9002", "Failed to read log file: "+err.Error())
		return
	}

	content, err := os.ReadFile(path)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9002", "Failed to read log file: "+err.Error())
		return
	}

	respondSuccess(w, map[string]interface{}{
		"content":      string(content),
		"filename":     filename,
		"size":         info.Size(),
		"lastModified": info.ModTime().Format(time.RFC3339),
	})
}
