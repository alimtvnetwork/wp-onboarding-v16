// Package envelope — fluent response modifiers and HTTP writer.
package envelope

import (
	"encoding/json"
	"fmt"
	"net/http"
)

// --- Fluent Modifiers ---

// WithEndpoints sets the requested and delegated endpoint in attributes.
func (r Response) WithEndpoints(requested, delegated string) Response {
	r.Attributes.RequestedAt = requested
	r.Attributes.RequestDelegatedAt = delegated

	return r
}

// WithSessionId attaches a session ID to the response attributes for frontend diagnostics.
func (r Response) WithSessionId(sessionId string) Response {
	r.Attributes.SessionId = sessionId

	return r
}

// WithBackendTrace appends backend stack trace lines to the Errors block.
// Only populated if IncludeStackTrace is enabled.
func (r Response) WithBackendTrace(lines []string) Response {
	isTraceDisabled := !globalDebug.IncludeStackTrace
	isEmpty := len(lines) == 0

	if isTraceDisabled || isEmpty {
		return r
	}

	lines = truncateFrames(lines)
	r.ensureErrors()
	r.Errors.Backend = lines

	return r
}

// WithDelegatedErrorStack attaches delegated service error stack lines.
// Only populated if IncludeStackTrace is enabled.
func (r Response) WithDelegatedErrorStack(lines []string) Response {
	isTraceDisabled := !globalDebug.IncludeStackTrace
	isEmpty := len(lines) == 0

	if isTraceDisabled || isEmpty {
		return r
	}

	lines = truncateFrames(lines)
	r.ensureErrors()
	r.Errors.DelegatedServiceErrorStack = lines

	return r
}

// truncateFrames limits the number of stack frames based on debug config.
func truncateFrames(lines []string) []string {
	hasFrameLimit := globalDebug.MaxStackFrames > 0
	isOverFrameLimit := len(lines) > globalDebug.MaxStackFrames

	if hasFrameLimit && isOverFrameLimit {
		return lines[:globalDebug.MaxStackFrames]
	}

	return lines
}

// WithMethodsStack attaches the backend methods stack for diagnostics.
// Only populated if IncludeMethodsStack is enabled.
func (r Response) WithMethodsStack(frames []MethodFrame) Response {
	isStackDisabled := !globalDebug.IncludeMethodsStack
	isEmpty := len(frames) == 0

	if isStackDisabled || isEmpty {
		return r
	}

	r.MethodsStack = &MethodsStack{
		Backend:  frames,
		Frontend: []MethodFrame{},
	}

	return r
}

// ensureErrors initializes the Errors block if nil.
func (r *Response) ensureErrors() {
	if r.Errors == nil {
		r.Errors = &Errors{}
		r.Attributes.HasAnyErrors = true
	}
}

// --- HTTP Writer ---

// Write serializes and writes the response to the HTTP response writer.
func Write(w http.ResponseWriter, resp Response) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(resp.Status.Code)
	json.NewEncoder(w).Encode(resp)
}

// --- Helpers ---

// FormatErrorMessage formats the error code and message for the Errors block.
func FormatErrorMessage(code, message string) string {
	return fmt.Sprintf("[%s] %s", code, message)
}
