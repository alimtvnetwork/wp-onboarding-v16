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
func (c *Client) UploadPluginZip(zipPath string, pluginSlug string) apperror.Result[*OnboardUploadResult] {
	tokenResult := c.requestUploadMutationToken(pluginSlug)
	if tokenResult.HasError() {
		return apperror.Fail[*OnboardUploadResult](tokenResult.AppError())
	}

	mutationToken := tokenResult.Value()
	c.reportMutationTokenObtained(zipPath, mutationToken)

	formResult := c.buildZipMultipartForm(zipPath, pluginSlug)
	if formResult.HasError() {
		return apperror.Fail[*OnboardUploadResult](formResult.AppError())
	}

	form := formResult.Value()
	endpoint := OnboardMutationUploadEndpoint(OnboardNamespace, mutationToken)

	return c.executeOnboardZipUpload(endpoint, form, zipPath, pluginSlug)
}

// requestUploadMutationToken requests and returns a mutation token for upload.
func (c *Client) requestUploadMutationToken(pluginSlug string) apperror.Result[string] {
	c.progress(ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("Requesting upload mutation token for %s...", pluginSlug),
	})

	tokenResult := c.RequestMutationToken("upload")
	if tokenResult.HasError() {
		return apperror.Fail[string](
			apperror.Wrap(tokenResult.AppError(), apperror.ErrWPPluginUpload, "failed to get upload mutation token"))
	}

	return tokenResult
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
func (c *Client) buildZipMultipartForm(zipPath, pluginSlug string) apperror.Result[*zipMultipartForm] {
	openedResult := openAndStatFile(zipPath)
	if openedResult.HasError() {
		return apperror.Fail[*zipMultipartForm](openedResult.AppError())
	}

	opened := openedResult.Value()
	defer opened.File.Close()

	return c.buildFormFromOpenedFile(opened, zipPath, pluginSlug)
}

// buildFormFromOpenedFile constructs the multipart form from an opened file.
func (c *Client) buildFormFromOpenedFile(opened *openedFile, zipPath, pluginSlug string) apperror.Result[*zipMultipartForm] {
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

		return apperror.Fail[*zipMultipartForm](writeErr)
	}

	closeErr := writer.Close()
	if closeErr != nil {

		return apperror.FailWrap[*zipMultipartForm](closeErr, apperror.ErrInternal, "failed to close multipart writer")
	}

	return apperror.Ok(&zipMultipartForm{Body: &reqBody, ContentType: writer.FormDataContentType(), FileSize: opened.Info.Size()})
}

// openedFile holds an open file and its stat info.
type openedFile struct {
	File *os.File
	Info os.FileInfo
}

// openAndStatFile opens a file and returns it with its FileInfo.
func openAndStatFile(path string) apperror.Result[*openedFile] {
	file, err := os.Open(path)
	if err != nil {
		return apperror.FailWrap[*openedFile](err, apperror.ErrFSRead, "failed to open zip file for upload")
	}

	stat, statErr := file.Stat()
	if statErr != nil {
		file.Close()

		return apperror.FailWrap[*openedFile](statErr, apperror.ErrFSRead, "failed to stat zip file")
	}

	return apperror.Ok(&openedFile{File: file, Info: stat})
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
func (c *Client) executeOnboardZipUpload(endpoint string, form *zipMultipartForm, zipPath, pluginSlug string) apperror.Result[*OnboardUploadResult] {
	url := BuildWPJSONURL(c.baseURL, endpoint)

	c.reportOnboardUploadStart(url, endpoint, form, zipPath)

	mpResult := c.sendMultipartUpload(endpoint, form)
	if mpResult.HasError() {
		return apperror.Fail[*OnboardUploadResult](mpResult.AppError())
	}

	mpResp := mpResult.Value()
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
func (c *Client) sendMultipartUpload(endpoint string, form *zipMultipartForm) apperror.Result[*multipartResponse] {
	input := multipartInput{
		Method:      httpmethod.Post,
		Endpoint:    endpoint,
		Body:        form.Body,
		ContentType: form.ContentType,
	}

	resp, appErr := c.requestMultipart(input)
	if appErr != nil {
		return apperror.Fail[*multipartResponse](appErr)
	}
	defer resp.Body.Close()

	respBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return apperror.FailWrap[*multipartResponse](err, apperror.ErrInternal, "failed to read multipart response body")
	}

	return apperror.Ok(&multipartResponse{StatusCode: resp.StatusCode, Body: string(respBytes)})
}

// parseOnboardUploadResponse validates the status code and unmarshals the result.
func (c *Client) parseOnboardUploadResponse(mpResp *multipartResponse, endpoint, url, pluginSlug string) apperror.Result[*OnboardUploadResult] {
	isSuccess := mpResp.StatusCode == HttpStatusOk.Int() ||
		mpResp.StatusCode == HttpStatusCreated.Int()

	if !isSuccess {
		return apperror.Fail[*OnboardUploadResult](
			apperror.New(apperror.ErrWPPluginUpload, "upload plugin zip failed").
				WithEndpoint(endpoint).
				WithURL(url).
				WithSlug(pluginSlug).
				WithValue("statusCode", fmt.Sprintf("%d", mpResp.StatusCode)).
				WithValue("responseBody", truncateBody(mpResp.Body, 8192)))
	}

	var result OnboardUploadResult
	unmarshalErr := json.Unmarshal([]byte(mpResp.Body), &result)
	if unmarshalErr != nil {
		result.Success = true
		result.Message = "Upload completed"
	}

	return apperror.Ok(&result)
}

// --- Legacy compatibility aliases ---

// EnablePlugin activates/enables a plugin on the remote WordPress site.
func (c *Client) EnablePlugin(pluginSlug string) *apperror.AppError {
	return c.EnablePluginViaUploader(pluginSlug)
}

// CheckOnboardPluginAvailable checks if the companion plugin is installed and available.
// Deprecated: Now checks for Riseup Asia Uploader availability instead.
func (c *Client) CheckOnboardPluginAvailable() apperror.Result[*UploaderAvailability] {
	return c.CheckRiseupAsiaAvailable()
}

// CheckOnboardAvailable is an alias for CheckOnboardPluginAvailable
func (c *Client) CheckOnboardAvailable() apperror.Result[*UploaderAvailability] {
	return c.CheckOnboardPluginAvailable()
}

// UploadPluginViaOnboard uploads a plugin via the Riseup Asia Uploader and returns UploaderUploadResult.
// Deprecated: Delegates to UploadPluginViaUploader.
func (c *Client) UploadPluginViaOnboard(zipPath string, isActivate bool) apperror.Result[*UploaderUploadResult] {
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
