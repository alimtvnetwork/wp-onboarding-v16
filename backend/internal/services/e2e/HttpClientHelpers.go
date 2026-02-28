package e2e

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"

	"wp-plugin-publish/pkg/apperror"
)

// do performs an HTTP request and returns the parsed response.
func (c *apiClient) do(method, path string, body any) (*apiResponse, *apperror.AppError) {
	req, appErr := c.buildRequest(method, path, body)

	if appErr != nil {
		return nil, appErr
	}

	resp, err := c.client.Do(req)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrE2ERequest, "execute request")
	}
	defer resp.Body.Close()

	return c.parseResponse(resp)
}

// buildRequest creates an http.Request with optional JSON body.
func (c *apiClient) buildRequest(method, path string, body any) (*http.Request, *apperror.AppError) {
	url := fmt.Sprintf("%s/api/v1%s", c.baseUrl, path)

	var reqBody io.Reader
	if body != nil {
		b, err := json.Marshal(body)

		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrE2ERequest, "marshal request")
		}
		reqBody = bytes.NewReader(b)
	}

	req, err := http.NewRequest(method, url, reqBody)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrE2ERequest, "create request")
	}

	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	return req, nil
}

// parseResponse reads the response body and parses the JSON envelope.
func (c *apiClient) parseResponse(resp *http.Response) (*apiResponse, *apperror.AppError) {
	rawBytes, err := io.ReadAll(resp.Body)

	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrE2ERequest, "read response")
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
