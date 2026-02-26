// Package main — log file setup and startup cleanup helpers.
package main

import (
	"io"
	"os"
	"path/filepath"

	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/logger"
)

// logPaths holds paths for on-disk log files.
type logPaths struct {
	errorsDir  string
	allLogPath string
	errLogPath string
}

// resolveLogPaths computes and ensures the errors directory and log file paths.
func resolveLogPaths(dbPath string, log *logger.Logger) logPaths {
	errorsDir := filepath.Join(filepath.Dir(dbPath), "errors")
	if err := os.MkdirAll(errorsDir, 0755); err != nil {
		log.Error("Failed to create errors dir", "path", errorsDir, "error", err)
	}
	middleware.ErrorLogDir = errorsDir

	return logPaths{
		errorsDir:  errorsDir,
		allLogPath: filepath.Join(errorsDir, "log.txt"),
		errLogPath: filepath.Join(errorsDir, "error.log.txt"),
	}
}

// clearStartupLogs removes log files if clearLogsOnStartup is configured.
func clearStartupLogs(paths logPaths, log *logger.Logger) {
	log.Info("Clearing logs on startup (clearLogsOnStartup=true)")
	removeIfExists(paths.allLogPath, "log.txt", log)
	removeIfExists(paths.errLogPath, "error.log.txt", log)
}

// removeIfExists removes a file, ignoring not-found errors.
func removeIfExists(path, label string, log *logger.Logger) {
	if err := os.Remove(path); err != nil {
		isRealError := !os.IsNotExist(err)
		if isRealError {
			log.Error("Failed to clear "+label, "error", err)
		}
	}
}

// clearStartupSessions removes the sessions directory if configured.
func clearStartupSessions(dbPath string, log *logger.Logger) {
	sessionsDir := filepath.Join(filepath.Dir(dbPath), "sessions")
	log.Info("Clearing sessions on startup (clearSessionsOnStartup=true)", "path", sessionsDir)

	if err := os.RemoveAll(sessionsDir); err != nil {
		isRealError := !os.IsNotExist(err)
		if isRealError {
			log.Error("Failed to clear sessions directory", "error", err)
		}
	}
	if err := os.MkdirAll(sessionsDir, 0755); err != nil {
		log.Error("Failed to recreate sessions directory", "error", err)
	}
}

// openLogFiles opens on-disk log files and returns a multi-writer for the logger output.
func openLogFiles(paths logPaths, log *logger.Logger) (io.Writer, func()) {
	allFile, err1 := os.OpenFile(paths.allLogPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0644)
	errFile, err2 := os.OpenFile(paths.errLogPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0644)

	if err1 != nil || err2 != nil {
		return handleLogFileErrors(allFile, errFile, paths, log)
	}

	return buildLogWriters(allFile, errFile, paths, log)
}

// handleLogFileErrors handles failures opening log files.
func handleLogFileErrors(allFile, errFile *os.File, paths logPaths, log *logger.Logger) (io.Writer, func()) {
	log.Error("Failed to open on-disk log files", "allLog", paths.allLogPath, "errorLog", paths.errLogPath)
	if allFile != nil {
		allFile.Close()
	}
	if errFile != nil {
		errFile.Close()
	}
	return os.Stdout, func() {}
}

// buildLogWriters builds the multi-writer for stdout + on-disk logging.
func buildLogWriters(allFile, errFile *os.File, paths logPaths, log *logger.Logger) (io.Writer, func()) {
	logOutput := io.MultiWriter(
		os.Stdout,
		stripAnsiWriter{w: allFile},
		errorOnlyWriter{w: stripAnsiWriter{w: errFile}},
	)
	log.Info("On-disk logs enabled", "log", paths.allLogPath, "errorLog", paths.errLogPath)

	cleanup := func() {
		allFile.Close()
		errFile.Close()
	}
	return logOutput, cleanup
}
