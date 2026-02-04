// WP Plugin Publish - Application Entry Point
// Manages WordPress plugin development workflows with local file watching and remote sync
package main

import (
	"context"
	"fmt"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"wp-plugin-publish/internal/api"
	"wp-plugin-publish/internal/api/handlers"
	"wp-plugin-publish/internal/config"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
	"wp-plugin-publish/internal/version"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

// Services holds all application services
type Services struct {
	Site    *site.Service
	Plugin  *plugin.Service
	Watcher *watcher.Service
	Sync    sync.Service
	Publish *publish.Service
	Backup  *backup.Service
}

func main() {
	// Bootstrap logger (minimal, used only until config is loaded)
	bootstrapLog := logger.New(logger.Config{Level: logger.LevelInfo})

	// Load configuration FIRST so we can use logging.timeFormat
	cfg, err := config.Load("config.json")
	if err != nil {
		bootstrapLog.Fatal("Failed to load config", "error", err)
	}

	// Load version info from version.json (frontend/dist or public/)
	versionInfo, _ := version.Load(cfg.Server.StaticDir)

	// Initialize the real logger with configured timeFormat (single source of truth)
	log := logger.New(logger.Config{
		Level:      parseLogLevel(cfg.Logging.Level),
		TimeFormat: cfg.Logging.TimeFormat,
		AppName:    versionInfo.AppName,
		AppVersion: versionInfo.Version,
	})

	log.Info("Starting application", "version", versionInfo.String())

	// Initialize database
	db, err := database.New(cfg.DatabasePath)
	if err != nil {
		log.Fatal("Failed to connect to database", "error", err)
	}
	defer db.Close()

	// Run migrations
	if err := database.Migrate(db); err != nil {
		log.Fatal("Failed to run migrations", "error", err)
	}

	// Seed from config if needed
	if err := config.SeedIfNeeded(db, cfg); err != nil {
		log.Fatal("Failed to seed database", "error", err)
	}

	// Initialize WebSocket hub
	wsHub := ws.NewHub()
	go wsHub.Run()

	// Initialize services
	services := initServices(db, cfg, wsHub, log)

	// Initialize file caches for registered plugins
	ctx := context.Background()
	plugins, _ := services.Plugin.List(ctx)
	for _, p := range plugins {
		if err := services.Watcher.InitializeCache(ctx, p.ID); err != nil {
			log.Error("Failed to initialize watcher cache", "pluginId", p.ID, "error", err)
		}
	}

	// Start HTTP server - use handlers.NewServiceRegistry to wrap services with adapters
	serviceRegistry := handlers.NewServiceRegistry(
		services.Site,
		services.Plugin,
		services.Sync,
		nil, // TODO: Add git service when implemented
		services.Watcher,
		services.Publish,
		services.Backup,
	)
	server := api.NewServer(api.ServerConfig{
		Port:      cfg.Server.Port,
		StaticDir: cfg.Server.StaticDir,
		Services: &api.ServiceRegistry{
			Site:    serviceRegistry.SiteService,
			Plugin:  serviceRegistry.PluginService,
			Sync:    serviceRegistry.SyncService,
			Git:     serviceRegistry.GitService,
			Watcher: serviceRegistry.WatcherService,
			Publish: serviceRegistry.PublishService,
			Backup:  serviceRegistry.BackupService,
		},
		WSHub:  wsHub,
		Logger: log,
	})
	go func() {
		if err := server.Start(); err != nil {
			log.Fatal("Server failed", "error", err)
		}
	}()

	log.Info("Server started", "port", cfg.Server.Port)
	fmt.Printf("\n  %s\n", versionInfo.String())
	fmt.Printf("  Local:   http://localhost:%d\n\n", cfg.Server.Port)

	// Wait for shutdown signal
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	// Graceful shutdown
	log.Info("Shutting down...")

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	// Watcher uses hybrid mode - no background polling to stop
	if err := server.Shutdown(ctx); err != nil {
		log.Error("Server shutdown error", "error", err)
	}

	log.Info("Application stopped")
}

// parseLogLevel converts a string log level to logger.Level
func parseLogLevel(level string) logger.Level {
	switch strings.ToLower(strings.TrimSpace(level)) {
	case "debug":
		return logger.LevelDebug
	case "info":
		return logger.LevelInfo
	case "warn", "warning":
		return logger.LevelWarn
	case "error":
		return logger.LevelError
	case "fatal":
		return logger.LevelFatal
	default:
		return logger.LevelInfo
	}
}

// initServices creates and wires all application services
func initServices(db *database.DB, cfg *config.Config, wsHub *ws.Hub, log *logger.Logger) *Services {
	// WordPress REST API client factory with progress callback support
	wpClientFactoryWithProgress := func(siteURL, username, password string, onProgress func(step, status, message string, details map[string]interface{})) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseURL:    siteURL,
			Username:   username,
			Password:   password,
			Timeout:    time.Duration(cfg.WordPress.TimeoutSeconds) * time.Second,
			OnProgress: onProgress,
		})
	}

	// Simple client factory for services that don't need progress callbacks
	wpClientFactory := func(siteURL, username, password string) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseURL:  siteURL,
			Username: username,
			Password: password,
			Timeout:  time.Duration(cfg.WordPress.TimeoutSeconds) * time.Second,
		})
	}

	// Initialize services with dependencies
	siteService := site.New(site.Config{
		DB:              db,
		Logger:          log,
		EncryptionKey:   cfg.Security.EncryptionKey,
		WPClientFactory: wpClientFactoryWithProgress,
		WSHub:           wsHub,
	})

	pluginService := plugin.New(plugin.Config{
		DB:     db,
		Logger: log,
	})

	backupService := backup.New(backup.Config{
		DB:            db,
		Logger:        log,
		BackupDir:     cfg.Backup.Location,
		RetentionDays: cfg.Backup.RetentionDays,
		MaxPerPlugin:  cfg.Backup.MaxBackupsPerPlugin,
	})

	syncService := sync.New(sync.Config{
		DB:              db,
		Logger:          log,
		PluginService:   pluginService,
		WPClientFactory: wpClientFactory,
		WSHub:           wsHub,
	})

	publishService := publish.New(publish.Config{
		DB:              db,
		Logger:          log,
		PluginService:   pluginService,
		BackupService:   backupService,
		SyncService:     syncService,
		WPClientFactory: wpClientFactory,
		TempDir:         cfg.TempDir,
		WSHub:           wsHub,
	})

	watcherService := watcher.New(watcher.Config{
		DB:            db,
		Logger:        log,
		PluginService: pluginService,
		WSHub:         wsHub,
	})

	return &Services{
		Site:    siteService,
		Plugin:  pluginService,
		Watcher: watcherService,
		Sync:    syncService,
		Publish: publishService,
		Backup:  backupService,
	}
}
