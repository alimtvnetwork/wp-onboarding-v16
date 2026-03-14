// Package api provides HTTP API routing and handlers
package api

import (
	"context"
	"fmt"
	"net/http"
	"path/filepath"
	"strings"
	"time"

	"github.com/gorilla/mux"

	"wp-plugin-publish/internal/api/handlers"
	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
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
	wireHandlerServices(cfg)

	router := mux.NewRouter()
	router.Use(middleware.CORS)
	router.Use(middleware.Logging(cfg.Logger))
	router.Use(middleware.Recovery(cfg.Logger))
	router.Use(middleware.SessionLogging(cfg.Logger, cfg.RequestSessionStore, cfg.SessionLoggingEnabled))

	api := router.PathPrefix("/api/v1").Subrouter()
	registerRoutes(api, cfg)

	if cfg.StaticDir != "" {
		staticDir := resolveSpaStaticDir(cfg.StaticDir)
		spa := spaHandler{staticDir: staticDir, indexPath: "index.html"}
		router.PathPrefix("/").Handler(spa)
	}

	srv := &http.Server{
		Addr:         fmt.Sprintf("127.0.0.1:%d", cfg.Port),
		Handler:      router,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	return &Server{server: srv, config: cfg}
}

// Start begins listening for HTTP connections.
func (s *Server) Start() *apperror.AppError {
	listenErr := s.server.ListenAndServe()

	if listenErr != nil {
		return apperror.Wrap(listenErr, apperror.ErrServerStart, "listen and serve")
	}

	return nil
}

// Shutdown gracefully stops the server.
func (s *Server) Shutdown(ctx context.Context) *apperror.AppError {
	shutdownErr := s.server.Shutdown(ctx)

	if shutdownErr != nil {
		return apperror.Wrap(shutdownErr, apperror.ErrServerShutdown, "graceful shutdown")
	}

	return nil
}

func resolveSpaStaticDir(dir string) string {
	indexPath, err := pathutil.Join(dir, "index.html")

	if err == nil && fileExists(indexPath) {
		return dir
	}

	distIndexPath, err := pathutil.Join(dir, "dist", "index.html")

	if err == nil && fileExists(distIndexPath) {
		distDir, err := pathutil.Join(dir, "dist")

		if err == nil {
			return distDir
		}
	}

	return dir
}

// fileExists checks if a file exists and is not a directory
func fileExists(path string) bool {
	fi, appErr := pathutil.StatFile(path)
	hasStatError := appErr != nil

	if hasStatError {

		return false
	}

	isRegularFile := fi.Info.Mode().IsRegular()

	return isRegularFile
}

// spaHandler serves static files with SPA fallback for client-side routing
type spaHandler struct {
	staticDir string
	indexPath string
}

// ServeHTTP handles static file requests and SPA routing
func (h spaHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	requestedPath := filepath.Clean(r.URL.Path)
	requestedPath = strings.TrimPrefix(requestedPath, "/")

	serveIndex := func() {
		indexPath, err := pathutil.Join(h.staticDir, h.indexPath)
		if err != nil {
			http.Error(w, "Internal Server Error", http.StatusInternalServerError)
			return
		}
		http.ServeFile(w, r, indexPath)
	}

	isRootPath :=
		requestedPath == "" ||
		requestedPath == "."

	if isRootPath {
		serveIndex()
		return
	}

	path, err := pathutil.Join(h.staticDir, requestedPath)

	if err != nil {
		http.Error(w, "Internal Server Error", http.StatusInternalServerError)
		return
	}

	fi, statErr := pathutil.StatFile(path)

	if statErr != nil {
		serveIndex()
		return
	}

	if fi.Info.IsDir() {
		serveIndex()
		return
	}

	http.ServeFile(w, r, path)
}
