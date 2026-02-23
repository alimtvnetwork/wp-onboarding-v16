// Package handlers provides shared response helpers for HTTP API handlers.
// All helpers now emit the universal envelope format via the envelope package.
package handlers

import (
	"encoding/json"
	"errors"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	responsemessage "wp-plugin-publish/internal/enums/response_message"
	"wp-plugin-publish/internal/envelope"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// respondJSON writes a raw JSON response (used only for non-envelope responses like file downloads)
func respondJSON(w http.ResponseWriter, status int, data any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// respondSuccess writes a single-item success envelope.
// Generic: compile-time type checking on the data parameter.
func respondSuccess[T any](w http.ResponseWriter, data T) {
	envelope.Write(w, envelope.Success(data))
}

// respondCreated writes a 201 Created envelope.
// Generic: compile-time type checking on the data parameter.
func respondCreated[T any](w http.ResponseWriter, data T) {
	envelope.Write(w, envelope.Created(data))
}

// respondError writes an error envelope with auto-captured Go stack traces
func respondError(
	w http.ResponseWriter,
	status wordpress.HttpStatusType,
	code string,
	message string,
) {
	envelope.Write(w, envelope.ErrorWithStack(status.Int(), code, message))
}

// respondErrorWithSession writes an error envelope with session ID and stack traces.
// Extracts sessionId from apperror diagnostic if available.
func respondErrorWithSession(
	w http.ResponseWriter,
	status wordpress.HttpStatusType,
	code string,
	message string,
	err error,
) {
	resp := envelope.ErrorWithStack(status.Int(), code, message)
	if appErr, ok := err.(*apperror.AppError); ok {
		if appErr.Diagnostic.SessionId != "" {
			resp = resp.WithSessionId(appErr.Diagnostic.SessionId)
		}
	}

	envelope.Write(w, resp)
}

// respondDeleted writes a standard deletion success envelope
func respondDeleted(w http.ResponseWriter) {
	envelope.Write(w, envelope.Deleted())
}

// respondList writes a paginated list envelope.
// Generic: compile-time type checking on the data parameter.
// requestPath is the base URL path used to generate navigation URLs.
func respondList[T any](
	w http.ResponseWriter,
	data T,
	pg envelope.Pagination,
	requestPath string,
) {
	envelope.Write(w, envelope.List(data, pg, requestPath))
}

// respondListUnpaginated writes an unpaginated list envelope.
// Generic: compile-time type checking on the data parameter.
func respondListUnpaginated[T any](w http.ResponseWriter, data T, count int) {
	envelope.Write(w, envelope.ListUnpaginated(data, count))
}

// getIDParam extracts an ID parameter from the URL
func getIDParam(r *http.Request, name string) (int64, error) {
	vars := mux.Vars(r)

	return strconv.ParseInt(vars[name], 10, 64)
}

// requireService checks if a service is non-nil, writing 503 if unavailable.
// Returns true if the service is available.
func requireService(w http.ResponseWriter, service any, name string) bool {
	if service == nil {
		respondError(
			w,
			wordpress.HttpStatusServiceUnavailable,
			"E9001",
			responsemessage.ServiceNotAvailable.String(),
		)

		return false
	}

	return true
}

// decodeJSON decodes a JSON request body into target. Returns false and writes
// a 400 error response if decoding fails.
func decodeJSON(w http.ResponseWriter, r *http.Request, target any) bool {
	if err := json.NewDecoder(r.Body).Decode(target); err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1001",
			responsemessage.InvalidRequestBody.String(),
		)

		return false
	}

	return true
}

// parseID extracts a URL path param as int64. Returns false and writes 400 on failure.
func parseID(w http.ResponseWriter, r *http.Request, paramName, label string) (int64, bool) {
	id, err := getIDParam(r, paramName)
	if err != nil {
		respondError(
			w,
			wordpress.HttpStatusBadRequest,
			"E1002",
			responsemessage.InvalidId.String(),
		)

		return 0, false
	}

	return id, true
}

// decodeJSONSilent decodes a JSON request body without writing an error response.
// Returns nil on success, error on failure. Used by optional body decoders.
func decodeJSONSilent(r *http.Request, target any) error {
	return json.NewDecoder(r.Body).Decode(target)
}

// resolveHTTPStatus extracts the HTTP status code from a WordPress APIError
// wrapped inside an apperror chain. Returns fallback if no APIError is found.
// This ensures that PHP-side 404s are forwarded to the frontend instead of
// being masked as 500 Internal Server Error.
func resolveHTTPStatus(err error, fallback wordpress.HttpStatusType) wordpress.HttpStatusType {
	// Check direct APIError
	var apiErr *wordpress.APIError
	if errors.As(err, &apiErr) && apiErr.StatusCode > 0 {
		return wordpress.HttpStatusType(apiErr.StatusCode)
	}

	// Check apperror wrapping an APIError
	var appErr *apperror.AppError
	if errors.As(err, &appErr) && appErr.Unwrap() != nil {
		var inner *wordpress.APIError
		if errors.As(appErr.Unwrap(), &inner) && inner.StatusCode > 0 {
			return wordpress.HttpStatusType(inner.StatusCode)
		}
	}

	return fallback
}
