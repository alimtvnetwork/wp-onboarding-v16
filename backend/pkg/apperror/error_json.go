package apperror

import "encoding/json"

// appErrorJSON is an alias used to prevent infinite recursion during JSON marshaling.
type appErrorJSON struct {
	Code       string            `json:"code"`
	Message    string            `json:"message"`
	Details    string            `json:"details,omitempty"`
	Values     map[string]string `json:"values,omitempty"`
	Diagnostic ErrorDiagnostic   `json:"diagnostic,omitempty"`
	Stack      StackTrace        `json:"stack"`
	Cause      string            `json:"cause,omitempty"`
}

// MarshalJSON serializes AppError to JSON, converting Cause to a string message.
func (e *AppError) MarshalJSON() ([]byte, error) {
	alias := appErrorJSON{
		Code:       e.Code,
		Message:    e.Message,
		Details:    e.Details,
		Values:     e.Values,
		Diagnostic: e.Diagnostic,
		Stack:      e.Stack,
	}

	if e.Cause != nil {
		alias.Cause = e.Cause.Error()
	}

	return json.Marshal(alias)
}

// UnmarshalJSON deserializes JSON into AppError, restoring Cause as a plain error.
func (e *AppError) UnmarshalJSON(data []byte) error {
	var alias appErrorJSON
	if err := json.Unmarshal(data, &alias); err != nil {
		return err
	}

	e.Code = alias.Code
	e.Message = alias.Message
	e.Details = alias.Details
	e.Values = alias.Values
	e.Diagnostic = alias.Diagnostic
	e.Stack = alias.Stack

	if alias.Cause != "" {
		e.Cause = &plainError{msg: alias.Cause}
	}

	return nil
}

// plainError is a minimal error implementation for deserialized cause strings.
type plainError struct {
	msg string
}

func (e *plainError) Error() string {
	return e.msg
}
