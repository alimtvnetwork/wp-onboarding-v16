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

	connectionstep "wp-plugin-publish/internal/enums/connection_step"
	contenttype "wp-plugin-publish/internal/enums/content_type"
	"wp-plugin-publish/internal/enums/header"
	poststatus "wp-plugin-publish/internal/enums/post_status"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
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

// APIError contains rich request/response context for failed WordPress REST calls.
// It intentionally keeps Error() short/stable (so user-facing messages remain readable)
// while exposing full diagnostics via fields.
type APIError struct {
	Operation     string
	Method        string
	Endpoint      string
	Url           string
	StatusCode    int
	RequestBody   string // The JSON body sent in the request
	ResponseBody  string
	PluginSlugIn  string
	PluginIdUsed  string
	StackTrace    string // Captured stack trace at error time
}

func (e *APIError) Error() string {
	op := e.Operation
	if op == "" {
		op = "WordPress API request failed"
	}

	// Always include endpoint/method in the user-facing error string when available.
	// This is critical for troubleshooting missing/incorrect routes.
	req := ""
	if e.Method != "" || e.Endpoint != "" {
		req = fmt.Sprintf(" (%s %s)", strings.ToUpper(e.Method), e.Endpoint)
	} else if e.Url != "" {
		req = fmt.Sprintf(" (%s)", e.Url)
	}

	return fmt.Sprintf("%s%s: status %d", op, req, e.StatusCode)
}

// FullError returns the complete error message with response body for logging
func (e *APIError) FullError() string {
	msg := e.Error()
	if e.ResponseBody != "" {
		msg += fmt.Sprintf("\nResponse Body: %s", e.ResponseBody)
	}
	if e.StackTrace != "" {
		msg += fmt.Sprintf("\n--- Stack Trace ---\n%s--- End Stack Trace ---", e.StackTrace)
	}
	return msg
}

// ProgressDetails holds structured metadata for progress callback reporting.
// GE-2 exception: this is a callback boundary type with ~30 ad-hoc call sites;
// typed structs per call site would add excessive boilerplate for diagnostic-only data.
type ProgressDetails map[string]any //nolint:ge2

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
	// If caller already provided an escaped segment (e.g., contains %2F), avoid double-encoding.
	// This is not a full validation; it's a pragmatic guard for our plugin identifier use-case.
	if strings.Contains(strings.ToLower(s), "%2f") {
		return s
	}
	return url.PathEscape(s)
}

// ResolvePluginIdentifier attempts to map a short slug (e.g. "akismet") to the full plugin
// identifier used by WP REST API (e.g. "akismet/akismet.php").
// If slug already looks like a full identifier (contains "/"), it is returned as-is.
func (c *Client) ResolvePluginIdentifier(slug string) (string, error) {
	slug = strings.TrimSpace(slug)
	if slug == "" {
		return "", apperror.New(apperror.ErrValidation, "empty plugin slug")
	}
	if strings.Contains(slug, "/") {
		// Ensure the identifier ends with .php as required by WP REST API
		if !strings.HasSuffix(slug, ".php") {
			slug = slug + ".php"
		}
		return slug, nil
	}

	plugs, err := c.GetPlugins()
	if err != nil {
		return slug, err
	}

	target := strings.ToLower(slug)
	for _, p := range plugs {
		pluginID := strings.ToLower(strings.TrimSpace(p.Plugin))
		textDomain := strings.ToLower(strings.TrimSpace(p.TextDomain))

		if pluginID == target || textDomain == target || strings.HasPrefix(pluginID, target+"/") {
			return p.Plugin, nil
		}
	}

	return slug, apperror.New(apperror.ErrNotFound, "plugin not found").
		WithSlug(slug)
}

// TestConnection verifies the API is accessible and credentials are valid
// This performs multiple checks: site reachability, REST API availability, auth, and write permissions
func (c *Client) TestConnection() (*ConnectionInfo, error) {
	result := &ConnectionInfo{
		Connected: false,
		Username:  c.username,
	}

	// Step 1: Check if site is reachable
	c.progress(connectionstep.DnsCheck.Value(), stagestatus.Running.String(), fmt.Sprintf("Resolving %s...", c.baseURL), ProgressDetails{
		"url": c.baseURL,
	})
	resp, err := c.httpClient.Get(c.baseURL)
	if err != nil {
		c.progress(connectionstep.DnsCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Cannot reach site: %v", err), ProgressDetails{"error": err.Error(), "url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "cannot reach WordPress site").
			WithURL(c.baseURL)
	}
	resp.Body.Close()
	c.progress(connectionstep.DnsCheck.Value(), stagestatus.Completed.String(), "Site is reachable", ProgressDetails{"status": resp.StatusCode, "url": c.baseURL})

	// Step 2: Check WordPress REST API root
	c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Running.String(), "Checking WordPress REST API...", ProgressDetails{
		"url": c.baseURL,
	})
	resp, err = c.httpClient.Get(fmt.Sprintf("%s/wp-json/", c.baseURL))
	if err != nil {
		c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("REST API not accessible: %v", err), ProgressDetails{"url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPAPIDisabled, "REST API not accessible").
			WithURL(c.baseURL)
	}
	defer resp.Body.Close()

	if resp.StatusCode == HttpStatusNotFound.Int() {
		c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Failed.String(), "REST API not found - is permalink structure set?", ProgressDetails{"url": c.baseURL})
		return nil, apperror.New(apperror.ErrWPAPIDisabled, "WordPress REST API not found - ensure permalinks are enabled").
			WithURL(c.baseURL)
	}

	// Parse root response to get WordPress version
	var rootInfo struct {
		Name        string `json:"name"`        // external key (WordPress REST API)
		Description string `json:"description"` // external key
	}
	if err := json.NewDecoder(resp.Body).Decode(&rootInfo); err == nil {
		result.SiteName = rootInfo.Name
		result.SiteDescription = rootInfo.Description
	}
	c.progress(connectionstep.RestApiCheck.Value(), stagestatus.Completed.String(), "REST API is available", ProgressDetails{"siteName": result.SiteName, "url": c.baseURL})

	// Step 3: Test authentication with users/me endpoint
	c.progress(connectionstep.AuthCheck.Value(), stagestatus.Running.String(), fmt.Sprintf("Authenticating as %s...", c.username), ProgressDetails{
		"url":      c.baseURL,
		"username": c.username,
	})
	resp, err = c.request("GET", WPCoreUsersMe, nil)
	if err != nil {
		c.progress(connectionstep.AuthCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Authentication request failed: %v", err), ProgressDetails{"url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPAuth, "authentication request failed").
			WithURL(c.baseURL).
			WithUsername(c.username)
	}
	defer resp.Body.Close()

	if resp.StatusCode == HttpStatusUnauthorized.Int() {
		c.progress(connectionstep.AuthCheck.Value(), stagestatus.Failed.String(), "Invalid username or application password", ProgressDetails{
			"hint": "Generate an application password in WordPress: Users → Profile → Application Passwords",
			"url":  c.baseURL,
		})
		return nil, apperror.New(apperror.ErrWPAuth, "authentication failed: invalid username or application password").
			WithURL(c.baseURL).
			WithUsername(c.username)
	}
	if resp.StatusCode == HttpStatusForbidden.Int() {
		c.progress(connectionstep.AuthCheck.Value(), stagestatus.Failed.String(), "Access forbidden - user lacks permissions", ProgressDetails{"url": c.baseURL})
		return nil, apperror.New(apperror.ErrWPAuth, "authentication failed: user lacks required permissions").
			WithURL(c.baseURL).
			WithStatusCode(resp.StatusCode)
	}
	if resp.StatusCode != HttpStatusOk.Int() {
		body, _ := io.ReadAll(resp.Body)
		c.progress(connectionstep.AuthCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Unexpected response: %d", resp.StatusCode), ProgressDetails{
			"body": string(body),
			"url":  c.baseURL,
		})
		return nil, apperror.New(apperror.ErrWPConnection, "unexpected authentication response").
			WithURL(c.baseURL).
			WithStatusCode(resp.StatusCode).
			WithDetails(string(body))
	}

	// Parse user info
	var userInfo struct {
		Id          int      `json:"id"`           // external key (WordPress REST API)
		Name        string   `json:"name"`         // external key
		Slug        string   `json:"slug"`         // external key
		Roles       []string `json:"roles"`        // external key
		Capabilities map[string]bool `json:"capabilities"` // external key
	}
	if err := json.NewDecoder(resp.Body).Decode(&userInfo); err == nil {
		result.UserId = userInfo.Id
		result.UserDisplayName = userInfo.Name
		result.UserRoles = userInfo.Roles
		
		// Check for plugin management capability
		result.CanManagePlugins = userInfo.Capabilities["activate_plugins"] || userInfo.Capabilities["install_plugins"]
	}
	c.progress(connectionstep.AuthCheck.Value(), stagestatus.Completed.String(), fmt.Sprintf("Authenticated as %s (ID: %d)", result.UserDisplayName, result.UserId), ProgressDetails{
		"userId": result.UserId,
		"roles":  result.UserRoles,
		"url":    c.baseURL,
	})

	// Step 4: Check plugin management permissions
	c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Running.String(), "Checking plugin management access...", ProgressDetails{
		"url": c.baseURL,
	})
	resp, err = c.request("GET", WPCorePlugins, nil)
	if err != nil {
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Failed.String(), fmt.Sprintf("Plugin endpoint request failed: %v", err), ProgressDetails{"url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPPluginList, "plugin endpoint not accessible").
			WithURL(c.baseURL)
	}
	defer resp.Body.Close()

	if resp.StatusCode == HttpStatusUnauthorized.Int() || resp.StatusCode == HttpStatusForbidden.Int() {
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Failed.String(), "User cannot manage plugins - requires administrator role", ProgressDetails{
			"userRoles": result.UserRoles,
			"url":       c.baseURL,
		})
		return nil, apperror.New(apperror.ErrWPAuth, "insufficient permissions: user cannot manage plugins (requires administrator role)").
			WithURL(c.baseURL).
			WithStatusCode(resp.StatusCode)
	}
	if resp.StatusCode == HttpStatusOk.Int() {
		result.CanManagePlugins = true
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Completed.String(), "Plugin management access confirmed", ProgressDetails{"url": c.baseURL})
	} else {
		c.progress(connectionstep.PluginAccessCheck.Value(), stagestatus.Warning.String(), fmt.Sprintf("Plugin endpoint returned %d", resp.StatusCode), ProgressDetails{"url": c.baseURL})
	}

	// Step 5: Test write permissions by creating a draft post (optional, non-destructive)
	c.progress(connectionstep.WriteTest.Value(), stagestatus.Running.String(), "Testing write permissions...", ProgressDetails{
		"url": c.baseURL,
	})
	testPost := struct {
		Title   string `json:"title"`   // external key (WordPress REST API)
		Content string `json:"content"` // external key
		Status  string `json:"status"`  // external key
	}{
		Title:   "WP Plugin Publish Connection Test",
		Content: "This draft was created to test API write permissions. You can safely delete it.",
		Status:  poststatus.Draft.String(),
	}
	resp, err = c.request("POST", WPCorePosts, testPost)
	if err != nil {
		c.progress(connectionstep.WriteTest.Value(), stagestatus.Warning.String(), "Could not test write permissions", ProgressDetails{"error": err.Error(), "url": c.baseURL})
		// Non-fatal - just report
	} else {
		defer resp.Body.Close()
	if resp.StatusCode == HttpStatusCreated.Int() {
			// Successfully created - now delete it
		var createdPost struct {
				Id int `json:"id"` // external key (WordPress REST API)
			}
			if err := json.NewDecoder(resp.Body).Decode(&createdPost); err == nil && createdPost.Id > 0 {
				// Delete the test post
				deleteResp, _ := c.request("DELETE", fmt.Sprintf(WPCorePostById+"?force=true", createdPost.Id), nil)
				if deleteResp != nil {
					deleteResp.Body.Close()
				}
				result.CanWritePosts = true
				c.progress(connectionstep.WriteTest.Value(), stagestatus.Completed.String(), "Write permissions verified (test post created and deleted)", ProgressDetails{
					"testPostId": createdPost.Id,
					"url":        c.baseURL,
				})
			}
		} else if resp.StatusCode == HttpStatusUnauthorized.Int() || resp.StatusCode == HttpStatusForbidden.Int() {
			c.progress(connectionstep.WriteTest.Value(), stagestatus.Warning.String(), "User cannot create posts", ProgressDetails{"url": c.baseURL})
		} else {
			c.progress(connectionstep.WriteTest.Value(), stagestatus.Warning.String(), fmt.Sprintf("Write test returned %d", resp.StatusCode), ProgressDetails{"url": c.baseURL})
		}
	}

	result.IsConnected = true
	return result, nil
}

// GetPlugins returns a list of installed plugins
func (c *Client) GetPlugins() ([]PluginInfo, error) {
	endpoint := WPCorePlugins
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get plugins list",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var plugins []PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugins); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode plugins response").
			WithEndpoint(endpoint)
	}

	return plugins, nil
}

// GetPlugin returns information about a specific plugin
func (c *Client) GetPlugin(slug string) (*PluginInfo, error) {
	endpoint := fmt.Sprintf(WPCorePluginBySlug, escapePathSegmentPreservingPercent(slug))
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode == HttpStatusNotFound.Int() {
		return nil, &APIError{
			Operation:    "get plugin (not found)",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: "",
			PluginSlugIn: slug,
		}
	}

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get plugin",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	var plugin PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugin); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode plugin response").
			WithSlug(slug)
	}

	return &plugin, nil
}


// ConnectionInfo represents WordPress connection details (built internally, not parsed from external)
type ConnectionInfo struct {
	IsConnected      bool
	Username         string
	WPVersion        string   `json:",omitempty"`
	SiteName         string   `json:",omitempty"`
	SiteDescription  string   `json:",omitempty"`
	UserId           int      `json:",omitempty"`
	UserDisplayName  string   `json:",omitempty"`
	UserRoles        []string `json:",omitempty"`
	CanManagePlugins bool
	CanWritePosts    bool
}

// PluginInfo represents a WordPress plugin (parsed from WordPress REST API)
type PluginInfo struct {
	Plugin      string `json:"plugin"`       // external key (WordPress REST API)
	Status      string `json:"status"`       // external key
	Name        string `json:"name"`         // external key
	PluginURI   string `json:"plugin_uri"`   // external key
	Author      string `json:"author"`       // external key
	AuthorURI   string `json:"author_uri"`   // external key
	Description struct {
		Raw      string `json:"raw"`      // external key
		Rendered string `json:"rendered"` // external key
	} `json:"description"` // external key
	Version     string `json:"version"`      // external key
	NetworkOnly bool   `json:"network_only"` // external key
	RequiresWP  string `json:"requires_wp"`  // external key
	RequiresPHP string `json:"requires_php"` // external key
	TextDomain  string `json:"textdomain"`   // external key
}
