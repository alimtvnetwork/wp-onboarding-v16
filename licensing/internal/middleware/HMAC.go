package middleware

import (
	"io"
	"net/http"

	licensehmac "riseup-licensing/pkg/hmac"
)

// HMACAuth returns middleware that verifies HMAC-SHA256 request signatures.
// Expects headers: X-Signature (hex HMAC), X-Timestamp (unix seconds).
func HMACAuth(secret string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {

		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			signature := r.Header.Get("X-Signature")
			timestamp := r.Header.Get("X-Timestamp")

			isMissingHeaders := signature == "" || timestamp == ""

			if isMissingHeaders {
				http.Error(w, `{"error":"missing signature headers"}`, http.StatusUnauthorized)

				return
			}

			body := readBody(r)
			isValid := licensehmac.Verify(secret, signature, timestamp, body)

			if isValid {
				next.ServeHTTP(w, r)

				return
			}

			http.Error(w, `{"error":"invalid signature"}`, http.StatusForbidden)
		})
	}
}

// readBody reads and returns the request body bytes.
// For GET requests with no body, returns an empty slice.
func readBody(r *http.Request) []byte {
	isNoBody := r.Body == nil

	if isNoBody {

		return []byte{}
	}

	body, readErr := io.ReadAll(r.Body)
	if readErr != nil {

		return []byte{}
	}

	return body
}
