// Package wordpress — UploadPluginViaUploader step helpers.
// Each step is extracted to comply with the 15-line function body limit.
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

	action "wp-plugin-publish/internal/enums/actiontype"
	ep "wp-plugin-publish/internal/enums/endpointtype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	uploadsource "wp-plugin-publish/internal/enums/uploadsourcetype"
	"wp-plugin-publish/pkg/apperror"
	"wp-plugin-publish/pkg/pathutil"
)

// uploadContext holds resolved state for a plugin upload operation.
type uploadContext struct {
	AbsZipPath     string
	Slug           string
	ZipSize        int64
	Namespace      string
	UploadEndpoint string
	UploadURL      string
	ZipFile        *os.File
}

// prepareUploadContext resolves paths, validates slug, opens the ZIP, and computes metadata.
func (c *Client) prepareUploadContext(zipPath, slug string) (*uploadContext, *apperror.AppError) {
	absZipPath, err := pathutil.ToAbsolute(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "resolve zip path").WithPath(zipPath)
	}

	slug = normalizeUploadSlug(absZipPath, slug)

	zfh, openErr := openAndStatZip(absZipPath)
	if openErr != nil {
		return nil, openErr
	}

	return c.buildUploadContext(absZipPath, slug, zfh), nil
}

// buildUploadContext constructs the uploadContext from resolved inputs.
// This function never errors — it is pure struct construction.
func (c *Client) buildUploadContext(absZipPath, slug string, zfh *zipFileHandle) *uploadContext {
	namespace := c.resolveNamespace()
	uploadEndpoint := BuildNamespacedEndpoint(namespace, ep.Upload)
	uploadURL := BuildWPJSONURL(c.baseURL, uploadEndpoint)

	return &uploadContext{
		AbsZipPath:     absZipPath,
		Slug:           slug,
		ZipSize:        zfh.Size,
		Namespace:      namespace,
		UploadEndpoint: uploadEndpoint,
		UploadURL:      uploadURL,
		ZipFile:        zfh.File,
	}
}

// normalizeUploadSlug ensures a valid slug for the upload, stripping .zip extensions.
func normalizeUploadSlug(absZipPath, slug string) string {
	isSlugEmpty := slug == ""

	if isSlugEmpty {
		slug = strings.TrimSuffix(filepath.Base(absZipPath), ".zip")
	}

	return strings.TrimSuffix(slug, ".zip")
}

// zipFileHandle holds an open ZIP file and its size.
type zipFileHandle struct {
	File *os.File
	Size int64
}

// openAndStatZip opens a ZIP file and returns the handle and size.
func openAndStatZip(absZipPath string) (*zipFileHandle, *apperror.AppError) {
	zipFile, err := os.Open(absZipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "open zip file").WithPath(pathutil.ForDisplay(absZipPath))
	}

	fileInfo, err := zipFile.Stat()
	if err != nil {
		zipFile.Close()

		return nil, apperror.Wrap(err, apperror.ErrFSRead, "stat zip file").WithPath(pathutil.ForDisplay(absZipPath))
	}

	return &zipFileHandle{File: zipFile, Size: fileInfo.Size()}, nil
}

// multipartResult holds the output of building a multipart request body.
type multipartResult struct {
	Body        *bytes.Buffer
	ContentType string
}

// buildMultipartBody creates the multipart/form-data request body for plugin upload.
func buildMultipartBody(uc *uploadContext, isActivate bool, source uploadsource.Variant) (*multipartResult, *apperror.AppError) {
	var requestBody bytes.Buffer
	writer := multipart.NewWriter(&requestBody)

	part, err := writer.CreateFormFile("plugin_zip", filepath.Base(uc.AbsZipPath))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create multipart form file")
	}
	_, copyErr := io.Copy(part, uc.ZipFile)

	if copyErr != nil {
		return nil, apperror.Wrap(copyErr, apperror.ErrFSRead, "stream zip to multipart")
	}

	writeUploadFields(uploadFieldsInput{Writer: writer, Slug: uc.Slug, IsActivate: isActivate, Source: source})

	closeErr := writer.Close()

	if closeErr != nil {
		return nil, apperror.Wrap(closeErr, apperror.ErrInternal, "close multipart writer")
	}

	return &multipartResult{Body: &requestBody, ContentType: writer.FormDataContentType()}, nil
}

// uploadFieldsInput bundles parameters for writeUploadFields.
type uploadFieldsInput struct {
	Writer     *multipart.Writer
	Slug       string
	IsActivate bool
	Source     uploadsource.Variant
}

// writeUploadFields adds form fields to the multipart writer.
func writeUploadFields(input uploadFieldsInput) {
	_ = input.Writer.WriteField("slug", input.Slug)
	if input.IsActivate {
		_ = input.Writer.WriteField("activate", "1")
	} else {
		_ = input.Writer.WriteField("activate", "0")
	}
	_ = input.Writer.WriteField("upload_source", input.Source.String())
}

// executeUploadHTTP sends the multipart upload request and parses the response.
func (c *Client) executeUploadHTTP(uc *uploadContext, body *bytes.Buffer, contentType string) (*UploaderUploadResult, *apperror.AppError) {
	req, err := http.NewRequest("POST", uc.UploadURL, body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create upload HTTP request").WithURL(uc.UploadURL)
	}

	c.setStandardHeaders(req, contentType)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "upload request failed").WithURL(uc.UploadURL)
	}
	defer resp.Body.Close()

	return c.parseUploadResponse(resp, uc)
}

// parseUploadResponse reads and processes the upload HTTP response.
func (c *Client) parseUploadResponse(resp *http.Response, uc *uploadContext) (*UploaderUploadResult, *apperror.AppError) {
	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.reportUploadResponseProgress(resp.StatusCode, respBody, uc.UploadURL)

	isErrorResponse := resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated

	if isErrorResponse {
		return nil, c.buildUploadFailureAppError(uc, resp.StatusCode, respBytes, respBody)
	}

	return decodeUploadResult(respBytes), nil
}

// reportUploadResponseProgress logs the upload response progress.
func (c *Client) reportUploadResponseProgress(statusCode int, respBody, uploadURL string) {
	c.progress(ProgressEvent{
		Step: action.Upload.String(), Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Upload response: %d from %s", statusCode, uploadURL),
		Details: toProgress(ResponseProgress{
			URL:    uploadURL,
			Status: statusCode,
			Body:   truncateBody(respBody, 2000),
		}),
	})
}

// buildUploadFailureAppError constructs an *apperror.AppError wrapping the structured APIError.
func (c *Client) buildUploadFailureAppError(uc *uploadContext, statusCode int, respBytes []byte, respBody string) *apperror.AppError {
	errInput := uploadAPIErrorInput{
		AbsZipPath:      uc.AbsZipPath,
		UploadURL:       uc.UploadURL,
		UploadEndpoint:  uc.UploadEndpoint,
		StatusCode:      statusCode,
		RespBytes:       respBytes,
		RespBody:        respBody,
		StackTraceDepth: c.stackTraceDepth,
	}

	apiErr := buildUploadAPIError(errInput)

	return apperror.WrapWithSkip(apiErr, apperror.ErrWPPluginUpload, "upload plugin failed", 1).
		WithURL(uc.UploadURL).
		WithStatusCode(statusCode)
}

// decodeUploadResult unmarshals the upload response or returns a default success.
// This function never errors — on unmarshal failure it returns a default success result.
func decodeUploadResult(respBytes []byte) *UploaderUploadResult {
	var result UploaderUploadResult
	err := json.Unmarshal(respBytes, &result)

	if err != nil {
		result.Success = true
		result.Message = "Upload completed"
	}

	return &result
}
