// Package api provides HTTP API routing and handlers
package api

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/api/handlers"
	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/pathutil"
)

// ServerConfig holds server configuration
type ServerConfig struct {
	Port      int
	StaticDir string
	Services  *ServiceRegistry // Typed service registry
	WSHub     *ws.Hub
	Logger    *logger.Logger
}

// ServiceRegistry holds all services for handlers
type ServiceRegistry struct {
	Site    handlers.SiteServiceInterface
	Plugin  handlers.PluginServiceInterface
	Sync    handlers.SyncServiceInterface
	Git     handlers.GitServiceInterface
	Watcher handlers.WatcherServiceInterface
	Publish handlers.PublishServiceInterface
	Backup  handlers.BackupServiceInterface
	Session handlers.SessionServiceInterface
}

// Server represents the HTTP server
type Server struct {
	server *http.Server
	config ServerConfig
}

// NewServer creates a new HTTP server
func NewServer(cfg ServerConfig) *Server {
	// Wire up the handlers service registry from the config
	if cfg.Services != nil {
		handlers.Services = &handlers.ServiceRegistry{
			PluginService:  cfg.Services.Plugin,
			SiteService:    cfg.Services.Site,
			SyncService:    cfg.Services.Sync,
			GitService:     cfg.Services.Git,
			WatcherService: cfg.Services.Watcher,
			PublishService: cfg.Services.Publish,
			BackupService:  cfg.Services.Backup,
			SessionService: cfg.Services.Session,
		}
	}

	router := mux.NewRouter()

	// Apply global middleware
	router.Use(middleware.CORS)
	router.Use(middleware.Logging(cfg.Logger))
	router.Use(middleware.Recovery(cfg.Logger))

	// API v1 routes
	api := router.PathPrefix("/api/v1").Subrouter()

	// API index and health check
	api.HandleFunc("", handlers.APIIndex).Methods("GET")
	api.HandleFunc("/", handlers.APIIndex).Methods("GET")
	api.HandleFunc("/health", handlers.Health).Methods("GET")

	// Sites endpoints
	api.HandleFunc("/sites", handlers.GetSites).Methods("GET")
	api.HandleFunc("/sites", handlers.CreateSite).Methods("POST")
	api.HandleFunc("/sites/test", handlers.TestSiteCredentials).Methods("POST") // Test before create
	api.HandleFunc("/sites/{id}", handlers.GetSite).Methods("GET")
	api.HandleFunc("/sites/{id}", handlers.UpdateSite).Methods("PUT")
	api.HandleFunc("/sites/{id}", handlers.DeleteSite).Methods("DELETE")
	api.HandleFunc("/sites/{id}/test", handlers.TestSiteConnection).Methods("POST")
	api.HandleFunc("/sites/{id}/bootstrap-uploader", handlers.BootstrapUploader).Methods("POST")
	api.HandleFunc("/sites/bulk-bootstrap-uploader", handlers.BulkBootstrapUploader).Methods("POST")
	api.HandleFunc("/sites/{id}/mappings", handlers.GetSiteMappings).Methods("GET")
	api.HandleFunc("/sites/{id}/mappings", handlers.UpdateSiteMappings).Methods("PUT")

	// Plugins endpoints
	api.HandleFunc("/plugins", handlers.GetPlugins).Methods("GET")
	api.HandleFunc("/plugins", handlers.CreatePlugin).Methods("POST")
	api.HandleFunc("/plugins/{id}", handlers.GetPlugin).Methods("GET")
	api.HandleFunc("/plugins/{id}", handlers.UpdatePlugin).Methods("PUT")
	api.HandleFunc("/plugins/{id}", handlers.DeletePlugin).Methods("DELETE")
	api.HandleFunc("/plugins/{id}/mappings", handlers.GetPluginMappings).Methods("GET")
	api.HandleFunc("/plugins/{id}/mappings", handlers.CreatePluginMapping).Methods("POST")
	api.HandleFunc("/plugins/{id}/mappings", handlers.UpdatePluginMappings).Methods("PUT")
	api.HandleFunc("/plugins/{id}/changes", handlers.GetFileChanges).Methods("GET")

	// Plugin scanning endpoints
	api.HandleFunc("/plugins/{id}/scan", handlers.ScanPlugin).Methods("POST")
	api.HandleFunc("/plugins/scan", handlers.ScanAllPlugins).Methods("POST")
	api.HandleFunc("/plugins/scan-directory", handlers.ScanDirectoryPath).Methods("POST")
	api.HandleFunc("/plugins/scan-directories", handlers.ScanDirectoriesPath).Methods("POST")

	// Git endpoints
	api.HandleFunc("/plugins/{id}/git/pull", handlers.GitPull).Methods("POST")
	api.HandleFunc("/plugins/{id}/git/status", handlers.GitStatus).Methods("GET")
	api.HandleFunc("/plugins/{id}/git/commit", handlers.GitCommit).Methods("POST")
	api.HandleFunc("/plugins/{id}/git/push", handlers.GitPush).Methods("POST")
	api.HandleFunc("/plugins/git/pull", handlers.GitPullAll).Methods("POST")

	// Sync endpoints
	api.HandleFunc("/plugins/{id}/sites/{siteId}/sync", handlers.CheckSync).Methods("POST")
	api.HandleFunc("/plugins/{id}/sync/check-all", handlers.CheckAllSites).Methods("POST")

	// Publish endpoints
	api.HandleFunc("/plugins/{id}/sites/{siteId}/publish", handlers.PublishPlugin).Methods("POST")

	// Backup endpoints
	api.HandleFunc("/plugins/{id}/backups", handlers.GetBackups).Methods("GET")
	api.HandleFunc("/backups/{id}/restore", handlers.RestoreBackup).Methods("POST")
	api.HandleFunc("/backups/{id}", handlers.DeleteBackup).Methods("DELETE")

	// Plugin version history endpoints
	api.HandleFunc("/plugins/{id}/versions", handlers.GetPluginVersions).Methods("GET")
	api.HandleFunc("/plugins/{id}/versions/{versionId}", handlers.GetPluginVersion).Methods("GET")
	api.HandleFunc("/plugins/{id}/versions/{versionId}/rollback", handlers.RollbackPluginVersion).Methods("POST")
	api.HandleFunc("/plugins/{id}/versions/{versionId}", handlers.DeletePluginVersion).Methods("DELETE")

	// Error logs endpoints
	api.HandleFunc("/errors", handlers.GetErrors).Methods("GET")
	api.HandleFunc("/errors", handlers.ClearErrors).Methods("DELETE")
	api.HandleFunc("/errors/{id}", handlers.GetError).Methods("GET")
	api.HandleFunc("/errors/bundle", handlers.DownloadErrorBundle).Methods("GET", "POST")
	api.HandleFunc("/errors/stream", handlers.StreamErrorLogs).Methods("GET")

	// Settings endpoints
	api.HandleFunc("/settings", handlers.GetSettings).Methods("GET")
	api.HandleFunc("/settings", handlers.UpdateSettings).Methods("PUT")

	// Mappings endpoints
	api.HandleFunc("/mappings/{id}", handlers.DeletePluginMapping).Methods("DELETE")

	// Session endpoints
	api.HandleFunc("/sessions", handlers.GetSessions).Methods("GET")
	api.HandleFunc("/sessions/{id}", handlers.GetSession).Methods("GET")
	api.HandleFunc("/sessions/{id}/logs", handlers.GetSessionLogs).Methods("GET")
	api.HandleFunc("/sessions/{id}", handlers.DeleteSession).Methods("DELETE")

	// E2E Testing endpoints
	api.HandleFunc("/e2e/suites", handlers.GetE2ESuites).Methods("GET")
	api.HandleFunc("/e2e/suites/{id}/cases", handlers.GetE2ECases).Methods("GET")
	api.HandleFunc("/e2e/run", handlers.StartE2ERun).Methods("POST")
	api.HandleFunc("/e2e/runs", handlers.GetE2ERuns).Methods("GET")
	api.HandleFunc("/e2e/runs/{id}", handlers.GetE2ERun).Methods("GET")
	api.HandleFunc("/e2e/runs/{id}", handlers.DeleteE2ERun).Methods("DELETE")
	api.HandleFunc("/e2e/runs/{id}/abort", handlers.AbortE2ERun).Methods("POST")

	// WebSocket endpoint
	router.HandleFunc("/ws", cfg.WSHub.HandleWebSocket).Methods("GET")

	// Static file serving with SPA fallback
	if cfg.StaticDir != "" {
		staticDir := resolveSpaStaticDir(cfg.StaticDir)
		spa := spaHandler{staticDir: staticDir, indexPath: "index.html"}
		router.PathPrefix("/").Handler(spa)
	}

	// Create HTTP server
	srv := &http.Server{
		Addr:         fmt.Sprintf("127.0.0.1:%d", cfg.Port),
		Handler:      router,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	return &Server{
		server: srv,
		config: cfg,
	}
}

func resolveSpaStaticDir(dir string) string {
	// Normal case: index.html exists at the configured static dir.
	if fileExists(pathutil.MustJoin(dir, "index.html")) {
		return dir
	}

	// Common packaging/copy mistake: copying the entire dist folder into the target
	// directory, resulting in "<dir>/dist/index.html".
	if fileExists(pathutil.MustJoin(dir, "dist", "index.html")) {
		return pathutil.MustJoin(dir, "dist")
	}

	return dir
}

func fileExists(path string) bool {
	fi, err := os.Stat(path)
	return err == nil && !fi.IsDir()
}

// Start begins listening for HTTP requests
func (s *Server) Start() error {
	return s.server.ListenAndServe()
}

// Shutdown gracefully stops the server
func (s *Server) Shutdown(ctx context.Context) error {
	return s.server.Shutdown(ctx)
}

// APIResponse is the standard API response format
type APIResponse struct {
	Success bool        `json:"success"`
	Data    interface{} `json:"data,omitempty"`
	Error   *APIError   `json:"error,omitempty"`
}

// APIError represents an error in API responses
type APIError struct {
	Code       string                 `json:"code"`
	Message    string                 `json:"message"`
	Details    string                 `json:"details,omitempty"`
	Context    map[string]interface{} `json:"context,omitempty"`
	File       string                 `json:"file,omitempty"`
	Line       int                    `json:"line,omitempty"`
	Function   string                 `json:"function,omitempty"`
	StackTrace string                 `json:"stackTrace,omitempty"`
	Timestamp  string                 `json:"timestamp"`
}

// JSON helper to write JSON responses
func JSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// Success writes a successful JSON response
func Success(w http.ResponseWriter, data interface{}) {
	JSON(w, http.StatusOK, APIResponse{
		Success: true,
		Data:    data,
	})
}

// Error writes an error JSON response
func Error(w http.ResponseWriter, status int, code, message string) {
	JSON(w, status, APIResponse{
		Success: false,
		Error: &APIError{
			Code:      code,
			Message:   message,
			Timestamp: time.Now().Format(time.RFC3339),
		},
	})
}

// spaHandler serves static files with SPA fallback for client-side routing
type spaHandler struct {
	staticDir string
	indexPath string
}

// ServeHTTP handles static file requests and SPA routing
func (h spaHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// Clean and normalize the request path.
	// IMPORTANT: r.URL.Path begins with "/"; joining with an absolute path would drop staticDir.
	requestedPath := filepath.Clean(r.URL.Path)
	requestedPath = strings.TrimPrefix(requestedPath, "/")

	// Root or directory routes should render the SPA entrypoint.
	if requestedPath == "" || requestedPath == "." {
		http.ServeFile(w, r, pathutil.MustJoin(h.staticDir, h.indexPath))
		return
	}

	path := pathutil.MustJoin(h.staticDir, requestedPath)

	fi, err := os.Stat(path)
	if err != nil {
		if os.IsNotExist(err) {
			// Missing file (including client-side routes) -> SPA fallback
			http.ServeFile(w, r, pathutil.MustJoin(h.staticDir, h.indexPath))
			return
		}
		http.Error(w, "Internal Server Error", http.StatusInternalServerError)
		return
	}

	if fi.IsDir() {
		http.ServeFile(w, r, pathutil.MustJoin(h.staticDir, h.indexPath))
		return
	}

	// Serve the actual file
	http.ServeFile(w, r, path)
}
