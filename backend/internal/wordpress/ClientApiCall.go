package wordpress

import (
	"encoding/json"
	"io"
	"net/http"

	"wp-plugin-publish/internal/enums/http_method"
	"wp-plugin-publish/pkg/apperror"
)

// apiCallInput holds common parameters for a WordPress REST API call.
type apiCallInput struct {
	Method     httpmethod.Variant
	Endpoint   string
	Body       any
	Operation  string
	OkStatuses []int  // defaults to [200] if empty
	PluginSlug string // optional: populates APIError.PluginSlugIn
	ErrorCode  string // optional: apperror wrap code (defaults to ErrInternal)
}

// APICallResponse holds the raw body and status code from an API call.
type APICallResponse struct {
	Body       []byte
	StatusCode int
}

// doAPICallWithStatus sends the request and returns the raw response.
// Unlike doAPICallRaw, it does NOT validate the status code — the caller decides how to handle it.
// The error return is only for transport-level failures (DNS, timeout, request creation).
func (c *Client) doAPICallWithStatus(input apiCallInput) (*APICallResponse, error) {
	resp, err := c.request(input.Method.Value(), input.Endpoint, input.Body)
	if err != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)

		return nil, apperror.Wrap(err, code, input.Operation)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	result := &APICallResponse{
		Body:       bodyBytes,
		StatusCode: resp.StatusCode,
	}
	return result, nil
}

// doAPICallRaw sends the request, checks the status code, and returns raw body bytes on success.
func (c *Client) doAPICallRaw(input apiCallInput) ([]byte, error) {
	callResp, err := c.doAPICallWithStatus(input)
	if err != nil {
		return nil, err
	}

	if isErrorStatus(callResp.StatusCode, input.OkStatuses) {
		return nil, c.buildCallError(input, callResp.StatusCode, callResp.Body)
	}

	return callResp.Body, nil
}

// doAPICallStream sends the request, validates the status code, and returns the raw HTTP response.
// The caller is responsible for closing the response body. Use this for streaming responses (e.g. ZIP downloads).
func (c *Client) doAPICallStream(input apiCallInput) (*http.Response, error) {
	resp, err := c.request(input.Method.Value(), input.Endpoint, input.Body)
	if err != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)

		return nil, apperror.Wrap(err, code, input.Operation)
	}

	if isErrorStatus(resp.StatusCode, input.OkStatuses) {
		bodyBytes, _ := io.ReadAll(resp.Body)
		resp.Body.Close()

		return nil, c.buildCallError(input, resp.StatusCode, bodyBytes)
	}

	return resp, nil
}

// buildCallError constructs an APIError from a failed API call.
func (c *Client) buildCallError(input apiCallInput, statusCode int, body []byte) *APIError {
	return &APIError{
		Operation:    input.Operation,
		Method:       input.Method.Value(),
		Endpoint:     input.Endpoint,
		Url:          c.fullURL(input.Endpoint),
		StatusCode:   statusCode,
		ResponseBody: truncateBody(string(body), 8192),
		PluginSlugIn: input.PluginSlug,
	}
}

// isOkStatus checks whether statusCode is in the accepted list (defaults to 200).
func isOkStatus(statusCode int, okStatuses []int) bool {
	if len(okStatuses) == 0 {
		return statusCode == HttpStatusOk.Int()
	}
	for _, ok := range okStatuses {
		if statusCode == ok {
			return true
		}
	}
	return false
}

// isErrorStatus checks whether statusCode is NOT in the accepted list.
func isErrorStatus(statusCode int, okStatuses []int) bool {
	return !isOkStatus(statusCode, okStatuses)
}

// doAPICall sends a request, checks status, and JSON-decodes the response into T.
func doAPICall[T any](c *Client, input apiCallInput) (*T, error) {
	data, err := c.doAPICallRaw(input)
	if err != nil {
		return nil, err
	}
	return decodeAPIResponse[T](data, input.Operation)
}

// decodeAPIResponse unmarshals raw JSON bytes into *T.
func decodeAPIResponse[T any](data []byte, operation string) (*T, error) {
	var result T
	if err := json.Unmarshal(data, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode "+operation)
	}
	return &result, nil
}

// firstNonEmpty returns the first non-empty string argument.
func firstNonEmpty(values ...string) string {
	for _, v := range values {
		if v != "" {
			return v
		}
	}
	return ""
}
