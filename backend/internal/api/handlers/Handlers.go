// Package handlers provides HTTP request handler interfaces and service registry
package handlers

import (
	"net/http"
	"time"

	"wp-plugin-publish/internal/wordpress"
)

// ServiceRegistry holds references to all services
type ServiceRegistry struct {
	PluginService         PluginServiceInterface
	SiteService           SiteServiceInterface
	SyncService           SyncServiceInterface
	GitService            GitServiceInterface
	WatcherService        WatcherServiceInterface
	PublishService        PublishServiceInterface
	BackupService         BackupServiceInterface
	SessionService        SessionServiceInterface
	ErrorHistoryService   ErrorHistoryServiceInterface
	PublishHistoryService PublishHistoryServiceInterface
	SiteHealthService     SiteHealthServiceInterface
}

// Global service registry - set during server initialization
var Services *ServiceRegistry

// Health returns server health status
func Health(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, HealthResponse{
		Status:    "ok",
		Timestamp: time.Now().Format(time.RFC3339),
	})
}

// APIIndex returns API metadata for the base /api/v1 endpoint
func APIIndex(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, ApiIndexResponse{
		Name:    "WP Plugin Publish API",
		Version: "v1",
		Health:  wordpress.GoAPIHealth,
		WS:      wordpress.GoAPIWebSocket,
	})
}
