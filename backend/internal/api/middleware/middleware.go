// Package middleware provides HTTP middleware functions
package middleware

import (
	"bufio"
	"bytes"
	"fmt"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"runtime/debug"
	"strings"
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
		// Allow any origin for local development
		if origin != "" {
			w.Header().Set("Access-Control-Allow-Origin", origin)
		} else {
			w.Header().Set("Access-Control-Allow-Origin", "*")
		}
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
		w.Header().Set("Access-Control-Allow-Credentials", "true")

		// Handle preflight requests
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

			// Create response wrapper to capture status code and body for errors
			wrapped := &responseWriter{ResponseWriter: w, statusCode: http.StatusOK}

			// Process request
			next.ServeHTTP(wrapped, r)

			// Log request
			duration := time.Since(start)
			log.Info("HTTP request",
				"method", r.Method,
				"path", r.URL.Path,
				"status", wrapped.statusCode,
				"duration", duration.String(),
			)

			// Persist error responses (>= 400) to error.log.txt
			if wrapped.statusCode >= 400 && ErrorLogDir != "" {
				appendToErrorLog(r, wrapped, duration)
			}
		})
	}
}

// appendToErrorLog writes a structured error entry to data/errors/error.log.txt
func appendToErrorLog(r *http.Request, rw *responseWriter, duration time.Duration) {
	logPath := filepath.Join(ErrorLogDir, "error.log.txt")

	_ = os.MkdirAll(ErrorLogDir, 0755)

	f, err := os.OpenFile(logPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		return
	}
	defer f.Close()

	now := time.Now().Format("2006-01-02 15:04:05")

	var sb strings.Builder
	sb.WriteString(fmt.Sprintf("[%s] HTTP %d %s %s\n", now, rw.statusCode, r.Method, r.URL.Path))
	if r.URL.RawQuery != "" {
		sb.WriteString(fmt.Sprintf("  Query: %s\n", r.URL.RawQuery))
	}
	sb.WriteString(fmt.Sprintf("  Duration: %s\n", duration.String()))
	if rw.body.Len() > 0 {
		// Truncate very large bodies to 2KB
		body := rw.body.String()
		if len(body) > 2048 {
			body = body[:2048] + "... (truncated)"
		}
		sb.WriteString(fmt.Sprintf("  Response: %s\n", body))
	}
	sb.WriteString("───────────────────────────────────────────────────────────────────────────────\n")

	f.WriteString(sb.String())
}

// Recovery recovers from panics and logs the error
func Recovery(log *logger.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			defer func() {
				if err := recover(); err != nil {
					stack := debug.Stack()
					log.Error("Panic recovered",
						"error", err,
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
	statusCode int
	body       bytes.Buffer
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
	// Capture body for error responses (>= 400) to persist in error.log.txt
	if rw.statusCode >= 400 {
		rw.body.Write(b)
	}
	return rw.ResponseWriter.Write(b)
}

// Unwrap allows net/http's ResponseController (and other middleware) to reach the
// underlying ResponseWriter so optional interfaces (Hijacker/Flusher/Pusher)
// keep working through wrappers.
func (rw *responseWriter) Unwrap() http.ResponseWriter {
	return rw.ResponseWriter
}

// Flush implements http.Flusher for streaming responses (required by some
// WebSocket upgrade paths).
func (rw *responseWriter) Flush() {
	if flusher, ok := rw.ResponseWriter.(http.Flusher); ok {
		flusher.Flush()
	}
}

// Hijack implements http.Hijacker for WebSocket upgrade support
func (rw *responseWriter) Hijack() (net.Conn, *bufio.ReadWriter, error) {
	if hijacker, ok := rw.ResponseWriter.(http.Hijacker); ok {
		return hijacker.Hijack()
	}
	return nil, nil, http.ErrNotSupported
}

// Push implements http.Pusher (HTTP/2). Not strictly required for WebSockets,
// but ensures compatibility with servers/proxies that rely on it.
func (rw *responseWriter) Push(target string, opts *http.PushOptions) error {
	if pusher, ok := rw.ResponseWriter.(http.Pusher); ok {
		return pusher.Push(target, opts)
	}
	return http.ErrNotSupported
}
