// Package wordpress provides a client for the WordPress REST API
package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/url"
	"net/http"
	"strings"
	"time"

	"wp-plugin-publish/pkg/apperror"
)

// APIError contains rich request/response context for failed WordPress REST calls.
// It intentionally keeps Error() short/stable (so user-facing messages remain readable)
// while exposing full diagnostics via fields.
type APIError struct {
	Operation     string
	Method        string
	Endpoint      string
	URL           string
	StatusCode    int
	ResponseBody  string
	PluginSlugIn  string
	PluginIDUsed  string
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
	} else if e.URL != "" {
		req = fmt.Sprintf(" (%s)", e.URL)
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

// ClientConfig holds WordPress client configuration
type ClientConfig struct {
	BaseURL   string
	Username  string
	Password  string
	Timeout   time.Duration
	OnProgress func(step, status, message string, details map[string]interface{})
}

// Client is a WordPress REST API client
type Client struct {
	baseURL    string
	username   string
	password   string
	httpClient *http.Client
	onProgress func(step, status, message string, details map[string]interface{})
}

// NewClient creates a new WordPress API client
func NewClient(cfg ClientConfig) *Client {
	return &Client{
		baseURL:  strings.TrimSuffix(cfg.BaseURL, "/"),
		username: cfg.Username,
		password: cfg.Password,
		httpClient: &http.Client{
			Timeout: cfg.Timeout,
		},
		onProgress: cfg.OnProgress,
	}
}

// progress reports progress if a callback is set
func (c *Client) progress(step, status, message string, details map[string]interface{}) {
	if c.onProgress != nil {
		c.onProgress(step, status, message, details)
	}
}

// request makes an authenticated HTTP request to the WordPress API
func (c *Client) request(method, endpoint string, body interface{}) (*http.Response, error) {
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
			WithContext("url", url).
			WithContext("method", method)
	}

	// Add Application Password authentication
	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set(HeaderAuthorization, "Basic "+auth)
	req.Header.Set(HeaderContentType, ContentTypeJSON)
	req.Header.Set(HeaderUserAgent, UserAgentValue)

	return c.httpClient.Do(req)
}

func (c *Client) fullURL(endpoint string) string {
	return fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
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
		WithContext("slug", slug)
}

// TestConnection verifies the API is accessible and credentials are valid
// This performs multiple checks: site reachability, REST API availability, auth, and write permissions
func (c *Client) TestConnection() (*ConnectionInfo, error) {
	result := &ConnectionInfo{
		Connected: false,
		Username:  c.username,
	}

	// Step 1: Check if site is reachable
	c.progress("dns_check", "running", fmt.Sprintf("Resolving %s...", c.baseURL), map[string]interface{}{
		"url": c.baseURL,
	})
	resp, err := c.httpClient.Get(c.baseURL)
	if err != nil {
		c.progress("dns_check", "error", fmt.Sprintf("Cannot reach site: %v", err), map[string]interface{}{"error": err.Error(), "url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "cannot reach WordPress site").
			WithContext("url", c.baseURL)
	}
	resp.Body.Close()
	c.progress("dns_check", "success", "Site is reachable", map[string]interface{}{"status": resp.StatusCode, "url": c.baseURL})

	// Step 2: Check WordPress REST API root
	c.progress("rest_api_check", "running", "Checking WordPress REST API...", map[string]interface{}{
		"url": c.baseURL,
	})
	resp, err = c.httpClient.Get(fmt.Sprintf("%s/wp-json/", c.baseURL))
	if err != nil {
		c.progress("rest_api_check", "error", fmt.Sprintf("REST API not accessible: %v", err), map[string]interface{}{"url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPAPIDisabled, "REST API not accessible").
			WithContext("url", c.baseURL)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 404 {
		c.progress("rest_api_check", "error", "REST API not found - is permalink structure set?", map[string]interface{}{"url": c.baseURL})
		return nil, apperror.New(apperror.ErrWPAPIDisabled, "WordPress REST API not found - ensure permalinks are enabled").
			WithContext("url", c.baseURL)
	}

	// Parse root response to get WordPress version
	var rootInfo map[string]interface{}
	if err := json.NewDecoder(resp.Body).Decode(&rootInfo); err == nil {
		if name, ok := rootInfo["name"].(string); ok {
			result.SiteName = name
		}
		if desc, ok := rootInfo["description"].(string); ok {
			result.SiteDescription = desc
		}
	}
	c.progress("rest_api_check", "success", "REST API is available", map[string]interface{}{"siteName": result.SiteName, "url": c.baseURL})

	// Step 3: Test authentication with users/me endpoint
	c.progress("auth_check", "running", fmt.Sprintf("Authenticating as %s...", c.username), map[string]interface{}{
		"url":      c.baseURL,
		"username": c.username,
	})
	resp, err = c.request("GET", "/wp/v2/users/me", nil)
	if err != nil {
		c.progress("auth_check", "error", fmt.Sprintf("Authentication request failed: %v", err), map[string]interface{}{"url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPAuth, "authentication request failed").
			WithContext("url", c.baseURL).
			WithContext("username", c.username)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 401 {
		c.progress("auth_check", "error", "Invalid username or application password", map[string]interface{}{
			"hint": "Generate an application password in WordPress: Users → Profile → Application Passwords",
			"url":  c.baseURL,
		})
		return nil, apperror.New(apperror.ErrWPAuth, "authentication failed: invalid username or application password").
			WithContext("url", c.baseURL).
			WithContext("username", c.username)
	}
	if resp.StatusCode == 403 {
		c.progress("auth_check", "error", "Access forbidden - user lacks permissions", map[string]interface{}{"url": c.baseURL})
		return nil, apperror.New(apperror.ErrWPAuth, "authentication failed: user lacks required permissions").
			WithContext("url", c.baseURL).
			WithContext("statusCode", resp.StatusCode)
	}
	if resp.StatusCode != 200 {
		body, _ := io.ReadAll(resp.Body)
		c.progress("auth_check", "error", fmt.Sprintf("Unexpected response: %d", resp.StatusCode), map[string]interface{}{
			"body": string(body),
			"url":  c.baseURL,
		})
		return nil, apperror.New(apperror.ErrWPConnection, "unexpected authentication response").
			WithContext("url", c.baseURL).
			WithContext("statusCode", resp.StatusCode).
			WithDetails(string(body))
	}

	// Parse user info
	var userInfo struct {
		ID          int      `json:"id"`
		Name        string   `json:"name"`
		Slug        string   `json:"slug"`
		Roles       []string `json:"roles"`
		Capabilities map[string]bool `json:"capabilities"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&userInfo); err == nil {
		result.UserID = userInfo.ID
		result.UserDisplayName = userInfo.Name
		result.UserRoles = userInfo.Roles
		
		// Check for plugin management capability
		result.CanManagePlugins = userInfo.Capabilities["activate_plugins"] || userInfo.Capabilities["install_plugins"]
	}
	c.progress("auth_check", "success", fmt.Sprintf("Authenticated as %s (ID: %d)", result.UserDisplayName, result.UserID), map[string]interface{}{
		"userId": result.UserID,
		"roles":  result.UserRoles,
		"url":    c.baseURL,
	})

	// Step 4: Check plugin management permissions
	c.progress("plugin_access_check", "running", "Checking plugin management access...", map[string]interface{}{
		"url": c.baseURL,
	})
	resp, err = c.request("GET", "/wp/v2/plugins", nil)
	if err != nil {
		c.progress("plugin_access_check", "error", fmt.Sprintf("Plugin endpoint request failed: %v", err), map[string]interface{}{"url": c.baseURL})
		return nil, apperror.Wrap(err, apperror.ErrWPPluginList, "plugin endpoint not accessible").
			WithContext("url", c.baseURL)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 401 || resp.StatusCode == 403 {
		c.progress("plugin_access_check", "error", "User cannot manage plugins - requires administrator role", map[string]interface{}{
			"userRoles": result.UserRoles,
			"url":       c.baseURL,
		})
		return nil, apperror.New(apperror.ErrWPAuth, "insufficient permissions: user cannot manage plugins (requires administrator role)").
			WithContext("url", c.baseURL).
			WithContext("statusCode", resp.StatusCode)
	}
	if resp.StatusCode == 200 {
		result.CanManagePlugins = true
		c.progress("plugin_access_check", "success", "Plugin management access confirmed", map[string]interface{}{"url": c.baseURL})
	} else {
		c.progress("plugin_access_check", "warning", fmt.Sprintf("Plugin endpoint returned %d", resp.StatusCode), map[string]interface{}{"url": c.baseURL})
	}

	// Step 5: Test write permissions by creating a draft post (optional, non-destructive)
	c.progress("write_test", "running", "Testing write permissions...", map[string]interface{}{
		"url": c.baseURL,
	})
	testPost := map[string]interface{}{
		"title":   "WP Plugin Publish Connection Test",
		"content": "This draft was created to test API write permissions. You can safely delete it.",
		"status":  "draft",
	}
	resp, err = c.request("POST", "/wp/v2/posts", testPost)
	if err != nil {
		c.progress("write_test", "warning", "Could not test write permissions", map[string]interface{}{"error": err.Error(), "url": c.baseURL})
		// Non-fatal - just report
	} else {
		defer resp.Body.Close()
		if resp.StatusCode == 201 {
			// Successfully created - now delete it
			var createdPost struct {
				ID int `json:"id"`
			}
			if err := json.NewDecoder(resp.Body).Decode(&createdPost); err == nil && createdPost.ID > 0 {
				// Delete the test post
				deleteResp, _ := c.request("DELETE", fmt.Sprintf("/wp/v2/posts/%d?force=true", createdPost.ID), nil)
				if deleteResp != nil {
					deleteResp.Body.Close()
				}
				result.CanWritePosts = true
				c.progress("write_test", "success", "Write permissions verified (test post created and deleted)", map[string]interface{}{
					"testPostId": createdPost.ID,
					"url":        c.baseURL,
				})
			}
		} else if resp.StatusCode == 401 || resp.StatusCode == 403 {
			c.progress("write_test", "warning", "User cannot create posts", map[string]interface{}{"url": c.baseURL})
		} else {
			c.progress("write_test", "warning", fmt.Sprintf("Write test returned %d", resp.StatusCode), map[string]interface{}{"url": c.baseURL})
		}
	}

	result.Connected = true
	return result, nil
}

// GetPlugins returns a list of installed plugins
func (c *Client) GetPlugins() ([]PluginInfo, error) {
	endpoint := "/wp/v2/plugins"
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get plugins list",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var plugins []PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugins); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode plugins response").
			WithContext("endpoint", endpoint)
	}

	return plugins, nil
}

// GetPlugin returns information about a specific plugin
func (c *Client) GetPlugin(slug string) (*PluginInfo, error) {
	endpoint := "/wp/v2/plugins/" + escapePathSegmentPreservingPercent(slug)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode == 404 {
		return nil, &APIError{
			Operation:    "get plugin (not found)",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: "",
			PluginSlugIn: slug,
		}
	}

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get plugin",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	var plugin PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugin); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode plugin response").
			WithContext("slug", slug)
	}

	return &plugin, nil
}

// ActivatePlugin activates a plugin
func (c *Client) ActivatePlugin(slug string) error {
	resolvedID, resolveErr := c.ResolvePluginIdentifier(slug)
	if resolveErr != nil {
		// Keep going with the given slug, but the error can be surfaced upstream via extra logs.
		resolvedID = slug
	}

	endpoint := "/wp/v2/plugins/" + escapePathSegmentPreservingPercent(resolvedID)
	resp, err := c.request("PUT", endpoint, map[string]string{
		"status": "active",
	})
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		body := string(bodyBytes)
		if len(body) > 8192 {
			body = body[:8192] + "..."
		}
		return &APIError{
			Operation:    "failed to activate plugin",
			Method:       "PUT",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: body,
			PluginSlugIn: slug,
			PluginIDUsed: resolvedID,
		}
	}

	return nil
}

// DeactivatePlugin deactivates a plugin
func (c *Client) DeactivatePlugin(slug string) error {
	endpoint := "/wp/v2/plugins/" + escapePathSegmentPreservingPercent(slug)
	resp, err := c.request("PUT", endpoint, map[string]string{
		"status": "inactive",
	})
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		body := string(bodyBytes)
		if len(body) > 8192 {
			body = body[:8192] + "..."
		}
		return &APIError{
			Operation:    "failed to deactivate plugin",
			Method:       "PUT",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: body,
			PluginSlugIn: slug,
			PluginIDUsed: slug,
		}
	}

	return nil
}

// ConnectionInfo represents WordPress connection details
type ConnectionInfo struct {
	Connected        bool     `json:"connected"`
	Username         string   `json:"username"`
	WPVersion        string   `json:"wpVersion,omitempty"`
	SiteName         string   `json:"siteName,omitempty"`
	SiteDescription  string   `json:"siteDescription,omitempty"`
	UserID           int      `json:"userId,omitempty"`
	UserDisplayName  string   `json:"userDisplayName,omitempty"`
	UserRoles        []string `json:"userRoles,omitempty"`
	CanManagePlugins bool     `json:"canManagePlugins"`
	CanWritePosts    bool     `json:"canWritePosts"`
}

// PluginInfo represents a WordPress plugin
type PluginInfo struct {
	Plugin      string `json:"plugin"`
	Status      string `json:"status"`
	Name        string `json:"name"`
	PluginURI   string `json:"plugin_uri"`
	Author      string `json:"author"`
	AuthorURI   string `json:"author_uri"`
	Description struct {
		Raw      string `json:"raw"`
		Rendered string `json:"rendered"`
	} `json:"description"`
	Version     string `json:"version"`
	NetworkOnly bool   `json:"network_only"`
	RequiresWP  string `json:"requires_wp"`
	RequiresPHP string `json:"requires_php"`
	TextDomain  string `json:"textdomain"`
}
