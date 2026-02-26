// Package wordpress provides remote file/upload capabilities via the Riseup Asia Uploader companion plugin API.
package wordpress

import (
	"context"
	"fmt"
	"time"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/enums/http_method"
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

	callInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      PluginSlugRequest{Plugin: slug},
		Operation: "get sync manifest",
		ErrorCode: apperror.ErrWPConnection,
	}
	data, err := c.doAPICallRaw(callInput)
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

	callInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      PluginSlugRequest{Plugin: slug},
		Operation: "get plugin files",
		ErrorCode: apperror.ErrWPConnection,
	}
	data, err := c.doAPICallRaw(callInput)
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

	callInput := apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "request mutation token",
		ErrorCode: apperror.ErrWPConnection,
	}
	data, err := c.doAPICallRaw(callInput)
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

	callInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  endpoint,
		Body:      PluginFileRequest{Plugin: pluginSlug, Path: filePath},
		Operation: "get file content",
		ErrorCode: apperror.ErrWPConnection,
	}
	data, err := c.doAPICallRaw(callInput)
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
	isFailure := !isSuccess

	if isFailure {
		var zero T
		return zero, apperror.New(apperror.ErrWPConnection, "remote API returned failure for "+operation).
			WithValue("slug", slug)
	}
	return data, nil
}

// mapNotFoundError checks if err is an APIError with 404 status and returns a typed not-found error.
func mapNotFoundError(err error, message, identifier string) error {
	if apiErr := ExtractAPIError(err); apiErr != nil && apiErr.StatusCode == HttpStatusNotFound.Int() {
		return apperror.New(apperror.ErrNotFound, message).WithValue("identifier", identifier)
	}
	return err
}
