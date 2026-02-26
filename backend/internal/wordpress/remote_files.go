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

// syncManifestResult is the response shape from the sync-manifest endpoint.
type syncManifestResult struct {
	Success bool `json:"success"` // external key (Riseup Asia Uploader API)
	Data    struct {
		Plugin      string       `json:"plugin"`      // external key
		FileCount   int          `json:"fileCount"`    // external key
		GeneratedAt string       `json:"generatedAt"` // external key
		Cached      bool         `json:"cached"`       // external key
		Files       []RemoteFile `json:"files"`        // external key
	} `json:"data"` // external key
}

// GetPluginSyncManifest retrieves the cached file manifest for a remote plugin via Riseup Asia Uploader.
func (c *Client) GetPluginSyncManifest(ctx context.Context, slug string) ([]RemoteFile, error) {
	endpoint := "/" + RiseupAsiaNamespace + ep.SyncManifest.String()

	data, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body: PluginSlugRequest{Plugin: slug}, Operation: "get sync manifest",
		ErrorCode: apperror.ErrWPConnection,
	})
	if err != nil {
		return nil, err
	}

	result, err := decodeAPIResponse[syncManifestResult](data, "sync manifest")
	if err != nil {
		return nil, err
	}

	return validateSuccessAndReturn(result.Success, result.Data.Files, "sync manifest", slug)
}

// pluginFilesResult is the response shape from the files endpoint.
type pluginFilesResult struct {
	Success    bool         `json:"success"`    // external key (Riseup Asia Uploader API)
	Plugin     string       `json:"plugin"`     // external key
	TotalFiles int          `json:"totalFiles"` // external key
	Files      []RemoteFile `json:"files"`      // external key
}

// GetPluginFilesViaRiseup retrieves the list of files for a remote plugin via Riseup Asia Uploader.
func (c *Client) GetPluginFilesViaRiseup(ctx context.Context, slug string) ([]RemoteFile, error) {
	endpoint := "/" + RiseupAsiaNamespace + ep.Files.String()

	data, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body: PluginSlugRequest{Plugin: slug}, Operation: "get plugin files",
		ErrorCode: apperror.ErrWPConnection,
	})
	if err != nil {
		return nil, mapNotFoundError(err, "plugin not found on remote", slug)
	}

	result, err := decodeAPIResponse[pluginFilesResult](data, "plugin files")
	if err != nil {
		return nil, err
	}

	return validateSuccessAndReturn(result.Success, result.Files, "plugin files", slug)
}

// mutationTokenResult is the response from the mutation token endpoint.
type mutationTokenResult struct {
	MutationToken string `json:"mutation_token"` // external key (Riseup Asia Uploader API)
	ExpiresIn     int    `json:"expires_in"`     // external key
}

// RequestMutationToken requests a mutation token from the legacy Onboard companion plugin.
// Deprecated: The Riseup Asia Uploader does not use mutation tokens.
func (c *Client) RequestMutationToken(action string) (string, error) {
	endpoint := fmt.Sprintf("/%s/request-mutation?action=%s", OnboardNamespace, action)

	data, err := c.doAPICallRaw(apiCallInput{
		Method: "GET", Endpoint: endpoint, Operation: "request mutation token",
		ErrorCode: apperror.ErrWPConnection,
	})
	if err != nil {
		return "", err
	}

	result, err := decodeAPIResponse[mutationTokenResult](data, "mutation token")
	if err != nil {
		return "", err
	}

	if result.MutationToken == "" {
		return "", apperror.New(apperror.ErrWPConnection, "empty mutation token in response")
	}
	return result.MutationToken, nil
}

// fileContentResult is the response from the file content endpoint.
type fileContentResult struct {
	Success bool   `json:"success"` // external key (Riseup Asia Uploader API)
	Path    string `json:"path"`    // external key
	Content string `json:"content"` // external key
}

// GetPluginFileContent retrieves the content of a specific file from a remote plugin.
func (c *Client) GetPluginFileContent(ctx context.Context, pluginSlug, filePath string) (string, error) {
	endpoint := "/" + RiseupAsiaNamespace + ep.File.String()

	data, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body: PluginFileRequest{Plugin: pluginSlug, Path: filePath}, Operation: "get file content",
		ErrorCode: apperror.ErrWPConnection,
	})
	if err != nil {
		return "", mapNotFoundError(err, "file not found on remote", filePath)
	}

	result, err := decodeAPIResponse[fileContentResult](data, "file content")
	if err != nil {
		return "", err
	}

	if !result.Success {
		return "", apperror.New(apperror.ErrWPConnection, "remote API returned failure for file content").
			WithValue("filePath", filePath)
	}
	return result.Content, nil
}

// validateSuccessAndReturn checks the success flag and returns data or an error.
func validateSuccessAndReturn[T any](isSuccess bool, data T, operation, slug string) (T, error) {
	if !isSuccess {
		var zero T
		return zero, apperror.New(apperror.ErrWPConnection, "remote API returned failure for "+operation).
			WithValue("slug", slug)
	}
	return data, nil
}

// mapNotFoundError checks if err is an APIError with 404 status and returns a typed not-found error.
func mapNotFoundError(err error, message, identifier string) error {
	if apiErr, ok := err.(*APIError); ok && apiErr.StatusCode == HttpStatusNotFound.Int() {
		return apperror.New(apperror.ErrNotFound, message).WithValue("identifier", identifier)
	}
	return err
}

// --- Upload methods (legacy Onboard) ---

// UploadPluginZip uploads a plugin ZIP file to WordPress via the legacy Onboard companion plugin.
// Deprecated: Use UploadPluginViaUploader instead (Riseup Asia Uploader).
func (c *Client) UploadPluginZip(zipPath string, pluginSlug string) (*OnboardUploadResult, error) {
	c.progress(ProgressEvent{
		Step: "upload", Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Requesting upload mutation token for %s...", pluginSlug),
	})

	mutationToken, err := c.RequestMutationToken("upload")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPPluginUpload, "failed to get upload mutation token")
	}

	c.progress(ProgressEvent{
		Step: "upload", Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Mutation token obtained, uploading %s...", filepath.Base(zipPath)),
		Details: toProgress(TokenProgress{TokenLength: len(mutationToken)}),
	})

	reqBody, contentType, fileSize, err := c.buildZipMultipartForm(zipPath, pluginSlug)
	if err != nil {
		return nil, err
	}

	endpoint := fmt.Sprintf("/%s/mutations/%s/plugins/upload", OnboardNamespace, mutationToken)
	return c.executeZipUpload(zipUploadInput{Endpoint: endpoint, Body: reqBody, ContentType: contentType, FileSize: fileSize, ZipPath: zipPath, PluginSlug: pluginSlug})
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

	if err := writeZipFormFields(zipFormInput{Writer: writer, File: file, ZipPath: zipPath, PluginSlug: pluginSlug}); err != nil {
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

// zipFormInput bundles parameters for writeZipFormFields.
type zipFormInput struct {
	Writer     *multipart.Writer
	File       *os.File
	ZipPath    string
	PluginSlug string
}

// writeZipFormFields writes the ZIP file and metadata fields to the multipart writer.
func writeZipFormFields(input zipFormInput) error {
	part, err := input.Writer.CreateFormFile("plugin_zip", filepath.Base(input.ZipPath))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to create form file for upload")
	}

	if _, err := io.Copy(part, input.File); err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to copy file to multipart form")
	}

	if err := input.Writer.WriteField("pluginSlug", input.PluginSlug); err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to write pluginSlug field")
	}

	return input.Writer.WriteField("overwrite", "true")
}

// zipUploadInput bundles parameters for executeZipUpload.
type zipUploadInput struct {
	Endpoint    string
	Body        *bytes.Buffer
	ContentType string
	FileSize    int64
	ZipPath     string
	PluginSlug  string
}

// executeZipUpload sends the multipart upload request and parses the response.
func (c *Client) executeZipUpload(input zipUploadInput) (*OnboardUploadResult, error) {
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, input.Endpoint)

	c.progress(ProgressEvent{
		Step: "upload", Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("POSTing %d bytes to %s", input.FileSize, url),
		Details: toProgress(ZipUploadProgress{
			ZipSize: input.FileSize, ZipFile: filepath.Base(input.ZipPath), Endpoint: input.Endpoint,
		}),
	})

	resp, respBody, err := c.doMultipartRequest(url, input.Body, input.ContentType)
	if err != nil {
		return nil, err
	}

	c.progress(ProgressEvent{
		Step: "upload", Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Upload response: %d", resp.StatusCode),
		Details: toProgress(ResponseProgress{Status: resp.StatusCode, Body: truncateBody(respBody, 500)}),
	})

	return c.parseZipUploadResponse(zipUploadResponseInput{StatusCode: resp.StatusCode, Body: respBody, Endpoint: input.Endpoint, URL: url, PluginSlug: input.PluginSlug})
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

// zipUploadResponseInput bundles parameters for parseZipUploadResponse.
type zipUploadResponseInput struct {
	StatusCode int
	Body       string
	Endpoint   string
	URL        string
	PluginSlug string
}

// parseZipUploadResponse validates the status code and unmarshals the result.
func (c *Client) parseZipUploadResponse(input zipUploadResponseInput) (*OnboardUploadResult, error) {
	if input.StatusCode != http.StatusOK && input.StatusCode != http.StatusCreated {
		return nil, &APIError{
			Operation:    "upload plugin zip",
			Method:       "POST",
			Endpoint:     input.Endpoint,
			Url:          input.URL,
			StatusCode:   input.StatusCode,
			ResponseBody: truncateBody(input.Body, 8192),
			PluginSlugIn: input.PluginSlug,
		}
	}

	var result OnboardUploadResult
	if err := json.Unmarshal([]byte(input.Body), &result); err != nil {
		result.Success = true
		result.Message = "Upload completed"
	}

	return &result, nil
}

// EnablePlugin activates/enables a plugin on the remote WordPress site.
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
func (c *Client) UploadPluginViaOnboard(zipPath string, isActivate bool) (*UploaderUploadResult, error) {
	slug := strings.TrimSuffix(filepath.Base(zipPath), ".zip")
	return c.UploadPluginViaUploader(UploadInput{ZipPath: zipPath, Slug: slug, IsActivate: isActivate, UploadSource: uploadsource.RestAPI})
}

// truncateBody truncates a string to maxLen for error messages.
func truncateBody(body string, maxLen int) string {
	if len(body) > maxLen {
		return body[:maxLen] + "..."
	}
	return body
}
