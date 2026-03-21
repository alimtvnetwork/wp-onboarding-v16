// Package handlers provides error log HTTP request handlers
package handlers

import (
	"net/http"
	"os"
	"regexp"
	"strconv"
	"strings"
	"time"

	"wp-plugin-publish/internal/api/middleware"
	"wp-plugin-publish/internal/constants/logfile"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// timestampPattern matches the [YYYY-MM-DD HH:MM:SS] prefix in error log entries.
var timestampPattern = regexp.MustCompile(`^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]`)

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
		respondSuccess(w, LogFileResponse{Content: "", Path: logPath, IsExists: false, LogType: logType})
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
	FileStat *pathutil.FileInfo
}

// respondErrorLogContent reads and responds with log file content (filtered to current session).
func respondErrorLogContent(w http.ResponseWriter, input logContentInput) {
	content, err := os.ReadFile(input.LogPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E4001", "Failed to read log file: "+err.Error())

		return
	}

	filtered := filterToCurrentSession(string(content))

	respondSuccess(w, LogFileResponse{
		Content:    filtered,
		Path:       input.LogPath,
		IsExists:   true,
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
		respondSuccess(w, LogLinesResponse{Lines: []string{}, Path: logPath, IsExists: false, LogType: logType})
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

// respondStreamedLines reads the file, filters to current session, tails lines, and responds.
func respondStreamedLines(w http.ResponseWriter, input streamLinesInput) {
	content, err := os.ReadFile(input.LogPath)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E4001", "Failed to read log file: "+err.Error())

		return
	}

	filtered := filterToCurrentSession(string(content))
	allLines := splitLines(filtered)
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
	FileStat *pathutil.FileInfo
}

// buildLogLinesResponse constructs the LogLinesResponse from parts.
func buildLogLinesResponse(input logLinesResponseInput) LogLinesResponse {
	resp := LogLinesResponse{
		Lines:      input.Lines,
		TotalLines: len(input.AllLines),
		Path:       input.LogPath,
		IsExists:   true,
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
	FileStat *pathutil.FileInfo
}

// respondLogFileContent reads file and responds with LogFileResponse (filtered to current session).
func respondLogFileContent(w http.ResponseWriter, input logFileContentInput) {
	content, err := os.ReadFile(input.Path)
	if err != nil {
		respondError(w, wordpress.HttpStatusServerError, "E9002", "Failed to read log file: "+err.Error())

		return
	}

	filtered := filterToCurrentSession(string(content))

	respondSuccess(w, LogFileResponse{
		Content:    filtered,
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

// filterToCurrentSession splits the error log into entry blocks (delimited by ─── lines)
// and returns only those whose timestamp is at or after the current server session start.
func filterToCurrentSession(content string) string {
	sessionStart := middleware.SessionStartTime
	lines := strings.Split(content, "\n")

	var currentBlock []string
	var sessionBlocks []string

	for _, line := range lines {
		isDivider := strings.HasPrefix(line, "───")

		if isDivider {
			// End of a block — check if it belongs to this session
			if len(currentBlock) > 0 {
				blockText := strings.Join(currentBlock, "\n")
				if isBlockInSession(currentBlock, sessionStart) {
					sessionBlocks = append(sessionBlocks, blockText)
				}
				currentBlock = nil
			}
			continue
		}

		currentBlock = append(currentBlock, line)
	}

	// Handle last block (no trailing divider)
	if len(currentBlock) > 0 && isBlockInSession(currentBlock, sessionStart) {
		sessionBlocks = append(sessionBlocks, strings.Join(currentBlock, "\n"))
	}

	if len(sessionBlocks) == 0 {
		return ""
	}

	divider := "───────────────────────────────────────────────────────────────────────────────"
	return strings.Join(sessionBlocks, "\n"+divider+"\n") + "\n" + divider
}

// isBlockInSession checks if any line in the block has a timestamp >= session start.
func isBlockInSession(blockLines []string, sessionStart time.Time) bool {
	for _, line := range blockLines {
		match := timestampPattern.FindStringSubmatch(line)
		if len(match) < 2 {
			continue
		}

		entryTime, err := time.Parse("2006-01-02 15:04:05", match[1])
		if err != nil {
			continue
		}

		return !entryTime.Before(sessionStart)
	}

	return false
}
