// WP Plugin Publish - Application Entry Point
// Manages WordPress plugin development workflows with local file watching and remote sync
package main

import (
	"context"
	"fmt"
	"os"
	"os/signal"
	"syscall"
	"time"

	"wp-plugin-publish/internal/api"
	"wp-plugin-publish/internal/config"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

const (
	AppName    = "WP Plugin Publish"
	AppVersion = "1.0.0"
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
	// Initialize logger
	log := logger.New(logger.Config{
		Level:      logger.LevelInfo,
		TimeFormat: time.RFC3339,
	})

	log.Info("Starting application", "name", AppName, "version", AppVersion)

	// Load configuration
	cfg, err := config.Load("config.json")
	if err != nil {
		log.Fatal("Failed to load config", "error", err)
	}

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

	// Start HTTP server
	server := api.NewServer(api.ServerConfig{
		Port:     cfg.Server.Port,
		Services: services,
		WSHub:    wsHub,
		Logger:   log,
	})
	go func() {
		if err := server.Start(); err != nil {
			log.Fatal("Server failed", "error", err)
		}
	}()

	log.Info("Server started", "port", cfg.Server.Port)
	fmt.Printf("\n  %s v%s\n", AppName, AppVersion)
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

// initServices creates and wires all application services
func initServices(db *database.DB, cfg *config.Config, wsHub *ws.Hub, log *logger.Logger) *Services {
	// WordPress REST API client factory
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
		WPClientFactory: wpClientFactory,
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
