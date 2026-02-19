// Package session provides session-based logging for operations
package session

import (
	"encoding/json"
)

// ToJSON marshals a typed struct into json.RawMessage for use as log/error details.
// This generic helper avoids map[string]any at call sites.
func ToJSON[T any](v T) json.RawMessage {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	return data
}

// ServiceInterface defines the session service contract
type ServiceInterface interface {
	// StartSession creates a new session and returns its ID
	StartSession(sessionType SessionType, pluginID, siteID int64, pluginName, siteName string) (string, error)

	// Log writes a log entry to the session
	Log(sessionID, level, step, message string, details json.RawMessage)

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

	// SetMetadata sets a key-value pair on a session's metadata JSON object
	SetMetadata(sessionID, key string, value json.RawMessage)

	// SaveRequest persists the inbound request as request.json
	SaveRequest(sessionID string, req *SessionRequest)

	// SaveResponse persists the delegated response as response.json
	SaveResponse(sessionID string, resp *SessionResponse)

	// SaveError persists error details and stack traces as error.log
	SaveError(sessionID string, stackTrace *SessionStackTrace, errorMsg string, details json.RawMessage)
}
