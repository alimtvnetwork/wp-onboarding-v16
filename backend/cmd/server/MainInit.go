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
		cacheErr := services.Watcher.InitializeCache(ctx, p.ID)

		if cacheErr != nil {
			log.Error("Failed to initialize watcher cache", "pluginId", p.ID, "error", cacheErr)
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

// e2eInput bundles dependencies for initE2EService.
type e2eInput struct {
	Cfg   *config.Config
	DB    *database.DB
	WSHub *ws.Hub
	Log   *logger.Logger
}

// initE2EService initializes the E2E test service if enabled.
func initE2EService(input e2eInput) {
	if !input.Cfg.E2E.Enabled {
		return
	}
	e2eSvc := e2e.New(e2e.Config{
		DB:               input.DB.DB,
		Broadcast:        func(event string, data any) { ws.Broadcast(input.WSHub, event, data) },
		BaseUrl:          fmt.Sprintf("http://localhost:%d", input.Cfg.Server.Port),
		TestPluginPath:   input.Cfg.E2E.TestPluginPath,
		TestSiteUrl:      input.Cfg.E2E.TestSiteUrl,
		TestSiteUsername:  input.Cfg.E2E.TestSiteUsername,
		TestSitePassword: input.Cfg.E2E.TestSitePassword,
	})
	handlers.E2EService = &E2EServiceAdapter{e2eSvc}
	input.Log.Info("E2E test service enabled")
}

// serverBuildInput bundles dependencies for buildServer and buildServerConfig.
type serverBuildInput struct {
	Cfg      *config.Config
	Services *Services
	WSHub    *ws.Hub
	Log      *logger.Logger
	ReqStore *requestsession.Store
}

// buildServer creates the HTTP server with all service handlers wired.
func buildServer(input serverBuildInput) *api.Server {
	registry := handlers.NewServiceRegistry(
		input.Services.Site, input.Services.Plugin, input.Services.Sync, nil,
		input.Services.Watcher, input.Services.Publish, input.Services.Backup,
		input.Services.Session, input.Services.ErrorHistory, input.Services.PublishHistory, input.Services.SiteHealth,
	)
	serverCfg := buildServerConfig(input, registry)

	return api.NewServer(serverCfg)
}

// buildServerConfig constructs the api.ServerConfig from the registry and dependencies.
func buildServerConfig(input serverBuildInput, registry *handlers.ServiceRegistry) api.ServerConfig {

	return api.ServerConfig{
		Port:      input.Cfg.Server.Port,
		StaticDir: input.Cfg.Server.StaticDir,
		Services: &api.ServiceRegistry{
			Site: registry.SiteService, Plugin: registry.PluginService,
			Sync: registry.SyncService, Git: registry.GitService,
			Watcher: registry.WatcherService, Publish: registry.PublishService,
			Backup: registry.BackupService, Session: registry.SessionService,
			ErrorHistory: registry.ErrorHistoryService, PublishHistory: registry.PublishHistoryService,
			SiteHealth: registry.SiteHealthService,
		},
		WSHub: input.WSHub, Logger: input.Log,
		RequestSessionStore:   input.ReqStore,
		SessionLoggingEnabled: input.Cfg.Logging.SessionLoggingEnabled,
	}
}

// launchServer starts the server and opens the browser.
func launchServer(server *api.Server, cfg *config.Config, log *logger.Logger, vi *version.Info) {
	portErr := portutil.EnsurePortFree(cfg.Server.Port)

	if portErr != nil {
		log.Warn("Port conflict resolution", "port", cfg.Server.Port, "result", portErr.Error())
	}

	go func() {
		startErr := server.Start()
		isRealError := startErr != nil && startErr.Error() != "http: Server closed"

		if isRealError {
			log.Fatal("Server failed", "error", startErr)
		}
	}()
	log.Info("Server started", "port", cfg.Server.Port)
	printStartupBanner(cfg.Server.Port, vi)
	go openBrowser(cfg.Server.Port, log)
}

// printStartupBanner prints the server URL info.
func printStartupBanner(port int, vi *version.Info) {
	localUrl := fmt.Sprintf("http://localhost:%d", port)
	fmt.Printf("\n  %s\n", vi.String())
	fmt.Printf("  Local:     %s\n", localUrl)
	fmt.Printf("  WebSocket: ws://localhost:%d/ws\n\n", port)
}

// openBrowser attempts to open the default browser.
func openBrowser(port int, log *logger.Logger) {
	localUrl := fmt.Sprintf("http://localhost:%d", port)
	var cmd *exec.Cmd
	switch runtime.GOOS {
	case "windows":
		cmd = exec.Command("cmd", "/c", "start", localUrl)
	case "darwin":
		cmd = exec.Command("open", localUrl)
	default:
		cmd = exec.Command("xdg-open", localUrl)
	}
	runErr := cmd.Run()

	if runErr != nil {
		log.Warn("Could not open browser automatically", "error", runErr)
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

	shutdownErr := server.Shutdown(ctx)

	if shutdownErr != nil {
		log.Error("Server shutdown error", "error", shutdownErr)
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
