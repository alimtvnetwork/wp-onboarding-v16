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
	RequestedAt      string `json:"RequestedAt"`
	RequestDelegatedAt string `json:"RequestDelegatedAt"`
	HasAnyErrors     bool   `json:"HasAnyErrors"`
	IsSingle         bool   `json:"IsSingle"`
	IsMultiple       bool   `json:"IsMultiple"`
	TotalRecords     int    `json:"TotalRecords"`
	PerPage          int    `json:"PerPage"`
	TotalPages       int    `json:"TotalPages"`
	CurrentPage      int    `json:"CurrentPage"`
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

// ParseEnvelope attempts to parse the body as an envelope.
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

// UnwrapResults extracts the Results array and unmarshals into the target.
// If the data is in legacy format (no envelope), it returns false so the
// caller can fall back to legacy parsing.
func UnwrapResults(data []byte, target interface{}) bool {
	env := ParseEnvelope(data)
	if env == nil || env.Results == nil {
		return false
	}
	return json.Unmarshal(env.Results, target) == nil
}

// UnwrapSingleResult extracts the first item from Results into target.
// Returns false if not envelope format or Results is empty.
func UnwrapSingleResult(data []byte, target interface{}) bool {
	env := ParseEnvelope(data)
	if env == nil || env.Results == nil {
		return false
	}
	var items []json.RawMessage
	if json.Unmarshal(env.Results, &items) != nil || len(items) == 0 {
		return false
	}
	return json.Unmarshal(items[0], target) == nil
}
