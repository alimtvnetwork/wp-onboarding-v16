// Package hmac provides HMAC-SHA256 request signing and verification.
package hmac

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"math"
	"strconv"
	"time"
)

// MaxTimestampSkew is the maximum allowed time difference for request validation.
const MaxTimestampSkew = 5 * time.Minute

// Sign creates an HMAC-SHA256 signature for the given timestamp and body.
func Sign(secret string, timestamp int64, body []byte) string {
	payload := fmt.Sprintf("%d:%s", timestamp, string(body))

	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(payload))

	return hex.EncodeToString(mac.Sum(nil))
}

// Verify checks an HMAC-SHA256 signature against the expected value.
// It also validates that the timestamp is within the allowed skew window.
func Verify(secret, signature, timestampStr string, body []byte) bool {
	timestamp, parseErr := strconv.ParseInt(timestampStr, 10, 64)
	if parseErr != nil {
		return false
	}

	isTimestampStale := isOutsideSkewWindow(timestamp)

	if isTimestampStale {
		return false
	}

	expected := Sign(secret, timestamp, body)

	return hmac.Equal([]byte(expected), []byte(signature))
}

// isOutsideSkewWindow returns true if the timestamp is too far from now.
func isOutsideSkewWindow(timestamp int64) bool {
	now := time.Now().Unix()
	diff := math.Abs(float64(now - timestamp))

	return diff > MaxTimestampSkew.Seconds()
}
