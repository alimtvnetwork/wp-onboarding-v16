// Typed response structs for remote log management endpoints.
// These match the PHP plugin's JSON output and the frontend's TypeScript types.
package wordpress

// --- Raw PHP response types (match PHP JSON output exactly) ---

// LogsStatusPhpResponse is the raw response from the PHP logs/status endpoint.
// The PHP returns: { Success, logs: { log_file, error_file, stacktrace_file, archive_count }, database: {...} }
type LogsStatusPhpResponse struct {
	Success  bool                    `json:"Success"`
	Logs     LogsStatusPhpLogsBlock  `json:"logs"`
	Database LogsStatusPhpDbBlock    `json:"database"`
}

// LogsStatusPhpLogsBlock maps the "logs" object from PHP.
type LogsStatusPhpLogsBlock struct {
	LogFile        LogsStatusPhpFileInfo `json:"log_file"`
	ErrorFile      LogsStatusPhpFileInfo `json:"error_file"`
	StacktraceFile LogsStatusPhpFileInfo `json:"stacktrace_file"`
	ArchiveCount   int                   `json:"archive_count"`
}

// LogsStatusPhpFileInfo maps a single file entry from PHP logs/status.
type LogsStatusPhpFileInfo struct {
	Exists       bool   `json:"exists"`
	SizeBytes    int64  `json:"size_bytes"`
	LastModified string `json:"last_modified"`
	LineCount    int    `json:"line_count"`
}

// LogsStatusPhpDbBlock maps the "database" object from PHP.
type LogsStatusPhpDbBlock struct {
	TransactionCount   int `json:"transaction_count"`
	ErrorSessionCount  int `json:"error_session_count"`
}

// --- Frontend-facing types (sent to React) ---

// LogsStatusData is the normalized response sent to the frontend.
type LogsStatusData struct {
	Files           []LogFileInfo `json:"files"`
	TotalSizeBytes  int64         `json:"totalSizeBytes"`
	ArchiveCount    int           `json:"archiveCount"`
	PluginOutdated  bool          `json:"pluginOutdated,omitempty"`
	OutdatedMessage string        `json:"outdatedMessage,omitempty"`
}

// LogFileInfo represents a single log file in the normalized response.
type LogFileInfo struct {
	Name      string `json:"name"`
	SizeBytes int64  `json:"sizeBytes"`
	LineCount int    `json:"lineCount"`
	Exists    bool   `json:"exists"`
	Modified  string `json:"modified,omitempty"`
}

// ToLogsStatusData transforms the raw PHP response into the frontend-facing format.
func (r *LogsStatusPhpResponse) ToLogsStatusData() *LogsStatusData {
	files := []LogFileInfo{
		phpFileToLogFileInfo("log.txt", r.Logs.LogFile),
		phpFileToLogFileInfo("error.txt", r.Logs.ErrorFile),
		phpFileToLogFileInfo("stacktrace.txt", r.Logs.StacktraceFile),
	}

	totalSize := int64(0)
	for _, f := range files {
		totalSize += f.SizeBytes
	}

	return &LogsStatusData{
		Files:          files,
		TotalSizeBytes: totalSize,
		ArchiveCount:   r.Logs.ArchiveCount,
	}
}

// phpFileToLogFileInfo converts a raw PHP file info to the normalized format.
func phpFileToLogFileInfo(name string, info LogsStatusPhpFileInfo) LogFileInfo {
	return LogFileInfo{
		Name:      name,
		SizeBytes: info.SizeBytes,
		LineCount: info.LineCount,
		Exists:    info.Exists,
		Modified:  info.LastModified,
	}
}

// --- Other log endpoint types ---

// LogsClearRequestData is the typed response from PHP logs/clear Step 1 (request token).
type LogsClearRequestData struct {
	Token     string `json:"token"`
	ExpiresIn int    `json:"expiresIn"`
	Message   string `json:"message"`
}

// LogsClearConfirmData is the typed response from PHP logs/clear Step 2 (confirm).
type LogsClearConfirmData struct {
	Deleted []string `json:"deleted"`
	Failed  []string `json:"failed"`
	Message string   `json:"message"`
}

// LogsEmailResultData is the typed response from the PHP logs/email endpoint.
type LogsEmailResultData struct {
	Message         string `json:"Message"`
	Recipient       string `json:"Recipient"`
	AttachmentCount int    `json:"AttachmentCount"`
	TotalSizeBytes  int64  `json:"TotalSizeBytes"`
}

// LogsRotationStatusData is the typed response from the PHP logs rotation status endpoint.
type LogsRotationStatusData struct {
	IsEnabled       bool   `json:"isEnabled"`
	MaxSizeBytes    int64  `json:"maxSizeBytes"`
	MaxFiles        int    `json:"maxFiles"`
	Interval        string `json:"interval"`
	PluginOutdated  bool   `json:"pluginOutdated,omitempty"`
	OutdatedMessage string `json:"outdatedMessage,omitempty"`
}

// BuildOutdatedLogsRotationStatus returns a graceful fallback when the remote plugin lacks the logs rotation endpoint.
func BuildOutdatedLogsRotationStatus() *LogsRotationStatusData {
	return &LogsRotationStatusData{
		PluginOutdated:  true,
		OutdatedMessage: "Remote plugin is outdated — the /logs/rotation-status endpoint is not available. Please update the plugin using Deploy Uploader.",
	}
}

// BuildOutdatedLogsStatus returns a graceful fallback LogsStatusData
// when the remote plugin is outdated and doesn't have the /logs/status endpoint.
func BuildOutdatedLogsStatus() *LogsStatusData {
	return &LogsStatusData{
		Files:          []LogFileInfo{},
		TotalSizeBytes: 0,
		ArchiveCount:   0,
		PluginOutdated: true,
		OutdatedMessage: "Remote plugin is outdated — the /logs/status endpoint is not available. Please update the plugin using Deploy Uploader.",
	}
}
