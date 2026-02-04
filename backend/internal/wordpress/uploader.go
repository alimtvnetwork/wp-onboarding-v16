// Package wordpress provides uploader capabilities using the Plugin Uploader Helper API.
package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
)

// UploaderNamespace is the REST API namespace for the Plugin Uploader Helper.
const UploaderNamespace = "plugin-uploader/v1"

// UploaderStatus represents the /status endpoint response.
type UploaderStatus struct {
	Status           string            `json:"status"`
	Message          string            `json:"message"`
	Version          string            `json:"version"`
	WordPressVersion string            `json:"wordpress_version"`
	PHPVersion       string            `json:"php_version"`
	Endpoints        map[string]string `json:"endpoints,omitempty"`
}

// UploaderUploadResult represents the /upload endpoint response.
type UploaderUploadResult struct {
	Success       bool   `json:"success"`
	Message       string `json:"message"`
	Plugin        string `json:"plugin,omitempty"`
	Activated     bool   `json:"activated"`
	PluginDetails *struct {
		Name        string `json:"name"`
		Version     string `json:"version"`
		Author      string `json:"author"`
		Description string `json:"description"`
	} `json:"plugin_details,omitempty"`
	ActivationError string `json:"activation_error,omitempty"`
}

// UploaderPluginInfo represents plugin info from the list endpoint.
type UploaderPluginInfo struct {
	Slug        string `json:"slug"`
	File        string `json:"file"`
	Name        string `json:"name"`
	Version     string `json:"version"`
	Author      string `json:"author"`
	Description string `json:"description"`
	Active      bool   `json:"active"`
}

// UploaderFileInfo represents file info from the files endpoint.
type UploaderFileInfo struct {
	Path     string `json:"path"`
	Size     int64  `json:"size"`
	Modified string `json:"modified"`
	Hash     string `json:"hash"`
}

// CheckUploaderHelperAvailable checks if the Plugin Uploader Helper is installed.
func (c *Client) CheckUploaderHelperAvailable() (bool, error) {
	endpoint := fmt.Sprintf("/%s/status", UploaderNamespace)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return false, err
	}
	defer resp.Body.Close()

	// 404 means the route doesn't exist (plugin not installed)
	if resp.StatusCode == http.StatusNotFound {
		return false, nil
	}

	// 401/403 means auth required - plugin exists but we need credentials
	if resp.StatusCode == http.StatusUnauthorized || resp.StatusCode == http.StatusForbidden {
		return true, nil
	}

	// 200 means plugin is available and we're authenticated
	return resp.StatusCode == http.StatusOK, nil
}

// GetUploaderStatus gets the Plugin Uploader Helper status.
func (c *Client) GetUploaderStatus() (*UploaderStatus, error) {
	endpoint := fmt.Sprintf("/%s/status", UploaderNamespace)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("status request failed: status %d", resp.StatusCode)
	}

	var status UploaderStatus
	if err := json.NewDecoder(resp.Body).Decode(&status); err != nil {
		return nil, fmt.Errorf("decode status: %w", err)
	}

	return &status, nil
}

// UploadPluginViaUploader uploads a plugin ZIP via the Plugin Uploader Helper.
// This uses base64-encoded upload for reliability (like the PowerShell script).
func (c *Client) UploadPluginViaUploader(zipPath string, activate bool) (*UploaderUploadResult, error) {
	c.progress("upload", "running", fmt.Sprintf("Reading %s for upload...", filepath.Base(zipPath)), nil)

	// Read the ZIP file
	fileBytes, err := os.ReadFile(zipPath)
	if err != nil {
		return nil, fmt.Errorf("read zip file: %w", err)
	}

	// Encode to base64
	base64Data := base64.StdEncoding.EncodeToString(fileBytes)

	c.progress("upload", "running", fmt.Sprintf("Uploading %d bytes (base64) to Plugin Uploader Helper...", len(fileBytes)), map[string]interface{}{
		"zipSize": len(fileBytes),
		"zipFile": filepath.Base(zipPath),
	})

	// Build request body
	body := map[string]interface{}{
		"plugin_name": filepath.Base(zipPath),
		"plugin_data": base64Data,
		"activate":    activate,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, fmt.Errorf("marshal request body: %w", err)
	}

	// Build request
	endpoint := fmt.Sprintf("/%s/upload", UploaderNamespace)
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)

	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return nil, fmt.Errorf("create upload request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set("Authorization", "Basic "+auth)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "WP-Plugin-Publish/1.0")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("upload request failed: %w", err)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress("upload", "running", fmt.Sprintf("Upload response: %d", resp.StatusCode), map[string]interface{}{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 500),
	})

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		return nil, &APIError{
			Operation:    "upload plugin via uploader helper",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
		}
	}

	var result UploaderUploadResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		// If JSON parsing fails but status was OK, treat as success
		result.Success = true
		result.Message = "Upload completed"
	}

	return &result, nil
}

// EnablePluginViaUploader enables (activates) a plugin via the Plugin Uploader Helper.
func (c *Client) EnablePluginViaUploader(slug string) error {
	endpoint := fmt.Sprintf("/%s/plugins/%s/enable", UploaderNamespace, slug)
	resp, err := c.request("POST", endpoint, nil)
	if err != nil {
		return fmt.Errorf("enable plugin request failed: %w", err)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "enable plugin via uploader helper",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}

// DisablePluginViaUploader disables (deactivates) a plugin via the Plugin Uploader Helper.
func (c *Client) DisablePluginViaUploader(slug string) error {
	endpoint := fmt.Sprintf("/%s/plugins/%s/disable", UploaderNamespace, slug)
	resp, err := c.request("POST", endpoint, nil)
	if err != nil {
		return fmt.Errorf("disable plugin request failed: %w", err)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "disable plugin via uploader helper",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}

// DeletePluginViaUploader deletes a plugin via the Plugin Uploader Helper.
func (c *Client) DeletePluginViaUploader(slug string) error {
	endpoint := fmt.Sprintf("/%s/plugins/%s/delete", UploaderNamespace, slug)

	// Build DELETE request
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("DELETE", url, nil)
	if err != nil {
		return fmt.Errorf("create delete request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set("Authorization", "Basic "+auth)
	req.Header.Set("User-Agent", "WP-Plugin-Publish/1.0")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("delete plugin request failed: %w", err)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "delete plugin via uploader helper",
			Method:       "DELETE",
			Endpoint:     endpoint,
			URL:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}

// ListPluginsViaUploader lists all plugins via the Plugin Uploader Helper.
func (c *Client) ListPluginsViaUploader() ([]UploaderPluginInfo, error) {
	endpoint := fmt.Sprintf("/%s/plugins", UploaderNamespace)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("list plugins failed: status %d", resp.StatusCode)
	}

	var response struct {
		Success bool                 `json:"success"`
		Count   int                  `json:"count"`
		Plugins []UploaderPluginInfo `json:"plugins"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&response); err != nil {
		return nil, fmt.Errorf("decode plugins: %w", err)
	}

	return response.Plugins, nil
}

// ListPluginFilesViaUploader lists files in a plugin via the Plugin Uploader Helper.
func (c *Client) ListPluginFilesViaUploader(slug string) ([]UploaderFileInfo, error) {
	endpoint := fmt.Sprintf("/%s/plugins/%s/files", UploaderNamespace, slug)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("list files failed: status %d", resp.StatusCode)
	}

	var response struct {
		Success bool               `json:"success"`
		Slug    string             `json:"slug"`
		Count   int                `json:"count"`
		Files   []UploaderFileInfo `json:"files"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&response); err != nil {
		return nil, fmt.Errorf("decode files: %w", err)
	}

	return response.Files, nil
}

// ReplaceFileViaUploader replaces a single file in a plugin via the Plugin Uploader Helper.
func (c *Client) ReplaceFileViaUploader(slug, relPath string, content []byte, isBase64 bool) error {
	endpoint := fmt.Sprintf("/%s/plugins/%s/files", UploaderNamespace, slug)

	encoding := "plain"
	contentStr := string(content)
	if isBase64 {
		encoding = "base64"
		contentStr = base64.StdEncoding.EncodeToString(content)
	}

	body := map[string]string{
		"path":     relPath,
		"content":  contentStr,
		"encoding": encoding,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return fmt.Errorf("marshal body: %w", err)
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("PUT", url, bytes.NewReader(jsonBody))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set("Authorization", "Basic "+auth)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "WP-Plugin-Publish/1.0")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("replace file request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		respBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "replace file via uploader helper",
			Method:       "PUT",
			Endpoint:     endpoint,
			URL:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}

// DeleteFileViaUploader deletes a single file from a plugin via the Plugin Uploader Helper.
func (c *Client) DeleteFileViaUploader(slug, relPath string) error {
	endpoint := fmt.Sprintf("/%s/plugins/%s/files", UploaderNamespace, slug)

	body := map[string]string{"path": relPath}
	jsonBody, _ := json.Marshal(body)

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("DELETE", url, bytes.NewReader(jsonBody))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set("Authorization", "Basic "+auth)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "WP-Plugin-Publish/1.0")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("delete file request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		respBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "delete file via uploader helper",
			Method:       "DELETE",
			Endpoint:     endpoint,
			URL:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}
