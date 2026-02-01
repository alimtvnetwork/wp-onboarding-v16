// Package wordpress provides a client for the WordPress REST API
package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

// ClientConfig holds WordPress client configuration
type ClientConfig struct {
	BaseURL  string
	Username string
	Password string
	Timeout  time.Duration
}

// Client is a WordPress REST API client
type Client struct {
	baseURL    string
	username   string
	password   string
	httpClient *http.Client
}

// NewClient creates a new WordPress API client
func NewClient(cfg ClientConfig) *Client {
	return &Client{
		baseURL:  cfg.BaseURL,
		username: cfg.Username,
		password: cfg.Password,
		httpClient: &http.Client{
			Timeout: cfg.Timeout,
		},
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

	return c.httpClient.Do(req)
}

// TestConnection verifies the API is accessible and credentials are valid
func (c *Client) TestConnection() (*ConnectionInfo, error) {
	resp, err := c.request("GET", "/wp/v2/users/me", nil)
	if err != nil {
		return nil, fmt.Errorf("connection failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 401 || resp.StatusCode == 403 {
		return nil, fmt.Errorf("authentication failed: invalid credentials")
	}

	if resp.StatusCode != 200 {
		return nil, fmt.Errorf("unexpected status code: %d", resp.StatusCode)
	}

	return &ConnectionInfo{
		Connected: true,
		Username:  c.username,
	}, nil
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
	Connected bool   `json:"connected"`
	Username  string `json:"username"`
	WPVersion string `json:"wpVersion,omitempty"`
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
