// WP Plugin Publish - Application Entry Point
// Manages WordPress plugin development workflows with local file watching and remote sync
package main

import (
	"context"
	"fmt"
	"io"
	"os"
	"os/signal"
	"path/filepath"
	"regexp"
	"strings"
	"syscall"
	"time"

	"wp-plugin-publish/internal/api"
	"wp-plugin-publish/internal/api/handlers"
	"wp-plugin-publish/internal/config"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/errorhistory"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
	"wp-plugin-publish/internal/version"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
)

var ansiRegexp = regexp.MustCompile(`\x1b\[[0-9;]*m`)

type stripAnsiWriter struct{ w io.Writer }

func (s stripAnsiWriter) Write(p []byte) (int, error) {
	clean := ansiRegexp.ReplaceAll(p, nil)
	_, err := s.w.Write(clean)
	// Always report the original length so the upstream logger doesn't think it's a short write.
	return len(p), err
}

type errorOnlyWriter struct{ w io.Writer }

func (e errorOnlyWriter) Write(p []byte) (int, error) {
	clean := string(ansiRegexp.ReplaceAll(p, nil))
	if strings.Contains(clean, "(ERROR ") || strings.Contains(clean, "(FATAL ") {
		_, err := e.w.Write([]byte(clean))
		return len(p), err
	}
	return len(p), nil
}

// Services holds all application services
type Services struct {
	Site         *site.Service
	Plugin       *plugin.Service
	Watcher      *watcher.Service
	Sync         sync.Service
	Publish      *publish.Service
	Backup       *backup.Service
	Session      *session.Service
	ErrorHistory *errorhistory.Service
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

	// Ensure on-disk log files exist for troubleshooting bundles.
	// Paths:
	//   data/errors/log.txt       (all backend logs)
	//   data/errors/error.log.txt (errors + fatals only)
	logOutput := io.Writer(os.Stdout)
	errorsDir := filepath.Join(filepath.Dir(cfg.DatabasePath), "errors")
	if err := os.MkdirAll(errorsDir, 0755); err != nil {
		bootstrapLog.Error("Failed to create errors dir", "path", errorsDir, "error", err)
	} else {
		allLogPath := filepath.Join(errorsDir, "log.txt")
		errLogPath := filepath.Join(errorsDir, "error.log.txt")

		allFile, err1 := os.OpenFile(allLogPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0644)
		errFile, err2 := os.OpenFile(errLogPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0644)
		if err1 != nil || err2 != nil {
			bootstrapLog.Error("Failed to open on-disk log files", "allLog", allLogPath, "errorLog", errLogPath, "err1", err1, "err2", err2)
			if allFile != nil {
				allFile.Close()
			}
			if errFile != nil {
				errFile.Close()
			}
		} else {
			defer allFile.Close()
			defer errFile.Close()
			logOutput = io.MultiWriter(
				os.Stdout,
				stripAnsiWriter{w: allFile},
				errorOnlyWriter{w: stripAnsiWriter{w: errFile}},
			)
			bootstrapLog.Info("On-disk logs enabled", "log", allLogPath, "errorLog", errLogPath)
		}
	}

	// Initialize the real logger with configured timeFormat (single source of truth)
	log := logger.New(logger.Config{
		Level:      parseLogLevel(cfg.Logging.Level),
		Output:     logOutput,
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

	// Run migrations (with logging)
	if err := database.Migrate(db, log); err != nil {
		log.Fatal("Failed to run migrations", "error", err)
	}

	// Seed from config if needed (with logging)
	if err := config.SeedIfNeeded(db, cfg, log); err != nil {
		log.Fatal("Failed to seed database", "error", err)
	}

	// Initialize WebSocket hub
	wsHub := ws.NewHub()
	go wsHub.Run()

	// Set app version for WebSocket log formatting
	ws.SetAppVersion(versionInfo.Version)

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
		services.Session,
		services.ErrorHistory,
	)
	server := api.NewServer(api.ServerConfig{
		Port:      cfg.Server.Port,
		StaticDir: cfg.Server.StaticDir,
		Services: &api.ServiceRegistry{
			Site:         serviceRegistry.SiteService,
			Plugin:       serviceRegistry.PluginService,
			Sync:         serviceRegistry.SyncService,
			Git:          serviceRegistry.GitService,
			Watcher:      serviceRegistry.WatcherService,
			Publish:      serviceRegistry.PublishService,
			Backup:       serviceRegistry.BackupService,
			Session:      serviceRegistry.SessionService,
			ErrorHistory: serviceRegistry.ErrorHistoryService,
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
	fmt.Printf("  Local:     http://localhost:%d\n", cfg.Server.Port)
	fmt.Printf("  WebSocket: ws://localhost:%d/ws\n\n", cfg.Server.Port)

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

	// Initialize session service for operation logging (must be before siteService)
	sessionService, err := session.New(session.Config{
		DataDir:       filepath.Dir(cfg.DatabasePath),
		Logger:        log,
		RetentionDays: 7,
	})
	if err != nil {
		log.Error("Failed to initialize session service", "error", err)
	}

	// Initialize services with dependencies
	siteService := site.New(site.Config{
		DB:              db,
		Logger:          log,
		EncryptionKey:   cfg.Security.EncryptionKey,
		WPClientFactory: wpClientFactoryWithProgress,
		WSHub:           wsHub,
		SessionService:  sessionService,
		CacheEnabled:    cfg.RemotePlugins.CacheEnabled,
		CacheTTLMinutes: cfg.RemotePlugins.CacheTTLMinutes,
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
		DB:                    db,
		Logger:                log,
		PluginService:         pluginService,
		BackupService:         backupService,
		SyncService:           syncService,
		SitePasswordDecryptor: siteService, // Site service implements SitePasswordDecryptor
		WPClientFactory:       wpClientFactory,
		TempDir:               cfg.TempDir,
		WSHub:                 wsHub,
		SessionService:        sessionService,
	})

	watcherService := watcher.New(watcher.Config{
		DB:            db,
		Logger:        log,
		PluginService: pluginService,
		WSHub:         wsHub,
	})

	// Initialize error history service for persistent error storage
	errorHistoryService := errorhistory.New(errorhistory.Config{
		DB:     db,
		Logger: log,
	})

	return &Services{
		Site:         siteService,
		Plugin:       pluginService,
		Watcher:      watcherService,
		Sync:         syncService,
		Publish:      publishService,
		Backup:       backupService,
		Session:      sessionService,
		ErrorHistory: errorHistoryService,
	}
}
