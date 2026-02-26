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

	"wp-plugin-publish/internal/enums/action"
	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/internal/enums/upload_source"
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
func (c *Client) prepareUploadContext(zipPath, slug string) (*uploadContext, error) {
	absZipPath, err := pathutil.ToAbsolute(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "resolve zip path").WithPath(zipPath)
	}

	slug = normalizeUploadSlug(absZipPath, slug)

	zfh, err := openAndStatZip(absZipPath)
	if err != nil {
		return nil, err
	}

	namespace := c.resolveNamespace()
	uploadEndpoint := fmt.Sprintf("/%s%s", namespace, ep.Upload)
	uploadURL := fmt.Sprintf("%s/wp-json%s", c.baseURL, uploadEndpoint)

	return &uploadContext{
		AbsZipPath:     absZipPath,
		Slug:           slug,
		ZipSize:        zfh.Size,
		Namespace:      namespace,
		UploadEndpoint: uploadEndpoint,
		UploadURL:      uploadURL,
		ZipFile:        zfh.File,
	}, nil
}

// normalizeUploadSlug ensures a valid slug for the upload, stripping .zip extensions.
func normalizeUploadSlug(absZipPath, slug string) string {
	if slug == "" {
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
func openAndStatZip(absZipPath string) (*zipFileHandle, error) {
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
func buildMultipartBody(uc *uploadContext, isActivate bool, source uploadsource.Variant) (*multipartResult, error) {
	var requestBody bytes.Buffer
	writer := multipart.NewWriter(&requestBody)

	part, err := writer.CreateFormFile("plugin_zip", filepath.Base(uc.AbsZipPath))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create multipart form file")
	}
	if _, err := io.Copy(part, uc.ZipFile); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrFSRead, "stream zip to multipart")
	}

	writeUploadFields(uploadFieldsInput{Writer: writer, Slug: uc.Slug, IsActivate: isActivate, Source: source})

	if err := writer.Close(); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "close multipart writer")
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
func (c *Client) executeUploadHTTP(uc *uploadContext, body *bytes.Buffer, contentType string) (*UploaderUploadResult, error) {
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
func (c *Client) parseUploadResponse(resp *http.Response, uc *uploadContext) (*UploaderUploadResult, error) {
	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress(ProgressEvent{
		Step: action.Upload.String(), Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Upload response: %d from %s", resp.StatusCode, uc.UploadURL),
		Details: toProgress(ResponseProgress{
			URL:    uc.UploadURL,
			Status: resp.StatusCode,
			Body:   truncateBody(respBody, 2000),
		}),
	})

	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusCreated {
		return nil, buildUploadAPIError(uploadAPIErrorInput{AbsZipPath: uc.AbsZipPath, UploadURL: uc.UploadURL, UploadEndpoint: uc.UploadEndpoint, StatusCode: resp.StatusCode, RespBytes: respBytes, RespBody: respBody, StackTraceDepth: c.stackTraceDepth})
	}

	var result UploaderUploadResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		result.Success = true
		result.Message = "Upload completed"
	}
	return &result, nil
}
