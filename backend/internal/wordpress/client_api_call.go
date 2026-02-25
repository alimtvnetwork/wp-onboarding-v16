package wordpress

import (
	"encoding/json"
	"io"

	"wp-plugin-publish/pkg/apperror"
)

// apiCallInput holds common parameters for a WordPress REST API call.
type apiCallInput struct {
	Method     string
	Endpoint   string
	Body       any
	Operation  string
	OkStatuses []int  // defaults to [200] if empty
	PluginSlug string // optional: populates APIError.PluginSlugIn
	ErrorCode  string // optional: apperror wrap code (defaults to ErrInternal)
}

// doAPICallRaw sends the request, checks the status code, and returns raw body bytes on success.
func (c *Client) doAPICallRaw(input apiCallInput) ([]byte, error) {
	resp, err := c.request(input.Method, input.Endpoint, input.Body)
	if err != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)
		return nil, apperror.Wrap(err, code, input.Operation)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if !isOkStatus(resp.StatusCode, input.OkStatuses) {
		return nil, c.buildCallError(input, resp.StatusCode, bodyBytes)
	}

	return bodyBytes, nil
}

// buildCallError constructs an APIError from a failed API call.
func (c *Client) buildCallError(input apiCallInput, statusCode int, body []byte) *APIError {
	return &APIError{
		Operation:    input.Operation,
		Method:       input.Method,
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
