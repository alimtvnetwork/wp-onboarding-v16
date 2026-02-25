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
	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/pkg/apperror"
)

// Note: OnboardNamespace is defined in constants.go

// RemoteFile represents a file in a remote WordPress plugin
type RemoteFile struct {
	Path       string    `json:"path"`       // external key (Riseup Asia Uploader API)
	Hash       string    `json:"hash"`       // external key
	Size       int64     `json:"size"`       // external key
	ModifiedAt time.Time `json:"modifiedAt"` // external key
}

// OnboardUploadResult represents the response from the upload endpoint.
type OnboardUploadResult struct {
	Success      bool   `json:"success"`                    // external key (Riseup Asia Uploader API)
	Message      string `json:"message"`                    // external key
	PluginSlug   string `json:"plugin_slug,omitempty"`      // external key
	PluginName   string `json:"plugin_name,omitempty"`      // external key
	Version      string `json:"version,omitempty"`          // external key
	PreviousVer  string `json:"previous_version,omitempty"` // external key
	FilesUpdated int    `json:"files_updated,omitempty"`    // external key
	Overwritten  bool   `json:"overwritten,omitempty"`      // external key
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
	reqBody := PluginSlugRequest{Plugin: slug}
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
			Url:          c.fullURL(string(endpoint)),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 2000),
		}
	}

	// The sync-manifest endpoint wraps files in {success, data: {files: [...]}}
	var result struct {
		Success bool `json:"success"` // external key (Riseup Asia Uploader API)
		Data    struct {
			Plugin      string       `json:"plugin"`      // external key
			FileCount   int          `json:"fileCount"`   // external key
			GeneratedAt string       `json:"generatedAt"` // external key
			Cached      bool         `json:"cached"`      // external key
			Files       []RemoteFile `json:"files"`       // external key
		} `json:"data"` // external key
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
	reqBody := PluginSlugRequest{Plugin: slug}
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
			Url:          c.fullURL(string(endpoint)),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 2000),
		}
	}

	// Parse the response
	var result struct {
		Success    bool         `json:"success"`    // external key (Riseup Asia Uploader API)
		Plugin     string       `json:"plugin"`     // external key
		TotalFiles int          `json:"totalFiles"` // external key
		Files      []RemoteFile `json:"files"`      // external key
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
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(body, 8192),
		}
	}

	// Response format: { "mutation_token": "abc123", "expires_in": 1200 }
	var result struct {
		MutationToken string `json:"mutation_token"` // external key (Riseup Asia Uploader API)
		ExpiresIn     int    `json:"expires_in"`     // external key
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

	c.progress("upload", stagestatus.Running.String(), fmt.Sprintf("Mutation token obtained, uploading %s...", filepath.Base(zipPath)), toProgress(TokenProgress{
		TokenLength: len(mutationToken),
	}))

	reqBody, contentType, fileSize, err := c.buildZipMultipartForm(zipPath, pluginSlug)
	if err != nil {
		return nil, err
	}

	endpoint := fmt.Sprintf("/%s/mutations/%s/plugins/upload", OnboardNamespace, mutationToken)
	return c.executeZipUpload(endpoint, reqBody, contentType, fileSize, zipPath, pluginSlug)
}

// buildZipMultipartForm opens the ZIP file and builds the multipart form body.
func (c *Client) buildZipMultipartForm(zipPath, pluginSlug string) (*bytes.Buffer, string, int64, error) {
	file, stat, err := openAndStatFile(zipPath)
	if err != nil {
		return nil, "", 0, err
	}
	defer file.Close()

	var reqBody bytes.Buffer
	writer := multipart.NewWriter(&reqBody)

	if err := writeZipFormFields(writer, file, zipPath, pluginSlug); err != nil {
		return nil, "", 0, err
	}

	if err := writer.Close(); err != nil {
		return nil, "", 0, apperror.Wrap(err, apperror.ErrInternal, "failed to close multipart writer")
	}

	return &reqBody, writer.FormDataContentType(), stat.Size(), nil
}

// openAndStatFile opens a file and returns it with its FileInfo.
func openAndStatFile(path string) (*os.File, os.FileInfo, error) {
	file, err := os.Open(path)
	if err != nil {
		return nil, nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to open zip file for upload").
			WithValue("zipPath", path)
	}

	stat, err := file.Stat()
	if err != nil {
		file.Close()
		return nil, nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to stat zip file").
			WithValue("zipPath", path)
	}

	return file, stat, nil
}

// writeZipFormFields writes the ZIP file and metadata fields to the multipart writer.
func writeZipFormFields(writer *multipart.Writer, file *os.File, zipPath, pluginSlug string) error {
	part, err := writer.CreateFormFile("plugin_zip", filepath.Base(zipPath))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to create form file for upload")
	}

	if _, err := io.Copy(part, file); err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to copy file to multipart form")
	}

	if err := writer.WriteField("pluginSlug", pluginSlug); err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to write pluginSlug field")
	}

	return writer.WriteField("overwrite", "true")
}

// executeZipUpload sends the multipart upload request and parses the response.
func (c *Client) executeZipUpload(endpoint string, reqBody *bytes.Buffer, contentType string, fileSize int64, zipPath, pluginSlug string) (*OnboardUploadResult, error) {
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)

	c.progress("upload", stagestatus.Running.String(), fmt.Sprintf("POSTing %d bytes to %s", fileSize, url), toProgress(ZipUploadProgress{
		ZipSize:  fileSize,
		ZipFile:  filepath.Base(zipPath),
		Endpoint: endpoint,
	}))

	resp, respBody, err := c.doMultipartRequest(url, reqBody, contentType)
	if err != nil {
		return nil, err
	}

	c.progress("upload", stagestatus.Running.String(), fmt.Sprintf("Upload response: %d", resp.StatusCode), toProgress(ResponseProgress{
		Status: resp.StatusCode,
		Body:   truncateBody(respBody, 500),
	}))

	return c.parseZipUploadResponse(resp.StatusCode, respBody, endpoint, url, pluginSlug)
}

// doMultipartRequest sends a POST with multipart body and returns status + body.
func (c *Client) doMultipartRequest(url string, body *bytes.Buffer, contentType string) (*http.Response, string, error) {
	req, err := http.NewRequest("POST", url, body)
	if err != nil {
		return nil, "", apperror.Wrap(err, apperror.ErrInternal, "failed to create upload HTTP request").WithURL(url)
	}

	c.setStandardHeaders(req, contentType)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, "", apperror.Wrap(err, apperror.ErrWPConnection, "upload request failed").WithURL(url)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	return resp, string(respBytes), nil
}

// parseZipUploadResponse validates the status code and unmarshals the result.
func (c *Client) parseZipUploadResponse(statusCode int, respBody, endpoint, url, pluginSlug string) (*OnboardUploadResult, error) {
	if statusCode != http.StatusOK && statusCode != http.StatusCreated {
		return nil, &APIError{
			Operation:    "upload plugin zip",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          url,
			StatusCode:   statusCode,
			ResponseBody: truncateBody(respBody, 8192),
			PluginSlugIn: pluginSlug,
		}
	}

	var result OnboardUploadResult
	if err := json.Unmarshal([]byte(respBody), &result); err != nil {
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
	return c.UploadPluginViaUploader(zipPath, slug, activate, uploadsource.RestAPI)
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

	body := PluginFileRequest{Plugin: pluginSlug, Path: filePath}
	resp, err := c.request("POST", string(endpoint), body)
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
			Url:          c.fullURL(string(endpoint)),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 500),
		}
	}

	// Parse the response
	var result struct {
		Success bool   `json:"success"` // external key (Riseup Asia Uploader API)
		Path    string `json:"path"`    // external key
		Content string `json:"content"` // external key
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
