// Package middleware - Session-based API logging
package middleware

import (
	"bytes"
	"context"
	"encoding/json"
	"io"
	"net/http"
	"strings"
	"time"

	"github.com/google/uuid"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// SessionContextKey is the context key for session ID
type SessionContextKey struct{}

// RequestSession contains session data for a single API request
type RequestSession struct {
	ID            string
	Method        string
	Path          string
	Query         string              `json:",omitempty"`
	RequestBody   string              `json:",omitempty"`
	ResponseBody  string              `json:",omitempty"`
	StatusCode    int
	StartedAt     time.Time
	EndedAt       time.Time
	DurationMs    int64
	Error         string              `json:",omitempty"`
	Logs          []SessionLogEntry
	Headers       map[string]string   `json:",omitempty"`
}

// SessionLogEntry is a single log entry within a request session
type SessionLogEntry struct {
	Timestamp string
	Level     string
	Message   string
	Details   json.RawMessage `json:",omitempty"`
}

// SessionStore interface for persisting request sessions
type SessionStore interface {
	SaveRequestSession(session *RequestSession) *apperror.AppError
	GetRequestSession(id string) (*RequestSession, *apperror.AppError)
	ListRequestSessions(limit, offset int) (*SessionListResult, *apperror.AppError)
	DeleteRequestSession(id string) *apperror.AppError
	ClearRequestSessions() *apperror.AppError
}

// SessionListResult holds paginated session results.
type SessionListResult struct {
	Sessions []*RequestSession
	Total    int
}

// SessionLogging creates middleware that logs all API requests with full details
func SessionLogging(log *logger.Logger, store SessionStore, isEnabled bool) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			// Skip if session logging is disabled or for non-API routes
			isDisabled := !isEnabled
			isNonApiRoute := !strings.HasPrefix(r.URL.Path, "/api/")
			isSkippable := isDisabled || isNonApiRoute

			if isSkippable {
				next.ServeHTTP(w, r)
				return
			}

			// Skip health checks and other high-frequency endpoints to reduce noise
			isHealthCheck := r.URL.Path == wordpress.GoAPIHealth

			if isHealthCheck {
				next.ServeHTTP(w, r)
				return
			}

			sessionID := uuid.New().String()
			startTime := time.Now()

			// Capture request body
			var requestBody string
			hasBody := r.Body != nil
			isNotGet := r.Method != "GET"
			isNotHead := r.Method != "HEAD"
			isBodyCapturable := hasBody && isNotGet && isNotHead

			if isBodyCapturable {
				bodyBytes, err := io.ReadAll(r.Body)
				if err == nil {
					requestBody = string(bodyBytes)
					// Restore body for downstream handlers
					r.Body = io.NopCloser(bytes.NewBuffer(bodyBytes))
				}
			}

			// Capture request headers (sanitize sensitive ones)
			headers := make(map[string]string)
			for key, values := range r.Header {
				lowerKey := strings.ToLower(key)
				isAuthHeader := lowerKey == "authorization"
				isCookieHeader := lowerKey == "cookie"
				isSensitive := isAuthHeader || isCookieHeader

				if isSensitive {
					headers[key] = "[REDACTED]"
				} else if len(values) > 0 {
					headers[key] = values[0]
				}
			}

			// Add session ID to context
			ctx := context.WithValue(r.Context(), SessionContextKey{}, sessionID)
			r = r.WithContext(ctx)

			// Wrap response writer to capture response
			wrapped := &sessionResponseWriter{
				ResponseWriter: w,
				statusCode:     http.StatusOK,
				body:           &bytes.Buffer{},
			}

			// Process request
			next.ServeHTTP(wrapped, r)

			endTime := time.Now()
			duration := endTime.Sub(startTime)

			// Build session record
			session := &RequestSession{
				ID:           sessionID,
				Method:       r.Method,
				Path:         r.URL.Path,
				Query:        r.URL.RawQuery,
				RequestBody:  truncateBody(requestBody, 50000),
				ResponseBody: truncateBody(wrapped.body.String(), 50000),
				StatusCode:   wrapped.statusCode,
				StartedAt:    startTime,
				EndedAt:      endTime,
				DurationMs:   duration.Milliseconds(),
				Headers:      headers,
				Logs:         []SessionLogEntry{},
			}

			// Extract error from response if status >= 400
			isErrorResponse := wrapped.statusCode >= 400

			if isErrorResponse {
				session.Error = extractErrorFromResponse(wrapped.body.String())
			}

			// Persist session if store is available
			if store != nil {
				saveErr := store.SaveRequestSession(session)
				if saveErr != nil {
					log.Error("Failed to save request session", "sessionId", sessionID, "error", saveErr)
				}
			}

			// Log summary
			if isErrorResponse {
				log.Warn("API request failed",
					"sessionId", sessionID,
					"method", r.Method,
					"path", r.URL.Path,
					"status", wrapped.statusCode,
					"durationMs", duration.Milliseconds(),
				)
			}
		})
	}
}

// GetSessionID extracts the session ID from context
func GetSessionID(ctx context.Context) string {
	id, ok := ctx.Value(SessionContextKey{}).(string)
	if ok {
		return id
	}
	return ""
}

// sessionResponseWriter captures response body and status code
type sessionResponseWriter struct {
	http.ResponseWriter
	statusCode int
	body       *bytes.Buffer
}

func (w *sessionResponseWriter) WriteHeader(code int) {
	w.statusCode = code
	w.ResponseWriter.WriteHeader(code)
}

func (w *sessionResponseWriter) Write(b []byte) (int, error) {
	// Capture body (up to limit)
	isUnderLimit := w.body.Len() < 100000

	if isUnderLimit {
		w.body.Write(b)
	}
	return w.ResponseWriter.Write(b)
}

// truncateBody limits body size for storage
func truncateBody(body string, maxLen int) string {
	isWithinLimit := len(body) <= maxLen

	if isWithinLimit {
		return body
	}
	return body[:maxLen] + "\n... [truncated]"
}

// extractErrorFromResponse tries to extract error message from JSON response
func extractErrorFromResponse(body string) string {
	var response struct {
		Error struct {
			Message string `json:"message"` // external key (our own envelope format)
			Code    string `json:"code"`    // external key
		} `json:"error"` // external key
	}
	err := json.Unmarshal([]byte(body), &response)
	if err == nil && response.Error.Message != "" {
		if response.Error.Code != "" {
			return "[" + response.Error.Code + "] " + response.Error.Message
		}
		return response.Error.Message
	}
	return ""
}
