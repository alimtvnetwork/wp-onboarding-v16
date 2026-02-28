package wordpress

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"mime/multipart"
	"os"
	"path/filepath"
	"strings"

	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/pkg/apperror"
)

// --- Upload methods (legacy Onboard) ---

// UploadPluginZip uploads a plugin ZIP file to WordPress via the legacy Onboard companion plugin.
// Deprecated: Use UploadPluginViaUploader instead (Riseup Asia Uploader).
func (c *Client) UploadPluginZip(zipPath string, pluginSlug string) (*OnboardUploadResult, *apperror.AppError) {
	mutationToken, tokenErr := c.requestUploadMutationToken(pluginSlug)
	if tokenErr != nil {
		return nil, tokenErr
	}

	c.reportMutationTokenObtained(zipPath, mutationToken)

	form, formErr := c.buildZipMultipartForm(zipPath, pluginSlug)
	if formErr != nil {
		return nil, formErr
	}

	endpoint := OnboardMutationUploadEndpoint(OnboardNamespace, mutationToken)

	return c.executeOnboardZipUpload(endpoint, form, zipPath, pluginSlug)
}

// requestUploadMutationToken requests and returns a mutation token for upload.
func (c *Client) requestUploadMutationToken(pluginSlug string) (string, *apperror.AppError) {
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
func (c *Client) buildZipMultipartForm(zipPath, pluginSlug string) (*zipMultipartForm, *apperror.AppError) {
	opened, openErr := openAndStatFile(zipPath)
	if openErr != nil {
		return nil, openErr
	}
	defer opened.File.Close()

	return c.buildFormFromOpenedFile(opened, zipPath, pluginSlug)
}

// buildFormFromOpenedFile constructs the multipart form from an opened file.
func (c *Client) buildFormFromOpenedFile(opened *openedFile, zipPath, pluginSlug string) (*zipMultipartForm, *apperror.AppError) {
	var reqBody bytes.Buffer
	writer := multipart.NewWriter(&reqBody)

	formInput := zipFormInput{
		Writer:     writer,
		File:       opened.File,
		ZipPath:    zipPath,
		PluginSlug: pluginSlug,
	}

	writeErr := writeZipFormFields(formInput)
	if writeErr != nil {

		return nil, writeErr
	}

	closeErr := writer.Close()
	if closeErr != nil {

		return nil, apperror.Wrap(closeErr, apperror.ErrInternal, "failed to close multipart writer")
	}

	return &zipMultipartForm{Body: &reqBody, ContentType: writer.FormDataContentType(), FileSize: opened.Info.Size()}, nil
}

// openedFile holds an open file and its stat info.
type openedFile struct {
	File *os.File
	Info os.FileInfo
}

// openAndStatFile opens a file and returns it with its FileInfo.
func openAndStatFile(path string) (*openedFile, *apperror.AppError) {
	file, err := os.Open(path)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "failed to open zip file for upload").
			WithValue("zipPath", path)
	}

	stat, statErr := file.Stat()
	if statErr != nil {
		file.Close()

		return nil, apperror.Wrap(statErr, apperror.ErrFSRead, "failed to stat zip file").
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
func writeZipFormFields(input zipFormInput) *apperror.AppError {
	part, err := input.Writer.CreateFormFile("plugin_zip", filepath.Base(input.ZipPath))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to create form file for upload")
	}

	_, copyErr := io.Copy(part, input.File)
	if copyErr != nil {

		return apperror.Wrap(copyErr, apperror.ErrInternal, "failed to copy file to multipart form")
	}

	fieldErr := input.Writer.WriteField("pluginSlug", input.PluginSlug)
	if fieldErr != nil {

		return apperror.Wrap(fieldErr, apperror.ErrInternal, "failed to write pluginSlug field")
	}

	overwriteErr := input.Writer.WriteField("overwrite", "true")
	if overwriteErr != nil {

		return apperror.Wrap(overwriteErr, apperror.ErrInternal, "failed to write overwrite field")
	}

	return nil
}

// executeOnboardZipUpload sends the multipart upload and parses the response.
func (c *Client) executeOnboardZipUpload(endpoint string, form *zipMultipartForm, zipPath, pluginSlug string) (*OnboardUploadResult, *apperror.AppError) {
	url := BuildWPJSONURL(c.baseURL, endpoint)

	c.reportOnboardUploadStart(url, endpoint, form, zipPath)

	mpResp, appErr := c.sendMultipartUpload(endpoint, form)
	if appErr != nil {
		return nil, appErr
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

// sendMultipartUpload sends a multipart POST via the standardized requestMultipart helper.
func (c *Client) sendMultipartUpload(endpoint string, form *zipMultipartForm) (*multipartResponse, *apperror.AppError) {
	input := multipartInput{
		Method:      httpmethod.Post,
		Endpoint:    endpoint,
		Body:        form.Body,
		ContentType: form.ContentType,
	}

	resp, appErr := c.requestMultipart(input)
	if appErr != nil {
		return nil, appErr
	}
	defer resp.Body.Close()

	respBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to read multipart response body")
	}

	return &multipartResponse{StatusCode: resp.StatusCode, Body: string(respBytes)}, nil
}

// parseOnboardUploadResponse validates the status code and unmarshals the result.
func (c *Client) parseOnboardUploadResponse(mpResp *multipartResponse, endpoint, url, pluginSlug string) (*OnboardUploadResult, *apperror.AppError) {
	isSuccess := mpResp.StatusCode == HttpStatusOk.Int() ||
		mpResp.StatusCode == HttpStatusCreated.Int()

	if !isSuccess {
		return nil, apperror.New(apperror.ErrWPPluginUpload, "upload plugin zip failed").
			WithEndpoint(endpoint).
			WithURL(url).
			WithSlug(pluginSlug).
			WithValue("statusCode", fmt.Sprintf("%d", mpResp.StatusCode)).
			WithValue("responseBody", truncateBody(mpResp.Body, 8192))
	}

	var result OnboardUploadResult
	unmarshalErr := json.Unmarshal([]byte(mpResp.Body), &result)
	if unmarshalErr != nil {
		result.Success = true
		result.Message = "Upload completed"
	}

	return &result, nil
}

// --- Legacy compatibility aliases ---

// EnablePlugin activates/enables a plugin on the remote WordPress site.
func (c *Client) EnablePlugin(pluginSlug string) *apperror.AppError {
	return c.EnablePluginViaUploader(pluginSlug)
}

// CheckOnboardPluginAvailable checks if the companion plugin is installed and available.
// Deprecated: Now checks for Riseup Asia Uploader availability instead.
func (c *Client) CheckOnboardPluginAvailable() (*UploaderAvailability, *apperror.AppError) {
	return c.CheckRiseupAsiaAvailable()
}

// CheckOnboardAvailable is an alias for CheckOnboardPluginAvailable
func (c *Client) CheckOnboardAvailable() (*UploaderAvailability, *apperror.AppError) {
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
	isTooLong := len(body) > maxLen

	if isTooLong {
		return body[:maxLen] + "..."
	}

	return body
}
