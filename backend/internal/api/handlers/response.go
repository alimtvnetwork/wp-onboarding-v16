// Package handlers provides shared response helpers for HTTP API handlers.
// All helpers now emit the universal envelope format via the envelope package.
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"wp-plugin-publish/internal/envelope"
)

// respondJSON writes a raw JSON response (used only for non-envelope responses like file downloads)
func respondJSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// respondSuccess writes a single-item success envelope
func respondSuccess(w http.ResponseWriter, data interface{}) {
	envelope.Write(w, envelope.Success(data))
}

// respondCreated writes a 201 Created envelope
func respondCreated(w http.ResponseWriter, data interface{}) {
	envelope.Write(w, envelope.Created(data))
}

// respondError writes an error envelope
func respondError(w http.ResponseWriter, status int, code, message string) {
	envelope.Write(w, envelope.Error(status, code, message))
}

// respondDeleted writes a standard deletion success envelope
func respondDeleted(w http.ResponseWriter) {
	envelope.Write(w, envelope.Deleted())
}

// respondList writes a paginated list envelope.
// requestPath is the base URL path used to generate navigation URLs.
func respondList(w http.ResponseWriter, data interface{}, pg envelope.Pagination, requestPath string) {
	envelope.Write(w, envelope.List(data, pg, requestPath))
}

// respondListUnpaginated writes an unpaginated list envelope
func respondListUnpaginated(w http.ResponseWriter, data interface{}, count int) {
	envelope.Write(w, envelope.ListUnpaginated(data, count))
}

// getIDParam extracts an ID parameter from the URL
func getIDParam(r *http.Request, name string) (int64, error) {
	vars := mux.Vars(r)
	return strconv.ParseInt(vars[name], 10, 64)
}

// requireService checks if a service is non-nil, writing 503 if unavailable.
// Returns true if the service is available.
func requireService(w http.ResponseWriter, service interface{}, name string) bool {
	if service == nil {
		respondError(w, http.StatusServiceUnavailable, "E9001", name+" not available")
		return false
	}
	return true
}

// decodeJSON decodes a JSON request body into target. Returns false and writes
// a 400 error response if decoding fails.
func decodeJSON(w http.ResponseWriter, r *http.Request, target interface{}) bool {
	if err := json.NewDecoder(r.Body).Decode(target); err != nil {
		respondError(w, http.StatusBadRequest, "E1001", "Invalid request body")
		return false
	}
	return true
}

// parseID extracts a URL path param as int64. Returns false and writes 400 on failure.
func parseID(w http.ResponseWriter, r *http.Request, paramName, label string) (int64, bool) {
	id, err := getIDParam(r, paramName)
	if err != nil {
		respondError(w, http.StatusBadRequest, "E1002", "Invalid "+label)
		return 0, false
	}
	return id, true
}

// decodeJSONSilent decodes a JSON request body without writing an error response.
// Returns nil on success, error on failure. Used by optional body decoders.
func decodeJSONSilent(r *http.Request, target interface{}) error {
	return json.NewDecoder(r.Body).Decode(target)
}
