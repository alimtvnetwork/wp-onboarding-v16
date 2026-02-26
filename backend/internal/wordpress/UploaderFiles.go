package wordpress

import (
	"encoding/base64"
	"fmt"

	"wp-plugin-publish/internal/enums/action"
	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/pkg/apperror"
)

// ReplaceFileViaUploader replaces a single file in a plugin via the RiseupAsia Uploader.
func (c *Client) ReplaceFileViaUploader(slug, relPath string, content []byte, isBase64 bool) error {
	namespace := c.resolveNamespace()
	endpoint := "/" + namespace + ep.Files.String()
	contentStr := base64.StdEncoding.EncodeToString(content)

	_, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body:       PluginFileReplaceRequest{Plugin: slug, Path: relPath, Content: contentStr},
		Operation:  "replace file via RiseupAsia Uploader",
		PluginSlug: slug, ErrorCode: apperror.ErrWPConnection,
	})
	return err
}

// DeleteFileViaUploader deletes a single file from a plugin via the Riseup Asia Uploader.
func (c *Client) DeleteFileViaUploader(slug, relPath string) error {
	namespace := c.resolveNamespace()
	endpoint := "/" + namespace + ep.Files.String()

	_, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body:       PluginFileDeleteRequest{Plugin: slug, Path: relPath, Action: "delete"},
		Operation:  "delete file via Riseup Asia Uploader",
		PluginSlug: slug, ErrorCode: apperror.ErrWPConnection,
	})
	return err
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
func (c *Client) SyncPluginFilesViaUploader(slug string, files []SyncFile) (*SyncResult, error) {
	namespace := c.resolveNamespace()
	c.reportSyncStart(slug, len(files), namespace)

	endpoint := "/" + namespace + ep.Sync.String()
	data, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body:       SyncRequestBody{Plugin: slug, Files: files},
		Operation:  "sync plugin files via Riseup Asia Uploader",
		PluginSlug: slug, ErrorCode: apperror.ErrWPConnection,
	})
	if err != nil {
		return nil, err
	}

	return decodeAPIResponse[SyncResult](data, "sync result")
}

// reportSyncStart emits a progress event for the start of a delta sync operation.
func (c *Client) reportSyncStart(slug string, fileCount int, namespace string) {
	c.progress(ProgressEvent{
		Step: action.Sync.String(), Status: stagestatus.Running.String(),
		Message: fmt.Sprintf("Syncing %d files to %s...", fileCount, slug),
		Details: toProgress(SyncInitProgress{Slug: slug, FileCount: fileCount, Namespace: namespace}),
	})
}
