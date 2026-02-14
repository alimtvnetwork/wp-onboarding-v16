// Package session provides session-based logging for operations
package session

// ServiceInterface defines the session service contract
type ServiceInterface interface {
	// StartSession creates a new session and returns its ID
	StartSession(sessionType SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error)
	
	// Log writes a log entry to the session
	Log(sessionID, level, step, message string, details map[string]any)
	
	// LogStageStart writes a stage header to the session log
	LogStageStart(sessionID, stageName string)
	
	// LogStageEnd writes a stage completion marker
	LogStageEnd(sessionID, stageName, status string, durationMs int64)
	
	// EndSession marks a session as complete
	EndSession(sessionID, status, errorMsg string)
	
	// GetSession returns session info
	GetSession(sessionID string) (*Session, error)
	
	// GetSessionLogs returns the full log content for a session
	GetSessionLogs(sessionID string) (string, error)
	
	// GetSessionDiagnostics returns structured request/response/stackTrace for a session
	GetSessionDiagnostics(sessionID string) (*SessionDiagnostics, error)
	
	// ListSessions returns recent sessions
	ListSessions(limit int) ([]*SessionSummary, error)
	
	// DeleteSession removes a session's log file
	DeleteSession(sessionID string) error
	
	// SetMetadata sets metadata on a session
	SetMetadata(sessionID, key string, value any)
	
	// SaveRequest persists the inbound request as request.json
	SaveRequest(sessionID string, req *SessionRequest)
	
	// SaveResponse persists the delegated response as response.json
	SaveResponse(sessionID string, resp *SessionResponse)
	
	// SaveError persists error details and stack traces as error.log
	SaveError(sessionID string, stackTrace *SessionStackTrace, errorMsg string, details map[string]any)
}
