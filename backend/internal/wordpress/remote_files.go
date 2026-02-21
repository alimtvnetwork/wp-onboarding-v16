// Package wordpress provides remote file/upload capabilities via the Riseup Asia Uploader companion plugin API.
package wordpress

import (
	"bytes"
	"context"

	"encoding/json"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	ep "wp-plugin-publish/internal/enums/endpoint"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/pkg/apperror"
)

// Note: OnboardNamespace is defined in constants.go

// RemoteFile represents a file in a remote WordPress plugin
type RemoteFile struct {
	Path       string    `json:"path"`
	Hash       string    `json:"hash"`
	Size       int64     `json:"size"`
	ModifiedAt time.Time `json:"modifiedAt"`
}

// OnboardUploadResult represents the response from the upload endpoint.
type OnboardUploadResult struct {
	Success      bool   `json:"success"`
	Message      string `json:"message"`
	PluginSlug   string `json:"plugin_slug,omitempty"`
	PluginName   string `json:"plugin_name,omitempty"`
	Version      string `json:"version,omitempty"`
	PreviousVer  string `json:"previous_version,omitempty"`
	FilesUpdated int    `json:"files_updated,omitempty"`
	Overwritten  bool   `json:"overwritten,omitempty"`
}

// GetPluginFiles retrieves the list of files for a remote plugin.
// Delegates to GetPluginFilesViaRiseup (Riseup Asia Uploader).
func (c *Client) GetPluginFiles(ctx context.Context, slug string) ([]RemoteFile, error) {
	return c.GetPluginFilesViaRiseup(ctx, slug)
}

// GetPluginSyncManifest retrieves the cached file manifest for a remote plugin via Riseup Asia Uploader.
// Uses a fixed endpoint with slug in JSON body.
func (c *Client) GetPluginSyncManifest(ctx context.Context, slug string) ([]RemoteFile, error) {
	endpoint := "/" + RiseupAsiaNamespace + ep.SyncManifest.String()
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", string(endpoint), reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to get sync manifest").
			WithURL(c.fullURL(string(endpoint)))
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != HttpStatusOk.Int() {
		return nil, &APIError{
			Operation:    "get sync manifest",
			Method:       "POST",
			Endpoint:     string(endpoint),
			URL:          c.fullURL(string(endpoint)),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 2000),
		}
	}

	// The sync-manifest endpoint wraps files in {success, data: {files: [...]}}
	var result struct {
		Success bool `json:"success"`
		Data    struct {
			Plugin      string       `json:"plugin"`
			FileCount   int          `json:"fileCount"`
			GeneratedAt string       `json:"generatedAt"`
			Cached      bool         `json:"cached"`
			Files       []RemoteFile `json:"files"`
		} `json:"data"`
	}
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode sync manifest").
			WithValue("body", truncateBody(body, 500))
	}

	if !result.Success {
		return nil, apperror.New(apperror.ErrWPConnection, "remote API returned failure for sync manifest").
			WithValue("slug", slug)
	}

	return result.Data.Files, nil
}

// GetPluginFilesViaRiseup retrieves the list of files for a remote plugin via Riseup Asia Uploader.
// Uses a fixed endpoint with slug in JSON body.
func (c *Client) GetPluginFilesViaRiseup(ctx context.Context, slug string) ([]RemoteFile, error) {
	endpoint := "/" + RiseupAsiaNamespace + ep.Files.String()
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", string(endpoint), reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "failed to get plugin files via Riseup").
			WithURL(c.fullURL(string(endpoint)))
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode == HttpStatusNotFound.Int() {
		return nil, apperror.New(apperror.ErrNotFound, "plugin not found on remote").
			WithValue("slug", slug)
	}

	if resp.StatusCode != HttpStatusOk.Int() {
		return nil, &APIError{
			Operation:    "get plugin files",
			Method:       "POST",
			Endpoint:     string(endpoint),
			URL:          c.fullURL(string(endpoint)),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 2000),
		}
	}

	// Parse the response
	var result struct {
		Success    bool         `json:"success"`
		Plugin     string       `json:"plugin"`
		TotalFiles int          `json:"totalFiles"`
		Files      []RemoteFile `json:"files"`
	}
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode plugin files").
			WithValue("body", truncateBody(body, 500))
	}

	if !result.Success {
		return nil, apperror.New(apperror.ErrWPConnection, "remote API returned failure for plugin files").
			WithValue("slug", slug)
	}

	return result.Files, nil
}

// RequestMutationToken requests a mutation token from the legacy Onboard companion plugin.
// Deprecated: The Riseup Asia Uploader does not use mutation tokens.
// Kept for backward compatibility; will be removed in a future version.
func (c *Client) RequestMutationToken(action string) (string, error) {
	endpoint := fmt.Sprintf("/%s/request-mutation?action=%s", OnboardNamespace, action)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrWPConnection, "request mutation token failed").
			WithURL(c.fullURL(endpoint))
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	body := string(bodyBytes)

	if resp.StatusCode != http.StatusOK {
		return "", &APIError{
			Operation:    "request mutation token",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 8192),
		}
	}

	// Response format: { "mutation_token": "abc123", "expires_in": 1200 }
	var result struct {
		MutationToken string `json:"mutation_token"`
		ExpiresIn     int    `json:"expires_in"`
	}
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to parse mutation token response").
			WithValue("body", truncateBody(body, 500))
	}

	if result.MutationToken == "" {
		return "", apperror.New(apperror.ErrWPConnection, "empty mutation token in response").
			WithValue("body", truncateBody(body, 500))
	}

	return result.MutationToken, nil
}

// UploadPluginZip uploads a plugin ZIP file to WordPress via the legacy Onboard companion plugin.
// Deprecated: Use UploadPluginViaUploader instead (Riseup Asia Uploader).
// Kept for backward compatibility; will be removed in a future version.
func (c *Client) UploadPluginZip(zipPath string, pluginSlug string) (*OnboardUploadResult, error) {
	c.progress("upload", stagestatus.Running.String(), fmt.Sprintf("Requesting upload mutation token for %s...", pluginSlug), nil)

	mutationToken, err := c.RequestMutationToken("upload")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPPluginUpload, "failed to get upload mutation token")
	}

	c.progress("upload", stagestatus.Running.String(), fmt.Sprintf("Mutation token obtained, uploading %s...", filepath.Base(zipPath)), ProgressDetails{
		"tokenLength": len(mutationToken),
	})

	// Open the ZIP file
	file, err := os.Open(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to open zip file for upload").
			WithValue("zipPath", zipPath)
	}
	defer file.Close()

	stat, err := file.Stat()
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to stat zip file").
			WithValue("zipPath", zipPath)
	}

	// Create multipart form
	var reqBody bytes.Buffer
	writer := multipart.NewWriter(&reqBody)

	// Add the ZIP file part
	part, err := writer.CreateFormFile("plugin_zip", filepath.Base(zipPath))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create form file for upload")
	}

	if _, err := io.Copy(part, file); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to copy file to multipart form")
	}

	// Add plugin slug field
	if err := writer.WriteField("plugin_slug", pluginSlug); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to write plugin_slug field")
	}

	// Add overwrite=true to replace existing
	if err := writer.WriteField("overwrite", "true"); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to write overwrite field")
	}

	if err := writer.Close(); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to close multipart writer")
	}

	// Build the upload URL
	endpoint := fmt.Sprintf("/%s/mutations/%s/plugins/upload", OnboardNamespace, mutationToken)
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)

	c.progress("upload", stagestatus.Running.String(), fmt.Sprintf("POSTing %d bytes to %s", stat.Size(), url), ProgressDetails{
		"zipSize":  stat.Size(),
		"zipFile":  filepath.Base(zipPath),
		"endpoint": endpoint,
	})

	req, err := http.NewRequest("POST", url, &reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create upload HTTP request").
			WithURL(url)
	}

	// Set standard headers with multipart content type
	c.setStandardHeaders(req, writer.FormDataContentType())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "upload request failed").
			WithURL(url)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress("upload", stagestatus.Running.String(), fmt.Sprintf("Upload response: %d", resp.StatusCode), ProgressDetails{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 500),
	})

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		return nil, &APIError{
			Operation:    "upload plugin zip",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
			PluginSlugIn: pluginSlug,
		}
	}

	var result OnboardUploadResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		// If JSON parsing fails but status was OK, treat as success
		result.Success = true
		result.Message = "Upload completed"
	}

	return &result, nil
}

// EnablePlugin activates/enables a plugin on the remote WordPress site.
// Delegates to EnablePluginViaUploader (Riseup Asia Uploader).
func (c *Client) EnablePlugin(pluginSlug string) error {
	return c.EnablePluginViaUploader(pluginSlug)
}

// CheckOnboardPluginAvailable checks if the companion plugin is installed and available.
// Deprecated: Now checks for Riseup Asia Uploader availability instead.
func (c *Client) CheckOnboardPluginAvailable() (bool, error) {
	available, _, err := c.CheckRiseupAsiaAvailable()
	return available, err
}

// CheckOnboardAvailable is an alias for CheckOnboardPluginAvailable
func (c *Client) CheckOnboardAvailable() (bool, error) {
	return c.CheckOnboardPluginAvailable()
}

// UploadPluginViaOnboard uploads a plugin via the Riseup Asia Uploader and returns UploaderUploadResult.
// Deprecated: Delegates to UploadPluginViaUploader.
func (c *Client) UploadPluginViaOnboard(zipPath string, activate bool) (*UploaderUploadResult, error) {
	slug := strings.TrimSuffix(filepath.Base(zipPath), ".zip")
	return c.UploadPluginViaUploader(zipPath, slug, activate, UploadSourceRestAPI)
}

// truncateBody truncates a string to maxLen for error messages.
func truncateBody(body string, maxLen int) string {
	if len(body) > maxLen {
		return body[:maxLen] + "..."
	}
	return body
}

// GetPluginFileContent retrieves the content of a specific file from a remote plugin.
// Uses a fixed endpoint with slug and path in JSON body.
func (c *Client) GetPluginFileContent(ctx context.Context, pluginSlug, filePath string) (string, error) {
	// Use the Riseup Asia Uploader fixed endpoint
	endpoint := "/" + RiseupAsiaNamespace + ep.File.String()

	body := map[string]string{"plugin": pluginSlug, "path": filePath}
	jsonBody, err := json.Marshal(body)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to marshal request body for file content")
	}

	resp, err := c.request("POST", string(endpoint), bytes.NewReader(jsonBody))
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrWPConnection, "failed to get file content").
			WithURL(c.fullURL(string(endpoint)))
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode == HttpStatusNotFound.Int() {
		return "", apperror.New(apperror.ErrNotFound, "file not found on remote").
			WithValue("filePath", filePath)
	}

	if resp.StatusCode != HttpStatusOk.Int() {
		return "", &APIError{
			Operation:    "get file content",
			Method:       "POST",
			Endpoint:     string(endpoint),
			URL:          c.fullURL(string(endpoint)),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 500),
		}
	}

	// Parse the response
	var result struct {
		Success bool   `json:"success"`
		Path    string `json:"path"`
		Content string `json:"content"`
	}
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return "", apperror.Wrap(err, apperror.ErrInternal, "failed to decode file content response")
	}

	if !result.Success {
		return "", apperror.New(apperror.ErrWPConnection, "remote API returned failure for file content").
			WithValue("filePath", filePath)
	}

	return result.Content, nil
}
