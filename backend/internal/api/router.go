// Package api provides HTTP API routing and handlers
package api

import (
	"context"
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
	Port                   int
	StaticDir              string
	Services               *ServiceRegistry // Typed service registry
	WSHub                  *ws.Hub
	Logger                 *logger.Logger
	RequestSessionStore    middleware.SessionStore
	SessionLoggingEnabled  bool
}

// ServiceRegistry holds all services for handlers
type ServiceRegistry struct {
	Site           handlers.SiteServiceInterface
	Plugin         handlers.PluginServiceInterface
	Sync           handlers.SyncServiceInterface
	Git            handlers.GitServiceInterface
	Watcher        handlers.WatcherServiceInterface
	Publish        handlers.PublishServiceInterface
	Backup         handlers.BackupServiceInterface
	Session        handlers.SessionServiceInterface
	ErrorHistory   handlers.ErrorHistoryServiceInterface
	PublishHistory handlers.PublishHistoryServiceInterface
	SiteHealth     handlers.SiteHealthServiceInterface
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
			PluginService:         cfg.Services.Plugin,
			SiteService:           cfg.Services.Site,
			SyncService:           cfg.Services.Sync,
			GitService:            cfg.Services.Git,
			WatcherService:        cfg.Services.Watcher,
			PublishService:        cfg.Services.Publish,
			BackupService:         cfg.Services.Backup,
			SessionService:       cfg.Services.Session,
			ErrorHistoryService:   cfg.Services.ErrorHistory,
			PublishHistoryService: cfg.Services.PublishHistory,
			SiteHealthService:    cfg.Services.SiteHealth,
		}
	}

	// Wire up request session store for handlers
	handlers.RequestSessionStore = cfg.RequestSessionStore

	router := mux.NewRouter()

	// Apply global middleware
	router.Use(middleware.CORS)
	router.Use(middleware.Logging(cfg.Logger))
	router.Use(middleware.Recovery(cfg.Logger))
	router.Use(middleware.SessionLogging(cfg.Logger, cfg.RequestSessionStore, cfg.SessionLoggingEnabled))

	// API v1 routes
	api := router.PathPrefix("/api/v1").Subrouter()

	// API index, health check, and OpenAPI spec
	api.HandleFunc("", handlers.APIIndex).Methods("GET")
	api.HandleFunc("/", handlers.APIIndex).Methods("GET")
	api.HandleFunc("/health", handlers.Health).Methods("GET")
	api.HandleFunc("/openapi", handlers.ServeOpenAPISpec).Methods("GET")

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
	// Remote plugin management
	api.HandleFunc("/sites/{id}/remote-plugins", handlers.GetRemotePlugins).Methods("GET")
	api.HandleFunc("/sites/{id}/remote-plugins/force-sync", handlers.ForceSyncRemotePlugins).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/cache", handlers.ClearRemotePluginsCache).Methods("DELETE")
	// Remote plugin actions - JSON body based (plugin slug in request body, not URL)
	api.HandleFunc("/sites/{id}/remote-plugins/enable", handlers.EnableRemotePlugin).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/disable", handlers.DisableRemotePlugin).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/delete", handlers.DeleteRemotePlugin).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/files", handlers.GetRemotePluginFiles).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/file", handlers.GetRemotePluginFileContent).Methods("POST")
	// API Explorer credentials (on-demand decryption)
	api.HandleFunc("/sites/{id}/credentials", handlers.GetSiteCredentials).Methods("GET")

	// Remote snapshot management (Phase 28)
	api.HandleFunc("/sites/{id}/snapshots", handlers.GetRemoteSnapshots).Methods("GET")
	api.HandleFunc("/sites/{id}/snapshots", handlers.CreateRemoteSnapshot).Methods("POST")
	api.HandleFunc("/sites/{id}/snapshots/settings", handlers.GetRemoteSnapshotSettings).Methods("GET")
	api.HandleFunc("/sites/{id}/snapshots/settings", handlers.UpdateRemoteSnapshotSettings).Methods("PUT")
	api.HandleFunc("/sites/{id}/snapshots/providers", handlers.GetRemoteSnapshotProviders).Methods("GET")
	api.HandleFunc("/sites/{id}/snapshots/tables", handlers.GetRemoteAvailableTables).Methods("GET")
	api.HandleFunc("/sites/{id}/snapshots/full-backup", handlers.FullBackupRemoteSnapshot).Methods("POST")
	api.HandleFunc("/sites/{id}/snapshots/incremental", handlers.IncrementalBackupRemoteSnapshot).Methods("POST")
	api.HandleFunc("/sites/{id}/snapshots/import", handlers.ImportRemoteSnapshot).Methods("POST")
	api.HandleFunc("/sites/{id}/snapshots/cleanup", handlers.CleanupRemoteSnapshots).Methods("POST")
	api.HandleFunc("/sites/{id}/snapshots/{snapshotId}", handlers.GetRemoteSnapshot).Methods("GET")
	api.HandleFunc("/sites/{id}/snapshots/{snapshotId}", handlers.DeleteRemoteSnapshot).Methods("DELETE")
	api.HandleFunc("/sites/{id}/snapshots/{snapshotId}/restore", handlers.RestoreRemoteSnapshot).Methods("POST")
	api.HandleFunc("/sites/{id}/snapshots/{snapshotId}/export", handlers.ExportRemoteSnapshot).Methods("GET")

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
	api.HandleFunc("/plugins/{id}/sites/{siteId}/sync/push", handlers.PushSync).Methods("POST")
	api.HandleFunc("/plugins/{id}/sync/check-all", handlers.CheckAllSites).Methods("POST")

	// Publish endpoints
	api.HandleFunc("/plugins/{id}/sites/{siteId}/publish", handlers.PublishPlugin).Methods("POST")
	api.HandleFunc("/plugins/{id}/sites/{siteId}/preview", handlers.PreviewPublish).Methods("GET")
	api.HandleFunc("/plugins/{id}/sites/{siteId}/file-diff", handlers.GetFileDiff).Methods("POST")
	api.HandleFunc("/plugins/{id}/file", handlers.GetLocalFileContent).Methods("POST")

	// Site health endpoints
	api.HandleFunc("/site-health/check-all", handlers.CheckAllSitesHealth).Methods("POST")
	api.HandleFunc("/site-health/summaries", handlers.GetSiteHealthSummaries).Methods("GET")
	api.HandleFunc("/site-health/stats", handlers.GetSiteHealthStats).Methods("GET")
	api.HandleFunc("/site-health/history", handlers.GetSiteHealthHistory).Methods("GET")
	api.HandleFunc("/site-health/history", handlers.ClearSiteHealthHistory).Methods("DELETE")
	api.HandleFunc("/site-health/sites/{id}/check", handlers.CheckSiteHealth).Methods("POST")

	// Publish history endpoints
	api.HandleFunc("/publish-history", handlers.ListPublishHistory).Methods("GET")
	api.HandleFunc("/publish-history", handlers.ClearPublishHistory).Methods("DELETE")
	api.HandleFunc("/publish-history/stats", handlers.GetPublishHistoryStats).Methods("GET")
	api.HandleFunc("/publish-history/{id}", handlers.GetPublishHistoryByID).Methods("GET")
	api.HandleFunc("/publish-history/{id}", handlers.DeletePublishHistoryEntry).Methods("DELETE")

	// Backup endpoints
	api.HandleFunc("/plugins/{id}/backups", handlers.GetBackups).Methods("GET")
	api.HandleFunc("/backups/{id}/restore", handlers.RestoreBackup).Methods("POST")
	api.HandleFunc("/backups/{id}", handlers.DeleteBackup).Methods("DELETE")

	// Plugin version history endpoints
	api.HandleFunc("/plugins/{id}/versions", handlers.GetPluginVersions).Methods("GET")
	api.HandleFunc("/plugins/{id}/versions/{versionId}", handlers.GetPluginVersion).Methods("GET")
	api.HandleFunc("/plugins/{id}/versions/{versionId}/rollback", handlers.RollbackPluginVersion).Methods("POST")
	api.HandleFunc("/plugins/{id}/versions/{versionId}", handlers.DeletePluginVersion).Methods("DELETE")

	// Error logs endpoints (legacy - existing error log functionality)
	// IMPORTANT: Specific routes MUST be registered before parameterized routes ({id})
	api.HandleFunc("/errors/bundle", handlers.DownloadErrorBundle).Methods("GET", "POST")
	api.HandleFunc("/errors/stream", handlers.StreamErrorLogs).Methods("GET")
	api.HandleFunc("/errors/log", handlers.GetBackendErrorLog).Methods("GET")    // error.log.txt content
	api.HandleFunc("/errors", handlers.GetErrors).Methods("GET")
	api.HandleFunc("/errors", handlers.ClearErrors).Methods("DELETE")
	api.HandleFunc("/errors/{id}", handlers.GetError).Methods("GET")
	api.HandleFunc("/logs/full", handlers.GetBackendFullLog).Methods("GET")      // full log.txt content

	// Error history endpoints (new - persistent error/notification storage)
	api.HandleFunc("/error-history", handlers.ListErrorHistory).Methods("GET")
	api.HandleFunc("/error-history", handlers.SaveErrorHistory).Methods("POST")
	api.HandleFunc("/error-history", handlers.ClearErrorHistory).Methods("DELETE")
	api.HandleFunc("/error-history/stats", handlers.GetErrorHistoryStats).Methods("GET")
	api.HandleFunc("/error-history/bulk-export", handlers.BulkExportErrorHistory).Methods("POST")
	api.HandleFunc("/error-history/{id}", handlers.GetErrorHistoryByID).Methods("GET")
	api.HandleFunc("/error-history/{id}", handlers.DeleteErrorHistory).Methods("DELETE")

	// Settings endpoints
	api.HandleFunc("/settings", handlers.GetSettings).Methods("GET")
	api.HandleFunc("/settings", handlers.UpdateSettings).Methods("PUT")
	api.HandleFunc("/settings/clear-error-dedup", handlers.ClearErrorLogHashes).Methods("POST")

	// Mappings endpoints
	api.HandleFunc("/mappings/{id}", handlers.DeletePluginMapping).Methods("DELETE")

	// Session endpoints (operation sessions - publish, sync, etc.)
	api.HandleFunc("/sessions", handlers.GetSessions).Methods("GET")
	api.HandleFunc("/sessions/{id}", handlers.GetSession).Methods("GET")
	api.HandleFunc("/sessions/{id}/logs", handlers.GetSessionLogs).Methods("GET")
	api.HandleFunc("/sessions/{id}/diagnostics", handlers.GetSessionDiagnostics).Methods("GET")
	api.HandleFunc("/sessions/{id}", handlers.DeleteSession).Methods("DELETE")

	// Request session endpoints (per-API-call logging)
	api.HandleFunc("/request-sessions", handlers.GetRequestSessions).Methods("GET")
	api.HandleFunc("/request-sessions", handlers.ClearRequestSessions).Methods("DELETE")
	api.HandleFunc("/request-sessions/errors", handlers.GetRequestSessionsByError).Methods("GET")
	api.HandleFunc("/request-sessions/{id}", handlers.GetRequestSession).Methods("GET")
	api.HandleFunc("/request-sessions/{id}", handlers.DeleteRequestSession).Methods("DELETE")
	api.HandleFunc("/request-sessions/{id}/export", handlers.ExportRequestSession).Methods("GET")

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

// Start begins listening for HTTP connections.
func (s *Server) Start() error {
	return s.server.ListenAndServe()
}

// Shutdown gracefully stops the server.
func (s *Server) Shutdown(ctx context.Context) error {
	return s.server.Shutdown(ctx)
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

// fileExists checks if a file exists (used by SPA handler)
func fileExists(path string) bool {
	fi, err := os.Stat(path)
	return err == nil && !fi.IsDir()
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
