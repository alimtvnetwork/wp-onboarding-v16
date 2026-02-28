package api

import (
	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/api/handlers"
)

// wireHandlerServices connects the service registry to the handlers package.
func wireHandlerServices(cfg ServerConfig) {
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
	handlers.RequestSessionStore = cfg.RequestSessionStore
}

// registerRoutes registers all API v1 routes on the subrouter.
func registerRoutes(api *mux.Router, cfg ServerConfig) {
	registerCoreRoutes(api)
	registerSiteRoutes(api)
	registerPluginRoutes(api)
	registerGitSyncRoutes(api)
	registerPublishRoutes(api)
	registerHistoryRoutes(api)
	registerSessionRoutes(api)
	registerE2ERoutes(api)
	api.HandleFunc("/ws", cfg.WSHub.HandleWebSocket).Methods("GET")
}

func registerCoreRoutes(api *mux.Router) {
	api.HandleFunc("", handlers.ApiIndex).Methods("GET")
	api.HandleFunc("/", handlers.ApiIndex).Methods("GET")
	api.HandleFunc("/health", handlers.Health).Methods("GET")
	api.HandleFunc("/openapi", handlers.ServeOpenAPISpec).Methods("GET")
	api.HandleFunc("/settings", handlers.GetSettings).Methods("GET")
	api.HandleFunc("/settings", handlers.UpdateSettings).Methods("PUT")
	api.HandleFunc("/settings/clear-error-dedup", handlers.ClearErrorLogHashes).Methods("POST")
}

func registerSiteRoutes(api *mux.Router) {
	api.HandleFunc("/sites", handlers.GetSites).Methods("GET")
	api.HandleFunc("/sites", handlers.CreateSite).Methods("POST")
	api.HandleFunc("/sites/test", handlers.TestSiteCredentials).Methods("POST")
	api.HandleFunc("/sites/{id}", handlers.GetSite).Methods("GET")
	api.HandleFunc("/sites/{id}", handlers.UpdateSite).Methods("PUT")
	api.HandleFunc("/sites/{id}", handlers.DeleteSite).Methods("DELETE")
	api.HandleFunc("/sites/{id}/test", handlers.TestSiteConnection).Methods("POST")
	api.HandleFunc("/sites/{id}/bootstrap-uploader", handlers.BootstrapUploader).Methods("POST")
	api.HandleFunc("/sites/bulk-bootstrap-uploader", handlers.BulkBootstrapUploader).Methods("POST")
	api.HandleFunc("/sites/{id}/mappings", handlers.GetSiteMappings).Methods("GET")
	api.HandleFunc("/sites/{id}/mappings", handlers.UpdateSiteMappings).Methods("PUT")
	api.HandleFunc("/sites/{id}/remote-plugins", handlers.GetRemotePlugins).Methods("GET")
	api.HandleFunc("/sites/{id}/remote-plugins/force-sync", handlers.ForceSyncRemotePlugins).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/cache", handlers.ClearRemotePluginsCache).Methods("DELETE")
	api.HandleFunc("/sites/{id}/remote-plugins/exists", handlers.CheckRemotePluginExists).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/enable", handlers.EnableRemotePlugin).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/disable", handlers.DisableRemotePlugin).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/delete", handlers.DeleteRemotePlugin).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/files", handlers.GetRemotePluginFiles).Methods("POST")
	api.HandleFunc("/sites/{id}/remote-plugins/file", handlers.GetRemotePluginFileContent).Methods("POST")
	api.HandleFunc("/sites/{id}/credentials", handlers.GetSiteCredentials).Methods("GET")
	registerSnapshotRoutes(api)
}

func registerSnapshotRoutes(api *mux.Router) {
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
	api.HandleFunc("/sites/{id}/snapshots/download", handlers.DownloadSnapshotZip).Methods("POST")
}

func registerPluginRoutes(api *mux.Router) {
	api.HandleFunc("/plugins", handlers.GetPlugins).Methods("GET")
	api.HandleFunc("/plugins", handlers.CreatePlugin).Methods("POST")
	api.HandleFunc("/plugins/{id}", handlers.GetPlugin).Methods("GET")
	api.HandleFunc("/plugins/{id}", handlers.UpdatePlugin).Methods("PUT")
	api.HandleFunc("/plugins/{id}", handlers.DeletePlugin).Methods("DELETE")
	api.HandleFunc("/plugins/{id}/mappings", handlers.GetPluginMappings).Methods("GET")
	api.HandleFunc("/plugins/{id}/mappings", handlers.CreatePluginMapping).Methods("POST")
	api.HandleFunc("/plugins/{id}/mappings", handlers.UpdatePluginMappings).Methods("PUT")
	api.HandleFunc("/plugins/{id}/changes", handlers.GetFileChanges).Methods("GET")
	api.HandleFunc("/plugins/{id}/scan", handlers.ScanPlugin).Methods("POST")
	api.HandleFunc("/plugins/scan", handlers.ScanAllPlugins).Methods("POST")
	api.HandleFunc("/plugins/scan-directory", handlers.ScanDirectoryPath).Methods("POST")
	api.HandleFunc("/plugins/scan-directories", handlers.ScanDirectoriesPath).Methods("POST")
	api.HandleFunc("/plugins/{id}/file", handlers.GetLocalFileContent).Methods("POST")
	api.HandleFunc("/mappings/{id}", handlers.DeletePluginMapping).Methods("DELETE")
}

func registerGitSyncRoutes(api *mux.Router) {
	api.HandleFunc("/plugins/{id}/git/pull", handlers.GitPull).Methods("POST")
	api.HandleFunc("/plugins/{id}/git/status", handlers.GitStatus).Methods("GET")
	api.HandleFunc("/plugins/{id}/git/commit", handlers.GitCommit).Methods("POST")
	api.HandleFunc("/plugins/{id}/git/push", handlers.GitPush).Methods("POST")
	api.HandleFunc("/plugins/git/pull", handlers.GitPullAll).Methods("POST")
	api.HandleFunc("/plugins/{id}/sites/{siteId}/sync", handlers.CheckSync).Methods("POST")
	api.HandleFunc("/plugins/{id}/sites/{siteId}/sync/push", handlers.PushSync).Methods("POST")
	api.HandleFunc("/plugins/{id}/sync/check-all", handlers.CheckAllSites).Methods("POST")
}

func registerPublishRoutes(api *mux.Router) {
	api.HandleFunc("/plugins/{id}/sites/{siteId}/publish", handlers.PublishPlugin).Methods("POST")
	api.HandleFunc("/plugins/{id}/sites/{siteId}/preview", handlers.PreviewPublish).Methods("GET")
	api.HandleFunc("/plugins/{id}/sites/{siteId}/file-diff", handlers.GetFileDiff).Methods("POST")
	api.HandleFunc("/plugins/{id}/backups", handlers.GetBackups).Methods("GET")
	api.HandleFunc("/backups/{id}/restore", handlers.RestoreBackup).Methods("POST")
	api.HandleFunc("/backups/{id}", handlers.DeleteBackup).Methods("DELETE")
	api.HandleFunc("/plugins/{id}/versions", handlers.GetPluginVersions).Methods("GET")
	api.HandleFunc("/plugins/{id}/versions/{versionId}", handlers.GetPluginVersion).Methods("GET")
	api.HandleFunc("/plugins/{id}/versions/{versionId}/rollback", handlers.RollbackPluginVersion).Methods("POST")
	api.HandleFunc("/plugins/{id}/versions/{versionId}", handlers.DeletePluginVersion).Methods("DELETE")
}

func registerHistoryRoutes(api *mux.Router) {
	api.HandleFunc("/site-health/check-all", handlers.CheckAllSitesHealth).Methods("POST")
	api.HandleFunc("/site-health/summaries", handlers.GetSiteHealthSummaries).Methods("GET")
	api.HandleFunc("/site-health/stats", handlers.GetSiteHealthStats).Methods("GET")
	api.HandleFunc("/site-health/history", handlers.GetSiteHealthHistory).Methods("GET")
	api.HandleFunc("/site-health/history", handlers.ClearSiteHealthHistory).Methods("DELETE")
	api.HandleFunc("/site-health/sites/{id}/check", handlers.CheckSiteHealth).Methods("POST")
	api.HandleFunc("/publish-history", handlers.ListPublishHistory).Methods("GET")
	api.HandleFunc("/publish-history", handlers.ClearPublishHistory).Methods("DELETE")
	api.HandleFunc("/publish-history/stats", handlers.GetPublishHistoryStats).Methods("GET")
	api.HandleFunc("/publish-history/{id}", handlers.GetPublishHistoryByID).Methods("GET")
	api.HandleFunc("/publish-history/{id}", handlers.DeletePublishHistoryEntry).Methods("DELETE")
	api.HandleFunc("/errors/bundle", handlers.DownloadErrorBundle).Methods("GET", "POST")
	api.HandleFunc("/errors/stream", handlers.StreamErrorLogs).Methods("GET")
	api.HandleFunc("/errors/log", handlers.GetBackendErrorLog).Methods("GET")
	api.HandleFunc("/errors", handlers.GetErrors).Methods("GET")
	api.HandleFunc("/errors", handlers.ClearErrors).Methods("DELETE")
	api.HandleFunc("/errors/{id}", handlers.GetError).Methods("GET")
	api.HandleFunc("/logs/full", handlers.GetBackendFullLog).Methods("GET")
	api.HandleFunc("/error-history", handlers.ListErrorHistory).Methods("GET")
	api.HandleFunc("/error-history", handlers.SaveErrorHistory).Methods("POST")
	api.HandleFunc("/error-history", handlers.ClearErrorHistory).Methods("DELETE")
	api.HandleFunc("/error-history/stats", handlers.GetErrorHistoryStats).Methods("GET")
	api.HandleFunc("/error-history/bulk-export", handlers.BulkExportErrorHistory).Methods("POST")
	api.HandleFunc("/error-history/{id}", handlers.GetErrorHistoryByID).Methods("GET")
	api.HandleFunc("/error-history/{id}", handlers.DeleteErrorHistory).Methods("DELETE")
}

func registerSessionRoutes(api *mux.Router) {
	api.HandleFunc("/sessions", handlers.GetSessions).Methods("GET")
	api.HandleFunc("/sessions/{id}", handlers.GetSession).Methods("GET")
	api.HandleFunc("/sessions/{id}/logs", handlers.GetSessionLogs).Methods("GET")
	api.HandleFunc("/sessions/{id}/diagnostics", handlers.GetSessionDiagnostics).Methods("GET")
	api.HandleFunc("/sessions/{id}", handlers.DeleteSession).Methods("DELETE")
	api.HandleFunc("/request-sessions", handlers.GetRequestSessions).Methods("GET")
	api.HandleFunc("/request-sessions", handlers.ClearRequestSessions).Methods("DELETE")
	api.HandleFunc("/request-sessions/errors", handlers.GetRequestSessionsByError).Methods("GET")
	api.HandleFunc("/request-sessions/{id}", handlers.GetRequestSession).Methods("GET")
	api.HandleFunc("/request-sessions/{id}", handlers.DeleteRequestSession).Methods("DELETE")
	api.HandleFunc("/request-sessions/{id}/export", handlers.ExportRequestSession).Methods("GET")
}

func registerE2ERoutes(api *mux.Router) {
	api.HandleFunc("/e2e/suites", handlers.GetE2ESuites).Methods("GET")
	api.HandleFunc("/e2e/suites/{id}/cases", handlers.GetE2ECases).Methods("GET")
	api.HandleFunc("/e2e/run", handlers.StartE2ERun).Methods("POST")
	api.HandleFunc("/e2e/runs", handlers.GetE2ERuns).Methods("GET")
	api.HandleFunc("/e2e/runs/{id}", handlers.GetE2ERun).Methods("GET")
	api.HandleFunc("/e2e/runs/{id}", handlers.DeleteE2ERun).Methods("DELETE")
	api.HandleFunc("/e2e/runs/{id}/abort", handlers.AbortE2ERun).Methods("POST")
}
