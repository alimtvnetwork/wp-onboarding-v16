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

	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/enums/upload_source"
	"wp-plugin-publish/pkg/apperror"
)

// --- Upload methods (legacy Onboard) ---

// UploadPluginZip uploads a plugin ZIP file to WordPress via the legacy Onboard companion plugin.
// Deprecated: Use UploadPluginViaUploader instead (Riseup Asia Uploader).
func (c *Client) UploadPluginZip(zipPath string, pluginSlug string) (*OnboardUploadResult, error) {
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("Requesting upload mutation token for %s...", pluginSlug),
	})

	mutationToken, err := c.RequestMutationToken("upload")
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPPluginUpload, "failed to get upload mutation token")
	}

	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("Mutation token obtained, uploading %s...", filepath.Base(zipPath)),
		Details: toProgress(TokenProgress{TokenLength: len(mutationToken)}),
	})

	form, err := c.buildZipMultipartForm(zipPath, pluginSlug)
	if err != nil {
		return nil, err
	}

	endpoint := fmt.Sprintf("/%s/mutations/%s/plugins/upload", OnboardNamespace, mutationToken)
	uploadInput := zipUploadInput{
		Endpoint:    endpoint,
		Body:        form.Body,
		ContentType: form.ContentType,
		FileSize:    form.FileSize,
		ZipPath:     zipPath,
		PluginSlug:  pluginSlug,
	}
	return c.executeZipUpload(uploadInput)
}

// zipMultipartForm holds the built multipart form and file metadata.
type zipMultipartForm struct {
	Body        *bytes.Buffer
	ContentType string
	FileSize    int64
}

// buildZipMultipartForm opens the ZIP file and builds the multipart form body.
func (c *Client) buildZipMultipartForm(zipPath, pluginSlug string) (*zipMultipartForm, error) {
	opened, err := openAndStatFile(zipPath)
	if err != nil {
		return nil, err
	}
	defer opened.File.Close()

	var reqBody bytes.Buffer
	writer := multipart.NewWriter(&reqBody)

	formInput := zipFormInput{
		Writer:     writer,
		File:       opened.File,
		ZipPath:    zipPath,
		PluginSlug: pluginSlug,
	}
	if err := writeZipFormFields(formInput); err != nil {
		return nil, err
	}

	if err := writer.Close(); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to close multipart writer")
	}

	return &zipMultipartForm{Body: &reqBody, ContentType: writer.FormDataContentType(), FileSize: opened.Info.Size()}, nil
}

// openedFile holds an open file and its stat info.
type openedFile struct {
	File *os.File
	Info os.FileInfo
}

// openAndStatFile opens a file and returns it with its FileInfo.
func openAndStatFile(path string) (*openedFile, error) {
	file, err := os.Open(path)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to open zip file for upload").
			WithValue("zipPath", path)
	}

	stat, err := file.Stat()
	if err != nil {
		file.Close()
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to stat zip file").
			WithValue("zipPath", path)
	}

	return &openedFile{File: file, Info: stat}, nil
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

	uploadProgress := ZipUploadProgress{
		ZipSize:  input.FileSize,
		ZipFile:  filepath.Base(input.ZipPath),
		Endpoint: input.Endpoint,
	}
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("POSTing %d bytes to %s", input.FileSize, url),
		Details: toProgress(uploadProgress),
	})

	resp, respBody, err := c.doMultipartRequest(url, input.Body, input.ContentType)
	if err != nil {
		return nil, err
	}

	responseProgress := ResponseProgress{
		Status: resp.StatusCode,
		Body:   truncateBody(respBody, 500),
	}
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatus.Running.String(),
		Message: fmt.Sprintf("Upload response: %d", resp.StatusCode),
		Details: toProgress(responseProgress),
	})

	parseInput := zipUploadResponseInput{
		StatusCode: resp.StatusCode,
		Body:       respBody,
		Endpoint:   input.Endpoint,
		URL:        url,
		PluginSlug: input.PluginSlug,
	}
	return c.parseZipUploadResponse(parseInput)
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

// --- Legacy compatibility aliases ---

// EnablePlugin activates/enables a plugin on the remote WordPress site.
func (c *Client) EnablePlugin(pluginSlug string) error {
	return c.EnablePluginViaUploader(pluginSlug)
}

// CheckOnboardPluginAvailable checks if the companion plugin is installed and available.
// Deprecated: Now checks for Riseup Asia Uploader availability instead.
func (c *Client) CheckOnboardPluginAvailable() (*UploaderAvailability, error) {
	return c.CheckRiseupAsiaAvailable()
}

// CheckOnboardAvailable is an alias for CheckOnboardPluginAvailable
func (c *Client) CheckOnboardAvailable() (*UploaderAvailability, error) {
	return c.CheckOnboardPluginAvailable()
}

// UploadPluginViaOnboard uploads a plugin via the Riseup Asia Uploader and returns UploaderUploadResult.
// Deprecated: Delegates to UploadPluginViaUploader.
func (c *Client) UploadPluginViaOnboard(zipPath string, isActivate bool) (*UploaderUploadResult, error) {
	slug := strings.TrimSuffix(filepath.Base(zipPath), ".zip")
	uploadInput := UploadInput{
		ZipPath:      zipPath,
		Slug:         slug,
		IsActivate:   isActivate,
		UploadSource: uploadsource.RestAPI,
	}
	return c.UploadPluginViaUploader(uploadInput)
}

// truncateBody truncates a string to maxLen for error messages.
func truncateBody(body string, maxLen int) string {
	if len(body) > maxLen {
		return body[:maxLen] + "..."
	}
	return body
}
