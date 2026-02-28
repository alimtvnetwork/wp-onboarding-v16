// Package middleware provides HTTP middleware functions
package middleware

import (
	"bufio"
	"bytes"
	"io"
	"net"
	"net/http"
	"runtime/debug"
	"time"

	"wp-plugin-publish/internal/logger"
)

// ErrorLogDir is set by main.go to point at the errors directory
// (e.g. "backend/data/errors"). When empty, error-log persistence is disabled.
var ErrorLogDir string

// CORS adds CORS headers for local development
func CORS(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		origin := r.Header.Get("Origin")
		hasOrigin := origin != ""

		if hasOrigin {
			w.Header().Set("Access-Control-Allow-Origin", origin)
		} else {
			w.Header().Set("Access-Control-Allow-Origin", "*")
		}
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
		w.Header().Set("Access-Control-Allow-Credentials", "true")

		if r.Method == "OPTIONS" {
			w.WriteHeader(http.StatusNoContent)
			return
		}

		next.ServeHTTP(w, r)
	})
}

// Logging logs HTTP request details and persists error responses to error.log.txt
func Logging(log *logger.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			start := time.Now()

			var requestBodyBytes []byte
			if r.Body != nil {
				requestBodyBytes, _ = io.ReadAll(r.Body)
				r.Body = io.NopCloser(bytes.NewBuffer(requestBodyBytes))
			}

			wrapped := &responseWriter{ResponseWriter: w, statusCode: http.StatusOK}
			next.ServeHTTP(wrapped, r)

			duration := time.Since(start)
			log.Info("HTTP request",
				"method", r.Method,
				"path", r.URL.Path,
				"status", wrapped.statusCode,
				"duration", duration.String(),
			)

			isErrorResponse := wrapped.statusCode >= 400
			hasErrorLogDir := ErrorLogDir != ""
			isLoggableError := isErrorResponse && hasErrorLogDir

			if isLoggableError {
				appendToErrorLog(errorLogInput{Request: r, Writer: wrapped, Duration: duration, RequestBody: requestBodyBytes})
			}
		})
	}
}

// Recovery recovers from panics and logs the error
func Recovery(log *logger.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			defer func() {
				if recovered := recover(); recovered != nil {
					stack := debug.Stack()
					log.Error("Panic recovered",
						"error", recovered,
						"stack", string(stack),
					)
					http.Error(w, "Internal Server Error", http.StatusInternalServerError)
				}
			}()

			next.ServeHTTP(w, r)
		})
	}
}

// responseWriter wraps http.ResponseWriter to capture status code and response body for errors
type responseWriter struct {
	http.ResponseWriter
	statusCode  int
	body        bytes.Buffer
	wroteHeader bool
}

func (rw *responseWriter) WriteHeader(code int) {
	if rw.wroteHeader {
		return
	}
	rw.wroteHeader = true
	rw.statusCode = code
	rw.ResponseWriter.WriteHeader(code)
}

func (rw *responseWriter) Write(b []byte) (int, error) {
	if rw.statusCode >= 400 {
		rw.body.Write(b)
	}
	return rw.ResponseWriter.Write(b)
}

// Unwrap allows net/http's ResponseController to reach the underlying ResponseWriter.
func (rw *responseWriter) Unwrap() http.ResponseWriter {
	return rw.ResponseWriter
}

// Flush implements http.Flusher for streaming responses.
func (rw *responseWriter) Flush() {
	flusher, ok := rw.ResponseWriter.(http.Flusher)
	if ok {
		flusher.Flush()
	}
}

// Hijack implements http.Hijacker for WebSocket upgrade support
func (rw *responseWriter) Hijack() (net.Conn, *bufio.ReadWriter, error) {
	hijacker, ok := rw.ResponseWriter.(http.Hijacker)
	if ok {
		return hijacker.Hijack()
	}
	return nil, nil, http.ErrNotSupported
}

// Push implements http.Pusher (HTTP/2).
func (rw *responseWriter) Push(target string, opts *http.PushOptions) error {
	pusher, ok := rw.ResponseWriter.(http.Pusher)
	if ok {
		return pusher.Push(target, opts)
	}
	return http.ErrNotSupported
}
