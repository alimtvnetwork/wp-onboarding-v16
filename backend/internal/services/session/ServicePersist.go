// Package session — persistence: save request, response, and error artifacts.
package session

import (
	"encoding/json"
	"os"
	"time"
)

// SaveRequest persists the inbound request as request.json in the session folder
func (s *Service) SaveRequest(sessionID string, req *SessionRequest) {
	if req == nil {
		return
	}
	s.writeSessionArtifact(sessionID, "request.json", req, s.getRequestPath)
}

// SaveResponse persists the delegated response as response.json in the session folder
func (s *Service) SaveResponse(sessionID string, resp *SessionResponse) {
	if resp == nil {
		return
	}
	s.writeSessionArtifact(sessionID, "response.json", resp, s.getResponsePath)
}

// writeSessionArtifact marshals data and writes it to a session file.
func (s *Service) writeSessionArtifact(sessionID, filename string, data any, pathFn func(string) (string, error)) {
	jsonData, err := json.MarshalIndent(data, "", "  ")
	if err != nil {
		s.logPersistError("Failed to marshal "+filename, sessionID, err)
		return
	}

	path, err := pathFn(sessionID)
	if err != nil {
		s.logPersistError("Failed to resolve "+filename+" path", sessionID, err)
		return
	}

	if err := os.WriteFile(path, jsonData, 0644); err != nil {
		s.logPersistError("Failed to write "+filename, sessionID, err)
	}
}

// logPersistError logs a persistence error if the logger is available.
func (s *Service) logPersistError(message, sessionID string, err error) {
	if s.log != nil {
		s.log.Error(message, "sessionId", sessionID, "error", err)
	}
}

// ErrorLogData is the typed structure persisted as error.log in session folders.
type ErrorLogData struct {
	Timestamp  string             `json:"timestamp"`            // external key (error.log JSON file)
	Error      string             `json:"error"`                // external key
	StackTrace *SessionStackTrace `json:"stackTrace,omitempty"` // external key
	Details    json.RawMessage    `json:"details,omitempty"`    // external key
}

// SaveErrorInput bundles parameters for SaveError.
type SaveErrorInput struct {
	SessionID  string
	StackTrace *SessionStackTrace
	ErrorMsg   string
	Details    json.RawMessage
}

// SaveError persists error details (including stack traces) as error.log in the session folder
func (s *Service) SaveError(input SaveErrorInput) {
	errorData := ErrorLogData{
		Timestamp:  time.Now().UTC().Format("2006-01-02 15:04:05 UTC"),
		Error:      input.ErrorMsg,
		StackTrace: input.StackTrace,
		Details:    input.Details,
	}

	s.writeSessionArtifact(input.SessionID, "error.log", errorData, s.getErrorLogPath)
}

// SetMetadata sets a key-value pair on a session's metadata JSON object.
func (s *Service) SetMetadata(sessionID, key string, value json.RawMessage) {
	session := s.getActiveSession(sessionID)
	if session == nil {
		return
	}

	session.mu.Lock()
	var m map[string]json.RawMessage
	if len(session.Metadata) == 0 || json.Unmarshal(session.Metadata, &m) != nil {
		m = make(map[string]json.RawMessage)
	}
	m[key] = value
	session.Metadata, _ = json.Marshal(m)
	session.mu.Unlock()
}
