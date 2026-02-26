// WP Plugin Publish - Application Entry Point
// Manages WordPress plugin development workflows with local file watching and remote sync
package main

import (
	"context"
	"fmt"
	"io"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"regexp"
	"runtime"
	"strings"
	"syscall"
	"time"

	"wp-plugin-publish/internal/api"
	"wp-plugin-publish/internal/api/handlers"
	"wp-plugin-publish/internal/config"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/envelope"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/e2e"
	"wp-plugin-publish/internal/services/request_session"
	"wp-plugin-publish/internal/version"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/portutil"
)

var ansiRegexp = regexp.MustCompile(`\x1b\[[0-9;]*m`)

type stripAnsiWriter struct{ w io.Writer }

func (s stripAnsiWriter) Write(p []byte) (int, error) {
	clean := ansiRegexp.ReplaceAll(p, nil)
	_, err := s.w.Write(clean)
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

func main() {
	bootstrapLog := logger.New(logger.Config{Level: logger.LevelInfo})

	cfg := loadConfig(bootstrapLog)
	versionInfo, _ := version.Load(cfg.Server.StaticDir)

	paths := resolveLogPaths(cfg.DatabasePath, bootstrapLog)
	applyStartupCleanup(cfg, paths, bootstrapLog)

	logOutput, cleanupLogs := openLogFiles(paths, bootstrapLog)
	defer cleanupLogs()

	log := initLogger(cfg, versionInfo, logOutput)
	initEnvelopeDebug(cfg)
	log.Info("Starting application", "version", versionInfo.String())

	db := initDatabase(cfg, log)
	defer db.Close()

	wsHub := initWebSocket(versionInfo)
	services := initServices(InitServicesInput{DB: db, Cfg: cfg, WSHub: wsHub, Log: log})
	initPluginCaches(services, log)

	reqStore := initRequestSessionStore(cfg, log)
	initE2EService(cfg, db, wsHub, log)

	server := buildServer(cfg, services, wsHub, log, reqStore)
	launchServer(server, cfg, log, versionInfo)
	awaitShutdown(server, log)
}

// loadConfig loads the application configuration.
func loadConfig(log *logger.Logger) *config.Config {
	cfg, err := config.Load("config.json")
	if err != nil {
		log.Fatal("Failed to load config", "error", err)
	}
	return cfg
}

// applyStartupCleanup clears logs and sessions if configured.
func applyStartupCleanup(cfg *config.Config, paths logPaths, log *logger.Logger) {
	if cfg.Logging.ClearLogsOnStartup {
		clearStartupLogs(paths, log)
	}
	if cfg.Logging.ClearSessionsOnStartup {
		clearStartupSessions(cfg.DatabasePath, log)
	}
}

// initLogger creates the configured logger.
func initLogger(cfg *config.Config, vi *version.Info, output io.Writer) *logger.Logger {
	return logger.New(logger.Config{
		Level:      parseLogLevel(cfg.Logging.Level),
		Output:     output,
		TimeFormat: cfg.Logging.TimeFormat,
		AppName:    vi.AppName,
		AppVersion: vi.Version,
	})
}

// initEnvelopeDebug sets the global envelope debug config.
func initEnvelopeDebug(cfg *config.Config) {
	envelope.SetDebugConfig(envelope.DebugConfig{
		IncludeErrors:       cfg.ResponseDebug.IncludeInternalErrors,
		IncludeStackTrace:   cfg.ResponseDebug.IncludeStackTrace,
		IncludeMethodsStack: cfg.ResponseDebug.IncludeMethodsStack,
		MaxStackFrames:      cfg.ResponseDebug.MaxStackFrames,
	})
}

// initDatabase opens and migrates the database.
func initDatabase(cfg *config.Config, log *logger.Logger) *database.DB {
	db, err := database.New(cfg.DatabasePath)
	if err != nil {
		log.Fatal("Failed to connect to database", "error", err)
	}
	if err := database.Migrate(db, log); err != nil {
		log.Fatal("Failed to run migrations", "error", err)
	}
	if err := config.SeedIfNeeded(db, cfg, log); err != nil {
		log.Fatal("Failed to seed database", "error", err)
	}
	return db
}

// initWebSocket initializes the WebSocket hub.
func initWebSocket(vi *version.Info) *ws.Hub {
	wsHub := ws.NewHub()
	go wsHub.Run()
	ws.SetAppVersion(vi.Version)
	return wsHub
}

// initPluginCaches initializes watcher caches for registered plugins.
func initPluginCaches(services *Services, log *logger.Logger) {
	ctx := context.Background()
	pluginResult := services.Plugin.List(ctx)
	if !pluginResult.IsSafe() {
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
	return api.NewServer(api.ServerConfig{
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
	})
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
