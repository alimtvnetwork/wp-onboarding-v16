// Package handlers provides error log HTTP request handlers
package handlers

import (
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/internal/constants/logfile"
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

	respondErrorLogContent(w, logContentInput{
		LogPath: logPath,
		LogType: logType,
		FileStat: fi,
	})
}

// resolveErrorLogPath returns the file path for the given log type.
func resolveErrorLogPath(logType string) string {
	isAllLogs := logType == "all"

	if isAllLogs {
		return "data/errors/log.txt"
	}
	return "data/errors/error.log.txt"
}

// logContentInput bundles parameters for respondErrorLogContent.
type logContentInput struct {
	LogPath  string
	LogType  string
	FileStat *pathutil.FileStatResult
}

// respondErrorLogContent reads and responds with log file content.
func respondErrorLogContent(w http.ResponseWriter, input logContentInput) {
	content, err := os.ReadFile(input.LogPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E4001", "Failed to read log file: "+err.Error())

		return
	}

	respondSuccess(w, LogFileResponse{
		Content:    string(content),
		Path:       input.LogPath,
		Exists:     true,
		LogType:    input.LogType,
		Size:       input.FileStat.Info.Size(),
		ModifiedAt: input.FileStat.Info.ModTime().Format(time.RFC3339),
	})
}

// GetError returns a specific error by ID
func GetError(w http.ResponseWriter, r *http.Request) {
	respondError(w, wordpress.HttpStatusNotImplemented, "E9004", "Not implemented")
}

// ClearErrors removes all error logs
func ClearErrors(w http.ResponseWriter, r *http.Request) {
	truncErr := os.Truncate("data/errors/log.txt", 0)
	if truncErr != nil && !os.IsNotExist(truncErr) {
		respondError(w, wordpress.HttpStatusServerError, "E9005", "Failed to clear log file: "+truncErr.Error())

		return
	}

	errTruncErr := os.Truncate("data/errors/error.log.txt", 0)
	if errTruncErr != nil && !os.IsNotExist(errTruncErr) {
		respondError(w, wordpress.HttpStatusServerError, "E9005", "Failed to clear error log file: "+errTruncErr.Error())

		return
	}

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

	respondStreamedLines(w, streamLinesInput{
		LogPath:   logPath,
		LogType:   logType,
		TailLines: tailLines,
	})
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
	isErrorLogType := logType == "errors"

	if isErrorLogType {
		return "data/errors/error.log.txt"
	}
	return "data/errors/log.txt"
}

// streamLinesInput bundles parameters for respondStreamedLines.
type streamLinesInput struct {
	LogPath   string
	LogType   string
	TailLines int
}

// respondStreamedLines reads the file, tails lines, and responds.
func respondStreamedLines(w http.ResponseWriter, input streamLinesInput) {
	content, err := os.ReadFile(input.LogPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E4001", "Failed to read log file: "+err.Error())

		return
	}

	allLines := splitLines(string(content))
	lines := tailSlice(allLines, input.TailLines)
	fi, _ := pathutil.StatFile(input.LogPath)

	respondSuccess(w, buildLogLinesResponse(logLinesResponseInput{
		LogPath:  input.LogPath,
		LogType:  input.LogType,
		AllLines: allLines,
		Lines:    lines,
		FileStat: fi,
	}))
}

// tailSlice returns the last n items from a slice.
func tailSlice(items []string, n int) []string {
	if len(items) > n {
		return items[len(items)-n:]
	}
	return items
}

// logLinesResponseInput bundles parameters for buildLogLinesResponse.
type logLinesResponseInput struct {
	LogPath  string
	LogType  string
	AllLines []string
	Lines    []string
	FileStat *pathutil.FileStatResult
}

// buildLogLinesResponse constructs the LogLinesResponse from parts.
func buildLogLinesResponse(input logLinesResponseInput) LogLinesResponse {
	resp := LogLinesResponse{
		Lines:      input.Lines,
		TotalLines: len(input.AllLines),
		Path:       input.LogPath,
		Exists:     true,
		LogType:    input.LogType,
	}
	if input.FileStat != nil {
		resp.Size = input.FileStat.Info.Size()
		resp.ModifiedAt = input.FileStat.Info.ModTime().Format(time.RFC3339)
	}

	return resp
}

// GetBackendErrorLog returns the content of error.log.txt
func GetBackendErrorLog(w http.ResponseWriter, r *http.Request) {
	readLogFile(w, "data/"+logfile.ErrorsDir+"/"+logfile.ErrorLog, logfile.ErrorLog)
}

// GetBackendFullLog returns the content of the full log.txt
func GetBackendFullLog(w http.ResponseWriter, r *http.Request) {
	readLogFile(w, "data/"+logfile.ErrorsDir+"/"+logfile.AllLog, logfile.AllLog)
}

// readLogFile is a helper to read and return log file contents
func readLogFile(w http.ResponseWriter, path string, filename string) {
	fi, statErr := pathutil.StatFile(path)
	if statErr != nil {
		respondLogFileStatError(w, statErr, filename)
		return
	}

	respondLogFileContent(w, logFileContentInput{
		Path:     path,
		Filename: filename,
		FileStat: fi,
	})
}

// respondLogFileStatError responds based on file stat error type.
func respondLogFileStatError(w http.ResponseWriter, statErr error, filename string) {
	if apperror.Is(statErr, apperror.ErrFSNotFound) {
		respondError(w, wordpress.HttpStatusNotFound, "E9001", "Log file not found: "+filename)
	} else {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to read log file: "+statErr.Error())
	}
}

// logFileContentInput bundles parameters for respondLogFileContent.
type logFileContentInput struct {
	Path     string
	Filename string
	FileStat *pathutil.FileStatResult
}

// respondLogFileContent reads file and responds with LogFileResponse.
func respondLogFileContent(w http.ResponseWriter, input logFileContentInput) {
	content, err := os.ReadFile(input.Path)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to read log file: "+err.Error())

		return
	}

	respondSuccess(w, LogFileResponse{
		Content:    string(content),
		Filename:   input.Filename,
		Size:       input.FileStat.Info.Size(),
		ModifiedAt: input.FileStat.Info.ModTime().Format(time.RFC3339),
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
