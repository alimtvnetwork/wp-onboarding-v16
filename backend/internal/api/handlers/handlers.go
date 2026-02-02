// Package handlers provides HTTP request handlers
package handlers

import (
	"context"
	"encoding/json"
	"net/http"
	"strconv"
	"time"

	"github.com/gorilla/mux"
)

// ServiceRegistry holds references to all services
type ServiceRegistry struct {
	PluginService  PluginServiceInterface
	SiteService    SiteServiceInterface
	SyncService    SyncServiceInterface
	GitService     GitServiceInterface
	WatcherService WatcherServiceInterface
}

// PluginServiceInterface defines plugin service methods needed by handlers
type PluginServiceInterface interface {
	List(ctx context.Context) (interface{}, error)
	GetByID(ctx context.Context, id int64) (interface{}, error)
	Create(ctx context.Context, input interface{}) (interface{}, error)
	Update(ctx context.Context, id int64, input interface{}) (interface{}, error)
	Delete(ctx context.Context, id int64) error
	GetMappings(ctx context.Context, pluginID int64) (interface{}, error)
	CreateMapping(ctx context.Context, pluginID int64, input interface{}) (interface{}, error)
	DeleteMapping(ctx context.Context, id int64) error
	ScanDirectory(ctx context.Context, path string) (interface{}, error)
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
}

// WatcherServiceInterface defines watcher service methods
type WatcherServiceInterface interface {
	TriggerScan(ctx context.Context, pluginID int64) (interface{}, error)
	ScanAll(ctx context.Context) (interface{}, error)
}

// Global service registry - set during server initialization
var Services *ServiceRegistry

// Health returns server health status
func Health(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	w.Write([]byte(`{"status":"healthy","timestamp":"` + time.Now().Format(time.RFC3339) + `"}`))
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
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// GetSite returns a specific site by ID
func GetSite(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// UpdateSite updates an existing site
func UpdateSite(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// DeleteSite removes a site
func DeleteSite(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// TestSiteConnection tests the WordPress REST API connection
func TestSiteConnection(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
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

// --- Publish Handlers ---

// PublishPlugin publishes plugin changes to a site
func PublishPlugin(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// --- Backup Handlers ---

// GetBackups returns backup history for a plugin
func GetBackups(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, []interface{}{})
}

// RestoreBackup restores a plugin from backup
func RestoreBackup(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// DeleteBackup removes a backup file
func DeleteBackup(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// --- Error Handlers ---

// GetErrors returns application error logs
func GetErrors(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, []interface{}{})
}

// GetError returns a specific error by ID
func GetError(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}

// ClearErrors removes all error logs
func ClearErrors(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
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
