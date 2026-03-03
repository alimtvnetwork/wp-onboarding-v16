package middleware

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"wp-plugin-publish/internal/constants/logfile"
)

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
func appendToErrorLog(input errorLogInput) {
	logPath := filepath.Join(ErrorLogDir, logfile.ErrorLog)
	mkdirErr := os.MkdirAll(ErrorLogDir, 0755)
	if mkdirErr != nil {
		return
	}

	f, err := os.OpenFile(logPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		return
	}
	defer f.Close()

	entry := buildErrorLogEntry(input)
	f.WriteString(entry)
}

// buildErrorLogEntry constructs the full error log string.
func buildErrorLogEntry(input errorLogInput) string {
	now := time.Now().Format("2006-01-02 15:04:05")
	var sb strings.Builder

	writeErrorLogHeader(&sb, now, input)
	writeErrorLogRequestBody(&sb, input)
	sb.WriteString(fmt.Sprintf("  Duration: %s\n", input.Duration.String()))

	env, parsed := parseEnvelope(input.Writer)
	if parsed {
		writeEnvelopeDetails(&sb, env)
	}

	writeResponseBody(&sb, input.Writer)
	sb.WriteString("───────────────────────────────────────────────────────────────────────────────\n")
	return sb.String()
}

// writeErrorLogHeader writes the HTTP status, method, and URL line.
func writeErrorLogHeader(sb *strings.Builder, now string, input errorLogInput) {
	sb.WriteString(fmt.Sprintf("[%s] HTTP %d %s FAILED\n", now, input.Writer.statusCode, input.Request.Method))

	fullUrl := resolveFullUrl(input.Request)
	sb.WriteString(fmt.Sprintf("  Requested To: %s %s\n", input.Request.Method, fullUrl))

	hasQueryParams := input.Request.URL.RawQuery != ""
	if hasQueryParams {
		sb.WriteString(fmt.Sprintf("  Query Params: %s\n", input.Request.URL.RawQuery))
	}
}

// resolveFullUrl constructs the full request URL from the http.Request.
func resolveFullUrl(r *http.Request) string {
	scheme := "http"
	hasTLS := r.TLS != nil
	if hasTLS {
		scheme = "https"
	}

	host := r.Host
	isHostEmpty := host == ""
	if isHostEmpty {
		host = r.URL.Host
	}

	return fmt.Sprintf("%s://%s%s", scheme, host, r.URL.RequestURI())
}

// writeErrorLogRequestBody writes the request body (pretty-printed if JSON).
func writeErrorLogRequestBody(sb *strings.Builder, input errorLogInput) {
	isBodyEmpty := len(input.RequestBody) == 0
	if isBodyEmpty {
		return
	}

	bodyStr := truncateBody(string(input.RequestBody))
	writePrettyOrRawBody(sb, input.RequestBody, bodyStr)
}

// truncateBody truncates a body string to 4096 chars.
func truncateBody(body string) string {
	isBodyTooLong := len(body) > 4096
	if isBodyTooLong {
		return body[:4096] + "... (truncated)"
	}
	return body
}

// writePrettyOrRawBody writes JSON-indented body if possible, otherwise raw.
func writePrettyOrRawBody(sb *strings.Builder, raw []byte, fallback string) {
	var prettyBuf bytes.Buffer
	isPrettyPrintable := json.Indent(&prettyBuf, raw, "    ", "  ") == nil && prettyBuf.Len() > 0

	if isPrettyPrintable {
		sb.WriteString("  Request Body:\n")
		sb.WriteString(fmt.Sprintf("    %s\n", prettyBuf.String()))
	} else {
		sb.WriteString(fmt.Sprintf("  Request Body: %s\n", fallback))
	}
}

// parseEnvelope attempts to parse the response body as an envelope.
func parseEnvelope(w *responseWriter) (envelopeForParsing, bool) {
	var env envelopeForParsing
	isBodyEmpty := w.body.Len() == 0
	if isBodyEmpty {
		return env, false
	}

	isParsed := json.Unmarshal(w.body.Bytes(), &env) == nil && env.Status.Message != ""
	if isParsed {
		return env, true
	}

	return env, false
}

// writeEnvelopeDetails writes error code, message, attributes, errors, and methods stack.
func writeEnvelopeDetails(sb *strings.Builder, env envelopeForParsing) {
	sb.WriteString(fmt.Sprintf("  Error Code: %d\n", env.Status.Code))
	sb.WriteString(fmt.Sprintf("  Error Message: %s\n", env.Status.Message))

	writeEnvelopeAttributes(sb, env)
	if env.Errors != nil {
		writeEnvelopeErrors(sb, env)
	}
	writeMethodsStack(sb, env)
}

// writeEnvelopeAttributes writes RequestedAt and RequestDelegatedAt if present.
func writeEnvelopeAttributes(sb *strings.Builder, env envelopeForParsing) {
	hasAttributes := env.Attributes != nil
	isMissingAttributes := !hasAttributes

	if isMissingAttributes {
		return
	}

	if env.Attributes.RequestedAt != "" {
		sb.WriteString(fmt.Sprintf("  RequestedAt: %s\n", env.Attributes.RequestedAt))
	}
	if env.Attributes.RequestDelegatedAt != "" {
		sb.WriteString(fmt.Sprintf("  RequestDelegatedAt: %s\n", env.Attributes.RequestDelegatedAt))
	}
}

// writeMethodsStack writes the Go methods stack trace if present.
func writeMethodsStack(sb *strings.Builder, env envelopeForParsing) {
	hasMethodsStack := env.MethodsStack != nil
	hasBackendMethods := hasMethodsStack && len(env.MethodsStack.Backend) > 0
	isBackendStackEmpty := !hasBackendMethods

	if isBackendStackEmpty {
		return
	}

	sb.WriteString("  Go Methods Stack:\n")
	for i, frame := range env.MethodsStack.Backend {
		sb.WriteString(fmt.Sprintf("    #%d %s at %s:%d\n", i, frame.Method, frame.File, frame.LineNumber))
	}
}

// writeEnvelopeErrors writes backend message and error stacks.
func writeEnvelopeErrors(sb *strings.Builder, env envelopeForParsing) {
	hasBackendMessage := env.Errors.BackendMessage != ""
	if hasBackendMessage {
		sb.WriteString(fmt.Sprintf("  Backend Error: %s\n", env.Errors.BackendMessage))
	}

	writeStackLines(sb, "Delegated Service Error Stack (PHP)", env.Errors.DelegatedServiceErrorStack)
	writeStackLines(sb, "Go Backend Stack", env.Errors.Backend)
}

// writeStackLines writes a labeled list of stack lines if non-empty.
func writeStackLines(sb *strings.Builder, label string, lines []string) {
	if len(lines) == 0 {
		return
	}

	sb.WriteString(fmt.Sprintf("  %s:\n", label))
	for _, line := range lines {
		sb.WriteString(fmt.Sprintf("    %s\n", line))
	}
}

// writeResponseBody writes the truncated response body.
func writeResponseBody(sb *strings.Builder, w *responseWriter) {
	isBodyEmpty := w.body.Len() == 0
	if isBodyEmpty {
		return
	}

	body := truncateBody(w.body.String())
	sb.WriteString(fmt.Sprintf("  Response Body:\n    %s\n", body))
}
