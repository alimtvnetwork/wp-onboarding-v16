// Typed response structs for remote log management endpoints.
// These match the PHP plugin's JSON output and the frontend's TypeScript types.
package wordpress

// LogsStatusData is the typed response from the PHP logs/status endpoint.
type LogsStatusData struct {
	Files          []LogFileInfo `json:"files"`
	TotalSizeBytes int64         `json:"totalSizeBytes"`
	ArchiveCount   int           `json:"archiveCount"`
}

// LogFileInfo represents a single log file in the status response.
type LogFileInfo struct {
	Name      string `json:"name"`
	Path      string `json:"path"`
	Size      int64  `json:"size"`
	Modified  string `json:"modified"`
	LineCount int    `json:"lineCount"`
}

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
	IsEnabled    bool   `json:"isEnabled"`
	MaxSizeBytes int64  `json:"maxSizeBytes"`
	MaxFiles     int    `json:"maxFiles"`
	Interval     string `json:"interval"`
}
