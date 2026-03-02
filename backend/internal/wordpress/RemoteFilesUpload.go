package wordpress

import (
	"bytes"
	"fmt"
	"io"
	"mime/multipart"
	"os"
	"path/filepath"
	"strings"

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
	tokenRequestEvent := ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("Requesting upload mutation token for %s...", pluginSlug),
	}
	c.progress(tokenRequestEvent)

	tokenResult := c.RequestMutationToken("upload")
	if tokenResult.HasError() {
		return apperror.Fail[string](
			apperror.Wrap(tokenResult.AppError(), apperror.ErrWPPluginUpload, "failed to get upload mutation token"))
	}

	return tokenResult
}

// reportMutationTokenObtained logs that the mutation token was obtained.
func (c *Client) reportMutationTokenObtained(zipPath, mutationToken string) {
	tokenObtainedEvent := ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("Mutation token obtained, uploading %s...", filepath.Base(zipPath)),
		Details: toProgress(TokenProgress{TokenLength: len(mutationToken)}),
	}
	c.progress(tokenObtainedEvent)
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
		UploadSource: uploadsourcetype.RestApi,
	}

	return c.UploadPluginViaUploader(uploadInput)
}
