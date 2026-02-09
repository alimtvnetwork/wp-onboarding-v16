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

	info, err := os.Stat(logPath)
	if os.IsNotExist(err) {
		respondSuccess(w, map[string]interface{}{
			"content": "", "path": logPath, "exists": false, "logType": logType,
		})
		return
	}

	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4001", "Failed to read log file: "+err.Error())
		return
	}

	respondSuccess(w, map[string]interface{}{
		"content":    string(content),
		"path":       logPath,
		"exists":     true,
		"logType":    logType,
		"size":       info.Size(),
		"modifiedAt": info.ModTime().Format(time.RFC3339),
	})
}

// GetError returns a specific error by ID
func GetError(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
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

	respondSuccess(w, map[string]interface{}{
		"cleared": cleared,
		"message": "Log files cleared",
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

	if _, err := os.Stat(logPath); os.IsNotExist(err) {
		respondSuccess(w, map[string]interface{}{
			"lines": []string{}, "path": logPath, "exists": false, "logType": logType,
		})
		return
	}

	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E4001", "Failed to read log file: "+err.Error())
		return
	}

	allLines := splitLines(string(content))
	var lines []string
	if len(allLines) > tailLines {
		lines = allLines[len(allLines)-tailLines:]
	} else {
		lines = allLines
	}

	info, _ := os.Stat(logPath)

	respondSuccess(w, map[string]interface{}{
		"lines":      lines,
		"totalLines": len(allLines),
		"path":       logPath,
		"exists":     true,
		"logType":    logType,
		"size":       info.Size(),
		"modifiedAt": info.ModTime().Format(time.RFC3339),
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
	info, err := os.Stat(path)
	if err != nil {
		if os.IsNotExist(err) {
			respondError(w, http.StatusNotFound, "E9001", "Log file not found: "+filename)
			return
		}
		respondError(w, http.StatusInternalServerError, "E9002", "Failed to read log file: "+err.Error())
		return
	}

	content, err := os.ReadFile(path)
	if err != nil {
		respondError(w, http.StatusInternalServerError, "E9002", "Failed to read log file: "+err.Error())
		return
	}

	respondSuccess(w, map[string]interface{}{
		"content":      string(content),
		"filename":     filename,
		"size":         info.Size(),
		"lastModified": info.ModTime().Format(time.RFC3339),
	})
}

// DownloadErrorBundle creates and serves a ZIP bundle of error logs
func DownloadErrorBundle(w http.ResponseWriter, r *http.Request) {
	dataDir := "data/errors"

	report := ""
	if r.Method == http.MethodPost {
		var payload struct {
			Report string `json:"report"`
		}
		bodyBytes, _ := io.ReadAll(io.LimitReader(r.Body, 2*1024*1024))
		if len(bodyBytes) > 0 {
			_ = json.Unmarshal(bodyBytes, &payload)
			report = payload.Report
		}
	}

	logFile := dataDir + "/log.txt"
	errorFile := dataDir + "/error.log.txt"

	logExists := fileExists(logFile)
	errorExists := fileExists(errorFile)

	if !logExists && !errorExists {
		respondError(w, http.StatusNotFound, "E9001", "No error log files found")
		return
	}

	w.Header().Set("Content-Type", "application/zip")
	w.Header().Set("Content-Disposition", "attachment; filename=error-bundle-"+time.Now().Format("20060102-150405")+".zip")

	zipWriter := zip.NewWriter(w)
	ziputil.RegisterBestCompression(zipWriter)
	defer zipWriter.Close()

	if logExists {
		if err := addFileToZip(zipWriter, logFile, "log.txt"); err != nil {
			return
		}
	}
	if errorExists {
		if err := addFileToZip(zipWriter, errorFile, "error.log.txt"); err != nil {
			return
		}
	}
	if report != "" {
		reportWriter, err := zipWriter.Create("report.md")
		if err == nil {
			_, _ = io.WriteString(reportWriter, report)
		}
	}

	manifest := struct {
		GeneratedAt string   `json:"generatedAt"`
		Files       []string `json:"files"`
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
	_, err := os.Stat(path)
	return err == nil
}

func addFileToZip(zipWriter *zip.Writer, srcPath, destName string) error {
	file, err := os.Open(srcPath)
	if err != nil {
		return err
	}
	defer file.Close()

	writer, err := zipWriter.Create(destName)
	if err != nil {
		return err
	}

	_, err = io.Copy(writer, file)
	return err
}

// --- Settings Handlers ---

// GetSettings returns application settings
func GetSettings(w http.ResponseWriter, r *http.Request) {
	respondSuccess(w, map[string]interface{}{
		"watcher": map[string]interface{}{
			"pollingEnabled":         false,
			"scanAfterGitPull":       true,
			"debounceMs":             500,
			"defaultExcludePatterns": []string{".git", "node_modules", ".DS_Store"},
		},
		"backup": map[string]interface{}{
			"autoBackupBeforePublish": true,
			"retentionDays":           30,
			"maxBackupsPerPlugin":     10,
			"location":                "backups",
		},
		"logging": map[string]interface{}{
			"level":         "info",
			"retentionDays": 7,
			"debugMode":     false,
		},
		"appearance": map[string]interface{}{
			"theme":       "system",
			"compactMode": false,
		},
		"server": map[string]interface{}{
			"port":               8080,
			"wsReconnectDelayMs": 3000,
		},
	})
}

// UpdateSettings updates application settings
func UpdateSettings(w http.ResponseWriter, r *http.Request) {
	respondError(w, http.StatusNotImplemented, "E9004", "Not implemented")
}
