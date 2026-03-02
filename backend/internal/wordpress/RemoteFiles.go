// Package wordpress provides remote file/upload capabilities via the Riseup Asia Uploader companion plugin API.
package wordpress

import (
	"context"
	"time"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
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

// IsDefined returns true if the result is not nil (nil-safe).
func (r *OnboardUploadResult) IsDefined() bool { return r != nil }

// IsSuccessful returns true if the upload succeeded (nil-safe).
func (r *OnboardUploadResult) IsSuccessful() bool { return r != nil && r.Success }

// IsFailed returns true if the result is nil or the upload failed (nil-safe).
func (r *OnboardUploadResult) IsFailed() bool { return r == nil || !r.Success }

// GetPluginFiles retrieves the list of files for a remote plugin.
// Delegates to GetPluginFilesViaRiseup (Riseup Asia Uploader).
func (c *Client) GetPluginFiles(ctx context.Context, slug string) apperror.Result[[]RemoteFile] {
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
func (c *Client) GetPluginSyncManifest(ctx context.Context, slug string) apperror.Result[[]RemoteFile] {
	endpoint := "/" + RiseupAsiaNamespace + ep.SyncManifest.String()

	callInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      PluginSlugRequest{Plugin: slug},
		Operation: operationtype.GetSyncManifest,
		ErrorCode: apperror.ErrWPConnection,
	}
	rawResult := c.doApiCallRaw(callInput)
	if rawResult.HasError() {
		return apperror.Fail[[]RemoteFile](rawResult.AppError())
	}

	decodeResult := decodeApiResponse[syncManifestResult](rawResult.Value(), "sync manifest")
	if decodeResult.HasError() {
		return apperror.Fail[[]RemoteFile](decodeResult.AppError())
	}

	result := decodeResult.Value()

	return validateSuccessAndReturn(result.Success, result.Data.Files, successCheckContext{Operation: "sync manifest", Slug: slug})
}

// pluginFilesResult is the response shape from the files endpoint.
type pluginFilesResult struct {
	Success    bool         `json:"success"`    // external key (Riseup Asia Uploader API)
	Plugin     string       `json:"plugin"`     // external key
	TotalFiles int          `json:"totalFiles"` // external key
	Files      []RemoteFile `json:"files"`      // external key
}

// GetPluginFilesViaRiseup retrieves the list of files for a remote plugin via Riseup Asia Uploader.
func (c *Client) GetPluginFilesViaRiseup(ctx context.Context, slug string) apperror.Result[[]RemoteFile] {
	endpoint := "/" + RiseupAsiaNamespace + ep.Files.String()

	callInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      PluginSlugRequest{Plugin: slug},
		Operation: operationtype.GetPluginFiles,
		ErrorCode: apperror.ErrWPConnection,
	}
	rawResult := c.doApiCallRaw(callInput)
	if rawResult.HasError() {
		return apperror.Fail[[]RemoteFile](mapNotFoundError(rawResult.AppError(), "plugin not found on remote", slug))
	}

	decodeResult := decodeApiResponse[pluginFilesResult](rawResult.Value(), "plugin files")
	if decodeResult.HasError() {
		return apperror.Fail[[]RemoteFile](decodeResult.AppError())
	}

	result := decodeResult.Value()

	return validateSuccessAndReturn(result.Success, result.Files, successCheckContext{Operation: "plugin files", Slug: slug})
}

// mutationTokenResult is the response from the mutation token endpoint.
type mutationTokenResult struct {
	MutationToken string `json:"mutation_token"` // external key (Riseup Asia Uploader API)
	ExpiresIn     int    `json:"expires_in"`     // external key
}

// RequestMutationToken requests a mutation token from the legacy Onboard companion plugin.
// Deprecated: The Riseup Asia Uploader does not use mutation tokens.
func (c *Client) RequestMutationToken(action string) apperror.Result[string] {
	endpoint := fmt.Sprintf("/%s%s?action=%s", OnboardNamespace, OnboardRequestMutationPath, action)

	callInput := apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.RequestMutationToken,
		ErrorCode: apperror.ErrWPConnection,
	}
	rawResult := c.doApiCallRaw(callInput)
	if rawResult.HasError() {
		return apperror.Fail[string](rawResult.AppError())
	}

	decodeResult := decodeApiResponse[mutationTokenResult](rawResult.Value(), "mutation token")
	if decodeResult.HasError() {
		return apperror.Fail[string](decodeResult.AppError())
	}

	result := decodeResult.Value()
	isTokenEmpty := result.MutationToken == ""

	if isTokenEmpty {
		return apperror.FailNew[string](apperror.ErrWPConnection, "empty mutation token in response")
	}

	return apperror.Ok(result.MutationToken)
}

// fileContentResult is the response from the file content endpoint.
type fileContentResult struct {
	Success bool   `json:"success"` // external key (Riseup Asia Uploader API)
	Path    string `json:"path"`    // external key
	Content string `json:"content"` // external key
}

// GetPluginFileContent retrieves the content of a specific file from a remote plugin.
func (c *Client) GetPluginFileContent(ctx context.Context, pluginSlug, filePath string) apperror.Result[string] {
	endpoint := "/" + RiseupAsiaNamespace + ep.File.String()

	callInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      PluginFileRequest{Plugin: pluginSlug, Path: filePath},
		Operation: operationtype.GetFileContent,
		ErrorCode: apperror.ErrWPConnection,
	}
	rawResult := c.doApiCallRaw(callInput)
	if rawResult.HasError() {
		return apperror.Fail[string](mapNotFoundError(rawResult.AppError(), "file not found on remote", filePath))
	}

	decodeResult := decodeApiResponse[fileContentResult](rawResult.Value(), "file content")
	if decodeResult.HasError() {
		return apperror.Fail[string](decodeResult.AppError())
	}

	result := decodeResult.Value()
	isFailure := !result.Success

	if isFailure {
		return apperror.FailNew[string](apperror.ErrWPConnection, "remote API returned failure for file content")
	}

	return apperror.Ok(result.Content)
}

// successCheckContext bundles the context fields for validateSuccessAndReturn.
type successCheckContext struct {
	Operation string
	Slug      string
}

// validateSuccessAndReturn checks the success flag and returns data or an error.
func validateSuccessAndReturn[T any](isSuccess bool, data T, ctx successCheckContext) apperror.Result[T] {
	isFailure := !isSuccess

	if isFailure {
		return apperror.FailNew[T](apperror.ErrWPConnection, "remote API returned failure for "+ctx.Operation)
	}

	return apperror.Ok(data)
}

// mapNotFoundError checks if err is an APIError with 404 status and returns a typed not-found error.
func mapNotFoundError(err *apperror.AppError, message, identifier string) *apperror.AppError {
	apiErr := ExtractApiError(err)
	isMissing := apiErr != nil && apiErr.StatusCode == HttpStatusNotFound.Int()

	if isMissing {
		return apperror.New(apperror.ErrNotFound, message).WithValue("identifier", identifier)
	}

	return err
}
