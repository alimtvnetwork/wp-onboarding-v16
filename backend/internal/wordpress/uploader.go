// Package wordpress provides uploader capabilities using the Rise Up Uploader API.
package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"runtime"
	"strings"

	"wp-plugin-publish/internal/enums/action"
	contenttype "wp-plugin-publish/internal/enums/content_type"
	ep "wp-plugin-publish/internal/enums/endpoint"
	uploadsource "wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// DefaultStackTraceDepth is used when no config value is provided
const DefaultStackTraceDepth = 20

// captureStackTrace captures the call stack for debugging.
// maxDepth controls max frames captured (0 = use DefaultStackTraceDepth).
func captureStackTrace(skip int) string {
	return captureStackTraceN(skip+1, DefaultStackTraceDepth)
}

// captureStackTraceN captures the call stack with a configurable depth.
func captureStackTraceN(skip int, maxDepth int) string {
	if maxDepth <= 0 {
		maxDepth = DefaultStackTraceDepth
	}
	var builder strings.Builder
	pcs := make([]uintptr, maxDepth+10) // extra buffer for runtime frames that get filtered
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
		if !more || frameNum >= maxDepth {
			break
		}
	}
	return builder.String()
}

// Note: RiseUpUploaderNamespace is defined in constants.go

// UploaderStatus represents the /status endpoint response.
// Supports both legacy flat format and envelope Results[0] format.
type UploaderStatus struct {
	// Legacy flat fields
	Status           string            `json:"status"`
	Message          string            `json:"message"`
	Version          string            `json:"version"`
	WordPressVersion string            `json:"wordpress_version"`
	PHPVersion       string            `json:"php_version"`
	Endpoints        map[string]string `json:"endpoints,omitempty"`
	// Envelope PascalCase fields (populated when parsing from envelope Results)
	EnvVersion  string `json:"Version,omitempty"`
	EnvPlugin   string `json:"Plugin,omitempty"`
	EnvSlug     string `json:"Slug,omitempty"`
	EnvWp       string `json:"Wp,omitempty"`
	EnvPhp      string `json:"Php,omitempty"`
	EnvIsActive bool   `json:"IsActive,omitempty"`
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

// uploaderNamespaces defines the namespace probe order: newest first, then legacy.
var uploaderNamespaces = []string{
	RiseupAsiaNamespace,
	RiseUpUploaderNamespace,
	PluginUploaderNamespace,
}

// CheckRiseupAsiaAvailable checks if the Riseup Asia Uploader plugin is installed.
// It tries namespaces in priority order (newest first) and returns the first match.
func (c *Client) CheckRiseupAsiaAvailable() (bool, string, error) {
	for _, ns := range uploaderNamespaces {
		endpoint := "/" + ns + ep.Status.String()
		resp, err := c.request("GET", endpoint, nil)
		if err != nil {
			return false, "", err
		}
		resp.Body.Close()

		if resp.StatusCode == http.StatusOK || resp.StatusCode == http.StatusUnauthorized || resp.StatusCode == http.StatusForbidden {
			return true, ns, nil
		}
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
	namespace := c.resolveNamespace()

	endpoint := fmt.Sprintf("/%s%s", namespace, ep.Status)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, apperror.New(apperror.ErrWPConnection, "status request failed").
			WithStatusCode(resp.StatusCode).
			WithEndpoint(endpoint)
	}

	respBody, _ := io.ReadAll(resp.Body)

	// Try envelope format first
	if status, ok := UnwrapSingleResult[UploaderStatus](respBody); ok {
		// Normalize envelope fields to legacy fields for backward compat
		if status.Version == "" && status.EnvVersion != "" {
			status.Version = status.EnvVersion
		}
		if status.WordPressVersion == "" && status.EnvWp != "" {
			status.WordPressVersion = status.EnvWp
		}
		if status.PHPVersion == "" && status.EnvPhp != "" {
			status.PHPVersion = status.EnvPhp
		}
		return status, nil
	}

	// Fall back to legacy flat format
	var status UploaderStatus
	if err := json.Unmarshal(respBody, &status); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode status response")
	}

	return &status, nil
}

// UploadPluginViaUploader uploads a plugin ZIP via the Rise Up Uploader.
// Uses multipart/form-data for efficiency (no base64 overhead, streamed upload).
// uploadSource identifies how the upload was triggered (e.g., uploadsource.RestAPI).
func (c *Client) UploadPluginViaUploader(zipPath string, slug string, activate bool, uploadSource uploadsource.Variant) (*UploaderUploadResult, error) {
	// CRITICAL: Always resolve to absolute path before any file operations
	absZipPath, err := pathutil.ToAbsolute(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "resolve zip path").
			WithPath(zipPath)
	}

	// Validate slug - must be provided and not include .zip extension
	if slug == "" {
		slug = strings.TrimSuffix(filepath.Base(absZipPath), ".zip")
	}
	slug = strings.TrimSuffix(slug, ".zip")

	// Open the ZIP file for streaming (no full memory load)
	zipFile, err := os.Open(absZipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "open zip file").
			WithPath(pathutil.ForDisplay(absZipPath))
	}
	defer zipFile.Close()

	// Get file size for progress reporting
	fileInfo, err := zipFile.Stat()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "stat zip file").
			WithPath(pathutil.ForDisplay(absZipPath))
	}
	zipSize := fileInfo.Size()

	namespace := c.resolveNamespace()
	uploadEndpoint := fmt.Sprintf("/%s%s", namespace, ep.Upload)
	uploadURL := fmt.Sprintf("%s/wp-json%s", c.baseURL, uploadEndpoint)

	c.progress(action.Upload.String(), "running", fmt.Sprintf("Uploading %s (%d bytes) via multipart to %s", filepath.Base(absZipPath), zipSize, uploadURL), ProgressDetails{
		"zipSize":   zipSize,
		"zipPath":   absZipPath,
		"namespace": namespace,
		"endpoint":  uploadEndpoint,
		"url":       uploadURL,
		"method":    "multipart/form-data",
	})

	// Build multipart form body — stream the ZIP file directly
	var requestBody bytes.Buffer
	writer := multipart.NewWriter(&requestBody)

	// Add the ZIP file as a form file field
	part, err := writer.CreateFormFile("plugin_zip", filepath.Base(absZipPath))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create multipart form file")
	}
	if _, err := io.Copy(part, zipFile); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "stream zip to multipart")
	}

	// Add form fields
	_ = writer.WriteField("slug", slug)
	if activate {
		_ = writer.WriteField("activate", "1")
	} else {
		_ = writer.WriteField("activate", "0")
	}
	_ = writer.WriteField("upload_source", uploadSource.String())

	if err := writer.Close(); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "close multipart writer")
	}

	c.progress(action.Upload.String(), "running", fmt.Sprintf("Multipart body ready: slug=%s, activate=%v, zipSize=%d bytes, bodySize=%d bytes", slug, activate, zipSize, requestBody.Len()), ProgressDetails{
		"slug":     slug,
		"activate": activate,
		"zipSize":  zipSize,
		"bodySize": requestBody.Len(),
	})

	req, err := http.NewRequest("POST", uploadURL, &requestBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create upload HTTP request").
			WithURL(uploadURL)
	}

	c.setStandardHeaders(req, writer.FormDataContentType())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "upload request failed").
			WithURL(uploadURL)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress(action.Upload.String(), "running", fmt.Sprintf("Upload response: %d from %s", resp.StatusCode, uploadURL), ProgressDetails{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 2000),
		"url":    uploadURL,
	})

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		stackTrace := captureStackTraceN(2, c.stackTraceDepth)

		diagnosticBody := truncateBody(respBody, 8192)
		if diagnosticBody == "" {
			diagnosticBody = "[EMPTY RESPONSE BODY - The WordPress server returned no content. " +
				"This typically indicates a fatal PHP error that crashed before the error handler could respond. " +
				"Check the WordPress debug.log, PHP error log, or wp-content/uploads/riseup-asia-uploader/fatal-errors.log for details.]"
		}

		fmt.Printf("[UPLOAD ERROR] POST %s\n  ZIP: %s\n  Status: %d\n  Response: %s\n--- Stack Trace ---\n%s--- End Stack Trace ---\n",
			uploadURL, absZipPath, resp.StatusCode, truncateBody(respBody, 4000), stackTrace)

		phpStackInfo := ExtractPHPStackTrace(respBytes)

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

// pluginLifecycleInput holds the parameters for a plugin lifecycle action.
type pluginLifecycleInput struct {
	Slug          string
	Endpoint      ep.Variant
	OperationName string
	ErrorCode     string
}

// pluginLifecycleAction is the shared implementation for Enable, Disable, and Delete.
// It resolves the namespace, normalizes the slug, sends the POST, and returns a
// structured APIError on failure.
func (c *Client) pluginLifecycleAction(input pluginLifecycleInput) error {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(input.Slug)

	endpoint := "/" + namespace + input.Endpoint.String()
	reqBody := map[string]string{"plugin": normalizedSlug}
	reqBodyJSON, _ := json.Marshal(reqBody)
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return apperror.Wrap(err, input.ErrorCode, input.OperationName+" request failed").
			WithSlug(normalizedSlug)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    input.OperationName + " via RiseupAsia Uploader",
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

// CheckPluginExistsViaUploader checks if a plugin slug is installed on the remote site.
// Returns exists (bool), status (string: active/inactive/not_installed), and pluginFile (string).
func (c *Client) CheckPluginExistsViaUploader(slug string) (bool, string, string, error) {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + ep.PluginExists.String()
	reqBody := map[string]string{"plugin": normalizedSlug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return false, "", "", apperror.Wrap(err, apperror.ErrWPConnection, "check plugin exists request failed").
			WithSlug(normalizedSlug)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return false, "", "", &APIError{
			Operation:    "check plugin exists via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 4096),
			PluginSlugIn: normalizedSlug,
		}
	}

	// Try envelope format
	type existsResult struct {
		PluginSlug string `json:"plugin_slug"`
		Exists     bool   `json:"exists"`
		Status     string `json:"status"`
		PluginFile string `json:"plugin_file"`
	}
	if results, ok := UnwrapResults[existsResult](bodyBytes); ok && len(results) > 0 {
		return results[0].Exists, results[0].Status, results[0].PluginFile, nil
	}

	// Legacy fallback
	var legacy struct {
		Exists     bool   `json:"exists"`
		Status     string `json:"status"`
		PluginFile string `json:"plugin_file"`
	}
	if err := json.Unmarshal(bodyBytes, &legacy); err != nil {
		return false, "", "", apperror.Wrap(err, apperror.ErrInternal, "decode plugin exists response")
	}
	return legacy.Exists, legacy.Status, legacy.PluginFile, nil
}

// EnablePluginViaUploader enables (activates) a plugin via the RiseupAsia Uploader.
func (c *Client) EnablePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Enable,
		OperationName: "enable plugin",
		ErrorCode:     apperror.ErrWPPluginActivate,
	})
}

// DisablePluginViaUploader disables (deactivates) a plugin via the RiseupAsia Uploader.
func (c *Client) DisablePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Disable,
		OperationName: "disable plugin",
		ErrorCode:     apperror.ErrWPPluginActivate,
	})
}

// DeletePluginViaUploader deletes a plugin via the RiseupAsia Uploader.
func (c *Client) DeletePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Delete,
		OperationName: "delete plugin",
		ErrorCode:     apperror.ErrWPConnection,
	})
}

// ListPluginsViaUploader lists all plugins via the RiseupAsia Uploader.
func (c *Client) ListPluginsViaUploader() ([]UploaderPluginInfo, error) {
	namespace := c.resolveNamespace()

	endpoint := fmt.Sprintf("/%s%s", namespace, ep.Plugins)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, apperror.New(apperror.ErrWPPluginList, "list plugins failed").
			WithStatusCode(resp.StatusCode)
	}

	var response struct {
		Success bool                 `json:"success"`
		Count   int                  `json:"count"`
		Plugins []UploaderPluginInfo `json:"plugins"`
	}

	respBody, _ := io.ReadAll(resp.Body)

	// Try envelope format first — Results is the plugins array directly
	if plugins, ok := UnwrapResults[UploaderPluginInfo](respBody); ok {
		return plugins, nil
	}

	// Fall back to legacy flat format
	if err := json.Unmarshal(respBody, &response); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode plugins response")
	}

	return response.Plugins, nil
}

// ListPluginFilesViaUploader lists files in a plugin via the RiseupAsia Uploader.
// Uses a fixed endpoint with JSON body containing the plugin slug.
func (c *Client) ListPluginFilesViaUploader(slug string) ([]UploaderFileInfo, error) {
	namespace := c.resolveNamespace()

	endpoint := "/" + namespace + ep.Files.String()
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, apperror.New(apperror.ErrWPPluginGet, "list plugin files failed").
			WithStatusCode(resp.StatusCode).
			WithSlug(slug)
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
	namespace := c.resolveNamespace()

	endpoint := "/" + namespace + ep.Files.String()

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
			WithURL(url)
	}

	c.setStandardHeaders(req, contenttype.JSON.String())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "replace file request failed").
			WithPath(relPath)
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
	namespace := c.resolveNamespace()

	endpoint := "/" + namespace + ep.Files.String()

	body := map[string]string{"plugin": slug, "path": relPath, "action": "delete"}
	jsonBody, _ := json.Marshal(body)

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "create delete file request").
			WithURL(url)
	}

	c.setStandardHeaders(req, contenttype.JSON.String())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "delete file request failed").
			WithPath(relPath)
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
	namespace := c.resolveNamespace()

	c.progress(action.Sync.String(), "running", fmt.Sprintf("Syncing %d files to %s...", len(files), slug), ProgressDetails{
		"slug":      slug,
		"fileCount": len(files),
		"namespace": namespace,
	})

	endpoint := "/" + namespace + ep.Sync.String()

	body := ProgressDetails{
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
			WithURL(url)
	}

	c.setStandardHeaders(req, contenttype.JSON.String())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "sync request failed").
			WithSlug(slug)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress(action.Sync.String(), "running", fmt.Sprintf("Sync response: %d", resp.StatusCode), ProgressDetails{
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
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	endpoint := "/" + namespace + ep.ExportPlugin.String()
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "export-plugin request failed").
			WithSlug(slug)
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
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	c.progress(action.ExportSelf.String(), "running", "Exporting Riseup Asia Uploader plugin...", nil)

	endpoint := fmt.Sprintf("/%s%s", namespace, ep.ExportSelf)

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

	c.progress(action.ExportSelf.String(), "completed", fmt.Sprintf("Exported %s v%s (%d files)", result.PluginName, result.Version, result.FileCount), nil)

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
	Success          bool                     `json:"success"`
	Version          string                   `json:"version"`
	Settings         ProgressDetails          `json:"settings"`
	ErrorLog         *RemoteLogFile           `json:"error_log,omitempty"`
	FullLog          *RemoteLogFile           `json:"full_log,omitempty"`
	StackTraceLog    *RemoteLogFile           `json:"stacktrace_log,omitempty"`
	StackTraceFrames []PHPStackTraceFrame     `json:"stackTraceFrames,omitempty"`
}

// FetchRemoteErrorLogs retrieves the PHP error and log files from the WordPress plugin.
// Uses GET /error-logs with Basic Auth. The response is controlled by the plugin's
// log_retrieval settings (which logs to include, max lines).
func (c *Client) FetchRemoteErrorLogs() (*RemoteErrorLogsResult, error) {
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	endpoint := fmt.Sprintf("/%s%s", namespace, ep.ErrorLogs)
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
			StackTrace:   captureStackTraceN(2, c.stackTraceDepth),
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
	ID               int                    `json:"id"`
	Level            string                 `json:"level"`
	Message          string                 `json:"message"`
	File             string                 `json:"file"`
	FileBase         string                 `json:"fileBase"`
	Line             *int                   `json:"line"`
	StackTrace       string                 `json:"stackTrace,omitempty"`
	StackTraceFrames []PHPStackTraceFrame   `json:"stackTraceFrames,omitempty"`
	Context          json.RawMessage        `json:"context,omitempty"`
	CreatedAt        string                 `json:"created_at"`
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
	StackTraceFrames []PHPStackTraceFrame      `json:"stackTraceFrames,omitempty"`
}

// FetchRemoteErrorSessions retrieves structured error entries from the WordPress plugin's
// error_sessions SQLite table. Supports filtering by level, search, since_id, and pagination.
func (c *Client) FetchRemoteErrorSessions(level string, search string, sinceID int, limit int, offset int) (*RemoteErrorSessionsResult, error) {
	namespace := c.resolveNamespace()
	if namespace == "" {
		return nil, apperror.New(apperror.ErrWPConnection, "Riseup Asia Uploader not available")
	}

	// Build query string
	endpoint := fmt.Sprintf("/%s%s", namespace, ep.ErrorSessions)
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
			StackTrace:   captureStackTraceN(2, c.stackTraceDepth),
		}
	}

	var result RemoteErrorSessionsResult
	if err := json.Unmarshal(respBody, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode remote error sessions response")
	}

	return &result, nil
}
