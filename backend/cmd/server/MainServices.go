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
	siteWSHub := &SiteWSHubAdapter{hub: input.WSHub}

	siteService := initSiteService(input, wpFactories.withProgress, siteWSHub, sessionService)
	pluginService := plugin.New(plugin.Config{DB: input.DB, Logger: input.Log})
	backupService := initBackupService(input)
	syncService := initSyncService(input, pluginService, siteService, wpFactories.simple)

	return buildServicesBundle(input, siteService, pluginService, backupService, syncService, sessionService, wpFactories.simple)
}

// buildServicesBundle constructs the final Services struct.
func buildServicesBundle(input InitServicesInput, siteSvc *site.Service, pluginSvc *plugin.Service, backupSvc *backup.Service, syncSvc sync.Service, sessionSvc *session.Service, wpFactory func(string, string, string) *wordpress.Client) *Services {
	publishHistorySvc := publishhistory.New(publishhistory.Config{DB: input.DB, Logger: input.Log})
	siteHealthSvc := sitehealth.New(sitehealth.Config{DB: input.DB, Logger: input.Log})
	publishSvc := initPublishService(input, pluginSvc, backupSvc, syncSvc, siteSvc, wpFactory, sessionSvc, publishHistorySvc)
	watcherSvc := watcher.New(watcher.Config{DB: input.DB, Logger: input.Log, PluginService: pluginSvc, WSHub: input.WSHub})
	errorHistorySvc := errorhistory.New(errorhistory.Config{DB: input.DB, Logger: input.Log})

	return &Services{
		Site: siteSvc, Plugin: pluginSvc, Watcher: watcherSvc, Sync: syncSvc,
		Publish: publishSvc, Backup: backupSvc, Session: sessionSvc,
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

	withProgress := func(siteURL, username, password string, onProgress func(event wordpress.ProgressEvent)) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseURL: siteURL, Username: username, Password: password,
			Timeout: timeout, StackTraceDepth: depth, OnProgress: onProgress,
		})
	}
	simple := func(siteURL, username, password string) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseURL: siteURL, Username: username, Password: password,
			Timeout: timeout, StackTraceDepth: depth,
		})
	}
	return wpFactories{withProgress: withProgress, simple: simple}
}

// initSessionService creates the session service.
func initSessionService(input InitServicesInput) *session.Service {
	svc, err := session.New(session.Config{
		DataDir:       filepath.Dir(input.Cfg.DatabasePath),
		Logger:        input.Log,
		RetentionDays: 7,
	})
	if err != nil {
		input.Log.Error("Failed to initialize session service", "error", err)
	}
	return svc
}

// initSiteService creates the site service.
func initSiteService(input InitServicesInput, wpFactory site.WPClientFactory, wsHub *SiteWSHubAdapter, sessionSvc *session.Service) *site.Service {
	return site.New(site.Config{
		DB: input.DB, Logger: input.Log, EncryptionKey: input.Cfg.Security.EncryptionKey,
		WPClientFactory: wpFactory, WSHub: wsHub, SessionService: sessionSvc,
		IsCacheEnabled: input.Cfg.RemotePlugins.CacheEnabled, CacheTTLMinutes: input.Cfg.RemotePlugins.CacheTTLMinutes,
	})
}

// initBackupService creates the backup service.
func initBackupService(input InitServicesInput) *backup.Service {
	return backup.New(backup.Config{
		DB: input.DB, Logger: input.Log, BackupDir: input.Cfg.Backup.Location,
		RetentionDays: input.Cfg.Backup.RetentionDays, MaxPerPlugin: input.Cfg.Backup.MaxBackupsPerPlugin,
	})
}

// initSyncService creates the sync service.
func initSyncService(input InitServicesInput, pluginSvc *plugin.Service, siteSvc *site.Service, wpFactory func(string, string, string) *wordpress.Client) sync.Service {
	return sync.New(sync.Config{
		DB: input.DB, Logger: input.Log, PluginService: pluginSvc,
		SitePasswordDecryptor: siteSvc, WPClientFactory: wpFactory, WSHub: input.WSHub,
	})
}

// initPublishService creates the publish service.
func initPublishService(input InitServicesInput, pluginSvc *plugin.Service, backupSvc *backup.Service, syncSvc sync.Service, siteSvc *site.Service, wpFactory func(string, string, string) *wordpress.Client, sessionSvc *session.Service, historySvc *publishhistory.Service) *publish.Service {
	return publish.New(publish.Config{
		DB: input.DB, Logger: input.Log, PluginService: pluginSvc, BackupService: backupSvc,
		SyncService: syncSvc, SitePasswordDecryptor: siteSvc, WPClientFactory: wpFactory,
		TempDir: input.Cfg.TempDir, WSHub: input.WSHub, SessionService: sessionSvc, HistoryService: historySvc,
	})
}
