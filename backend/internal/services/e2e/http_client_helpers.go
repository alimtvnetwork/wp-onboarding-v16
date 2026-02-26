package e2e

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
)

// do performs an HTTP request and returns the parsed response.
func (c *apiClient) do(method, path string, body any) (*apiResponse, error) {
	req, err := c.buildRequest(method, path, body)
	if err != nil {
		return nil, err
	}

	resp, err := c.client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("execute request: %w", err)
	}
	defer resp.Body.Close()

	return c.parseResponse(resp)
}

// buildRequest creates an http.Request with optional JSON body.
func (c *apiClient) buildRequest(method, path string, body any) (*http.Request, error) {
	url := fmt.Sprintf("%s/api/v1%s", c.baseURL, path)

	var reqBody io.Reader
	if body != nil {
		b, err := json.Marshal(body)
		if err != nil {
			return nil, fmt.Errorf("marshal request: %w", err)
		}
		reqBody = bytes.NewReader(b)
	}

	req, err := http.NewRequest(method, url, reqBody)
	if err != nil {
		return nil, fmt.Errorf("create request: %w", err)
	}
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	return req, nil
}

// parseResponse reads the response body and parses the JSON envelope.
func (c *apiClient) parseResponse(resp *http.Response) (*apiResponse, error) {
	rawBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read response: %w", err)
	}

	result := &apiResponse{
		StatusCode: resp.StatusCode,
		RawBody:    string(rawBytes),
	}

	var envelope struct {
		Success bool            `json:"success"` // external key
		Data    json.RawMessage `json:"data"`    // external key
		Error   json.RawMessage `json:"error"`   // external key
	}
	if json.Unmarshal(rawBytes, &envelope) == nil {
		result.Success = envelope.Success
		result.Data = envelope.Data
		result.Error = envelope.Error
	}

	return result, nil
}
