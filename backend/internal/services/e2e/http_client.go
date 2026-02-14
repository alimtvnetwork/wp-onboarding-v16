// Package e2e - HTTP client for making real API requests during E2E tests
package e2e

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
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
	Success    bool              `json:"success"`
	Data       any               `json:"data"`
	Error      map[string]any    `json:"error"`
	RawBody    string
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

func (c *apiClient) do(method, path string, body any) (*apiResponse, error) {
	url := fmt.Sprintf("%s/api/v1%s", c.baseURL, path)

	var reqBody io.Reader
	var reqJSON []byte
	if body != nil {
		var err error
		reqJSON, err = json.Marshal(body)
		if err != nil {
			return nil, fmt.Errorf("marshal request: %w", err)
		}
		reqBody = bytes.NewReader(reqJSON)
	}

	req, err := http.NewRequest(method, url, reqBody)
	if err != nil {
		return nil, fmt.Errorf("create request: %w", err)
	}
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	resp, err := c.client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("execute request: %w", err)
	}
	defer resp.Body.Close()

	rawBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read response: %w", err)
	}

	result := &apiResponse{
		StatusCode: resp.StatusCode,
		RawBody:    string(rawBytes),
	}

	// Parse JSON response
	var parsed map[string]any
	if err := json.Unmarshal(rawBytes, &parsed); err == nil {
		if s, ok := parsed["success"].(bool); ok {
			result.Success = s
		}
		if d, ok := parsed["data"]; ok {
			result.Data = d
		}
		if e, ok := parsed["error"].(map[string]any); ok {
			result.Error = e
		}
	}

	return result, nil
}

// dataMap returns the response data as a map, or nil
func (r *apiResponse) dataMap() map[string]any {
	if m, ok := r.Data.(map[string]any); ok {
		return m
	}
	return nil
}

// dataSlice returns the response data as a slice, or nil
func (r *apiResponse) dataSlice() []any {
	if s, ok := r.Data.([]any); ok {
		return s
	}
	return nil
}

// errorCode returns the error code from the response, or empty string
func (r *apiResponse) errorCode() string {
	if r.Error != nil {
		if code, ok := r.Error["code"].(string); ok {
			return code
		}
	}
	return ""
}
