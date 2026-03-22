// PhpEnvelope provides a generic typed wrapper for PHP REST API envelope responses.
//
// PHP endpoints return responses in a standard envelope format:
//
//	{
//	    "Status":     { "IsSuccess": true, ... },
//	    "Attributes": { "IsSingle": true, ... },
//	    "Results":    [ <inner data> ]
//	}
//
// Using PhpEnvelope[T] with DoApiCall allows typed deserialization of the inner
// data, eliminating the need for map[string]any + UnwrapPhpEnvelope.
package wordpress

import (
	"wp-plugin-publish/pkg/apperror"
)

// PhpEnvelope is a generic typed wrapper for PHP REST API responses.
// T is the type of each item in the Results array.
type PhpEnvelope[T any] struct {
	Status     PhpEnvelopeStatus     `json:"Status"`
	Attributes PhpEnvelopeAttributes `json:"Attributes"`
	Results    []T                    `json:"Results"`
}

// PhpEnvelopeStatus holds the status section of a PHP envelope response.
type PhpEnvelopeStatus struct {
	IsSuccess bool   `json:"IsSuccess"`
	Code      int    `json:"Code,omitempty"`
	Message   string `json:"Message,omitempty"`
}

// PhpEnvelopeAttributes holds the attributes section of a PHP envelope response.
type PhpEnvelopeAttributes struct {
	IsSingle   bool   `json:"IsSingle,omitempty"`
	Count      int    `json:"Count,omitempty"`
	ResultType string `json:"ResultType,omitempty"`
}

// UnwrapPhpResult extracts the first result from a typed PHP envelope.
// Returns an error if the Results array is empty.
func UnwrapPhpResult[T any](envelope PhpEnvelope[T]) (T, *apperror.AppError) {
	hasResults := len(envelope.Results) > 0

	if !hasResults {
		var zero T

		return zero, apperror.New(apperror.ErrConfigParse, "PHP envelope returned empty Results")
	}

	return envelope.Results[0], nil
}

// UnwrapPhpResultOrDefault extracts the first result, returning the default value if empty.
func UnwrapPhpResultOrDefault[T any](envelope PhpEnvelope[T], defaultVal T) T {
	hasResults := len(envelope.Results) > 0

	if !hasResults {
		return defaultVal
	}

	return envelope.Results[0]
}

// UnwrapPhpResults returns the full Results slice from a typed PHP envelope.
func UnwrapPhpResults[T any](envelope PhpEnvelope[T]) []T {
	return envelope.Results
}
