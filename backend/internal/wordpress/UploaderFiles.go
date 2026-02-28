package wordpress

import (
	"encoding/base64"
	"fmt"

	action "wp-plugin-publish/internal/enums/actiontype"
	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operation "wp-plugin-publish/internal/enums/operationtype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
)

// ReplaceFileInput bundles parameters for ReplaceFileViaUploader.
type ReplaceFileInput struct {
	Slug     string
	RelPath  string
	Content  []byte
	IsBase64 bool
}

// ReplaceFileViaUploader replaces a single file in a plugin via the RiseupAsia Uploader.
func (c *Client) ReplaceFileViaUploader(input ReplaceFileInput) *apperror.AppError {
	namespace := c.resolveNamespace()
	endpoint := "/" + namespace + ep.Files.String()
	contentStr := base64.StdEncoding.EncodeToString(input.Content)

	rawResult := c.doAPICallRaw(apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginFileReplaceRequest{Plugin: input.Slug, Path: input.RelPath, Content: contentStr},
		Operation:  operation.ReplaceFile,
		PluginSlug: input.Slug,
		ErrorCode:  apperror.ErrWPConnection,
	})

	return rawResult.AppError()
}

// DeleteFileViaUploader deletes a single file from a plugin via the Riseup Asia Uploader.
func (c *Client) DeleteFileViaUploader(slug, relPath string) *apperror.AppError {
	namespace := c.resolveNamespace()
	endpoint := "/" + namespace + ep.Files.String()

	rawResult := c.doAPICallRaw(apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginFileDeleteRequest{Plugin: slug, Path: relPath, Action: "delete"},
		Operation:  operation.DeleteFile,
		PluginSlug: slug,
		ErrorCode:  apperror.ErrWPConnection,
	})

	return rawResult.AppError()
}

// =============================================================================
// DELTA SYNC TYPES AND METHODS
// =============================================================================

// SyncFile represents a single file in a sync request.
type SyncFile struct {
	Path    string `json:"path"`              // external key (Riseup Asia Uploader API)
	Content string `json:"content,omitempty"` // external key (base64 encoded)
	Action  string `json:"action"`            // external key ("replace" or "delete")
}

// SyncFileResult represents the result of syncing a single file.
type SyncFileResult struct {
	Path   string `json:"path"`             // external key (Riseup Asia Uploader API)
	Action string `json:"action"`           // external key
	Status string `json:"status"`           // external key
	Reason string `json:"reason,omitempty"` // external key
}

// SyncResult represents the result of a delta sync operation.
type SyncResult struct {
	Success      bool             `json:"success"`       // external key (Riseup Asia Uploader API)
	FilesUpdated int              `json:"files_updated"` // external key
	FilesDeleted int              `json:"files_deleted"` // external key
	FilesIgnored int              `json:"files_ignored"` // external key
	IgnoredFiles []string         `json:"ignored_files"` // external key
	Results      []SyncFileResult `json:"results"`       // external key
}

// SyncPluginFilesViaUploader performs a delta sync of multiple files to a plugin.
func (c *Client) SyncPluginFilesViaUploader(slug string, files []SyncFile) apperror.Result[SyncResult] {
	namespace := c.resolveNamespace()
	c.reportSyncStart(slug, len(files), namespace)

	endpoint := "/" + namespace + ep.Sync.String()

	rawResult := c.doAPICallRaw(apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       SyncRequestBody{Plugin: slug, Files: files},
		Operation:  operation.SyncFiles,
		PluginSlug: slug,
		ErrorCode:  apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[SyncResult](rawResult.AppError())
	}

	return decodeAPIResponse[SyncResult](rawResult.Value(), operation.SyncFiles.Value())
}

// reportSyncStart emits a progress event for the start of a delta sync operation.
func (c *Client) reportSyncStart(slug string, fileCount int, namespace string) {
	c.progress(ProgressEvent{
		Step: action.Sync.String(), Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Syncing %d files to %s...", fileCount, slug),
		Details: toProgress(SyncInitProgress{Slug: slug, FileCount: fileCount, Namespace: namespace}),
	})
}
