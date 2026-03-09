// Package services provides core business logic for the licensing server.
package services

import (
	"crypto/rand"
	"fmt"
	"strings"

	"riseup-licensing/pkg/apperror"
)

// KeyPrefix is the standard license key prefix.
const KeyPrefix = "RISEUP"

// keyChars contains the allowed characters for license key segments.
// Ambiguous characters (0, O, 1, I, l, L) are excluded.
const keyChars = "ABCDEFGHJKMNPQRSTUVWXYZ23456789"

// GenerateKey creates a new license key in format RISEUP-XXXX-XXXX-XXXX-XXXX.
func GenerateKey() apperror.Result[string] {
	segments := make([]string, 4)

	for i := range segments {
		segment, segErr := generateSegment(4)
		if segErr != nil {

			return apperror.FailWrap[string](segErr, apperror.ErrKeyGeneration, fmt.Sprintf("generate segment %d", i))
		}

		segments[i] = segment
	}

	key := KeyPrefix + "-" + strings.Join(segments, "-")

	return apperror.Ok(key)
}

// generateSegment creates a random alphanumeric segment of the given length.
func generateSegment(length int) (string, error) {
	buf := make([]byte, length)
	_, readErr := rand.Read(buf)

	if readErr != nil {
		return "", fmt.Errorf("read random bytes: %w", readErr)
	}

	result := make([]byte, length)
	charsLen := len(keyChars)

	for i, b := range buf {
		result[i] = keyChars[int(b)%charsLen]
	}

	return string(result), nil
}
