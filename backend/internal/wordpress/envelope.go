package wordpress

import "encoding/json"

// Envelope represents the Universal Response Envelope from the PHP plugin.
// It provides a backward-compatible parser that works with both legacy (flat)
// and envelope (PascalCase) response formats.
//
// Schema: spec/response-envelope/envelope.schema.json v1.0.0
// NOTE: Navigation and MethodsStack are handled at the HTTP handler layer,
// not in this parsing utility which focuses on WordPress ↔ Go communication.
type Envelope struct {
	Status     *EnvelopeStatus     `json:",omitempty"` // external key (WordPress envelope)
	Attributes *EnvelopeAttributes `json:",omitempty"` // external key
	Results    json.RawMessage     `json:",omitempty"` // external key
	Errors     *EnvelopeErrors     `json:",omitempty"` // external key
}

// TypedEnvelope is the generic version of Envelope where Results is a typed slice.
type TypedEnvelope[T any] struct {
	Status     *EnvelopeStatus     `json:",omitempty"` // external key
	Attributes *EnvelopeAttributes `json:",omitempty"` // external key
	Results    []T                 `json:",omitempty"` // external key
	Errors     *EnvelopeErrors     `json:",omitempty"` // external key
}

// EnvelopeStatus represents the Status block.
type EnvelopeStatus struct {
	IsSuccess bool   // external key (WordPress envelope)
	IsFailed  bool   // external key
	Code      int    // external key
	Message   string // external key
	Timestamp string // external key
}

// EnvelopeAttributes represents the Attributes block.
type EnvelopeAttributes struct {
	RequestedAt        string // external key (WordPress envelope)
	RequestDelegatedAt string // external key
	HasAnyErrors       bool   // external key
	IsSingle           bool   // external key
	IsMultiple         bool   // external key
	TotalRecords       int    // external key
	PerPage            int    // external key
	TotalPages         int    // external key
	CurrentPage        int    // external key
}

// EnvelopeErrors represents the Errors block.
type EnvelopeErrors struct {
	BackendMessage             string   // external key (WordPress envelope)
	DelegatedServiceErrorStack []string // external key
	Backend                    []string // external key
	Frontend                   []string // external key
}

// IsEnvelope checks if a raw JSON body uses the envelope format
// by looking for the "Status" top-level key.
func IsEnvelope(data []byte) bool {
	var probe struct {
		Status *json.RawMessage
	}
	if json.Unmarshal(data, &probe) == nil && probe.Status != nil {
		return true
	}
	return false
}

// ParseEnvelope attempts to parse the body as an untyped envelope.
// Returns nil if it's not in envelope format.
func ParseEnvelope(data []byte) *Envelope {
	if !IsEnvelope(data) {
		return nil
	}
	var env Envelope
	if json.Unmarshal(data, &env) != nil {
		return nil
	}
	return &env
}

// ParseTypedEnvelope parses the body as a fully typed envelope with Results []T.
// Returns nil if it's not in envelope format or if Results cannot be decoded into []T.
func ParseTypedEnvelope[T any](data []byte) *TypedEnvelope[T] {
	if !IsEnvelope(data) {
		return nil
	}
	var env TypedEnvelope[T]
	if json.Unmarshal(data, &env) != nil {
		return nil
	}
	return &env
}

// UnwrapResults extracts the Results array from an envelope into a typed slice.
// Returns the slice and true on success; returns nil and false if the data is
// not in envelope format, enabling the caller to fall back to legacy parsing.
func UnwrapResults[T any](data []byte) ([]T, bool) {
	env := ParseTypedEnvelope[T](data)
	if env == nil {
		return nil, false
	}
	return env.Results, true
}

// UnwrapSingleResult extracts the first item from the Results array.
// Returns a pointer to the item and true on success; returns nil and false
// if the data is not in envelope format or Results is empty.
func UnwrapSingleResult[T any](data []byte) (*T, bool) {
	env := ParseTypedEnvelope[T](data)
	if env == nil || len(env.Results) == 0 {
		return nil, false
	}
	return &env.Results[0], true
}
