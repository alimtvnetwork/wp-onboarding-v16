// Package wordpress provides uploader capabilities using the Rise Up Uploader API.
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

// Note: RiseUpUploaderNamespace is defined in constants.go

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

// CheckRiseUpAsiaAvailable checks if the Rise Up Asia plugin is installed.
// It tries the new namespace first, then falls back to the legacy namespaces for backward compatibility.
func (c *Client) CheckRiseUpAsiaAvailable() (bool, string, error) {
	// Try Rise Up Asia first (newest namespace)
	endpoint := fmt.Sprintf("/%s%s", RiseUpAsiaNamespace, EndpointStatus)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return false, "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusOK || resp.StatusCode == http.StatusUnauthorized || resp.StatusCode == http.StatusForbidden {
		return true, RiseUpAsiaNamespace, nil
	}

	// Try legacy namespace (riseup-uploader/v1)
	endpoint = fmt.Sprintf("/%s%s", RiseUpUploaderNamespace, EndpointStatus)
	resp2, err := c.request("GET", endpoint, nil)
	if err != nil {
		return false, "", err
	}
	defer resp2.Body.Close()

	if resp2.StatusCode == http.StatusOK || resp2.StatusCode == http.StatusUnauthorized || resp2.StatusCode == http.StatusForbidden {
		return true, RiseUpUploaderNamespace, nil
	}

	// Try oldest legacy namespace (plugin-uploader/v1)
	endpoint = fmt.Sprintf("/%s%s", PluginUploaderNamespace, EndpointStatus)
	resp3, err := c.request("GET", endpoint, nil)
	if err != nil {
		return false, "", err
	}
	defer resp3.Body.Close()

	if resp3.StatusCode == http.StatusOK || resp3.StatusCode == http.StatusUnauthorized || resp3.StatusCode == http.StatusForbidden {
		return true, PluginUploaderNamespace, nil
	}

	return false, "", nil
}

// CheckRiseUpUploaderAvailable is deprecated, use CheckRiseUpAsiaAvailable.
func (c *Client) CheckRiseUpUploaderAvailable() (bool, string, error) {
	return c.CheckRiseUpAsiaAvailable()
}

// CheckUploaderHelperAvailable is deprecated, use CheckRiseUpAsiaAvailable.
func (c *Client) CheckUploaderHelperAvailable() (bool, error) {
	available, _, err := c.CheckRiseUpAsiaAvailable()
	return available, err
}

// GetUploaderStatus gets the Rise Up Uploader status.
func (c *Client) GetUploaderStatus() (*UploaderStatus, error) {
	// Detect which namespace is available
	_, namespace, err := c.CheckRiseUpUploaderAvailable()
	if err != nil {
		return nil, err
	}
	if namespace == "" {
		namespace = RiseUpUploaderNamespace // default
	}

	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointStatus)
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

// UploadPluginViaUploader uploads a plugin ZIP via the Rise Up Uploader.
// This uses base64-encoded upload for reliability (like the PowerShell script).
func (c *Client) UploadPluginViaUploader(zipPath string, activate bool) (*UploaderUploadResult, error) {
	c.progress(ActionUpload, "running", fmt.Sprintf("Reading %s for upload...", filepath.Base(zipPath)), nil)

	// Read the ZIP file
	fileBytes, err := os.ReadFile(zipPath)
	if err != nil {
		return nil, fmt.Errorf("read zip file: %w", err)
	}

	// Encode to base64
	base64Data := base64.StdEncoding.EncodeToString(fileBytes)

	// Detect which namespace is available
	_, namespace, _ := c.CheckRiseUpAsiaAvailable()
	if namespace == "" {
		namespace = RiseUpAsiaNamespace // default to new namespace
	}

	c.progress(ActionUpload, "running", fmt.Sprintf("Uploading %d bytes (base64) to %s...", len(fileBytes), namespace), map[string]interface{}{
		"zipSize":   len(fileBytes),
		"zipFile":   filepath.Base(zipPath),
		"namespace": namespace,
	})

	// Build request body - use plugin_zip for Rise Up Uploader, plugin_data for legacy
	body := map[string]interface{}{
		"plugin_zip": base64Data, // Rise Up Uploader format
		"slug":       filepath.Base(zipPath),
		"activate":   activate,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, fmt.Errorf("marshal request body: %w", err)
	}

	// Build request
	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointUpload)
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)

	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return nil, fmt.Errorf("create upload request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set(HeaderAuthorization, "Basic "+auth)
	req.Header.Set(HeaderContentType, ContentTypeJSON)
	req.Header.Set(HeaderUserAgent, UserAgentValue)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("upload request failed: %w", err)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress(ActionUpload, "running", fmt.Sprintf("Upload response: %d", resp.StatusCode), map[string]interface{}{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 500),
	})

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		return nil, &APIError{
			Operation:    "upload plugin via Rise Up Uploader",
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

// EnablePluginViaUploader enables (activates) a plugin via the Rise Up Uploader.
func (c *Client) EnablePluginViaUploader(slug string) error {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s"+EndpointEnable, namespace, slug)
	resp, err := c.request("POST", endpoint, nil)
	if err != nil {
		return fmt.Errorf("enable plugin request failed: %w", err)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "enable plugin via Rise Up Uploader",
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

// DisablePluginViaUploader disables (deactivates) a plugin via the Rise Up Uploader.
func (c *Client) DisablePluginViaUploader(slug string) error {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s"+EndpointDisable, namespace, slug)
	resp, err := c.request("POST", endpoint, nil)
	if err != nil {
		return fmt.Errorf("disable plugin request failed: %w", err)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "disable plugin via Rise Up Uploader",
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

// DeletePluginViaUploader deletes a plugin via the Rise Up Uploader.
func (c *Client) DeletePluginViaUploader(slug string) error {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s"+EndpointDelete, namespace, slug)

	// Build DELETE request
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("DELETE", url, nil)
	if err != nil {
		return fmt.Errorf("create delete request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set(HeaderAuthorization, "Basic "+auth)
	req.Header.Set(HeaderUserAgent, UserAgentValue)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("delete plugin request failed: %w", err)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "delete plugin via Rise Up Uploader",
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

// ListPluginsViaUploader lists all plugins via the Rise Up Uploader.
func (c *Client) ListPluginsViaUploader() ([]UploaderPluginInfo, error) {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointPlugins)
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

// ListPluginFilesViaUploader lists files in a plugin via the Rise Up Uploader.
func (c *Client) ListPluginFilesViaUploader(slug string) ([]UploaderFileInfo, error) {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s"+EndpointFiles, namespace, slug)
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

// ReplaceFileViaUploader replaces a single file in a plugin via the Rise Up Uploader.
func (c *Client) ReplaceFileViaUploader(slug, relPath string, content []byte, isBase64 bool) error {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s"+EndpointFiles, namespace, slug)

	// Always use base64 encoding for Rise Up Uploader
	contentStr := base64.StdEncoding.EncodeToString(content)

	body := map[string]string{
		"path":    relPath,
		"content": contentStr,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return fmt.Errorf("marshal body: %w", err)
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set(HeaderAuthorization, "Basic "+auth)
	req.Header.Set(HeaderContentType, ContentTypeJSON)
	req.Header.Set(HeaderUserAgent, UserAgentValue)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("replace file request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		respBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "replace file via Rise Up Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}

// DeleteFileViaUploader deletes a single file from a plugin via the Rise Up Uploader.
func (c *Client) DeleteFileViaUploader(slug, relPath string) error {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s"+EndpointFiles, namespace, slug)

	body := map[string]string{"path": relPath}
	jsonBody, _ := json.Marshal(body)

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("DELETE", url, bytes.NewReader(jsonBody))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set(HeaderAuthorization, "Basic "+auth)
	req.Header.Set(HeaderContentType, ContentTypeJSON)
	req.Header.Set(HeaderUserAgent, UserAgentValue)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("delete file request failed: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		respBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "delete file via Rise Up Uploader",
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
