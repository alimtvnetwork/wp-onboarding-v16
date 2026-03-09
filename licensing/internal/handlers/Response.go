// Package handlers provides HTTP handlers for the licensing API.
package handlers

import (
	"encoding/json"
	"net/http"

	"riseup-licensing/internal/models"
)

// errorBody is the JSON response for error messages.
type errorBody struct {
	Error string `json:"error"`
}

// statusBody is the JSON response for simple status messages.
type statusBody struct {
	Status string `json:"status"`
}

// licenseStatusResponse is the JSON response for the license status endpoint.
type licenseStatusResponse struct {
	License     *models.License      `json:"license"`
	Activations []models.Activation  `json:"activations"`
}

// jsonResponse writes a JSON response with the given status code.
func jsonResponse(
	w http.ResponseWriter,
	status int,
	data any,
) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data) //nolint:errcheck
}

// errorResponse writes a JSON error response.
func errorResponse(
	w http.ResponseWriter,
	status int,
	message string,
) {
	jsonResponse(w, status, errorBody{Error: message})
}

// decodeJSON reads and parses a JSON request body into the target.
func decodeJSON(r *http.Request, target any) error {

	return json.NewDecoder(r.Body).Decode(target)
}
