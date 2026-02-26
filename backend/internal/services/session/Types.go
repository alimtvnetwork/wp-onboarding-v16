// Package session provides session-based logging for operations
package session

import (
	"encoding/json"

	"wp-plugin-publish/pkg/apperror"
)

// ToJson marshals a typed struct into json.RawMessage for use as log/error details.
// This generic helper avoids map[string]any at call sites.
func ToJson[T any](v T) json.RawMessage {
	data, err := json.Marshal(v)
	if err != nil {
		return nil
	}
	return data
}

// StartSessionInput bundles parameters for StartSession.
type StartSessionInput struct {
	Type       SessionType
	PluginID   int64
	SiteID     int64
	PluginName string
	SiteName   string
}

// ServiceInterface defines the session service contract
type ServiceInterface interface {
	// StartSession creates a new session and returns its ID
	StartSession(input StartSessionInput) (string, error)

	// Log writes a log entry to the session
	Log(sessionId, level, step, message string, details json.RawMessage)

	// LogStageStart writes a stage header to the session log
	LogStageStart(sessionId, stageName string)

	// LogStageEnd writes a stage completion marker
	LogStageEnd(sessionId, stageName, status string, durationMs int64)

	// EndSession marks a session as complete
	EndSession(sessionId, status, errorMsg string)

	// GetSession returns session info
	GetSession(sessionId string) apperror.Result[*Session]

	// GetSessionLogs returns the full log content for a session
	GetSessionLogs(sessionId string) apperror.Result[string]

	// GetSessionDiagnostics returns structured request/response/stackTrace for a session
	GetSessionDiagnostics(sessionId string) apperror.Result[SessionDiagnostics]

	// ListSessions returns recent sessions
	ListSessions(limit int) apperror.ResultSlice[*SessionSummary]

	// DeleteSession removes a session's log file
	DeleteSession(sessionId string) error

	// SetMetadata sets a key-value pair on a session's metadata JSON object
	SetMetadata(sessionId, key string, value json.RawMessage)

	// SaveRequest persists the inbound request as request.json
	SaveRequest(sessionId string, req *SessionRequest)

	// SaveResponse persists the delegated response as response.json
	SaveResponse(sessionId string, resp *SessionResponse)

	// SaveError persists error details and stack traces as error.log
	SaveError(sessionId string, stackTrace *SessionStackTrace, errorMsg string, details json.RawMessage)
}
