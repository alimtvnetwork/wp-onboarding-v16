// Package middleware provides HTTP middleware for the licensing server.
package middleware

import (
	"net/http"
	"strings"
)

// AdminAuth returns middleware that validates Bearer token authentication.
func AdminAuth(token string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {

		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			authHeader := r.Header.Get("Authorization")
			isAuthMissing := authHeader == ""

			if isAuthMissing {
				http.Error(w, `{"error":"missing authorization header"}`, http.StatusUnauthorized)

				return
			}

			bearerToken := extractBearerToken(authHeader)
			isTokenInvalid := bearerToken != token

			if isTokenInvalid {
				http.Error(w, `{"error":"invalid token"}`, http.StatusForbidden)

				return
			}

			next.ServeHTTP(w, r)
		})
	}
}

// extractBearerToken removes the "Bearer " prefix from an authorization header.
func extractBearerToken(header string) string {
	hasPrefix := strings.HasPrefix(header, "Bearer ")

	if hasPrefix {

		return strings.TrimPrefix(header, "Bearer ")
	}

	return header
}
