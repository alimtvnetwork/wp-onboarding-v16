// Package handlers - Service registry factory
package handlers

import (
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/error_history"
	"wp-plugin-publish/internal/services/git"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/publish_history"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/site_health"
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
	isSessionAvailable := sessionService != nil

	if isSessionAvailable {
		sessionAdapter = &SessionServiceAdapter{sessionService}
	}

	var errorHistoryAdapter ErrorHistoryServiceInterface
	isErrorHistoryAvailable := errorHistoryService != nil

	if isErrorHistoryAvailable {
		errorHistoryAdapter = &ErrorHistoryServiceAdapter{errorHistoryService}
	}

	var publishHistoryAdapter PublishHistoryServiceInterface
	isPublishHistoryAvailable := publishHistoryService != nil

	if isPublishHistoryAvailable {
		publishHistoryAdapter = &PublishHistoryServiceAdapter{publishHistoryService}
	}

	var siteHealthAdapter SiteHealthServiceInterface
	isSiteHealthAvailable := siteHealthService != nil

	if isSiteHealthAvailable {
		siteHealthAdapter = &SiteHealthServiceAdapter{siteHealthService}
	}

	var gitAdapter GitServiceInterface
	isGitAvailable := gitService != nil

	if isGitAvailable {
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
