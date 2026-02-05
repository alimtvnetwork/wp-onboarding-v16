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
	"runtime"
	"strings"

	"wp-plugin-publish/pkg/pathutil"
)

// captureStackTrace captures the call stack for debugging
func captureStackTrace(skip int) string {
	var builder strings.Builder
	pcs := make([]uintptr, 32)
	n := runtime.Callers(skip+1, pcs)
	frames := runtime.CallersFrames(pcs[:n])
	
	frameNum := 0
	for {
		frame, more := frames.Next()
		// Skip runtime internals
		if strings.Contains(frame.Function, "runtime.") {
			if !more {
				break
			}
			continue
		}
		builder.WriteString(fmt.Sprintf("  #%d %s\n      %s:%d\n", frameNum, frame.Function, frame.File, frame.Line))
		frameNum++
		if !more || frameNum >= 10 { // Limit depth
			break
		}
	}
	return builder.String()
}

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

// CheckRiseupAsiaAvailable checks if the Riseup Asia Uploader plugin is installed.
// It tries the new namespace first, then falls back to legacy namespaces for backward compatibility.
func (c *Client) CheckRiseupAsiaAvailable() (bool, string, error) {
	// Try Riseup Asia Uploader first (newest namespace)
	endpoint := fmt.Sprintf("/%s%s", RiseupAsiaNamespace, EndpointStatus)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return false, "", err
	}
	defer resp.Body.Close()

	if resp.StatusCode == http.StatusOK || resp.StatusCode == http.StatusUnauthorized || resp.StatusCode == http.StatusForbidden {
		return true, RiseupAsiaNamespace, nil
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

// CheckRiseUpUploaderAvailable is deprecated, use CheckRiseupAsiaAvailable.
func (c *Client) CheckRiseUpUploaderAvailable() (bool, string, error) {
	return c.CheckRiseupAsiaAvailable()
}

// CheckUploaderHelperAvailable is deprecated, use CheckRiseupAsiaAvailable.
func (c *Client) CheckUploaderHelperAvailable() (bool, error) {
	available, _, err := c.CheckRiseupAsiaAvailable()
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
		return nil, apperror.New(apperror.ErrWPConnection, "status request failed").
			WithContext("statusCode", resp.StatusCode).
			WithContext("endpoint", endpoint)
	}

	var status UploaderStatus
	if err := json.NewDecoder(resp.Body).Decode(&status); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode status response")
	}

	return &status, nil
}

// UploadPluginViaUploader uploads a plugin ZIP via the Rise Up Uploader.
// This uses base64-encoded upload for reliability (like the PowerShell script).
func (c *Client) UploadPluginViaUploader(zipPath string, activate bool) (*UploaderUploadResult, error) {
	// CRITICAL: Always resolve to absolute path before any file operations
	absZipPath, err := pathutil.ToAbsolute(zipPath)
	if err != nil {
		return nil, fmt.Errorf("resolve zip path: %w", err)
	}

	c.progress(ActionUpload, "running", fmt.Sprintf("Reading %s for upload...", absZipPath), map[string]interface{}{
		"zipPath":    absZipPath,
		"zipPathRel": zipPath,
	})

	// Read the ZIP file
	fileBytes, err := os.ReadFile(absZipPath)
	if err != nil {
		return nil, fmt.Errorf("read zip file at %s: %w", absZipPath, err)
	}

	// STEP 1: Check uploader status BEFORE attempting upload
	_, namespace, checkErr := c.CheckRiseupAsiaAvailable()
	if checkErr != nil {
		return nil, fmt.Errorf("pre-upload status check failed: %w", checkErr)
	}
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	// Get detailed status to verify endpoint is working
	statusEndpoint := fmt.Sprintf("/%s%s", namespace, EndpointStatus)
	statusURL := fmt.Sprintf("%s/wp-json%s", c.baseURL, statusEndpoint)
	c.progress(ActionUpload, "running", fmt.Sprintf("Pre-upload status check: GET %s", statusURL), map[string]interface{}{
		"endpoint": statusEndpoint,
		"url":      statusURL,
	})

	statusResp, statusErr := c.request("GET", statusEndpoint, nil)
	if statusErr != nil {
		return nil, &APIError{
			Operation:    "pre-upload status check",
			Method:       "GET",
			Endpoint:     statusEndpoint,
			URL:          statusURL,
			StatusCode:   0,
			ResponseBody: statusErr.Error(),
		}
	}
	defer statusResp.Body.Close()

	if statusResp.StatusCode != http.StatusOK {
		statusBody, _ := io.ReadAll(statusResp.Body)
		return nil, &APIError{
			Operation:    "pre-upload status check",
			Method:       "GET",
			Endpoint:     statusEndpoint,
			URL:          statusURL,
			StatusCode:   statusResp.StatusCode,
			ResponseBody: truncateBody(string(statusBody), 2000),
		}
	}
	c.progress(ActionUpload, "success", "Pre-upload status check passed", map[string]interface{}{
		"endpoint": statusEndpoint,
		"url":      statusURL,
		"status":   statusResp.StatusCode,
	})

	// Encode to base64
	base64Data := base64.StdEncoding.EncodeToString(fileBytes)

	// STEP 2: Build and send upload request
	uploadEndpoint := fmt.Sprintf("/%s%s", namespace, EndpointUpload)
	uploadURL := fmt.Sprintf("%s/wp-json%s", c.baseURL, uploadEndpoint)

	c.progress(ActionUpload, "running", fmt.Sprintf("Uploading %d bytes (base64) to %s", len(fileBytes), uploadURL), map[string]interface{}{
		"zipSize":   len(fileBytes),
		"zipPath":   absZipPath,
		"namespace": namespace,
		"endpoint":  uploadEndpoint,
		"url":       uploadURL,
	})

	// Build request body (JSON with base64-encoded ZIP)
	body := map[string]interface{}{
		"plugin_zip": base64Data,
		"slug":       filepath.Base(absZipPath),
		"activate":   activate,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, fmt.Errorf("marshal request body: %w", err)
	}

	req, err := http.NewRequest("POST", uploadURL, bytes.NewReader(jsonBody))
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

	c.progress(ActionUpload, "running", fmt.Sprintf("Upload response: %d from %s", resp.StatusCode, uploadURL), map[string]interface{}{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 2000),
		"url":    uploadURL,
	})

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		// Capture stack trace for debugging
		stackTrace := captureStackTrace(2)
		
		// Log detailed error for on-disk logs
		fmt.Printf("[UPLOAD ERROR] POST %s\n  ZIP: %s\n  Status: %d\n  Response: %s\n--- Stack Trace ---\n%s--- End Stack Trace ---\n", 
			uploadURL, absZipPath, resp.StatusCode, truncateBody(respBody, 4000), stackTrace)
		
		return nil, &APIError{
			Operation:    "upload plugin via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     uploadEndpoint,
			URL:          uploadURL,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
			StackTrace:   stackTrace,
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

// EnablePluginViaUploader enables (activates) a plugin via the RiseupAsia Uploader.
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
			Operation:    "enable plugin via RiseupAsia Uploader",
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

// DisablePluginViaUploader disables (deactivates) a plugin via the RiseupAsia Uploader.
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
			Operation:    "disable plugin via RiseupAsia Uploader",
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

// DeletePluginViaUploader deletes a plugin via the RiseupAsia Uploader.
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
			Operation:    "delete plugin via RiseupAsia Uploader",
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

// ListPluginsViaUploader lists all plugins via the RiseupAsia Uploader.
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

// ListPluginFilesViaUploader lists files in a plugin via the RiseupAsia Uploader.
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

// ReplaceFileViaUploader replaces a single file in a plugin via the RiseupAsia Uploader.
func (c *Client) ReplaceFileViaUploader(slug, relPath string, content []byte, isBase64 bool) error {
	_, namespace, _ := c.CheckRiseUpUploaderAvailable()
	if namespace == "" {
		namespace = RiseUpUploaderNamespace
	}

	endpoint := fmt.Sprintf("/%s"+EndpointFiles, namespace, slug)

	// Always use base64 encoding for RiseupAsia Uploader
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
			Operation:    "replace file via RiseupAsia Uploader",
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

// DeleteFileViaUploader deletes a single file from a plugin via the Riseup Asia Uploader.
func (c *Client) DeleteFileViaUploader(slug, relPath string) error {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
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
			Operation:    "delete file via Riseup Asia Uploader",
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

// =============================================================================
// DELTA SYNC TYPES AND METHODS
// =============================================================================

// SyncFile represents a single file in a sync request.
type SyncFile struct {
	Path    string `json:"path"`
	Content string `json:"content,omitempty"` // base64 encoded
	Action  string `json:"action"`            // "replace" or "delete"
}

// SyncFileResult represents the result of syncing a single file.
type SyncFileResult struct {
	Path   string `json:"path"`
	Action string `json:"action"`
	Status string `json:"status"`
	Reason string `json:"reason,omitempty"`
}

// SyncResult represents the result of a delta sync operation.
type SyncResult struct {
	Success      bool             `json:"success"`
	FilesUpdated int              `json:"files_updated"`
	FilesDeleted int              `json:"files_deleted"`
	FilesIgnored int              `json:"files_ignored"`
	IgnoredFiles []string         `json:"ignored_files"`
	Results      []SyncFileResult `json:"results"`
}

// SyncPluginFilesViaUploader performs a delta sync of multiple files to a plugin.
func (c *Client) SyncPluginFilesViaUploader(slug string, files []SyncFile) (*SyncResult, error) {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	c.progress(ActionSync, "running", fmt.Sprintf("Syncing %d files to %s...", len(files), slug), map[string]interface{}{
		"slug":      slug,
		"fileCount": len(files),
		"namespace": namespace,
	})

	endpoint := fmt.Sprintf("/%s"+EndpointSync, namespace, slug)

	body := map[string]interface{}{
		"files": files,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, fmt.Errorf("marshal sync request: %w", err)
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return nil, fmt.Errorf("create sync request: %w", err)
	}

	auth := base64.StdEncoding.EncodeToString([]byte(c.username + ":" + c.password))
	req.Header.Set(HeaderAuthorization, "Basic "+auth)
	req.Header.Set(HeaderContentType, ContentTypeJSON)
	req.Header.Set(HeaderUserAgent, UserAgentValue)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("sync request failed: %w", err)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress(ActionSync, "running", fmt.Sprintf("Sync response: %d", resp.StatusCode), map[string]interface{}{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 500),
	})

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "sync plugin files via Riseup Asia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
			PluginSlugIn: slug,
		}
	}

	var result SyncResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		return nil, fmt.Errorf("decode sync result: %w", err)
	}

	return &result, nil
}

// =============================================================================
// EXPORT SELF TYPES AND METHODS
// =============================================================================

// ExportSelfResult represents the result of exporting the uploader plugin.
type ExportSelfResult struct {
	Success    bool   `json:"success"`
	PluginName string `json:"plugin_name"`
	Version    string `json:"version"`
	PluginSlug string `json:"plugin_slug"`
	PluginZip  string `json:"plugin_zip"` // base64 encoded
	Checksum   string `json:"checksum"`
	FileCount  int    `json:"file_count"`
}

// ExportSelfFromSite fetches the Riseup Asia Uploader plugin as a ZIP from a site.
func (c *Client) ExportSelfFromSite() (*ExportSelfResult, error) {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		return nil, fmt.Errorf("Riseup Asia Uploader not available on site")
	}

	c.progress(ActionExportSelf, "running", "Exporting Riseup Asia Uploader plugin...", nil)

	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointExportSelf)

	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, fmt.Errorf("export-self request failed: %w", err)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "export self via Riseup Asia Uploader",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
		}
	}

	var result ExportSelfResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		return nil, fmt.Errorf("decode export-self result: %w", err)
	}

	c.progress(ActionExportSelf, "completed", fmt.Sprintf("Exported %s v%s (%d files)", result.PluginName, result.Version, result.FileCount), nil)

	return &result, nil
}
