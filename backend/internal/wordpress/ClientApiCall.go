package wordpress

import (
	"encoding/json"
	"io"
	"net/http"

	"wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// apiCallInput holds common parameters for a WordPress REST API call.
type apiCallInput struct {
	Method     httpmethodtype.Variant
	Endpoint   string
	Body       any
	Operation  operationtype.Variant
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
func (c *Client) doAPICallWithStatus(input apiCallInput) apperror.Result[APICallResponse] {
	resp, appErr := c.request(input.Method.Value(), input.Endpoint, input.Body)
	if appErr != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)

		return apperror.FailWrap[APICallResponse](appErr, code, input.Operation.Value())
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	return apperror.Ok(APICallResponse{
		Body:       bodyBytes,
		StatusCode: resp.StatusCode,
	})
}

// doAPICallRaw sends the request, checks the status code, and returns raw body bytes on success.
func (c *Client) doAPICallRaw(input apiCallInput) apperror.Result[[]byte] {
	callResult := c.doAPICallWithStatus(input)
	if callResult.HasError() {
		return apperror.Fail[[]byte](callResult.AppError())
	}

	resp := callResult.Value()

	if isErrorStatus(resp.StatusCode, input.OkStatuses) {
		return apperror.Fail[[]byte](c.buildCallError(input, resp.StatusCode, resp.Body))
	}

	return apperror.Ok(resp.Body)
}

// doAPICallStream sends the request, validates the status code, and returns the raw HTTP response.
// The caller is responsible for closing the response body. Use this for streaming responses (e.g. ZIP downloads).
func (c *Client) doAPICallStream(input apiCallInput) apperror.Result[*http.Response] {
	resp, appErr := c.request(input.Method.Value(), input.Endpoint, input.Body)
	if appErr != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)

		return apperror.FailWrap[*http.Response](appErr, code, input.Operation.Value())
	}

	if isErrorStatus(resp.StatusCode, input.OkStatuses) {
		bodyBytes, _ := io.ReadAll(resp.Body)
		resp.Body.Close()

		return apperror.Fail[*http.Response](c.buildCallError(input, resp.StatusCode, bodyBytes))
	}

	return apperror.Ok(resp)
}

// buildCallError constructs an AppError from a failed API call, wrapping the structured APIError.
func (c *Client) buildCallError(input apiCallInput, statusCode int, body []byte) *apperror.AppError {
	apiErr := &APIError{
		Operation:    input.Operation.Value(),
		Method:       input.Method.Value(),
		Endpoint:     input.Endpoint,
		Url:          c.fullURL(input.Endpoint),
		StatusCode:   statusCode,
		ResponseBody: truncateBody(string(body), 8192),
		PluginSlugIn: input.PluginSlug,
	}

	code := firstNonEmpty(input.ErrorCode, apperror.ErrWPConnection)

	return apperror.Wrap(apiErr, code, input.Operation.Value())
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
func doAPICall[T any](c *Client, input apiCallInput) apperror.Result[T] {
	rawResult := c.doAPICallRaw(input)
	if rawResult.HasError() {
		return apperror.Fail[T](rawResult.AppError())
	}

	return decodeAPIResponse[T](rawResult.Value(), input.Operation.Value())
}

// decodeAPIResponse unmarshals raw JSON bytes into T.
func decodeAPIResponse[T any](data []byte, operationDesc string) apperror.Result[T] {
	var result T
	if err := json.Unmarshal(data, &result); err != nil {
		return apperror.FailWrap[T](err, apperror.ErrInternal, "decode "+operationDesc)
	}

	return apperror.Ok(result)
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
