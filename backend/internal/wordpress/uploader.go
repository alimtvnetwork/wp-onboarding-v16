// Package wordpress provides uploader capabilities using the Rise Up Uploader API.
package wordpress

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"path/filepath"
	"strings"

	"wp-plugin-publish/internal/enums/action"
	"wp-plugin-publish/internal/enums/content_type"
	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/pkg/apperror"
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
	uc, err := c.prepareUploadContext(zipPath, slug)
	if err != nil {
		return nil, err
	}
	defer uc.ZipFile.Close()

	c.progress(action.Upload.String(), stagestatus.Running.String(), fmt.Sprintf("Uploading %s (%d bytes) via multipart to %s", filepath.Base(uc.AbsZipPath), uc.ZipSize, uc.UploadURL), toProgress(UploadInitProgress{
		ZipSize: uc.ZipSize, ZipPath: uc.AbsZipPath, Namespace: uc.Namespace, Endpoint: uc.UploadEndpoint, URL: uc.UploadURL, Method: "multipart/form-data",
	}))

	body, contentType, err := buildMultipartBody(uc, activate, uploadSource)
	if err != nil {
		return nil, err
	}

	c.progress(action.Upload.String(), stagestatus.Running.String(), fmt.Sprintf("Multipart body ready: slug=%s, activate=%v, zipSize=%d bytes, bodySize=%d bytes", uc.Slug, activate, uc.ZipSize, body.Len()), toProgress(UploadBodyProgress{
		Slug: uc.Slug, Activate: activate, ZipSize: uc.ZipSize, BodySize: body.Len(),
	}))

	return c.executeUploadHTTP(uc, body, contentType)
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
