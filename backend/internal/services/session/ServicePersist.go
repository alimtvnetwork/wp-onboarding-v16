// Package session — persistence: save request, response, and error artifacts.
package session

import (
	"encoding/json"
	"os"
	"time"

	"wp-plugin-publish/internal/constants/logfile"
	"wp-plugin-publish/pkg/apperror"
)

// SaveRequest persists the inbound request as request.json in the session folder
func (s *Service) SaveRequest(sessionId string, req *SessionRequest) {
	isRequestMissing := req == nil

	if isRequestMissing {
		return
	}
	s.writeSessionArtifact(sessionArtifactInput{
		SessionId: sessionId,
		Filename:  "request.json",
		Data:      req,
		PathFn:    s.getRequestPath,
	})
}

// SaveResponse persists the delegated response as response.json in the session folder
func (s *Service) SaveResponse(sessionId string, resp *SessionResponse) {
	isResponseMissing := resp == nil

	if isResponseMissing {
		return
	}
	s.writeSessionArtifact(sessionArtifactInput{
		SessionId: sessionId,
		Filename:  "response.json",
		Data:      resp,
		PathFn:    s.getResponsePath,
	})
}

// sessionArtifactInput bundles parameters for writeSessionArtifact.
type sessionArtifactInput struct {
	SessionId string
	Filename  string
	Data      any
	PathFn    func(string) apperror.Result[string]
}

// writeSessionArtifact marshals data and writes it to a session file.
func (s *Service) writeSessionArtifact(input sessionArtifactInput) {
	jsonData, marshalErr := json.MarshalIndent(input.Data, "", "  ")

	if marshalErr != nil {
		s.logPersistError("Failed to marshal "+input.Filename, input.SessionId, marshalErr)

		return
	}

	pathResult := input.PathFn(input.SessionId)

	if pathResult.HasError() {
		s.logPersistAppError("Failed to resolve "+input.Filename+" path", input.SessionId, pathResult.AppError())

		return
	}

	writeErr := os.WriteFile(pathResult.Value(), jsonData, 0644)

	if writeErr != nil {
		s.logPersistError("Failed to write "+input.Filename, input.SessionId, writeErr)
	}
}

// logPersistError logs a persistence error if the logger is available.
func (s *Service) logPersistError(message, sessionId string, err error) {
	if s.log != nil {
		s.log.Error(message, "sessionId", sessionId, "error", err)
	}
}

// logPersistAppError logs a persistence AppError if the logger is available.
func (s *Service) logPersistAppError(message, sessionId string, appErr *apperror.AppError) {
	if s.log != nil {
		s.log.Error(message, "sessionId", sessionId, "error", appErr)
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
	SessionId  string
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

	s.writeSessionArtifact(sessionArtifactInput{
		SessionId: input.SessionId,
		Filename:  logfile.SessionErrorLog,
		Data:      errorData,
		PathFn:    s.getErrorLogPath,
	})
}

// SetMetadata sets a key-value pair on a session's metadata JSON object.
func (s *Service) SetMetadata(sessionId, key string, value json.RawMessage) {
	session := s.getActiveSession(sessionId)
	isSessionMissing := session == nil

	if isSessionMissing {
		return
	}

	session.mu.Lock()
	var m map[string]json.RawMessage
	isMetadataEmpty := len(session.Metadata) == 0
	isUnmarshalFailed := json.Unmarshal(session.Metadata, &m) != nil
	isMetadataInvalid := isMetadataEmpty || isUnmarshalFailed

	if isMetadataInvalid {
		m = make(map[string]json.RawMessage)
	}
	m[key] = value
	marshaled, marshalErr := json.Marshal(m)

	if marshalErr == nil {
		session.Metadata = marshaled
	}
	session.mu.Unlock()
}
