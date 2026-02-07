// Package handlers provides shared response helpers for HTTP API handlers
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"
	"time"

	"github.com/gorilla/mux"
)

// respondJSON writes a JSON response
func respondJSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// respondSuccess writes a successful API response
func respondSuccess(w http.ResponseWriter, data interface{}) {
	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"data":    data,
	})
}

// respondCreated writes a 201 Created API response
func respondCreated(w http.ResponseWriter, data interface{}) {
	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"data":    data,
	})
}

// respondError writes an error API response
func respondError(w http.ResponseWriter, status int, code, message string) {
	respondJSON(w, status, map[string]interface{}{
		"success": false,
		"error": map[string]interface{}{
			"code":      code,
			"message":   message,
			"timestamp": time.Now().Format(time.RFC3339),
		},
	})
}

// respondDeleted writes a standard deletion success response
func respondDeleted(w http.ResponseWriter) {
	respondSuccess(w, map[string]interface{}{"deleted": true})
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
