// Typed response structs for the /logs/retrieve endpoint.
// The PHP endpoint returns a flat response (not envelope-wrapped).
package wordpress

// --- Raw PHP response (matches PHP JSON output exactly) ---

// LogsRetrievePhpResponse is the raw response from the PHP /logs/retrieve endpoint.
type LogsRetrievePhpResponse struct {
	Success     bool                     `json:"Success"`
	Version     string                   `json:"Version"`
	RequestedAt string                   `json:"RequestedAt"`
	Settings    LogsRetrieveSettings     `json:"Settings"`
	InfoLog     *LogsRetrieveFileData    `json:"InfoLog,omitempty"`
	ErrorLog    *LogsRetrieveFileData    `json:"ErrorLog,omitempty"`
	StacktraceLog *LogsRetrieveFileData  `json:"StacktraceLog,omitempty"`
}

// LogsRetrieveSettings holds the request settings echoed back by PHP.
type LogsRetrieveSettings struct {
	IncludeInfoLog    bool `json:"IncludeInfoLog"`
	IncludeErrorLog   bool `json:"IncludeErrorLog"`
	IncludeStacktrace bool `json:"IncludeStacktrace"`
	MaxLines          int  `json:"MaxLines"`
}

// LogsRetrieveFileData represents a single log file's content and metadata.
type LogsRetrieveFileData struct {
	Exists    bool   `json:"Exists"`
	File      string `json:"File"`
	Path      string `json:"Path"`
	Content   string `json:"Content"`
	Lines     int    `json:"Lines"`
	TotalLines int   `json:"TotalLines"`
	TotalSize  int64 `json:"TotalSize"`
	Truncated bool   `json:"Truncated"`
}

// --- Frontend-facing types ---

// LogsRetrieveResult is the combined response sent to the React frontend.
// Contains results from both plugin namespaces probed in parallel.
type LogsRetrieveResult struct {
	Plugins []PluginLogsData `json:"plugins"`
}

// PluginLogsData holds retrieved log content for a single plugin namespace.
type PluginLogsData struct {
	Namespace string                `json:"namespace"`
	Label     string                `json:"label"`
	Available bool                  `json:"available"`
	InfoLog   *LogsRetrieveFileData `json:"infoLog,omitempty"`
	ErrorLog  *LogsRetrieveFileData `json:"errorLog,omitempty"`
	Stacktrace *LogsRetrieveFileData `json:"stacktrace,omitempty"`
}

// NamespaceLabel returns a human-readable label for a plugin namespace.
func NamespaceLabel(ns string) string {
	switch ns {
	case QUploadNamespace:
		return "QUpload"
	case RiseupAsiaNamespace:
		return "Riseup Asia"
	default:
		return ns
	}
}
