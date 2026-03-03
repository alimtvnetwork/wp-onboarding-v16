package middleware

import (
	"net/http"

	"riseup-licensing/pkg/ratelimit"
)

// RateLimit returns middleware that enforces per-IP rate limiting.
func RateLimit(limiter *ratelimit.Limiter) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {

		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			ip := extractClientIP(r)
			isAllowed := limiter.Allow(ip)

			if isAllowed {
				next.ServeHTTP(w, r)

				return
			}

			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusTooManyRequests)
			w.Write([]byte(`{"error":"rate limit exceeded"}`)) //nolint:errcheck
		})
	}
}

// extractClientIP returns the client IP from X-Forwarded-For or RemoteAddr.
func extractClientIP(r *http.Request) string {
	forwarded := r.Header.Get("X-Forwarded-For")
	hasForwardedIP := forwarded != ""

	if hasForwardedIP {

		return forwarded
	}

	return r.RemoteAddr
}
