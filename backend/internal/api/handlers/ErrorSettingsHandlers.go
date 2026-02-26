// Package handlers provides error log and settings HTTP request handlers
package handlers

import (
	"archive/zip"
	"encoding/json"
	"io"
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
	"wp-plugin-publish/pkg/ziputil"
)

// --- Error/Log Handlers ---

// GetErrors returns application error logs
func GetErrors(w http.ResponseWriter, r *http.Request) {
	logType := r.URL.Query().Get("type")
	if logType == "" {
		logType = "errors"
	}

	var logPath string
	if logType == "all" {
		logPath = "data/errors/log.txt"
	} else {
		logPath = "data/errors/error.log.txt"
	}

	fi, statErr := pathutil.StatFile(logPath)
	if statErr != nil {
		respondSuccess(w, LogFileResponse{
			Content: "",
			Path:    logPath,
			Exists:  false,
			LogType: logType,
		})

		return
	}

	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E4001",
			"Failed to read log file: "+err.Error(),
		)

		return
	}

	respondSuccess(w, LogFileResponse{
		Content:    string(content),
		Path:       logPath,
		Exists:     true,
		LogType:    logType,
		Size:       fi.Info.Size(),
		ModifiedAt: fi.Info.ModTime().Format(time.RFC3339),
	})
}

// GetError returns a specific error by ID
func GetError(w http.ResponseWriter, r *http.Request) {
	respondError(
		w,
		wordpress.HttpStatusNotImplemented,
		"E9004",
		"Not implemented",
	)
}

// ClearErrors removes all error logs
func ClearErrors(w http.ResponseWriter, r *http.Request) {
	logPath := "data/errors/log.txt"
	errorPath := "data/errors/error.log.txt"

	cleared := []string{}

	if err := os.Truncate(logPath, 0); err == nil {
		cleared = append(cleared, "log.txt")
	}

	if err := os.Truncate(errorPath, 0); err == nil {
		cleared = append(cleared, "error.log.txt")
	}

	respondSuccess(w, ActionResponse{
		IsCleared: true,
		Message: "Log files cleared",
	})
}

// StreamErrorLogs streams the error log file content for real-time viewing
func StreamErrorLogs(w http.ResponseWriter, r *http.Request) {
	logType := r.URL.Query().Get("type")
	if logType == "" {
		logType = "all"
	}

	tailLines := 100

	if tailStr := r.URL.Query().Get("tail"); tailStr != "" {
		if n, err := strconv.Atoi(tailStr); err == nil && n > 0 && n <= 10000 {
			tailLines = n
		}
	}

	var logPath string
	if logType == "errors" {
		logPath = "data/errors/error.log.txt"
	} else {
		logPath = "data/errors/log.txt"
	}

	if pathutil.IsFileMissing(logPath) {
		respondSuccess(w, LogLinesResponse{
			Lines:   []string{},
			Path:    logPath,
			Exists:  false,
			LogType: logType,
		})

		return
	}

	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E4001",
			"Failed to read log file: "+err.Error(),
		)

		return
	}

	allLines := splitLines(string(content))
	var lines []string

	if len(allLines) > tailLines {
		lines = allLines[len(allLines)-tailLines:]
	} else {
		lines = allLines
	}

	fi, _ := pathutil.StatFile(logPath)

	var fileSize int64
	var modTime string
	if fi != nil {
		fileSize = fi.Info.Size()
		modTime = fi.Info.ModTime().Format(time.RFC3339)
	}

	respondSuccess(w, LogLinesResponse{
		Lines:      lines,
		TotalLines: len(allLines),
		Path:       logPath,
		Exists:     true,
		LogType:    logType,
		Size:       fileSize,
		ModifiedAt: modTime,
	})
}

// GetBackendErrorLog returns the content of error.log.txt
func GetBackendErrorLog(w http.ResponseWriter, r *http.Request) {
	readLogFile(w, "data/errors/error.log.txt", "error.log.txt")
}

// GetBackendFullLog returns the content of the full log.txt
func GetBackendFullLog(w http.ResponseWriter, r *http.Request) {
	readLogFile(w, "data/errors/log.txt", "log.txt")
}

// readLogFile is a helper to read and return log file contents
func readLogFile(w http.ResponseWriter, path string, filename string) {
	fi, statErr := pathutil.StatFile(path)
	if statErr != nil {
		if apperror.Is(statErr, apperror.ErrFSNotFound) {
			respondError(
				w,
				wordpress.HttpStatusNotFound,
				"E9001",
				"Log file not found: "+filename,
			)

			return
		}

		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9002",
			"Failed to read log file: "+statErr.Error(),
		)

		return
	}

	content, err := os.ReadFile(path)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusServerError,
			"E9002",
			"Failed to read log file: "+err.Error(),
		)

		return
	}

	respondSuccess(w, LogFileResponse{
		Content:    string(content),
		Filename:   filename,
		Size:       fi.Info.Size(),
		ModifiedAt: fi.Info.ModTime().Format(time.RFC3339),
	})
}

// DownloadErrorBundle creates and serves a ZIP bundle of error logs
func DownloadErrorBundle(w http.ResponseWriter, r *http.Request) {
	dataDir := "data/errors"
	report := extractReportFromBody(r)

	logFile := dataDir + "/log.txt"
	errorFile := dataDir + "/error.log.txt"

	logExists := fileExists(logFile)
	errorExists := fileExists(errorFile)

	isNoLogsFound := !logExists && !errorExists
	if isNoLogsFound {
		respondError(w, wordpress.HttpStatusNotFound, "E9001", "No error log files found")
		return
	}

	writeErrorBundleZip(w, logFile, errorFile, logExists, errorExists, report)
}

// extractReportFromBody reads the optional report field from a POST body.
func extractReportFromBody(r *http.Request) string {
	if r.Method != http.MethodPost {
		return ""
	}

	var payload struct {
		Report string `json:"report"` // external key (frontend request body)
	}
	bodyBytes, _ := io.ReadAll(io.LimitReader(r.Body, 2*1024*1024))
	if len(bodyBytes) > 0 {
		_ = json.Unmarshal(bodyBytes, &payload)
	}
	return payload.Report
}

// errorBundleFiles holds the file paths and existence flags for the bundle.
type errorBundleFiles struct {
	LogFile     string
	ErrorFile   string
	LogExists   bool
	ErrorExists bool
	Report      string
}

// writeErrorBundleZip writes the ZIP response with log files and manifest.
func writeErrorBundleZip(w http.ResponseWriter, logFile, errorFile string, logExists, errorExists bool, report string) {
	w.Header().Set("Content-Type", "application/zip")
	w.Header().Set("Content-Disposition", "attachment; filename=error-bundle-"+time.Now().Format("20060102-150405")+".zip")

	zipWriter := zip.NewWriter(w)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	addBundleLogFiles(zipWriter, logFile, errorFile, logExists, errorExists)
	addBundleReport(zipWriter, report)
	addBundleManifest(zipWriter, logExists, errorExists, report)
}

// addBundleLogFiles adds the log and error files to the ZIP.
func addBundleLogFiles(zipWriter *zip.Writer, logFile, errorFile string, logExists, errorExists bool) {
	if logExists {
		_ = addFileToZip(zipWriter, logFile, "log.txt")
	}
	if errorExists {
		_ = addFileToZip(zipWriter, errorFile, "error.log.txt")
	}
}

// addBundleReport adds the user report to the ZIP if present.
func addBundleReport(zipWriter *zip.Writer, report string) {
	if report == "" {
		return
	}
	reportWriter, err := zipWriter.Create("report.md")
	if err == nil {
		_, _ = io.WriteString(reportWriter, report)
	}
}

// addBundleManifest writes the manifest.json into the ZIP.
func addBundleManifest(zipWriter *zip.Writer, logExists, errorExists bool, report string) {
	manifest := struct {
		GeneratedAt string   `json:"generatedAt"` // external key (export manifest JSON file)
		Files       []string `json:"files"`       // external key
	}{
		GeneratedAt: time.Now().Format(time.RFC3339),
		Files:       []string{},
	}

	if logExists {
		manifest.Files = append(manifest.Files, "log.txt")
	}
	if errorExists {
		manifest.Files = append(manifest.Files, "error.log.txt")
	}
	if report != "" {
		manifest.Files = append(manifest.Files, "report.md")
	}

	manifestWriter, err := zipWriter.Create("manifest.json")
	if err == nil {
		json.NewEncoder(manifestWriter).Encode(manifest)
	}
}

// --- Utility Functions ---

func splitLines(content string) []string {
	rawLines := strings.Split(content, "\n")
	lines := make([]string, 0, len(rawLines))

	for _, line := range rawLines {
		if strings.TrimSpace(line) != "" {
			lines = append(lines, line)
		}
	}

	return lines
}

func fileExists(path string) bool {
	return pathutil.IsFileExists(path)
}

func addFileToZip(
	zipWriter *zip.Writer,
	srcPath string,
	destName string,
) error {
	file, err := os.Open(srcPath)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrFileOpen, "failed to open file for zip").WithPath(srcPath)
	}
	defer file.Close()

	writer, err := zipWriter.Create(destName)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrZipCreate, "failed to create zip entry").WithPath(destName)
	}

	_, err = io.Copy(writer, file)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrZipWrite, "failed to copy file into zip").WithPath(srcPath)
	}

	return nil
}

// --- Settings Handlers ---

// GetSettings returns application settings
func GetSettings(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, SettingsResponse{
		Watcher: WatcherSettings{
			IsPollingEnabled:       false,
			IsScanAfterGitPull:     true,
			DebounceMs:             500,
			DefaultExcludePatterns: []string{".git", "node_modules", ".DS_Store"},
		},
		Backup: BackupSettings{
			IsAutoBackupBeforePublish: true,
			RetentionDays:             30,
			MaxBackupsPerPlugin:       10,
			Location:                  "backups",
		},
		Logging: LoggingSettings{
			Level:         loglevel.Info.String(),
			RetentionDays: 7,
			IsDebugMode:   false,
		},
		Appearance: AppearanceSettings{
			Theme:         "system",
			IsCompactMode: false,
		},
		Server: ServerSettings{
			Port:               8080,
			WSReconnectDelayMs: 3000,
		},
	})
}

// UpdateSettings updates application settings
func UpdateSettings(w http.ResponseWriter, r *http.Request) {
	respondError(
		w,
		wordpress.HttpStatusNotImplemented,
		"E9004",
		"Not implemented",
	)
}
