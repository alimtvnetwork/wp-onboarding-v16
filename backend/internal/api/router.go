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
)

// ServerConfig holds server configuration
type ServerConfig struct {
	Port      int
	StaticDir string
	Services  interface{} // *main.Services
	WSHub     *ws.Hub
	Logger    *logger.Logger
}

// Server represents the HTTP server
type Server struct {
	server *http.Server
	config ServerConfig
}

// NewServer creates a new HTTP server
func NewServer(cfg ServerConfig) *Server {
	router := mux.NewRouter()

	// Apply global middleware
	router.Use(middleware.CORS)
	router.Use(middleware.Logging(cfg.Logger))
	router.Use(middleware.Recovery(cfg.Logger))

	// API v1 routes
	api := router.PathPrefix("/api/v1").Subrouter()

	// Health check
	api.HandleFunc("/health", handlers.Health).Methods("GET")

	// Sites endpoints
	api.HandleFunc("/sites", handlers.GetSites).Methods("GET")
	api.HandleFunc("/sites", handlers.CreateSite).Methods("POST")
	api.HandleFunc("/sites/test", handlers.TestSiteCredentials).Methods("POST") // Test before create
	api.HandleFunc("/sites/{id}", handlers.GetSite).Methods("GET")
	api.HandleFunc("/sites/{id}", handlers.UpdateSite).Methods("PUT")
	api.HandleFunc("/sites/{id}", handlers.DeleteSite).Methods("DELETE")
	api.HandleFunc("/sites/{id}/test", handlers.TestSiteConnection).Methods("POST")

	// Plugins endpoints
	api.HandleFunc("/plugins", handlers.GetPlugins).Methods("GET")
	api.HandleFunc("/plugins", handlers.CreatePlugin).Methods("POST")
	api.HandleFunc("/plugins/{id}", handlers.GetPlugin).Methods("GET")
	api.HandleFunc("/plugins/{id}", handlers.UpdatePlugin).Methods("PUT")
	api.HandleFunc("/plugins/{id}", handlers.DeletePlugin).Methods("DELETE")
	api.HandleFunc("/plugins/{id}/mappings", handlers.GetPluginMappings).Methods("GET")
	api.HandleFunc("/plugins/{id}/mappings", handlers.CreatePluginMapping).Methods("POST")
	api.HandleFunc("/plugins/{id}/changes", handlers.GetFileChanges).Methods("GET")

	// Plugin scanning endpoints
	api.HandleFunc("/plugins/{id}/scan", handlers.ScanPlugin).Methods("POST")
	api.HandleFunc("/plugins/scan", handlers.ScanAllPlugins).Methods("POST")

	// Git endpoints
	api.HandleFunc("/plugins/{id}/git/pull", handlers.GitPull).Methods("POST")
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

	// Error logs endpoints
	api.HandleFunc("/errors", handlers.GetErrors).Methods("GET")
	api.HandleFunc("/errors", handlers.ClearErrors).Methods("DELETE")
	api.HandleFunc("/errors/{id}", handlers.GetError).Methods("GET")

	// Settings endpoints
	api.HandleFunc("/settings", handlers.GetSettings).Methods("GET")
	api.HandleFunc("/settings", handlers.UpdateSettings).Methods("PUT")

	// Mappings endpoints
	api.HandleFunc("/mappings/{id}", handlers.DeletePluginMapping).Methods("DELETE")

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
		spa := spaHandler{staticDir: cfg.StaticDir, indexPath: "index.html"}
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
	// Get the absolute path to prevent directory traversal
	path := filepath.Join(h.staticDir, filepath.Clean(r.URL.Path))

	// Check if file exists
	fi, err := os.Stat(path)
	if os.IsNotExist(err) || fi.IsDir() {
		// File doesn't exist or is directory, serve index.html for SPA routing
		http.ServeFile(w, r, filepath.Join(h.staticDir, h.indexPath))
		return
	}

	if err != nil {
		http.Error(w, "Internal Server Error", http.StatusInternalServerError)
		return
	}

	// Set proper content type for assets
	ext := strings.ToLower(filepath.Ext(path))
	switch ext {
	case ".js":
		w.Header().Set("Content-Type", "application/javascript")
	case ".css":
		w.Header().Set("Content-Type", "text/css")
	case ".svg":
		w.Header().Set("Content-Type", "image/svg+xml")
	case ".json":
		w.Header().Set("Content-Type", "application/json")
	}

	// Serve the actual file
	http.ServeFile(w, r, path)
}
