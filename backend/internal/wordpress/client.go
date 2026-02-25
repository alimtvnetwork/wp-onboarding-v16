// Package wordpress provides a client for the WordPress REST API
package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"strings"
	"time"

	"wp-plugin-publish/internal/enums/content_type"
	"wp-plugin-publish/internal/enums/header"
	"wp-plugin-publish/pkg/apperror"
)

// sourceMachineHostname caches the machine hostname for header attribution.
// Computed once at package init to avoid repeated syscalls.
var sourceMachineHostname string

func init() {
	var err error
	sourceMachineHostname, err = os.Hostname()
	if err != nil || sourceMachineHostname == "" {
		sourceMachineHostname = "unknown"
	}
}

// ProgressDetails is defined in progress_details.go as json.RawMessage.
// All call sites MUST use toProgress() with a typed struct.

// ClientConfig holds WordPress client configuration
type ClientConfig struct {
	BaseURL         string
	Username        string
	Password        string
	Timeout         time.Duration
	StackTraceDepth int
	OnProgress      func(step, status, message string, details ProgressDetails)
}

// Client is a WordPress REST API client
type Client struct {
	baseURL         string
	username        string
	password        string
	stackTraceDepth int
	httpClient      *http.Client
	onProgress      func(step, status, message string, details ProgressDetails)
}

// NewClient creates a new WordPress API client
func NewClient(cfg ClientConfig) *Client {
	depth := cfg.StackTraceDepth
	if depth <= 0 {
		depth = DefaultStackTraceDepth
	}
	return &Client{
		baseURL:  strings.TrimSuffix(cfg.BaseURL, "/"),
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
func (c *Client) progress(step, status, message string, details ProgressDetails) {
	if c.onProgress != nil {
		c.onProgress(step, status, message, details)
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

// request makes an authenticated HTTP request to the WordPress API
func (c *Client) request(method, endpoint string, body any) (*http.Response, error) {
	var bodyReader io.Reader
	if body != nil {
		jsonBody, err := json.Marshal(body)
		if err != nil {
			return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to marshal request body")
		}
		bodyReader = bytes.NewReader(jsonBody)
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest(method, url, bodyReader)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create HTTP request").
			WithURL(url).
			WithMethod(method)
	}

	c.setStandardHeaders(req, contenttype.JSON.Value())
	return c.httpClient.Do(req)
}

// requestMultipart sends a multipart HTTP request (for file uploads).
func (c *Client) requestMultipart(method, endpoint string, body io.Reader, contentType string) (*http.Response, error) {
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest(method, url, body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create HTTP request").
			WithURL(url).
			WithMethod(method)
	}

	c.setStandardHeaders(req, contentType)
	return c.httpClient.Do(req)
}

func (c *Client) fullURL(endpoint string) string {
	return fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
}

// rawGet performs an authenticated GET request to an arbitrary full URL on the same WordPress host.
func (c *Client) rawGet(fullURL string) (*http.Response, error) {
	req, err := http.NewRequest("GET", fullURL, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create raw GET request").
			WithURL(fullURL)
	}
	c.setStandardHeaders(req, contenttype.JSON.Value())
	return c.httpClient.Do(req)
}

func escapePathSegmentPreservingPercent(s string) string {
	if strings.Contains(strings.ToLower(s), "%2f") {
		return s
	}
	return url.PathEscape(s)
}

// TestConnection verifies the API is accessible and credentials are valid
// This performs multiple checks: site reachability, REST API availability, auth, and write permissions
func (c *Client) TestConnection() (*ConnectionInfo, error) {
	result := &ConnectionInfo{Connected: false, Username: c.username}

	if err := c.testDnsReachability(result); err != nil {
		return nil, err
	}
	if err := c.testRestApiAvailability(result); err != nil {
		return nil, err
	}
	if err := c.testAuthentication(result); err != nil {
		return nil, err
	}
	if err := c.testPluginAccess(result); err != nil {
		return nil, err
	}
	c.testWritePermissions(result)

	result.IsConnected = true
	return result, nil
}
