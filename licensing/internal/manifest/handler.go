package manifest

import (
	"encoding/json"
	"net/http"
)

// validateManifestResponse is the JSON envelope for the validation endpoint.
type validateManifestResponse struct {
	Success bool              `json:"success"`
	Result  *ValidationResult `json:"result,omitempty"`
	Error   string            `json:"error,omitempty"`
}

// HandleValidateManifest is an HTTP handler that accepts a manifest JSON body
// and returns validation results. Use with POST /api/v1/admin/manifest/validate.
func HandleValidateManifest(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	var m Manifest

	decodeErr := json.NewDecoder(r.Body).Decode(&m)
	isDecodeFailed := decodeErr != nil

	if isDecodeFailed {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(validateManifestResponse{ //nolint:errcheck
			Success: false,
			Error:   "invalid JSON: " + decodeErr.Error(),
		})

		return
	}

	result := Validate(&m)
	isInvalid := !result.Valid

	status := http.StatusOK

	if isInvalid {
		status = http.StatusUnprocessableEntity
	}

	w.WriteHeader(status)
	json.NewEncoder(w).Encode(validateManifestResponse{ //nolint:errcheck
		Success: result.Valid,
		Result:  &result,
	})
}
