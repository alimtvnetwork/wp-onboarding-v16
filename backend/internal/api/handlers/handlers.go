// Package handlers provides HTTP request handler interfaces and service registry
package handlers

import (
	"context"
	"net/http"
	"time"
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
	GetRemotePlugins(ctx context.Context, siteID int64) (interface{}, error)
	ForceSyncRemotePlugins(ctx context.Context, siteID int64) (interface{}, error)
	InvalidateRemotePluginsCache(ctx context.Context, siteID int64) error
	EnableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	DisableRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	DeleteRemotePlugin(ctx context.Context, siteID int64, pluginSlug string) error
	GetRemotePluginFiles(ctx context.Context, siteID int64, pluginSlug string) (interface{}, error)
	GetRemotePluginFileContent(ctx context.Context, siteID int64, pluginSlug, filePath string) (string, error)
	GetCredentials(ctx context.Context, siteID int64) (interface{}, error)
	GetRemoteSnapshots(ctx context.Context, siteID int64) (interface{}, error)
	GetRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (interface{}, error)
	CreateRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error)
	DeleteRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) error
	RestoreRemoteSnapshot(ctx context.Context, siteID, snapshotID int64, opts map[string]interface{}) (interface{}, error)
	ExportRemoteSnapshot(ctx context.Context, siteID, snapshotID int64) (*http.Response, error)
	GetRemoteSnapshotSettings(ctx context.Context, siteID int64) (interface{}, error)
	UpdateRemoteSnapshotSettings(ctx context.Context, siteID int64, settings map[string]interface{}) (interface{}, error)
	GetRemoteSnapshotProviders(ctx context.Context, siteID int64) (interface{}, error)
	GetRemoteAvailableTables(ctx context.Context, siteID int64) (interface{}, error)
	FullBackupRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error)
	IncrementalBackupRemoteSnapshot(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error)
	ImportRemoteSnapshot(ctx context.Context, siteID int64, zipPath string) (interface{}, error)
	CleanupRemoteSnapshots(ctx context.Context, siteID int64, opts map[string]interface{}) (interface{}, error)
}

// SyncServiceInterface defines sync service methods
type SyncServiceInterface interface {
	CheckSync(ctx context.Context, pluginID, siteID int64) (interface{}, error)
	CheckAllSites(ctx context.Context, pluginID int64) (interface{}, error)
	CheckAllPlugins(ctx context.Context) (interface{}, error)
	GetFileChanges(ctx context.Context, pluginID, siteID int64) (interface{}, error)
	PushSync(ctx context.Context, pluginID, siteID int64) (interface{}, error)
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


// Health returns server health status
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
