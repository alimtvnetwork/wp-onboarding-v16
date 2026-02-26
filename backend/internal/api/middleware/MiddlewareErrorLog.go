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
	logPath := filepath.Join(ErrorLogDir, "error.log.txt")
	_ = os.MkdirAll(ErrorLogDir, 0755)

	f, err := os.OpenFile(logPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		return
	}
	defer f.Close()

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
	f.WriteString(sb.String())
}

func writeErrorLogHeader(sb *strings.Builder, now string, input errorLogInput) {
	sb.WriteString(fmt.Sprintf("[%s] HTTP %d %s FAILED\n", now, input.Writer.statusCode, input.Request.Method))

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

	if input.Request.URL.RawQuery != "" {
		sb.WriteString(fmt.Sprintf("  Query Params: %s\n", input.Request.URL.RawQuery))
	}
}

func writeErrorLogRequestBody(sb *strings.Builder, input errorLogInput) {
	if len(input.RequestBody) == 0 {
		return
	}
	bodyStr := string(input.RequestBody)
	if len(bodyStr) > 4096 {
		bodyStr = bodyStr[:4096] + "... (truncated)"
	}
	var prettyBuf bytes.Buffer
	if json.Indent(&prettyBuf, input.RequestBody, "    ", "  ") == nil && prettyBuf.Len() > 0 {
		sb.WriteString("  Request Body:\n")
		sb.WriteString(fmt.Sprintf("    %s\n", prettyBuf.String()))
	} else {
		sb.WriteString(fmt.Sprintf("  Request Body: %s\n", bodyStr))
	}
}

func parseEnvelope(w *responseWriter) (envelopeForParsing, bool) {
	var env envelopeForParsing
	if w.body.Len() == 0 {
		return env, false
	}
	if json.Unmarshal(w.body.Bytes(), &env) == nil && env.Status.Message != "" {
		return env, true
	}
	return env, false
}

func writeEnvelopeDetails(sb *strings.Builder, env envelopeForParsing) {
	sb.WriteString(fmt.Sprintf("  Error Code: %d\n", env.Status.Code))
	sb.WriteString(fmt.Sprintf("  Error Message: %s\n", env.Status.Message))

	if env.Attributes != nil {
		if env.Attributes.RequestedAt != "" {
			sb.WriteString(fmt.Sprintf("  RequestedAt: %s\n", env.Attributes.RequestedAt))
		}
		if env.Attributes.RequestDelegatedAt != "" {
			sb.WriteString(fmt.Sprintf("  RequestDelegatedAt: %s\n", env.Attributes.RequestDelegatedAt))
		}
	}

	if env.Errors != nil {
		writeEnvelopeErrors(sb, env)
	}

	if env.MethodsStack != nil && len(env.MethodsStack.Backend) > 0 {
		sb.WriteString("  Go Methods Stack:\n")
		for i, frame := range env.MethodsStack.Backend {
			sb.WriteString(fmt.Sprintf("    #%d %s at %s:%d\n", i, frame.Method, frame.File, frame.LineNumber))
		}
	}
}

func writeEnvelopeErrors(sb *strings.Builder, env envelopeForParsing) {
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

func writeResponseBody(sb *strings.Builder, w *responseWriter) {
	if w.body.Len() == 0 {
		return
	}
	body := w.body.String()
	if len(body) > 4096 {
		body = body[:4096] + "... (truncated)"
	}
	sb.WriteString(fmt.Sprintf("  Response Body:\n    %s\n", body))
}
