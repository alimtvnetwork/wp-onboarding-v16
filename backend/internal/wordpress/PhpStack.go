package wordpress

import (
	"encoding/json"
	"fmt"
)

// PHPStackTraceFrame represents a single frame from a WordPress PHP stack trace.
type PHPStackTraceFrame struct {
	File     string `json:"file"`     // external key (WordPress PHP error response)
	FileBase string `json:"fileBase"` // external key
	Line     int    `json:"line"`     // external key
	Function string `json:"function"` // external key
	Class    string `json:"class"`    // external key
}

// phpErrorResponse is the expected structure of a WordPress error response
// containing PHP stack trace diagnostics.
type phpErrorResponse struct {
	Success bool `json:"success"` // external key (WordPress PHP error response)
	Error   struct {
		Code    string `json:"code"`    // external key
		Message string `json:"message"` // external key
		Details struct {
			StackTrace       string               `json:"stackTrace"`       // external key
			StackTraceFrames []PHPStackTraceFrame  `json:"stackTraceFrames"` // external key
			ExceptionClass   string                `json:"exceptionClass"`   // external key
			PHPVersion       string                `json:"phpVersion"`       // external key
		} `json:"details"` // external key
	} `json:"error"` // external key
}

// ExtractPHPStackTrace parses a WordPress error response body and returns a
// formatted PHP stack trace string. Returns an empty string if the response
// doesn't contain stack trace frames.
func ExtractPHPStackTrace(respBytes []byte) string {
	frames := parsePHPErrorFrames(respBytes)
	if len(frames) == 0 {
		return ""
	}

	return formatPHPStackTrace(frames)
}

// parsePHPErrorFrames extracts stack trace frames from a PHP error response.
func parsePHPErrorFrames(respBytes []byte) []PHPStackTraceFrame {
	if len(respBytes) == 0 {
		return nil
	}

	var parsed phpErrorResponse
	err := json.Unmarshal(respBytes, &parsed)

	if err != nil {
		return nil
	}

	return parsed.Error.Details.StackTraceFrames
}

// formatPHPStackTrace formats PHP stack trace frames into a readable string.
func formatPHPStackTrace(frames []PHPStackTraceFrame) string {
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
