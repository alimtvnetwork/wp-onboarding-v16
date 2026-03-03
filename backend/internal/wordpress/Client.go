// Package wordpress provides a client for the WordPress REST API
package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"io"
	"net/http"
	"net/url"
	"os"
	"strings"
	"time"

	contenttype "wp-plugin-publish/internal/enums/contenttype"
	header "wp-plugin-publish/internal/enums/headertype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/pkg/apperror"
)

// sourceMachineHostname caches the machine hostname for header attribution.
// Computed once at package init to avoid repeated syscalls.
var sourceMachineHostname string

func init() {
	var err error
	sourceMachineHostname, err = os.Hostname()

	hasHostnameError :=
		err != nil ||
		sourceMachineHostname == ""

	if hasHostnameError {
		sourceMachineHostname = "unknown"
	}
}

// ProgressDetails is defined in progress_details.go as json.RawMessage.
// All call sites MUST use toProgress() with a typed struct.

// ClientConfig holds WordPress client configuration
type ClientConfig struct {
	BaseUrl         string
	Username        string
	Password        string
	Timeout         time.Duration
	StackTraceDepth int
	OnProgress      func(event ProgressEvent)
}

// Client is a WordPress REST API client
type Client struct {
	baseUrl         string
	username        string
	password        string
	stackTraceDepth int
	httpClient      *http.Client
	onProgress      func(event ProgressEvent)
}

// NewClient creates a new WordPress API client
func NewClient(cfg ClientConfig) *Client {
	depth := cfg.StackTraceDepth
	isDepthMissing := depth <= 0

	if isDepthMissing {
		depth = DefaultStackTraceDepth
	}

	return &Client{
		baseUrl:  strings.TrimSuffix(cfg.BaseUrl, "/"),
		username: cfg.Username,
		password: cfg.Password,
		stackTraceDepth: depth,
		httpClient: &http.Client{
			Timeout: cfg.Timeout,
		},
		onProgress: cfg.OnProgress,
	}
}

// progress reports progress if a callback is set
func (c *Client) progress(event ProgressEvent) {
	if c.onProgress != nil {
		c.onProgress(event)
	}
}

// setStandardHeaders applies all standard headers to an HTTP request including
// authentication, content type, user agent, and source machine identification.
func (c *Client) setStandardHeaders(req *http.Request, contentType string) {
	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set(header.Authorization.Value(), "Basic "+auth)
	req.Header.Set(header.ContentType.Value(), contentType)
	req.Header.Set(header.UserAgent.Value(), header.UserAgentValue.Value())
	req.Header.Set(header.SourceMachine.Value(), sourceMachineHostname)
}

// marshalBody encodes the body to JSON if non-nil.
func marshalBody(body any) (io.Reader, *apperror.AppError) {
	isBodyEmpty := body == nil

	if isBodyEmpty {
		return nil, nil
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to marshal request body")
	}

	return bytes.NewReader(jsonBody), nil
}

// request makes an authenticated HTTP request to the WordPress API
func (c *Client) request(method, endpoint string, body any) (*http.Response, *apperror.AppError) {
	bodyReader, appErr := marshalBody(body)
	if appErr != nil {
		return nil, appErr
	}

	return c.buildAndSendRequest(method, endpoint, bodyReader)
}

// buildAndSendRequest creates and sends an HTTP request with standard headers.
func (c *Client) buildAndSendRequest(method, endpoint string, body io.Reader) (*http.Response, *apperror.AppError) {
	fullUrl := BuildWpJsonUrl(c.baseUrl, endpoint)
	req, err := http.NewRequest(method, fullUrl, body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create HTTP request").
			WithUrl(fullUrl).
			WithMethod(method)
	}

	c.setStandardHeaders(req, contenttype.Json.Value())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "HTTP request failed").
			WithUrl(fullUrl).
			WithMethod(method)
	}

	return resp, nil
}

// multipartInput bundles parameters for requestMultipart.
type multipartInput struct {
	Method      httpmethod.Variant
	Endpoint    string
	Body        io.Reader
	ContentType string
}

// requestMultipart sends a multipart HTTP request (for file uploads).
func (c *Client) requestMultipart(input multipartInput) (*http.Response, *apperror.AppError) {
	fullUrl := BuildWpJsonUrl(c.baseUrl, input.Endpoint)
	req, err := http.NewRequest(input.Method.Value(), fullUrl, input.Body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create HTTP request").
			WithUrl(fullUrl).
			WithMethod(input.Method.Value())
	}

	c.setStandardHeaders(req, input.ContentType)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "multipart HTTP request failed").
			WithUrl(fullUrl).
			WithMethod(input.Method.Value())
	}

	return resp, nil
}

func (c *Client) fullUrl(endpoint string) string {
	return BuildWpJsonUrl(c.baseUrl, endpoint)
}

// rawGet performs an authenticated GET request to an arbitrary full URL on the same WordPress host.
func (c *Client) rawGet(fullUrl string) (*http.Response, *apperror.AppError) {
	req, err := http.NewRequest(httpmethod.Get.Value(), fullUrl, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create raw GET request").
			WithUrl(fullUrl)
	}

	c.setStandardHeaders(req, contenttype.Json.Value())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "raw GET request failed").
			WithUrl(fullUrl)
	}

	return resp, nil
}

func escapePathSegmentPreservingPercent(s string) string {
	hasEncodedSlash := strings.Contains(strings.ToLower(s), "%2f")

	if hasEncodedSlash {
		return s
	}

	return url.PathEscape(s)
}

// TestConnection is implemented in ClientConnectionSteps.go
