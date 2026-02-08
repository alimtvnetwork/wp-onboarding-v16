package wordpress

import "encoding/json"

// Envelope represents the Universal Response Envelope from the PHP plugin.
// It provides a backward-compatible parser that works with both legacy (flat)
// and envelope (PascalCase) response formats.
type Envelope struct {
	// Envelope fields (PascalCase)
	Status     *EnvelopeStatus     `json:"Status,omitempty"`
	Attributes *EnvelopeAttributes `json:"Attributes,omitempty"`
	Results    json.RawMessage     `json:"Results,omitempty"`
	Errors     *EnvelopeErrors     `json:"Errors,omitempty"`
}

// TypedEnvelope is the generic version of Envelope where Results is a typed slice.
type TypedEnvelope[T any] struct {
	Status     *EnvelopeStatus     `json:"Status,omitempty"`
	Attributes *EnvelopeAttributes `json:"Attributes,omitempty"`
	Results    []T                 `json:"Results,omitempty"`
	Errors     *EnvelopeErrors     `json:"Errors,omitempty"`
}

// EnvelopeStatus represents the Status block.
type EnvelopeStatus struct {
	IsSuccess bool   `json:"IsSuccess"`
	IsFailed  bool   `json:"IsFailed"`
	Code      int    `json:"Code"`
	Message   string `json:"Message"`
	Timestamp string `json:"Timestamp"`
}

// EnvelopeAttributes represents the Attributes block.
type EnvelopeAttributes struct {
	RequestedAt        string `json:"RequestedAt"`
	RequestDelegatedAt string `json:"RequestDelegatedAt"`
	HasAnyErrors       bool   `json:"HasAnyErrors"`
	IsSingle           bool   `json:"IsSingle"`
	IsMultiple         bool   `json:"IsMultiple"`
	TotalRecords       int    `json:"TotalRecords"`
	PerPage            int    `json:"PerPage"`
	TotalPages         int    `json:"TotalPages"`
	CurrentPage        int    `json:"CurrentPage"`
}

// EnvelopeErrors represents the Errors block.
type EnvelopeErrors struct {
	BackendMessage             string   `json:"BackendMessage"`
	DelegatedServiceErrorStack []string `json:"DelegatedServiceErrorStack"`
	Backend                    []string `json:"Backend"`
	Frontend                   []string `json:"Frontend"`
}

// IsEnvelope checks if a raw JSON body uses the envelope format
// by looking for the "Status" top-level key.
func IsEnvelope(data []byte) bool {
	var probe struct {
		Status *json.RawMessage `json:"Status"`
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
