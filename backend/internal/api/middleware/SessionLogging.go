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
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/logger"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// SessionContextKey is the context key for session ID
type SessionContextKey struct{}

// RequestSession contains session data for a single API request
type RequestSession struct {
	Id            string
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
			isApiRoute := strings.HasPrefix(r.URL.Path, "/api/")
			shouldLog := isEnabled && isApiRoute

			if !shouldLog {
				next.ServeHTTP(w, r)
				return
			}

			// Skip health checks and other high-frequency endpoints to reduce noise
			isHealthCheck := r.URL.Path == wordpress.GoApiHealth

			if isHealthCheck {
				next.ServeHTTP(w, r)
				return
			}

			sessionId := uuid.New().String()
			startTime := time.Now()

			// Capture request body
			var requestBody string
			hasBody := r.Body != nil
			isReadOnly := r.Method == httpmethod.Get.Value() || r.Method == httpmethod.Head.Value()
			isMutatingMethod := !isReadOnly
			isBodyCapturable := hasBody && isMutatingMethod

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
			} else {
				hasValues := len(values) > 0

				if hasValues {
					headers[key] = values[0]
				}
			}
			}

			// Add session ID to context
			ctx := context.WithValue(r.Context(), SessionContextKey{}, sessionId)
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
				Id:           sessionId,
				Method:       r.Method,
				Path:         r.URL.Path,
				Query:        r.URL.RawQuery,
			RequestBody:  truncateBodyToLimit(requestBody, 50000),
			ResponseBody: truncateBodyToLimit(wrapped.body.String(), 50000),
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
			isStoreAvailable := store != nil

			if isStoreAvailable {
				saveErr := store.SaveRequestSession(session)
				if saveErr != nil {
					log.Error("Failed to save request session", "sessionId", sessionId, "error", saveErr)
				}
			}

			// Log summary
			if isErrorResponse {
				log.Warn("API request failed",
					"sessionId", sessionId,
					"method", r.Method,
					"path", r.URL.Path,
					"status", wrapped.statusCode,
					"durationMs", duration.Milliseconds(),
				)
			}
		})
	}
}

// GetSessionId extracts the session ID from context
func GetSessionId(ctx context.Context) string {
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

// truncateBodyToLimit limits body size for storage
func truncateBodyToLimit(body string, maxLen int) string {
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
	hasErrorMessage := err == nil && response.Error.Message != ""

	if hasErrorMessage {
		hasErrorCode := response.Error.Code != ""

		if hasErrorCode {
			return "[" + response.Error.Code + "] " + response.Error.Message
		}

		return response.Error.Message
	}

	return ""
}
