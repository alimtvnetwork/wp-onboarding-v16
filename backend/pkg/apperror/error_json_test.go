package apperror

import (
	"encoding/json"
	"testing"
)

func TestAppError_JSONRoundTrip(t *testing.T) {
	original := New(ErrInternal, "something broke").
		WithDetails("disk full")

	original.Values = map[string]string{
		"path": "/tmp/data",
		"size": "42",
	}
	original.Diagnostic = ErrorDiagnostic{
		URL:    "https://example.com/api",
		Method: "POST",
	}
	original.Cause = Wrap(nil, ErrNotFound, "inner cause")

	data, err := json.Marshal(original)
	if err != nil {
		t.Fatalf("Marshal failed: %v", err)
	}

	var restored AppError
	if err := json.Unmarshal(data, &restored); err != nil {
		t.Fatalf("Unmarshal failed: %v", err)
	}

	assertField(t, "Code", original.Code, restored.Code)
	assertField(t, "Message", original.Message, restored.Message)
	assertField(t, "Details", original.Details, restored.Details)
	assertField(t, "Diagnostic.URL", original.Diagnostic.URL, restored.Diagnostic.URL)
	assertField(t, "Diagnostic.Method", original.Diagnostic.Method, restored.Diagnostic.Method)

	if len(restored.Values) != len(original.Values) {
		t.Fatalf("Values length mismatch: got %d, want %d", len(restored.Values), len(original.Values))
	}
	for k, v := range original.Values {
		if restored.Values[k] != v {
			t.Errorf("Values[%s]: got %q, want %q", k, restored.Values[k], v)
		}
	}

	if restored.Cause == nil {
		t.Fatal("Cause is nil after round-trip")
	}
	if restored.Cause.Error() != original.Cause.Error() {
		t.Errorf("Cause: got %q, want %q", restored.Cause.Error(), original.Cause.Error())
	}

	if restored.Stack.IsEmpty() {
		t.Error("Stack is empty after round-trip")
	}
	if original.Stack.String() != restored.Stack.String() {
		t.Errorf("Stack mismatch:\ngot:  %s\nwant: %s", restored.Stack.String(), original.Stack.String())
	}
}

func assertField[T comparable](t *testing.T, name string, want, got T) {
	t.Helper()
	if got != want {
		t.Errorf("%s: got %v, want %v", name, got, want)
	}
}
