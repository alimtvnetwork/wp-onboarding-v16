// Package wordpress provides a client for the WordPress REST API
package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

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
			return nil, fmt.Errorf("failed to marshal request body: %w", err)
		}
		bodyReader = bytes.NewReader(jsonBody)
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest(method, url, bodyReader)
	if err != nil {
		return nil, fmt.Errorf("failed to create request: %w", err)
	}

	// Add Application Password authentication
	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set("Authorization", "Basic "+auth)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "WP-Plugin-Publish/1.0")

	return c.httpClient.Do(req)
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
		return nil, fmt.Errorf("cannot reach site: %w", err)
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
		return nil, fmt.Errorf("REST API not accessible: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 404 {
		c.progress("rest_api_check", "error", "REST API not found - is permalink structure set?", map[string]interface{}{"url": c.baseURL})
		return nil, fmt.Errorf("WordPress REST API not found. Ensure permalinks are enabled")
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
		return nil, fmt.Errorf("authentication request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 401 {
		c.progress("auth_check", "error", "Invalid username or application password", map[string]interface{}{
			"hint": "Generate an application password in WordPress: Users → Profile → Application Passwords",
			"url":  c.baseURL,
		})
		return nil, fmt.Errorf("authentication failed: invalid username or application password")
	}
	if resp.StatusCode == 403 {
		c.progress("auth_check", "error", "Access forbidden - user lacks permissions", map[string]interface{}{"url": c.baseURL})
		return nil, fmt.Errorf("authentication failed: user lacks required permissions")
	}
	if resp.StatusCode != 200 {
		body, _ := io.ReadAll(resp.Body)
		c.progress("auth_check", "error", fmt.Sprintf("Unexpected response: %d", resp.StatusCode), map[string]interface{}{
			"body": string(body),
			"url":  c.baseURL,
		})
		return nil, fmt.Errorf("unexpected status: %d - %s", resp.StatusCode, string(body))
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
		return nil, fmt.Errorf("plugin endpoint not accessible: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 401 || resp.StatusCode == 403 {
		c.progress("plugin_access_check", "error", "User cannot manage plugins - requires administrator role", map[string]interface{}{
			"userRoles": result.UserRoles,
			"url":       c.baseURL,
		})
		return nil, fmt.Errorf("insufficient permissions: user cannot manage plugins (requires administrator role)")
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
	resp, err := c.request("GET", "/wp/v2/plugins", nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		return nil, fmt.Errorf("failed to get plugins: status %d", resp.StatusCode)
	}

	var plugins []PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugins); err != nil {
		return nil, fmt.Errorf("failed to decode plugins: %w", err)
	}

	return plugins, nil
}

// GetPlugin returns information about a specific plugin
func (c *Client) GetPlugin(slug string) (*PluginInfo, error) {
	resp, err := c.request("GET", "/wp/v2/plugins/"+slug, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode == 404 {
		return nil, fmt.Errorf("plugin not found: %s", slug)
	}

	if resp.StatusCode != 200 {
		return nil, fmt.Errorf("failed to get plugin: status %d", resp.StatusCode)
	}

	var plugin PluginInfo
	if err := json.NewDecoder(resp.Body).Decode(&plugin); err != nil {
		return nil, fmt.Errorf("failed to decode plugin: %w", err)
	}

	return &plugin, nil
}

// ActivatePlugin activates a plugin
func (c *Client) ActivatePlugin(slug string) error {
	resp, err := c.request("PUT", "/wp/v2/plugins/"+slug, map[string]string{
		"status": "active",
	})
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		return fmt.Errorf("failed to activate plugin: status %d", resp.StatusCode)
	}

	return nil
}

// DeactivatePlugin deactivates a plugin
func (c *Client) DeactivatePlugin(slug string) error {
	resp, err := c.request("PUT", "/wp/v2/plugins/"+slug, map[string]string{
		"status": "inactive",
	})
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		return fmt.Errorf("failed to deactivate plugin: status %d", resp.StatusCode)
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
