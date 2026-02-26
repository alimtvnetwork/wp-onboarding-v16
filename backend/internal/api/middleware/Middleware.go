// Package middleware provides HTTP middleware functions
package middleware

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt"
	"io"
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

			// Capture request body before it's consumed by the handler
			var requestBodyBytes []byte
			if r.Body != nil {
				requestBodyBytes, _ = io.ReadAll(r.Body)
				r.Body = io.NopCloser(bytes.NewBuffer(requestBodyBytes))
			}

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
				appendToErrorLog(errorLogInput{Request: r, Writer: wrapped, Duration: duration, RequestBody: requestBodyBytes})
			}
		})
	}
}

// envelopeForParsing mirrors the envelope structure for JSON unmarshalling.
// Only the fields needed for error log enrichment are included.
type envelopeForParsing struct {
	Status struct {
		Code    int    `json:"Code"`    // external key (WordPress envelope)
		Message string `json:"Message"` // external key
	} `json:"Status"` // external key
	Errors *struct {
		BackendMessage             string   `json:"BackendMessage"`             // external key
		DelegatedServiceErrorStack []string `json:"DelegatedServiceErrorStack"` // external key
		Backend                    []string `json:"Backend"`                    // external key
	} `json:"Errors"` // external key
	MethodsStack *struct {
		Backend []struct {
			Method     string `json:"Method"`     // external key
			File       string `json:"File"`       // external key
			LineNumber int    `json:"LineNumber"` // external key
		} `json:"Backend"` // external key
	} `json:"MethodsStack"` // external key
	Attributes *struct {
		RequestedAt        string `json:"RequestedAt"`        // external key
		RequestDelegatedAt string `json:"RequestDelegatedAt"` // external key
	} `json:"Attributes"` // external key
}

// errorLogInput bundles parameters for appendToErrorLog.
type errorLogInput struct {
	Request     *http.Request
	Writer      *responseWriter
	Duration    time.Duration
	RequestBody []byte
}

// appendToErrorLog writes a structured error entry to data/errors/error.log.txt
// with full request/response context, envelope error blocks, and stack traces.
func appendToErrorLog(input errorLogInput) {
	logPath := filepath.Join(ErrorLogDir, "error.log.txt")

	_ = os.MkdirAll(ErrorLogDir, 0755)

	f, err := os.OpenFile(logPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		return
	}
	defer f.Close()

	now := time.Now().Format("2006-01-02 15:04:05")

	var sb strings.Builder

	// ── Header ──
	sb.WriteString(fmt.Sprintf("[%s] HTTP %d %s FAILED\n", now, input.Writer.statusCode, input.Request.Method))

	// ── Requested To (Go endpoint) ──
	scheme := "http"
	if input.Request.TLS != nil {
		scheme = "https"
	}
	host := input.Request.Host
	if host == "" {
		host = input.Request.URL.Host
	}
	fullURL := fmt.Sprintf("%s://%s%s", scheme, host, input.Request.URL.RequestURI())
	sb.WriteString(fmt.Sprintf("  Requested To: %s %s\n", input.Request.Method, fullURL))

	// ── Request Body & Params from React ──
	if input.Request.URL.RawQuery != "" {
		sb.WriteString(fmt.Sprintf("  Query Params: %s\n", input.Request.URL.RawQuery))
	}
	if len(input.RequestBody) > 0 {
		bodyStr := string(input.RequestBody)
		if len(bodyStr) > 4096 {
			bodyStr = bodyStr[:4096] + "... (truncated)"
		}
		// Pretty-print JSON if possible
		var prettyBuf bytes.Buffer
		if json.Indent(&prettyBuf, input.RequestBody, "    ", "  ") == nil && prettyBuf.Len() > 0 {
			sb.WriteString("  Request Body:\n")
			sb.WriteString(fmt.Sprintf("    %s\n", prettyBuf.String()))
		} else {
			sb.WriteString(fmt.Sprintf("  Request Body: %s\n", bodyStr))
		}
	}

	sb.WriteString(fmt.Sprintf("  Duration: %s\n", input.Duration.String()))

	// ── Parse envelope from response body ──
	var env envelopeForParsing
	isEnvelopeParsed := false
	if input.Writer.body.Len() > 0 {
		if json.Unmarshal(input.Writer.body.Bytes(), &env) == nil && env.Status.Message != "" {
			isEnvelopeParsed = true
		}
	}

	// ── Envelope Status ──
	if isEnvelopeParsed {
		sb.WriteString(fmt.Sprintf("  Error Code: %d\n", env.Status.Code))
		sb.WriteString(fmt.Sprintf("  Error Message: %s\n", env.Status.Message))
	}

	// ── Request Delegation Context ──
	if isEnvelopeParsed && env.Attributes != nil {
		if env.Attributes.RequestedAt != "" {
			sb.WriteString(fmt.Sprintf("  RequestedAt: %s\n", env.Attributes.RequestedAt))
		}
		if env.Attributes.RequestDelegatedAt != "" {
			sb.WriteString(fmt.Sprintf("  RequestDelegatedAt: %s\n", env.Attributes.RequestDelegatedAt))
		}
	}

	// ── Delegated Service Error Stack (PHP errors/stack from remote) ──
	if isEnvelopeParsed && env.Errors != nil {
		if env.Errors.BackendMessage != "" {
			sb.WriteString(fmt.Sprintf("  Backend Error: %s\n", env.Errors.BackendMessage))
		}
		if len(env.Errors.DelegatedServiceErrorStack) > 0 {
			sb.WriteString("  Delegated Service Error Stack (PHP):\n")
			for _, line := range env.Errors.DelegatedServiceErrorStack {
				sb.WriteString(fmt.Sprintf("    %s\n", line))
			}
		}
		if len(env.Errors.Backend) > 0 {
			sb.WriteString("  Go Backend Stack:\n")
			for _, line := range env.Errors.Backend {
				sb.WriteString(fmt.Sprintf("    %s\n", line))
			}
		}
	}

	// ── Methods Stack (Go call chain) ──
	if isEnvelopeParsed && env.MethodsStack != nil && len(env.MethodsStack.Backend) > 0 {
		sb.WriteString("  Go Methods Stack:\n")
		for i, frame := range env.MethodsStack.Backend {
			sb.WriteString(fmt.Sprintf("    #%d %s at %s:%d\n", i, frame.Method, frame.File, frame.LineNumber))
		}
	}

	// ── Raw Response Body (always include for full transparency) ──
	if input.Writer.body.Len() > 0 {
		body := input.Writer.body.String()
		if len(body) > 4096 {
			body = body[:4096] + "... (truncated)"
		}
		sb.WriteString(fmt.Sprintf("  Response Body:\n    %s\n", body))
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
