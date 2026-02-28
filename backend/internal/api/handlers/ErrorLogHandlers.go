// Package handlers provides error log HTTP request handlers
package handlers

import (
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// --- Error/Log Handlers ---

// GetErrors returns application error logs
func GetErrors(w http.ResponseWriter, r *http.Request) {
	logType := r.URL.Query().Get("type")
	isLogTypeEmpty := logType == ""

	if isLogTypeEmpty {
		logType = "errors"
	}

	logPath := resolveErrorLogPath(logType)

	fi, statErr := pathutil.StatFile(logPath)
	if statErr != nil {
		respondSuccess(w, LogFileResponse{Content: "", Path: logPath, Exists: false, LogType: logType})
		return
	}

	respondErrorLogContent(w, logPath, logType, fi)
}

// resolveErrorLogPath returns the file path for the given log type.
func resolveErrorLogPath(logType string) string {
	isAllLogs := logType == "all"

	if isAllLogs {
		return "data/errors/log.txt"
	}
	return "data/errors/error.log.txt"
}

// respondErrorLogContent reads and responds with log file content.
func respondErrorLogContent(w http.ResponseWriter, logPath, logType string, fi *pathutil.FileStatResult) {
	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E4001", "Failed to read log file: "+err.Error())
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
	respondError(w, wordpress.HttpStatusNotImplemented, "E9004", "Not implemented")
}

// ClearErrors removes all error logs
func ClearErrors(w http.ResponseWriter, r *http.Request) {
	_ = os.Truncate("data/errors/log.txt", 0)
	_ = os.Truncate("data/errors/error.log.txt", 0)

	respondSuccess(w, ActionResponse{IsCleared: true, Message: "Log files cleared"})
}

// StreamErrorLogs streams the error log file content for real-time viewing
func StreamErrorLogs(w http.ResponseWriter, r *http.Request) {
	logType, tailLines := parseStreamParams(r)
	logPath := resolveStreamLogPath(logType)

	if pathutil.IsFileMissing(logPath) {
		respondSuccess(w, LogLinesResponse{Lines: []string{}, Path: logPath, Exists: false, LogType: logType})
		return
	}

	respondStreamedLines(w, logPath, logType, tailLines)
}

// parseStreamParams extracts logType and tailLines from request query.
func parseStreamParams(r *http.Request) (string, int) {
	logType := r.URL.Query().Get("type")
	isLogTypeEmpty := logType == ""

	if isLogTypeEmpty {
		logType = "all"
	}

	tailLines := 100
	tailStr := r.URL.Query().Get("tail")
	hasTailParam := tailStr != ""

	if hasTailParam {
		n, err := strconv.Atoi(tailStr)
		isValidTail := err == nil && n > 0 && n <= 10000

		if isValidTail {
			tailLines = n
		}
	}

	return logType, tailLines
}

// resolveStreamLogPath returns the file path for streaming log type.
func resolveStreamLogPath(logType string) string {
	if logType == "errors" {
		return "data/errors/error.log.txt"
	}
	return "data/errors/log.txt"
}

// respondStreamedLines reads the file, tails lines, and responds.
func respondStreamedLines(w http.ResponseWriter, logPath, logType string, tailLines int) {
	content, err := os.ReadFile(logPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E4001", "Failed to read log file: "+err.Error())
		return
	}

	allLines := splitLines(string(content))
	lines := tailSlice(allLines, tailLines)
	fi, _ := pathutil.StatFile(logPath)

	respondSuccess(w, buildLogLinesResponse(logPath, logType, allLines, lines, fi))
}

// tailSlice returns the last n items from a slice.
func tailSlice(items []string, n int) []string {
	if len(items) > n {
		return items[len(items)-n:]
	}
	return items
}

// buildLogLinesResponse constructs the LogLinesResponse from parts.
func buildLogLinesResponse(logPath, logType string, allLines, lines []string, fi *pathutil.FileStatResult) LogLinesResponse {
	resp := LogLinesResponse{
		Lines:      lines,
		TotalLines: len(allLines),
		Path:       logPath,
		Exists:     true,
		LogType:    logType,
	}
	if fi != nil {
		resp.Size = fi.Info.Size()
		resp.ModifiedAt = fi.Info.ModTime().Format(time.RFC3339)
	}
	return resp
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
		respondLogFileStatError(w, statErr, filename)
		return
	}

	respondLogFileContent(w, path, filename, fi)
}

// respondLogFileStatError responds based on file stat error type.
func respondLogFileStatError(w http.ResponseWriter, statErr error, filename string) {
	if apperror.Is(statErr, apperror.ErrFSNotFound) {
		respondError(w, wordpress.HttpStatusNotFound, "E9001", "Log file not found: "+filename)
	} else {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to read log file: "+statErr.Error())
	}
}

// respondLogFileContent reads file and responds with LogFileResponse.
func respondLogFileContent(w http.ResponseWriter, path, filename string, fi *pathutil.FileStatResult) {
	content, err := os.ReadFile(path)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to read log file: "+err.Error())
		return
	}

	respondSuccess(w, LogFileResponse{
		Content:    string(content),
		Filename:   filename,
		Size:       fi.Info.Size(),
		ModifiedAt: fi.Info.ModTime().Format(time.RFC3339),
	})
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
