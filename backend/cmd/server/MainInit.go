// Package main — service initialization helpers and server startup
package main

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"runtime"
	"strings"
	"syscall"
	"time"

	"wp-plugin-publish/internal/api"
	"wp-plugin-publish/internal/api/handlers"
	"wp-plugin-publish/internal/config"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/e2e"
	"wp-plugin-publish/internal/services/request_session"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/internal/version"
	"wp-plugin-publish/pkg/portutil"
)

// initPluginCaches initializes watcher caches for registered plugins.
func initPluginCaches(services *Services, log *logger.Logger) {
	ctx := context.Background()
	pluginResult := services.Plugin.List(ctx)
	isListFailed := !pluginResult.IsSafe()

	if isListFailed {
		return
	}
	for _, p := range pluginResult.Items() {
		if err := services.Watcher.InitializeCache(ctx, p.ID); err != nil {
			log.Error("Failed to initialize watcher cache", "pluginId", p.ID, "error", err)
		}
	}
}

// initRequestSessionStore creates the request session store if enabled.
func initRequestSessionStore(cfg *config.Config, log *logger.Logger) *requestsession.Store {
	if !cfg.Logging.SessionLoggingEnabled {
		return nil
	}
	store, err := requestsession.New(requestsession.Config{
		DataDir:       filepath.Dir(cfg.DatabasePath),
		Logger:        log,
		RetentionDays: 1,
	})
	if err != nil {
		log.Error("Failed to initialize request session store", "error", err)
		return nil
	}
	log.Info("Request session logging enabled")
	return store
}

// initE2EService initializes the E2E test service if enabled.
func initE2EService(cfg *config.Config, db *database.DB, wsHub *ws.Hub, log *logger.Logger) {
	if !cfg.E2E.Enabled {
		return
	}
	e2eSvc := e2e.New(e2e.Config{
		DB:               db.DB,
		Broadcast:        func(event string, data any) { ws.Broadcast(wsHub, event, data) },
		BaseURL:          fmt.Sprintf("http://localhost:%d", cfg.Server.Port),
		TestPluginPath:   cfg.E2E.TestPluginPath,
		TestSiteURL:      cfg.E2E.TestSiteURL,
		TestSiteUsername:  cfg.E2E.TestSiteUsername,
		TestSitePassword: cfg.E2E.TestSitePassword,
	})
	handlers.E2EService = &E2EServiceAdapter{e2eSvc}
	log.Info("E2E test service enabled")
}

// buildServer creates the HTTP server with all service handlers wired.
func buildServer(cfg *config.Config, services *Services, wsHub *ws.Hub, log *logger.Logger, reqStore *requestsession.Store) *api.Server {
	registry := handlers.NewServiceRegistry(
		services.Site, services.Plugin, services.Sync, nil,
		services.Watcher, services.Publish, services.Backup,
		services.Session, services.ErrorHistory, services.PublishHistory, services.SiteHealth,
	)
	serverCfg := buildServerConfig(cfg, registry, wsHub, log, reqStore)

	return api.NewServer(serverCfg)
}

// buildServerConfig constructs the api.ServerConfig from the registry and dependencies.
func buildServerConfig(cfg *config.Config, registry *handlers.ServiceRegistry, wsHub *ws.Hub, log *logger.Logger, reqStore *requestsession.Store) api.ServerConfig {
	return api.ServerConfig{
		Port:      cfg.Server.Port,
		StaticDir: cfg.Server.StaticDir,
		Services: &api.ServiceRegistry{
			Site: registry.SiteService, Plugin: registry.PluginService,
			Sync: registry.SyncService, Git: registry.GitService,
			Watcher: registry.WatcherService, Publish: registry.PublishService,
			Backup: registry.BackupService, Session: registry.SessionService,
			ErrorHistory: registry.ErrorHistoryService, PublishHistory: registry.PublishHistoryService,
			SiteHealth: registry.SiteHealthService,
		},
		WSHub: wsHub, Logger: log,
		RequestSessionStore:   reqStore,
		SessionLoggingEnabled: cfg.Logging.SessionLoggingEnabled,
	}
}

// launchServer starts the server and opens the browser.
func launchServer(server *api.Server, cfg *config.Config, log *logger.Logger, vi *version.Info) {
	if err := portutil.EnsurePortFree(cfg.Server.Port); err != nil {
		log.Warn("Port conflict resolution", "port", cfg.Server.Port, "result", err.Error())
	}
	go func() {
		if err := server.Start(); err != nil && err.Error() != "http: Server closed" {
			log.Fatal("Server failed", "error", err)
		}
	}()
	log.Info("Server started", "port", cfg.Server.Port)
	printStartupBanner(cfg.Server.Port, vi)
	go openBrowser(cfg.Server.Port, log)
}

// printStartupBanner prints the server URL info.
func printStartupBanner(port int, vi *version.Info) {
	localURL := fmt.Sprintf("http://localhost:%d", port)
	fmt.Printf("\n  %s\n", vi.String())
	fmt.Printf("  Local:     %s\n", localURL)
	fmt.Printf("  WebSocket: ws://localhost:%d/ws\n\n", port)
}

// openBrowser attempts to open the default browser.
func openBrowser(port int, log *logger.Logger) {
	localURL := fmt.Sprintf("http://localhost:%d", port)
	var cmd *exec.Cmd
	switch runtime.GOOS {
	case "windows":
		cmd = exec.Command("cmd", "/c", "start", localURL)
	case "darwin":
		cmd = exec.Command("open", localURL)
	default:
		cmd = exec.Command("xdg-open", localURL)
	}
	if err := cmd.Run(); err != nil {
		log.Warn("Could not open browser automatically", "error", err)
	}
}

// awaitShutdown waits for shutdown signal and gracefully stops.
func awaitShutdown(server *api.Server, log *logger.Logger) {
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	log.Info("Shutting down...")
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

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
