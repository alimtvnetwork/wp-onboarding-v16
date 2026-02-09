package wordpress

import (
	"encoding/json"
	"fmt"
)

// PHPStackTraceFrame represents a single frame from a WordPress PHP stack trace.
type PHPStackTraceFrame struct {
	File     string `json:"file"`
	FileBase string `json:"fileBase"`
	Line     int    `json:"line"`
	Function string `json:"function"`
	Class    string `json:"class"`
}

// phpErrorResponse is the expected structure of a WordPress error response
// containing PHP stack trace diagnostics.
type phpErrorResponse struct {
	Success bool `json:"success"`
	Error   struct {
		Code    string `json:"code"`
		Message string `json:"message"`
		Details struct {
			StackTrace       string               `json:"stackTrace"`
			StackTraceFrames []PHPStackTraceFrame  `json:"stackTraceFrames"`
			ExceptionClass   string                `json:"exceptionClass"`
			PHPVersion       string                `json:"phpVersion"`
		} `json:"details"`
	} `json:"error"`
}

// ExtractPHPStackTrace parses a WordPress error response body and returns a
// formatted PHP stack trace string. Returns an empty string if the response
// doesn't contain stack trace frames.
func ExtractPHPStackTrace(respBytes []byte) string {
	if len(respBytes) == 0 {
		return ""
	}

	var parsed phpErrorResponse
	if err := json.Unmarshal(respBytes, &parsed); err != nil {
		return ""
	}

	frames := parsed.Error.Details.StackTraceFrames
	if len(frames) == 0 {
		return ""
	}

	result := "\n--- PHP Stack Trace (from WordPress) ---\n"
	for i, frame := range frames {
		funcName := frame.Function
		if frame.Class != "" {
			funcName = frame.Class + "::" + frame.Function
		}
		result += fmt.Sprintf("  #%d %s() at %s:%d\n", i, funcName, frame.FileBase, frame.Line)
	}
	result += "--- End PHP Stack Trace ---"
	return result
}
