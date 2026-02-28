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

	"wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/pkg/apperror"
)

// --- Upload methods (legacy Onboard) ---

// UploadPluginZip uploads a plugin ZIP file to WordPress via the legacy Onboard companion plugin.
// Deprecated: Use UploadPluginViaUploader instead (Riseup Asia Uploader).
func (c *Client) UploadPluginZip(zipPath string, pluginSlug string) (*OnboardUploadResult, error) {
	mutationToken, err := c.requestUploadMutationToken(pluginSlug)
	if err != nil {
		return nil, err
	}

	c.reportMutationTokenObtained(zipPath, mutationToken)

	form, err := c.buildZipMultipartForm(zipPath, pluginSlug)
	if err != nil {
		return nil, err
	}

	endpoint := fmt.Sprintf("/%s/mutations/%s/plugins/upload", OnboardNamespace, mutationToken)

	return c.executeOnboardZipUpload(endpoint, form, zipPath, pluginSlug)
}

// requestUploadMutationToken requests and returns a mutation token for upload.
func (c *Client) requestUploadMutationToken(pluginSlug string) (string, error) {
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("Requesting upload mutation token for %s...", pluginSlug),
	})

	mutationToken, err := c.RequestMutationToken("upload")
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrWPPluginUpload, "failed to get upload mutation token")
	}

	return mutationToken, nil
}

// reportMutationTokenObtained logs that the mutation token was obtained.
func (c *Client) reportMutationTokenObtained(zipPath, mutationToken string) {
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("Mutation token obtained, uploading %s...", filepath.Base(zipPath)),
		Details: toProgress(TokenProgress{TokenLength: len(mutationToken)}),
	})
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

	return c.buildFormFromOpenedFile(opened, zipPath, pluginSlug)
}

// buildFormFromOpenedFile constructs the multipart form from an opened file.
func (c *Client) buildFormFromOpenedFile(opened *openedFile, zipPath, pluginSlug string) (*zipMultipartForm, error) {
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

// zipUploadInput bundles parameters for executeOnboardZipUpload.
type zipUploadInput struct {
	Endpoint    string
	Body        *bytes.Buffer
	ContentType string
	FileSize    int64
	ZipPath     string
	PluginSlug  string
}

// executeOnboardZipUpload sends the multipart upload and parses the response.
func (c *Client) executeOnboardZipUpload(endpoint string, form *zipMultipartForm, zipPath, pluginSlug string) (*OnboardUploadResult, error) {
	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)

	c.reportOnboardUploadStart(url, endpoint, form, zipPath)

	mpResp, err := c.doMultipartRequest(url, form.Body, form.ContentType)
	if err != nil {
		return nil, err
	}

	c.reportOnboardUploadResponse(mpResp)

	return c.parseOnboardUploadResponse(mpResp, endpoint, url, pluginSlug)
}

// reportOnboardUploadStart logs the upload start progress.
func (c *Client) reportOnboardUploadStart(url, endpoint string, form *zipMultipartForm, zipPath string) {
	uploadProgress := ZipUploadProgress{
		ZipSize:  form.FileSize,
		ZipFile:  filepath.Base(zipPath),
		Endpoint: endpoint,
	}
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("POSTing %d bytes to %s", form.FileSize, url),
		Details: toProgress(uploadProgress),
	})
}

// reportOnboardUploadResponse logs the upload response progress.
func (c *Client) reportOnboardUploadResponse(mpResp *multipartResponse) {
	responseProgress := ResponseProgress{
		Status: mpResp.StatusCode,
		Body:   truncateBody(mpResp.Body, 500),
	}
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("Upload response: %d", mpResp.StatusCode),
		Details: toProgress(responseProgress),
	})
}

// multipartResponse holds the result of a multipart HTTP request.
type multipartResponse struct {
	StatusCode int
	Body       string
}

// doMultipartRequest sends a POST with multipart body and returns status + body.
func (c *Client) doMultipartRequest(url string, body *bytes.Buffer, contentType string) (*multipartResponse, error) {
	req, err := http.NewRequest(httpmethodtype.Post.Value(), url, body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create upload HTTP request").WithURL(url)
	}

	c.setStandardHeaders(req, contentType)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "upload request failed").WithURL(url)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)

	return &multipartResponse{StatusCode: resp.StatusCode, Body: string(respBytes)}, nil
}

// parseOnboardUploadResponse validates the status code and unmarshals the result.
func (c *Client) parseOnboardUploadResponse(mpResp *multipartResponse, endpoint, url, pluginSlug string) (*OnboardUploadResult, error) {
	if mpResp.StatusCode != http.StatusOK && mpResp.StatusCode != http.StatusCreated {
		return nil, &APIError{
			Operation:    "upload plugin zip",
			Method:       httpmethodtype.Post.Value(),
			Endpoint:     endpoint,
			Url:          url,
			StatusCode:   mpResp.StatusCode,
			ResponseBody: truncateBody(mpResp.Body, 8192),
			PluginSlugIn: pluginSlug,
		}
	}

	var result OnboardUploadResult
	if err := json.Unmarshal([]byte(mpResp.Body), &result); err != nil {
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
func (c *Client) UploadPluginViaOnboard(zipPath string, isActivate bool) (*UploaderUploadResult, *apperror.AppError) {
	slug := strings.TrimSuffix(filepath.Base(zipPath), ".zip")
	uploadInput := UploadInput{
		ZipPath:      zipPath,
		Slug:         slug,
		IsActivate:   isActivate,
		UploadSource: uploadsourcetype.RestAPI,
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
