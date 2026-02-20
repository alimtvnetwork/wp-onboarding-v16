// Package handlers - Service registry factory
package handlers

import (
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/errorhistory"
	"wp-plugin-publish/internal/services/git"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/publishhistory"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/sitehealth"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
)

// NewServiceRegistry creates a ServiceRegistry from concrete service implementations
func NewServiceRegistry(
	siteService *site.Service,
	pluginService *plugin.Service,
	syncService sync.Service,
	gitService *git.Service,
	watcherService *watcher.Service,
	publishService *publish.Service,
	backupService *backup.Service,
	sessionService *session.Service,
	errorHistoryService *errorhistory.Service,
	publishHistoryService *publishhistory.Service,
	siteHealthService *sitehealth.Service,
) *ServiceRegistry {
	var sessionAdapter SessionServiceInterface
	if sessionService != nil {
		sessionAdapter = &SessionServiceAdapter{sessionService}
	}

	var errorHistoryAdapter ErrorHistoryServiceInterface
	if errorHistoryService != nil {
		errorHistoryAdapter = &ErrorHistoryServiceAdapter{errorHistoryService}
	}

	var publishHistoryAdapter PublishHistoryServiceInterface
	if publishHistoryService != nil {
		publishHistoryAdapter = &PublishHistoryServiceAdapter{publishHistoryService}
	}

	var siteHealthAdapter SiteHealthServiceInterface
	if siteHealthService != nil {
		siteHealthAdapter = &SiteHealthServiceAdapter{siteHealthService}
	}

	var gitAdapter GitServiceInterface
	if gitService != nil {
		gitAdapter = &GitServiceAdapter{gitService}
	}

	return &ServiceRegistry{
		SiteService:           &SiteServiceAdapter{siteService},
		PluginService:         &PluginServiceAdapter{pluginService},
		SyncService:           &SyncServiceAdapter{syncService},
		GitService:            gitAdapter,
		WatcherService:        &WatcherServiceAdapter{watcherService},
		PublishService:        &PublishServiceAdapter{publishService},
		BackupService:         &BackupServiceAdapter{backupService},
		SessionService:        sessionAdapter,
		ErrorHistoryService:   errorHistoryAdapter,
		PublishHistoryService: publishHistoryAdapter,
		SiteHealthService:     siteHealthAdapter,
	}
}
