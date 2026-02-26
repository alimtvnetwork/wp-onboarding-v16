// Package e2e - HTTP client for making real API requests during E2E tests
package e2e

import (
	"encoding/json"
	"net/http"
	"time"
)

// apiClient wraps HTTP calls to the backend API
type apiClient struct {
	baseURL string
	client  *http.Client
}

func newAPIClient(baseURL string) *apiClient {
	return &apiClient{
		baseURL: baseURL,
		client: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

// apiResponse holds a parsed JSON API response
type apiResponse struct {
	StatusCode int
	Success    bool            `json:"success"` // external key (our own API envelope)
	Data       json.RawMessage `json:"data"`    // external key
	Error      json.RawMessage `json:"error"`   // external key
	RawBody    string
}

// apiErrorPayload is the typed structure for parsed error responses.
type apiErrorPayload struct {
	Code    string `json:"code"`    // external key (our own API envelope)
	Message string `json:"message"` // external key
}

// get performs a GET request
func (c *apiClient) get(path string) (*apiResponse, error) {
	return c.do("GET", path, nil)
}

// post performs a POST request with JSON body
func (c *apiClient) post(path string, body any) (*apiResponse, error) {
	return c.do("POST", path, body)
}

// put performs a PUT request with JSON body
func (c *apiClient) put(path string, body any) (*apiResponse, error) {
	return c.do("PUT", path, body)
}

// del performs a DELETE request
func (c *apiClient) del(path string) (*apiResponse, error) {
	return c.do("DELETE", path, nil)
}

// dataField extracts a string field from the data JSON object.
func (r *apiResponse) dataField(key string) string {
	if len(r.Data) == 0 {
		return ""
	}
	var m map[string]json.RawMessage
	if json.Unmarshal(r.Data, &m) != nil {
		return ""
	}
	raw, ok := m[key]
	if !ok {
		return ""
	}
	var s string
	if json.Unmarshal(raw, &s) == nil {
		return s
	}
	return ""
}

// dataFieldFloat extracts a float64 field from the data JSON object.
func (r *apiResponse) dataFieldFloat(key string) (float64, bool) {
	if len(r.Data) == 0 {
		return 0, false
	}
	var m map[string]json.RawMessage
	if json.Unmarshal(r.Data, &m) != nil {
		return 0, false
	}
	raw, ok := m[key]
	if !ok {
		return 0, false
	}
	var f float64
	if json.Unmarshal(raw, &f) == nil {
		return f, true
	}
	return 0, false
}

// hasDataField checks if the data JSON object contains a given key.
func (r *apiResponse) hasDataField(key string) bool {
	if len(r.Data) == 0 {
		return false
	}
	var m map[string]json.RawMessage
	if json.Unmarshal(r.Data, &m) != nil {
		return false
	}
	_, ok := m[key]
	return ok
}

// isDataMissing returns true if the data JSON object does NOT contain a given key.
func (r *apiResponse) isDataMissing(key string) bool { return !r.hasDataField(key) }

// isDataArray checks if the data is a non-empty JSON array.
func (r *apiResponse) isDataArray() bool {
	if len(r.Data) == 0 {
		return false
	}
	var arr []json.RawMessage
	return json.Unmarshal(r.Data, &arr) == nil && len(arr) > 0
}

// errorCode returns the error code from the response, or empty string.
func (r *apiResponse) errorCode() string {
	if len(r.Error) == 0 {
		return ""
	}
	var ep apiErrorPayload
	if json.Unmarshal(r.Error, &ep) == nil {
		return ep.Code
	}
	return ""
}
