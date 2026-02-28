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

	server := bootstrapServer(cfg, log, versionInfo)
	launchServer(server, cfg, log, versionInfo)
	awaitShutdown(server, log)
}

// bootstrapServer initializes database, services, and builds the HTTP server.
func bootstrapServer(cfg *config.Config, log *logger.Logger, versionInfo *version.Info) *api.Server {
	db := initDatabase(cfg, log)

	wsHub := initWebSocket(versionInfo)
	services := initServices(InitServicesInput{DB: db, Cfg: cfg, WSHub: wsHub, Log: log})
	initPluginCaches(services, log)

	reqStore := initRequestSessionStore(cfg, log)
	initE2EService(cfg, db, wsHub, log)

	return buildServer(cfg, services, wsHub, log, reqStore)
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
	migrateErr := database.Migrate(db, log)

	if migrateErr != nil {
		log.Fatal("Failed to run migrations", "error", migrateErr)
	}

	seedErr := config.SeedIfNeeded(db, cfg, log)

	if seedErr != nil {
		log.Fatal("Failed to seed database", "error", seedErr)
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
