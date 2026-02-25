// Package wordpress provides uploader capabilities using the Rise Up Uploader API.
package wordpress

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	action "wp-plugin-publish/internal/enums/action"
	contenttype "wp-plugin-publish/internal/enums/content_type"
	ep "wp-plugin-publish/internal/enums/endpoint"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	uploadsource "wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// Note: RiseUpUploaderNamespace is defined in constants.go

// UploaderStatus represents the /status endpoint response.
// Supports both legacy flat format and envelope Results[0] format.
type UploaderStatus struct {
	// Legacy flat fields
	Status           string            `json:"status"`            // external key (Riseup Asia Uploader API)
	Message          string            `json:"message"`           // external key
	Version          string            `json:"version"`           // external key
	WordPressVersion string            `json:"wordpress_version"` // external key
	PHPVersion       string            `json:"php_version"`       // external key
	Endpoints        map[string]string `json:"endpoints,omitempty"` // external key
	// Envelope PascalCase fields (populated when parsing from envelope Results)
	EnvVersion  string `json:"Version,omitempty"`  // external key (envelope format)
	EnvPlugin   string `json:"Plugin,omitempty"`   // external key
	EnvSlug     string `json:"Slug,omitempty"`     // external key
	EnvWp       string `json:"Wp,omitempty"`       // external key
	EnvPhp      string `json:"Php,omitempty"`      // external key
	EnvIsActive bool   `json:"IsActive,omitempty"` // external key
}

// UploaderUploadResult represents the /upload endpoint response.
type UploaderUploadResult struct {
	Success       bool   `json:"success"`                    // external key (Riseup Asia Uploader API)
	Message       string `json:"message"`                    // external key
	Plugin        string `json:"plugin,omitempty"`           // external key
	Activated     bool   `json:"activated"`                  // external key
	PluginDetails *struct {
		Name        string `json:"name"`        // external key
		Version     string `json:"version"`     // external key
		Author      string `json:"author"`      // external key
		Description string `json:"description"` // external key
	} `json:"plugin_details,omitempty"` // external key
	ActivationError string `json:"activation_error,omitempty"` // external key
}

// UploaderPluginInfo represents plugin info from the list endpoint.
type UploaderPluginInfo struct {
	Slug        string `json:"slug"`        // external key (Riseup Asia Uploader API)
	File        string `json:"file"`        // external key
	Name        string `json:"name"`        // external key
	Version     string `json:"version"`     // external key
	Author      string `json:"author"`      // external key
	Description string `json:"description"` // external key
	Active      bool   `json:"active"`      // external key
}

// UploaderFileInfo represents file info from the files endpoint.
type UploaderFileInfo struct {
	Path     string `json:"path"`     // external key (Riseup Asia Uploader API)
	Size     int64  `json:"size"`     // external key
	Modified string `json:"modified"` // external key
	Hash     string `json:"hash"`     // external key
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

	c.progress(action.Upload.String(), stagestatus.Running.String(), fmt.Sprintf("Uploading %s (%d bytes) via multipart to %s", filepath.Base(absZipPath), zipSize, uploadURL), ProgressDetails{
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

	c.progress(action.Upload.String(), stagestatus.Running.String(), fmt.Sprintf("Multipart body ready: slug=%s, activate=%v, zipSize=%d bytes, bodySize=%d bytes", slug, activate, zipSize, requestBody.Len()), ProgressDetails{
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

	c.progress(action.Upload.String(), stagestatus.Running.String(), fmt.Sprintf("Upload response: %d from %s", resp.StatusCode, uploadURL), ProgressDetails{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 2000),
		"url":    uploadURL,
	})

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		return nil, buildUploadAPIError(absZipPath, uploadURL, uploadEndpoint, resp.StatusCode, respBytes, respBody, c.stackTraceDepth)
	}

	var result UploaderUploadResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		result.Success = true
		result.Message = "Upload completed"
	}

	return &result, nil
}

// buildUploadAPIError constructs a detailed APIError for upload failures.
func buildUploadAPIError(absZipPath, uploadURL, uploadEndpoint string, statusCode int, respBytes []byte, respBody string, stackTraceDepth int) *APIError {
	stackTrace := captureStackTraceN(3, stackTraceDepth)

	diagnosticBody := truncateBody(respBody, 8192)
	if diagnosticBody == "" {
		diagnosticBody = "[EMPTY RESPONSE BODY - The WordPress server returned no content. " +
			"This typically indicates a fatal PHP error that crashed before the error handler could respond. " +
			"Check the WordPress debug.log, PHP error log, or wp-content/uploads/riseup-asia-uploader/fatal-errors.log for details.]"
	}

	fmt.Printf("[UPLOAD ERROR] POST %s\n  ZIP: %s\n  Status: %d\n  Response: %s\n--- Stack Trace ---\n%s--- End Stack Trace ---\n",
		uploadURL, absZipPath, statusCode, truncateBody(respBody, 4000), stackTrace)

	phpStackInfo := ExtractPHPStackTrace(respBytes)

	return &APIError{
		Operation:    "upload plugin via RiseupAsia Uploader",
		Method:       "POST",
		Endpoint:     uploadEndpoint,
		Url:          uploadURL,
		StatusCode:   statusCode,
		ResponseBody: diagnosticBody + phpStackInfo,
		StackTrace:   stackTrace,
	}
}
