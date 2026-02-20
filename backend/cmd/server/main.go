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
	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/config"
	"wp-plugin-publish/internal/database"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/services/backup"
	"wp-plugin-publish/internal/services/e2e"
	"wp-plugin-publish/internal/services/errorhistory"
	"wp-plugin-publish/internal/services/plugin"
	"wp-plugin-publish/internal/services/publish"
	"wp-plugin-publish/internal/services/publishhistory"
	"wp-plugin-publish/internal/services/requestsession"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/services/site"
	"wp-plugin-publish/internal/services/sitehealth"
	"wp-plugin-publish/internal/services/sync"
	"wp-plugin-publish/internal/services/watcher"
	"wp-plugin-publish/internal/version"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/internal/envelope"
	"wp-plugin-publish/pkg/portutil"
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

	// Ensure errors directory exists and set up paths
	errorsDir := filepath.Join(filepath.Dir(cfg.DatabasePath), "errors")
	if err := os.MkdirAll(errorsDir, 0755); err != nil {
		bootstrapLog.Error("Failed to create errors dir", "path", errorsDir, "error", err)
	}

	// Enable middleware-level error logging to error.log.txt
	middleware.ErrorLogDir = errorsDir

	allLogPath := filepath.Join(errorsDir, "log.txt")
	errLogPath := filepath.Join(errorsDir, "error.log.txt")

	// Clear logs on startup if configured
	if cfg.Logging.ClearLogsOnStartup {
		bootstrapLog.Info("Clearing logs on startup (clearLogsOnStartup=true)")
		if err := os.Remove(allLogPath); err != nil && !os.IsNotExist(err) {
			bootstrapLog.Error("Failed to clear log.txt", "error", err)
		}
		if err := os.Remove(errLogPath); err != nil && !os.IsNotExist(err) {
			bootstrapLog.Error("Failed to clear error.log.txt", "error", err)
		}
	}

	// Clear sessions on startup if configured
	if cfg.Logging.ClearSessionsOnStartup {
		sessionsDir := filepath.Join(filepath.Dir(cfg.DatabasePath), "sessions")
		bootstrapLog.Info("Clearing sessions on startup (clearSessionsOnStartup=true)", "path", sessionsDir)
		if err := os.RemoveAll(sessionsDir); err != nil && !os.IsNotExist(err) {
			bootstrapLog.Error("Failed to clear sessions directory", "error", err)
		}
		// Recreate the empty directory
		if err := os.MkdirAll(sessionsDir, 0755); err != nil {
			bootstrapLog.Error("Failed to recreate sessions directory", "error", err)
		}
	}

	// Set up on-disk log files
	logOutput := io.Writer(os.Stdout)
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

	// Initialize the real logger with configured timeFormat (single source of truth)
	log := logger.New(logger.Config{
		Level:      parseLogLevel(cfg.Logging.Level),
		Output:     logOutput,
		TimeFormat: cfg.Logging.TimeFormat,
		AppName:    versionInfo.AppName,
		AppVersion: versionInfo.Version,
	})

	// Initialize envelope debug config from seedable config
	envelope.SetDebugConfig(envelope.DebugConfig{
		IncludeErrors:       cfg.ResponseDebug.IncludeInternalErrors,
		IncludeStackTrace:   cfg.ResponseDebug.IncludeStackTrace,
		IncludeMethodsStack: cfg.ResponseDebug.IncludeMethodsStack,
		MaxStackFrames:      cfg.ResponseDebug.MaxStackFrames,
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
	pluginResult := services.Plugin.List(ctx)
	if pluginResult.IsSafe() {
		for _, p := range pluginResult.Items() {
			if err := services.Watcher.InitializeCache(ctx, p.ID); err != nil {
				log.Error("Failed to initialize watcher cache", "pluginId", p.ID, "error", err)
			}
		}
	}

	// Initialize request session store for per-request logging
	var reqSessionStore *requestsession.Store
	if cfg.Logging.SessionLoggingEnabled {
		var err error
		reqSessionStore, err = requestsession.New(requestsession.Config{
			DataDir:       filepath.Dir(cfg.DatabasePath),
			Logger:        log,
			RetentionDays: 1, // Keep request sessions for 1 day (high volume)
		})
		if err != nil {
			log.Error("Failed to initialize request session store", "error", err)
		} else {
			log.Info("Request session logging enabled")
		}
	}

	// Initialize E2E test service if enabled
	if cfg.E2E.Enabled {
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
		services.PublishHistory,
		services.SiteHealth,
	)
	server := api.NewServer(api.ServerConfig{
		Port:      cfg.Server.Port,
		StaticDir: cfg.Server.StaticDir,
		Services: &api.ServiceRegistry{
			Site:           serviceRegistry.SiteService,
			Plugin:         serviceRegistry.PluginService,
			Sync:           serviceRegistry.SyncService,
			Git:            serviceRegistry.GitService,
			Watcher:        serviceRegistry.WatcherService,
			Publish:        serviceRegistry.PublishService,
			Backup:         serviceRegistry.BackupService,
			Session:        serviceRegistry.SessionService,
			ErrorHistory:   serviceRegistry.ErrorHistoryService,
			PublishHistory: serviceRegistry.PublishHistoryService,
			SiteHealth:     serviceRegistry.SiteHealthService,
		},
		WSHub:                  wsHub,
		Logger:                 log,
		RequestSessionStore:    reqSessionStore,
		SessionLoggingEnabled:  cfg.Logging.SessionLoggingEnabled,
	})
	// Auto-resolve port conflict
	if err := portutil.EnsurePortFree(cfg.Server.Port); err != nil {
		log.Warn("Port conflict resolution", "port", cfg.Server.Port, "result", err.Error())
	}

	go func() {
		if err := server.Start(); err != nil && err.Error() != "http: Server closed" {
			log.Fatal("Server failed", "error", err)
		}
	}()

	log.Info("Server started", "port", cfg.Server.Port)
	localURL := fmt.Sprintf("http://localhost:%d", cfg.Server.Port)
	fmt.Printf("\n  %s\n", versionInfo.String())
	fmt.Printf("  Local:     %s\n", localURL)
	fmt.Printf("  WebSocket: ws://localhost:%d/ws\n\n", cfg.Server.Port)

	// Auto-open browser
	go func() {
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
	}()

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
	wpClientFactoryWithProgress := func(siteURL, username, password string, onProgress func(step, status, message string, details wordpress.ProgressDetails)) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseURL:         siteURL,
			Username:        username,
			Password:        password,
			Timeout:         time.Duration(cfg.WordPress.TimeoutSeconds) * time.Second,
			StackTraceDepth: cfg.Logging.StackTraceDepth,
			OnProgress:      onProgress,
		})
	}

	// Simple client factory for services that don't need progress callbacks
	wpClientFactory := func(siteURL, username, password string) *wordpress.Client {
		return wordpress.NewClient(wordpress.ClientConfig{
			BaseURL:         siteURL,
			Username:        username,
			Password:        password,
			Timeout:         time.Duration(cfg.WordPress.TimeoutSeconds) * time.Second,
			StackTraceDepth: cfg.Logging.StackTraceDepth,
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
		DB:                    db,
		Logger:                log,
		PluginService:         pluginService,
		SitePasswordDecryptor: siteService,
		WPClientFactory:       wpClientFactory,
		WSHub:                 wsHub,
	})

	// Initialize publish history service
	publishHistoryService := publishhistory.New(publishhistory.Config{
		DB:     db,
		Logger: log,
	})

	// Initialize site health service
	siteHealthService := sitehealth.New(sitehealth.Config{
		DB:     db,
		Logger: log,
	})

	publishService := publish.New(publish.Config{
		DB:                    db,
		Logger:                log,
		PluginService:         pluginService,
		BackupService:         backupService,
		SyncService:           syncService,
		SitePasswordDecryptor: siteService,
		WPClientFactory:       wpClientFactory,
		TempDir:               cfg.TempDir,
		WSHub:                 wsHub,
		SessionService:        sessionService,
		HistoryService:        publishHistoryService,
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
		Site:           siteService,
		Plugin:         pluginService,
		Watcher:        watcherService,
		Sync:           syncService,
		Publish:        publishService,
		Backup:         backupService,
		Session:        sessionService,
		ErrorHistory:   errorHistoryService,
		PublishHistory: publishHistoryService,
		SiteHealth:     siteHealthService,
	}
}

// E2EServiceAdapter wraps e2e.Service to implement handlers.E2EServiceInterface
type E2EServiceAdapter struct {
	svc e2e.Service
}

func (a *E2EServiceAdapter) ListSuites(ctx context.Context) ([]e2e.TestSuite, error) {
	return a.svc.ListSuites(ctx)
}

func (a *E2EServiceAdapter) GetCases(ctx context.Context, suiteID string) ([]e2e.TestCase, error) {
	return a.svc.GetCases(ctx, suiteID)
}

func (a *E2EServiceAdapter) StartRun(ctx context.Context, opts e2e.RunOptions) (*e2e.TestRun, error) {
	return a.svc.StartRun(ctx, opts)
}

func (a *E2EServiceAdapter) AbortRun(ctx context.Context, runID string) error {
	return a.svc.AbortRun(ctx, runID)
}

func (a *E2EServiceAdapter) ListRuns(ctx context.Context, limit int) ([]e2e.TestRun, error) {
	return a.svc.ListRuns(ctx, limit)
}

func (a *E2EServiceAdapter) GetRun(ctx context.Context, runID string) (*e2e.RunSummary, error) {
	return a.svc.GetRun(ctx, runID)
}

func (a *E2EServiceAdapter) DeleteRun(ctx context.Context, runID string) error {
	return a.svc.DeleteRun(ctx, runID)
}
