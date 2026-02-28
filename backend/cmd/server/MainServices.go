// Package main — service initialization and wiring.
package main

import (
	"path/filepath"
	"time"

	"wp-plugin-publish/internal/config"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/error_history"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/publish_history"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/site_health"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

// Services holds all application services
type Services struct {
	Site           *site.Service
	Plugin         *plugin.Service
	Watcher        *watcher.Service
	Sync           sync.Service
	Publish        *publish.Service
	Backup         *backup.Service
	Session        *session.Service
	ErrorHistory   *errorhistory.Service
	PublishHistory *publishhistory.Service
	SiteHealth     *sitehealth.Service
}

// InitServicesInput holds dependencies for service initialization.
type InitServicesInput struct {
	DB    *database.DB
	Cfg   *config.Config
	WSHub *ws.Hub
	Log   *logger.Logger
}

// initServices creates and wires all application services
func initServices(input InitServicesInput) *Services {
	wpFactories := buildWPFactories(input)
	sessionService := initSessionService(input)
	core := buildCoreServices(input, wpFactories, sessionService)

	return buildServicesBundle(coreServicesInput{
		Init:      input,
		Site:      core.Site,
		Plugin:    core.Plugin,
		Backup:    core.Backup,
		Sync:      core.Sync,
		Session:   sessionService,
		WPFactory: wpFactories.simple,
	})
}

// coreServicesDeps holds the core services built during init.
type coreServicesDeps struct {
	Site   *site.Service
	Plugin *plugin.Service
	Backup *backup.Service
	Sync   sync.Service
}

// buildCoreServices creates the site, plugin, backup, and sync services.
func buildCoreServices(input InitServicesInput, wf wpFactories, sessionSvc *session.Service) coreServicesDeps {
	siteWSHub := &SiteWSHubAdapter{hub: input.WSHub}
	siteService := initSiteService(siteDepsInput{
		Init:      input,
		WPFactory: wf.withProgress,
		WSHub:     siteWSHub,
		Session:   sessionSvc,
	})

	pluginService := plugin.New(plugin.Config{DB: input.DB, Logger: input.Log})

	return buildRemainingCoreServices(input, wf, siteService, pluginService)
}

// buildRemainingCoreServices creates backup and sync services and assembles the deps.
func buildRemainingCoreServices(input InitServicesInput, wf wpFactories, siteService *site.Service, pluginService *plugin.Service) coreServicesDeps {
	backupService := initBackupService(input)

	syncService := initSyncService(syncDepsInput{
		Init:      input,
		Plugin:    pluginService,
		Site:      siteService,
		WPFactory: wf.simple,
	})

	return coreServicesDeps{
		Site:   siteService,
		Plugin: pluginService,
		Backup: backupService,
		Sync:   syncService,
	}
}

// coreServicesInput bundles dependencies for buildServicesBundle.
type coreServicesInput struct {
	Init      InitServicesInput
	Site      *site.Service
	Plugin    *plugin.Service
	Backup    *backup.Service
	Sync      sync.Service
	Session   *session.Service
	WPFactory func(string, string, string) *wordpress.Client
}

// buildServicesBundle constructs the final Services struct.
func buildServicesBundle(input coreServicesInput) *Services {
	publishHistorySvc := publishhistory.New(publishhistory.Config{DB: input.Init.DB, Logger: input.Init.Log})
	siteHealthSvc := sitehealth.New(sitehealth.Config{DB: input.Init.DB, Logger: input.Init.Log})
	publishSvc := initPublishService(buildPublishDeps(input, publishHistorySvc))
	watcherSvc := watcher.New(watcher.Config{DB: input.Init.DB, Logger: input.Init.Log, PluginService: input.Plugin, WSHub: input.Init.WSHub})
	errorHistorySvc := errorhistory.New(errorhistory.Config{DB: input.Init.DB, Logger: input.Init.Log})

	return assembleServices(input, publishSvc, watcherSvc, errorHistorySvc, publishHistorySvc, siteHealthSvc)
}

// buildPublishDeps constructs the publish service dependency input.
func buildPublishDeps(input coreServicesInput, history *publishhistory.Service) publishDepsInput {
	return publishDepsInput{
		Init: input.Init, Plugin: input.Plugin, Backup: input.Backup,
		Sync: input.Sync, Site: input.Site, WPFactory: input.WPFactory,
		Session: input.Session, History: history,
	}
}

// assembleServices builds the final Services struct from all service instances.
func assembleServices(input coreServicesInput, publishSvc *publish.Service, watcherSvc *watcher.Service, errorHistorySvc *errorhistory.Service, publishHistorySvc *publishhistory.Service, siteHealthSvc *sitehealth.Service) *Services {
	return &Services{
		Site: input.Site, Plugin: input.Plugin, Watcher: watcherSvc, Sync: input.Sync,
		Publish: publishSvc, Backup: input.Backup, Session: input.Session,
		ErrorHistory: errorHistorySvc, PublishHistory: publishHistorySvc, SiteHealth: siteHealthSvc,
	}
}

// wpFactories holds the two client factory variants.
type wpFactories struct {
	withProgress site.WPClientFactory
	simple       func(string, string, string) *wordpress.Client
}

// buildWPFactories creates both WordPress client factory variants.
func buildWPFactories(input InitServicesInput) wpFactories {
	timeout := time.Duration(input.Cfg.WordPress.TimeoutSeconds) * time.Second
	depth := input.Cfg.Logging.StackTraceDepth

	withProgress := func(siteUrl, username, password string, onProgress func(event wordpress.ProgressEvent)) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseUrl: siteUrl, Username: username, Password: password,
			Timeout: timeout, StackTraceDepth: depth, OnProgress: onProgress,
		})
	}
	simple := func(siteUrl, username, password string) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseUrl: siteUrl, Username: username, Password: password,
			Timeout: timeout, StackTraceDepth: depth,
		})
	}

	return wpFactories{withProgress: withProgress, simple: simple}
}

// initSessionService creates the session service.
func initSessionService(input InitServicesInput) *session.Service {
	result := session.New(session.Config{
		DataDir:       filepath.Dir(input.Cfg.DatabasePath),
		Logger:        input.Log,
		RetentionDays: 7,
	})

	if result.HasError() {
		input.Log.Error("Failed to initialize session service", "error", result.AppError())
	}

	return result.Value()
}

// siteDepsInput bundles dependencies for initSiteService.
type siteDepsInput struct {
	Init      InitServicesInput
	WPFactory site.WPClientFactory
	WSHub     *SiteWSHubAdapter
	Session   *session.Service
}

// initSiteService creates the site service.
func initSiteService(input siteDepsInput) *site.Service {

	return site.New(site.Config{
		DB: input.Init.DB, Logger: input.Init.Log, EncryptionKey: input.Init.Cfg.Security.EncryptionKey,
		WPClientFactory: input.WPFactory, WSHub: input.WSHub, SessionService: input.Session,
		IsCacheEnabled: input.Init.Cfg.RemotePlugins.CacheEnabled, CacheTTLMinutes: input.Init.Cfg.RemotePlugins.CacheTTLMinutes,
	})
}

// initBackupService creates the backup service.
func initBackupService(input InitServicesInput) *backup.Service {

	return backup.New(backup.Config{
		DB: input.DB, Logger: input.Log, BackupDir: input.Cfg.Backup.Location,
		RetentionDays: input.Cfg.Backup.RetentionDays, MaxPerPlugin: input.Cfg.Backup.MaxBackupsPerPlugin,
	})
}

// syncDepsInput bundles dependencies for initSyncService.
type syncDepsInput struct {
	Init      InitServicesInput
	Plugin    *plugin.Service
	Site      *site.Service
	WPFactory func(string, string, string) *wordpress.Client
}

// initSyncService creates the sync service.
func initSyncService(input syncDepsInput) sync.Service {

	return sync.New(sync.Config{
		DB: input.Init.DB, Logger: input.Init.Log, PluginService: input.Plugin,
		SitePasswordDecryptor: input.Site, WPClientFactory: input.WPFactory, WSHub: input.Init.WSHub,
	})
}

// publishDepsInput bundles dependencies for initPublishService.
type publishDepsInput struct {
	Init      InitServicesInput
	Plugin    *plugin.Service
	Backup    *backup.Service
	Sync      sync.Service
	Site      *site.Service
	WPFactory func(string, string, string) *wordpress.Client
	Session   *session.Service
	History   *publishhistory.Service
}

// initPublishService creates the publish service.
func initPublishService(input publishDepsInput) *publish.Service {

	return publish.New(publish.Config{
		DB: input.Init.DB, Logger: input.Init.Log, PluginService: input.Plugin, BackupService: input.Backup,
		SyncService: input.Sync, SitePasswordDecryptor: input.Site, WPClientFactory: input.WPFactory,
		TempDir: input.Init.Cfg.TempDir, WSHub: input.Init.WSHub, SessionService: input.Session, HistoryService: input.History,
	})
}
