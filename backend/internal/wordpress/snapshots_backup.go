package wordpress

import (
	"bytes"
	"encoding/json"
	"io"
	"mime/multipart"
	"os"
	"path/filepath"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/pkg/apperror"
)

// SnapshotBackupOptions holds options for full/incremental backup triggers.
type SnapshotBackupOptions struct {
	Scope  string   `json:"scope,omitempty"`  // external key (Riseup Asia snapshot API)
	Tables []string `json:"tables,omitempty"` // external key
}

// SnapshotBackupResult holds the result of a backup operation.
type SnapshotBackupResult struct {
	Success    bool   `json:"success"`              // external key (Riseup Asia snapshot API)
	SnapshotId int64  `json:"snapshotId,omitempty"` // external key
	Message    string `json:"message,omitempty"`    // external key
	Status     string `json:"status,omitempty"`     // external key
}

// FullBackup triggers an end-to-end full backup orchestration on the remote site.
func (c *Client) FullBackup(opts SnapshotBackupOptions) (*SnapshotBackupResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsFullBackup)
	resp, err := c.request("POST", endpoint, opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to trigger full backup")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != HttpStatusOk.Int() && resp.StatusCode != HttpStatusCreated.Int() {
		return nil, &APIError{
			Operation:    "full backup",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotBackupResult
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode full backup response")
	}

	return &result, nil
}

// IncrementalBackup triggers an incremental backup against the latest master snapshot.
func (c *Client) IncrementalBackup(opts SnapshotBackupOptions) (*SnapshotBackupResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsIncremental)
	resp, err := c.request("POST", endpoint, opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to trigger incremental backup")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != HttpStatusOk.Int() && resp.StatusCode != HttpStatusCreated.Int() {
		return nil, &APIError{
			Operation:    "incremental backup",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotBackupResult
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode incremental backup response")
	}

	return &result, nil
}

// SnapshotImportResult holds the result of an import operation.
type SnapshotImportResult struct {
	Success    bool   `json:"success"`                 // external key (Riseup Asia snapshot API)
	SnapshotId int64  `json:"snapshot_id,omitempty"`   // external key
	Message    string `json:"message,omitempty"`       // external key
}

// ImportSnapshot uploads a ZIP file to import as a snapshot on the remote site.
func (c *Client) ImportSnapshot(zipPath string) (*SnapshotImportResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsImport)

	file, err := os.Open(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to open import ZIP file")
	}
	defer file.Close()

	body := &bytes.Buffer{}
	writer := multipart.NewWriter(body)

	part, err := writer.CreateFormFile("file", filepath.Base(zipPath))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create multipart form")
	}

	if _, err := io.Copy(part, file); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to write file to form")
	}

	writer.Close()

	resp, err := c.requestMultipart("POST", endpoint, body, writer.FormDataContentType())
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to import snapshot")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != HttpStatusOk.Int() && resp.StatusCode != HttpStatusCreated.Int() {
		return nil, &APIError{
			Operation:    "import snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotImportResult
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode import response")
	}

	return &result, nil
}

// SnapshotCleanupOptions holds options for snapshot cleanup.
type SnapshotCleanupOptions struct {
	DryRun bool `json:"dry_run,omitempty"` // external key (Riseup Asia Uploader API)
}

// SnapshotCleanupResult holds the result of a cleanup operation.
type SnapshotCleanupResult struct {
	Success        bool `json:"success"`                  // external key (Riseup Asia Uploader API)
	OrphansCleaned int  `json:"orphans_cleaned,omitempty"` // external key
	StuckCleaned   int  `json:"stuck_cleaned,omitempty"`   // external key
	AgedCleaned    int  `json:"aged_cleaned,omitempty"`    // external key
}

// CleanupSnapshots triggers cleanup of old, orphan, and stuck snapshots.
func (c *Client) CleanupSnapshots(opts SnapshotCleanupOptions) (*SnapshotCleanupResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsCleanup)
	resp, err := c.request("POST", endpoint, opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to trigger snapshot cleanup")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != HttpStatusOk.Int() {
		return nil, &APIError{
			Operation:    "snapshot cleanup",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotCleanupResult
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode cleanup response")
	}

	return &result, nil
}
