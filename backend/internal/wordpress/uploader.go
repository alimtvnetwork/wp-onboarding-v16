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

	"wp-plugin-publish/pkg/apperror"
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
func (c *Client) UploadPluginViaUploader(zipPath string, slug string, activate bool) (*UploaderUploadResult, error) {
	// CRITICAL: Always resolve to absolute path before any file operations
	absZipPath, err := pathutil.ToAbsolute(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "resolve zip path").
			WithContext("path", zipPath)
	}

	c.progress(ActionUpload, "running", fmt.Sprintf("Reading %s for upload...", absZipPath), map[string]interface{}{
		"zipPath":    absZipPath,
		"zipPathRel": zipPath,
	})

	// Validate slug - must be provided and not include .zip extension
	if slug == "" {
		// Derive slug from ZIP filename (strip .zip extension)
		slug = strings.TrimSuffix(filepath.Base(absZipPath), ".zip")
	}
	slug = strings.TrimSuffix(slug, ".zip") // Ensure no .zip extension

	// Read the ZIP file
	fileBytes, err := os.ReadFile(absZipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "read zip file").
			WithContext("path", pathutil.ForDisplay(absZipPath))
	}

	// STEP 1: Check uploader status BEFORE attempting upload
	_, namespace, checkErr := c.CheckRiseupAsiaAvailable()
	if checkErr != nil {
		return nil, apperror.Wrap(checkErr, apperror.ErrWPConnection, "pre-upload status check failed")
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

	// Build request body (JSON with base64-encoded ZIP) - match PowerShell script format
	// PowerShell sends: plugin_zip (base64), slug (folder name, no .zip), activate (bool)
	body := map[string]interface{}{
		"plugin_zip": base64Data,
		"slug":       slug,
		"activate":   activate,
	}

	c.progress(ActionUpload, "running", fmt.Sprintf("Upload request body: slug=%s, activate=%v, zipSize=%d bytes", slug, activate, len(fileBytes)), map[string]interface{}{
		"slug":     slug,
		"activate": activate,
		"zipSize":  len(fileBytes),
	})

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "marshal upload request body")
	}

	req, err := http.NewRequest("POST", uploadURL, bytes.NewReader(jsonBody))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create upload HTTP request").
			WithContext("url", uploadURL)
	}

	c.setStandardHeaders(req, ContentTypeJSON)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "upload request failed").
			WithContext("url", uploadURL)
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
		
		// Enhanced diagnostic for empty response body
		diagnosticBody := truncateBody(respBody, 8192)
		if diagnosticBody == "" {
			diagnosticBody = "[EMPTY RESPONSE BODY - The WordPress server returned no content. " +
				"This typically indicates a fatal PHP error that crashed before the error handler could respond. " +
				"Check the WordPress debug.log, PHP error log, or wp-content/uploads/riseup-asia-uploader/fatal-errors.log for details.]"
		}
		
		// Log detailed error for on-disk logs
		fmt.Printf("[UPLOAD ERROR] POST %s\n  ZIP: %s\n  Status: %d\n  Response: %s\n--- Stack Trace ---\n%s--- End Stack Trace ---\n", 
			uploadURL, absZipPath, resp.StatusCode, truncateBody(respBody, 4000), stackTrace)
		
		// Try to parse WordPress error response for PHP stack trace frames
		var parsedError struct {
			Success bool `json:"success"`
			Error   struct {
				Code    string `json:"code"`
				Message string `json:"message"`
				Details struct {
					StackTrace       string `json:"stackTrace"`
					StackTraceFrames []struct {
						File     string `json:"file"`
						FileBase string `json:"fileBase"`
						Line     int    `json:"line"`
						Function string `json:"function"`
						Class    string `json:"class"`
					} `json:"stackTraceFrames"`
					ExceptionClass string `json:"exceptionClass"`
					PHPVersion     string `json:"phpVersion"`
				} `json:"details"`
			} `json:"error"`
		}
		
		// Attempt to parse the error response for additional PHP context
		phpStackInfo := ""
		if respBody != "" {
			if err := json.Unmarshal(respBytes, &parsedError); err == nil && len(parsedError.Error.Details.StackTraceFrames) > 0 {
				phpStackInfo = "\n--- PHP Stack Trace (from WordPress) ---\n"
				for i, frame := range parsedError.Error.Details.StackTraceFrames {
					funcName := frame.Function
					if frame.Class != "" {
						funcName = frame.Class + "::" + frame.Function
					}
					phpStackInfo += fmt.Sprintf("  #%d %s() at %s:%d\n", i, funcName, frame.FileBase, frame.Line)
				}
				phpStackInfo += "--- End PHP Stack Trace ---"
			}
		}
		
		return nil, &APIError{
			Operation:    "upload plugin via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     uploadEndpoint,
			URL:          uploadURL,
			StatusCode:   resp.StatusCode,
			ResponseBody: diagnosticBody + phpStackInfo,
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

// normalizePluginSlug extracts the folder-level slug from a full WordPress plugin
// identifier like "broken-link-checker/broken-link-checker.php".
// The Riseup Asia Uploader's find_plugin_file() expects just the folder name
// (e.g. "broken-link-checker"), not the full path.
func normalizePluginSlug(slug string) string {
	// If it contains a slash, extract just the directory part
	if strings.Contains(slug, "/") {
		dir := filepath.Dir(slug)
		if dir != "." && dir != "" {
			return dir
		}
	}
	// Strip .php extension if present
	slug = strings.TrimSuffix(slug, ".php")
	return slug
}

// EnablePluginViaUploader enables (activates) a plugin via the RiseupAsia Uploader.
// Uses a fixed endpoint with JSON body containing the plugin slug.
func (c *Client) EnablePluginViaUploader(slug string) error {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + EndpointEnable
	reqBody := map[string]string{"plugin": normalizedSlug}
	reqBodyJSON, _ := json.Marshal(reqBody)
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPPluginActivate, "enable plugin request failed").
			WithContext("slug", normalizedSlug)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "enable plugin via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			RequestBody:  string(reqBodyJSON),
			ResponseBody: truncateBody(string(bodyBytes), 8192),
			PluginSlugIn: normalizedSlug,
		}
	}

	return nil
}

// DisablePluginViaUploader disables (deactivates) a plugin via the RiseupAsia Uploader.
// Uses a fixed endpoint with JSON body containing the plugin slug.
func (c *Client) DisablePluginViaUploader(slug string) error {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + EndpointDisable
	reqBody := map[string]string{"plugin": normalizedSlug}
	reqBodyJSON, _ := json.Marshal(reqBody)
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPPluginActivate, "disable plugin request failed").
			WithContext("slug", normalizedSlug)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "disable plugin via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			RequestBody:  string(reqBodyJSON),
			ResponseBody: truncateBody(string(bodyBytes), 8192),
			PluginSlugIn: normalizedSlug,
		}
	}

	return nil
}

// DeletePluginViaUploader deletes a plugin via the RiseupAsia Uploader.
// Uses a fixed endpoint with JSON body containing the plugin slug.
func (c *Client) DeletePluginViaUploader(slug string) error {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + EndpointDelete
	reqBody := map[string]string{"plugin": normalizedSlug}
	reqBodyJSON, _ := json.Marshal(reqBody)
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "delete plugin request failed").
			WithContext("slug", normalizedSlug)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    "delete plugin via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			RequestBody:  string(reqBodyJSON),
			ResponseBody: truncateBody(string(bodyBytes), 8192),
			PluginSlugIn: normalizedSlug,
		}
	}

	return nil
}

// ListPluginsViaUploader lists all plugins via the RiseupAsia Uploader.
func (c *Client) ListPluginsViaUploader() ([]UploaderPluginInfo, error) {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointPlugins)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, apperror.New(apperror.ErrWPPluginList, "list plugins failed").
			WithContext("statusCode", resp.StatusCode)
	}

	var response struct {
		Success bool                 `json:"success"`
		Count   int                  `json:"count"`
		Plugins []UploaderPluginInfo `json:"plugins"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&response); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode plugins response")
	}

	return response.Plugins, nil
}

// ListPluginFilesViaUploader lists files in a plugin via the RiseupAsia Uploader.
// Uses a fixed endpoint with JSON body containing the plugin slug.
func (c *Client) ListPluginFilesViaUploader(slug string) ([]UploaderFileInfo, error) {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	endpoint := "/" + namespace + EndpointFiles
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, apperror.New(apperror.ErrWPPluginGet, "list plugin files failed").
			WithContext("statusCode", resp.StatusCode).
			WithContext("slug", slug)
	}

	var response struct {
		Success bool               `json:"success"`
		Slug    string             `json:"slug"`
		Count   int                `json:"count"`
		Files   []UploaderFileInfo `json:"files"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&response); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode files response")
	}

	return response.Files, nil
}

// ReplaceFileViaUploader replaces a single file in a plugin via the RiseupAsia Uploader.
// Uses a fixed endpoint with slug and file details in JSON body.
func (c *Client) ReplaceFileViaUploader(slug, relPath string, content []byte, isBase64 bool) error {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	endpoint := "/" + namespace + EndpointFiles

	// Always use base64 encoding for RiseupAsia Uploader
	contentStr := base64.StdEncoding.EncodeToString(content)

	body := map[string]string{
		"plugin":  slug,
		"path":    relPath,
		"content": contentStr,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "marshal replace file body")
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "create replace file request").
			WithContext("url", url)
	}

	c.setStandardHeaders(req, ContentTypeJSON)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "replace file request failed").
			WithContext("path", relPath)
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
// Uses a fixed endpoint with slug and file path in JSON body.
func (c *Client) DeleteFileViaUploader(slug, relPath string) error {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		namespace = RiseupAsiaNamespace
	}

	endpoint := "/" + namespace + EndpointFiles

	body := map[string]string{"plugin": slug, "path": relPath, "action": "delete"}
	jsonBody, _ := json.Marshal(body)

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "create delete file request").
			WithContext("url", url)
	}

	c.setStandardHeaders(req, ContentTypeJSON)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "delete file request failed").
			WithContext("path", relPath)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		respBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "delete file via Riseup Asia Uploader",
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
// Uses a fixed endpoint with slug in JSON body.
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

	endpoint := "/" + namespace + EndpointSync

	body := map[string]interface{}{
		"plugin": slug,
		"files":  files,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "marshal sync request")
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create sync request").
			WithContext("url", url)
	}

	c.setStandardHeaders(req, ContentTypeJSON)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "sync request failed").
			WithContext("slug", slug)
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
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode sync result")
	}

	return &result, nil
}

// =============================================================================
// EXPORT TYPES AND METHODS
// =============================================================================

// ExportPluginResult holds the response from the export-plugin endpoint.
type ExportPluginResult struct {
	Success   bool   `json:"success"`
	PluginZip string `json:"plugin_zip"` // base64 encoded
	Slug      string `json:"slug"`
	FileCount int    `json:"file_count"`
	Size      int    `json:"size"`
}

// ExportPlugin fetches an arbitrary plugin as a base64-encoded ZIP from the remote site.
// Uses a fixed endpoint with slug in JSON body.
func (c *Client) ExportPlugin(slug string) (*ExportPluginResult, error) {
	_, namespace, _ := c.CheckRiseupAsiaAvailable()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	endpoint := "/" + namespace + EndpointExportPlugin
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "export-plugin request failed").
			WithContext("slug", slug)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "export plugin for rollback",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
			PluginSlugIn: slug,
		}
	}

	var result ExportPluginResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode export-plugin result")
	}

	return &result, nil
}

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
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	c.progress(ActionExportSelf, "running", "Exporting Riseup Asia Uploader plugin...", nil)

	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointExportSelf)

	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "export-self request failed")
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
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode export-self result")
	}

	c.progress(ActionExportSelf, "completed", fmt.Sprintf("Exported %s v%s (%d files)", result.PluginName, result.Version, result.FileCount), nil)

	return &result, nil
}

// RemoteLogFile represents a single log file returned by the error-logs endpoint.
type RemoteLogFile struct {
	Exists     bool   `json:"exists"`
	File       string `json:"file"`
	Path       string `json:"path"`
	Content    string `json:"content"`
	Lines      int    `json:"lines"`
	TotalLines int    `json:"total_lines"`
	TotalSize  int64  `json:"total_size"`
	Truncated  bool   `json:"truncated"`
}

// RemoteErrorLogsResult represents the /error-logs endpoint response.
type RemoteErrorLogsResult struct {
	Success          bool                   `json:"success"`
	Version          string                 `json:"version"`
	Settings         map[string]interface{} `json:"settings"`
	ErrorLog         *RemoteLogFile         `json:"error_log,omitempty"`
	FullLog          *RemoteLogFile         `json:"full_log,omitempty"`
	StackTraceFrames []map[string]interface{} `json:"stackTraceFrames,omitempty"`
}

// FetchRemoteErrorLogs retrieves the PHP error and log files from the WordPress plugin.
// Uses GET /error-logs with Basic Auth. The response is controlled by the plugin's
// log_retrieval settings (which logs to include, max lines).
func (c *Client) FetchRemoteErrorLogs() (*RemoteErrorLogsResult, error) {
	_, namespace, err := c.CheckRiseupAsiaAvailable()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "check uploader availability for error-logs")
	}
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointErrorLogs)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "fetch remote error logs")
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "fetch remote error logs",
			Method:       "GET",
			Endpoint:     endpoint,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBody), 4000),
			StackTrace:   captureStackTrace(2),
		}
	}

	var result RemoteErrorLogsResult
	if err := json.Unmarshal(respBody, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode remote error logs response")
	}

	return &result, nil
}

// RemoteErrorSessionEntry represents a single structured error from the plugin's SQLite DB.
type RemoteErrorSessionEntry struct {
	ID               int                      `json:"id"`
	Level            string                   `json:"level"`
	Message          string                   `json:"message"`
	File             string                   `json:"file"`
	FileBase         string                   `json:"fileBase"`
	Line             *int                     `json:"line"`
	StackTrace       string                   `json:"stackTrace,omitempty"`
	StackTraceFrames []map[string]interface{} `json:"stackTraceFrames,omitempty"`
	Context          interface{}              `json:"context,omitempty"`
	CreatedAt        string                   `json:"created_at"`
}

// RemoteFlashState represents the flash notification state from the plugin.
type RemoteFlashState struct {
	LastSeenID  int  `json:"last_seen_id"`
	HasUnseen   bool `json:"has_unseen"`
	UnseenCount int  `json:"unseen_count"`
}

// RemoteErrorSessionsResult represents the /error-sessions endpoint response.
type RemoteErrorSessionsResult struct {
	Success          bool                      `json:"success"`
	Version          string                    `json:"version"`
	Message          string                    `json:"message,omitempty"`
	Entries          []RemoteErrorSessionEntry `json:"entries"`
	Total            int                       `json:"total"`
	Limit            int                       `json:"limit"`
	Offset           int                       `json:"offset"`
	Flash            RemoteFlashState          `json:"flash"`
	StackTraceFrames []map[string]interface{}  `json:"stackTraceFrames,omitempty"`
}

// FetchRemoteErrorSessions retrieves structured error entries from the WordPress plugin's
// error_sessions SQLite table. Supports filtering by level, search, since_id, and pagination.
func (c *Client) FetchRemoteErrorSessions(level string, search string, sinceID int, limit int, offset int) (*RemoteErrorSessionsResult, error) {
	_, namespace, err := c.CheckRiseupAsiaAvailable()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "check uploader availability for error-sessions")
	}
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	// Build query string
	endpoint := fmt.Sprintf("/%s%s", namespace, EndpointErrorSessions)
	params := []string{}
	if level != "" {
		params = append(params, fmt.Sprintf("level=%s", level))
	}
	if search != "" {
		params = append(params, fmt.Sprintf("search=%s", search))
	}
	if sinceID > 0 {
		params = append(params, fmt.Sprintf("since_id=%d", sinceID))
	}
	if limit > 0 {
		params = append(params, fmt.Sprintf("limit=%d", limit))
	}
	if offset > 0 {
		params = append(params, fmt.Sprintf("offset=%d", offset))
	}
	if len(params) > 0 {
		endpoint += "?" + strings.Join(params, "&")
	}

	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "fetch remote error sessions")
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "fetch remote error sessions",
			Method:       "GET",
			Endpoint:     endpoint,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBody), 4000),
			StackTrace:   captureStackTrace(2),
		}
	}

	var result RemoteErrorSessionsResult
	if err := json.Unmarshal(respBody, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode remote error sessions response")
	}

	return &result, nil
}
