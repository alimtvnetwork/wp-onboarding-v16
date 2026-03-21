package wordpress

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"

	"wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// ApiCallInput holds common parameters for a WordPress REST API call.
type ApiCallInput struct {
	Method     httpmethodtype.Variant
	Endpoint   string
	Body       any
	Operation  operationtype.Variant
	OkStatuses []int  // defaults to [200] if empty
	PluginSlug string // optional: populates ApiError.PluginSlugIn
	ErrorCode  apperror.ErrorCode // optional: apperror wrap code (defaults to ErrInternal)
}

// ApiCallResponse holds the raw body and status code from an API call.
type ApiCallResponse struct {
	Body       []byte
	StatusCode int
}

// doApiCallWithStatus sends the request and returns the raw response.
// Unlike doApiCallRaw, it does NOT validate the status code — the caller decides how to handle it.
// The error return is only for transport-level failures (DNS, timeout, request creation).
func (c *Client) doApiCallWithStatus(input ApiCallInput) apperror.Result[ApiCallResponse] {
	resp, appErr := c.request(input.Method.Value(), input.Endpoint, input.Body)
	if appErr != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)

		return apperror.FailWrap[ApiCallResponse](appErr, code, input.Operation.Value())
	}
	defer resp.Body.Close()

	bodyBytes, readErr := io.ReadAll(resp.Body)
	if readErr != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)

		return apperror.FailWrap[ApiCallResponse](readErr, code, "read response body")
	}

	return apperror.Ok(ApiCallResponse{
		Body:       bodyBytes,
		StatusCode: resp.StatusCode,
	})
}

// doApiCallRaw sends the request, checks the status code, and returns raw body bytes on success.
func (c *Client) doApiCallRaw(input ApiCallInput) apperror.Result[[]byte] {
	callResult := c.doApiCallWithStatus(input)
	if callResult.HasError() {
		return apperror.Fail[[]byte](callResult.AppError())
	}

	resp := callResult.Value()

	if isErrorStatus(resp.StatusCode, input.OkStatuses) {
		return apperror.Fail[[]byte](c.buildCallError(input, resp.StatusCode, resp.Body))
	}

	// Detect HTML responses early — WordPress returns HTML for unregistered REST routes.
	if htmlErr := detectHtmlResponse(resp.Body, input.Endpoint, input.Operation); htmlErr != nil {
		return apperror.Fail[[]byte](htmlErr)
	}

	return apperror.Ok(resp.Body)
}

// doApiCallStream sends the request, validates the status code, and returns the raw HTTP response.
// The caller is responsible for closing the response body. Use this for streaming responses (e.g. ZIP downloads).
func (c *Client) doApiCallStream(input ApiCallInput) apperror.Result[*http.Response] {
	resp, appErr := c.request(input.Method.Value(), input.Endpoint, input.Body)
	if appErr != nil {
		code := firstNonEmpty(input.ErrorCode, apperror.ErrInternal)

		return apperror.FailWrap[*http.Response](appErr, code, input.Operation.Value())
	}

	if isErrorStatus(resp.StatusCode, input.OkStatuses) {
		bodyBytes, readErr := io.ReadAll(resp.Body)
		if readErr != nil {
			resp.Body.Close()

			return apperror.FailWrap[*http.Response](readErr, apperror.ErrInternal, "read error response body")
		}
		resp.Body.Close()

		return apperror.Fail[*http.Response](c.buildCallError(input, resp.StatusCode, bodyBytes))
	}

	return apperror.Ok(resp)
}

// buildCallError constructs an AppError from a failed API call, wrapping the structured ApiError.
func (c *Client) buildCallError(input ApiCallInput, statusCode int, body []byte) *apperror.AppError {
	apiErr := &ApiError{
		Operation:    input.Operation.Value(),
		Method:       input.Method.Value(),
		Endpoint:     input.Endpoint,
		Url:          c.fullUrl(input.Endpoint),
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

// DoApiCall sends a request, checks status, and JSON-decodes the response into T.
func DoApiCall[T any](c *Client, input ApiCallInput) apperror.Result[T] {
	rawResult := c.doApiCallRaw(input)
	if rawResult.HasError() {
		return apperror.Fail[T](rawResult.AppError())
	}

	return decodeApiResponse[T](rawResult.Value(), input.Operation.Value())
}

// decodeApiResponse unmarshals raw JSON bytes into T.
func decodeApiResponse[T any](data []byte, operationDesc string) apperror.Result[T] {
	// Guard against HTML before JSON decode — clearer than "invalid character '<'"
	if htmlErr := detectHtmlResponse(data, "", operationtype.Invalid); htmlErr != nil {
		return apperror.Fail[T](htmlErr)
	}

	var result T
	err := json.Unmarshal(data, &result)

	if err != nil {
		return apperror.FailWrap[T](err, apperror.ErrInternal, "decode "+operationDesc)
	}

	return apperror.Ok(result)
}

// detectHtmlResponse checks if response bytes look like HTML instead of JSON,
// which indicates the WordPress REST API namespace/endpoint is not registered.
// Returns nil if the response is not HTML.
func detectHtmlResponse(body []byte, endpoint string, op operationtype.Variant) *apperror.AppError {
	if len(body) == 0 {
		return nil
	}

	peek := strings.TrimSpace(string(body[:min(len(body), 512)]))
	lower := strings.ToLower(peek[:min(len(peek), 200)])

	isHtml := strings.HasPrefix(peek, "<") ||
		strings.HasPrefix(lower, "<!doctype") ||
		strings.Contains(lower, "<html")

	if !isHtml {
		return nil
	}

	msg := "received HTML instead of JSON — the REST API endpoint is not registered on this WordPress site"
	if endpoint != "" {
		msg = fmt.Sprintf("%s (endpoint: %s)", msg, endpoint)
	}
	msg += ". Verify the plugin is installed, activated, and the API namespace matches"

	return apperror.New(apperror.ErrWPEndpointMismatch, msg)
}

// firstNonEmpty returns the first non-empty ErrorCode argument.
func firstNonEmpty(values ...apperror.ErrorCode) apperror.ErrorCode {
	for _, v := range values {
		hasValue := v != ""

		if hasValue {
			return v
		}
	}

	return ""
}
