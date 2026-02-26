package wordpress

import (
	"bytes"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/enums/http_method"
	"wp-plugin-publish/internal/enums/operation"
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
func (c *Client) FullBackup(opts SnapshotBackupOptions) apperror.Result[SnapshotBackupResult] {
	return doAPICall[SnapshotBackupResult](c, apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   snapshotEndpoint(ep.SnapshotsFullBackup),
		Body:       opts,
		Operation:  operation.FullBackup,
		OkStatuses: []int{http.StatusOK, http.StatusCreated},
	})
}

// IncrementalBackup triggers an incremental backup against the latest master snapshot.
func (c *Client) IncrementalBackup(opts SnapshotBackupOptions) apperror.Result[SnapshotBackupResult] {
	return doAPICall[SnapshotBackupResult](c, apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   snapshotEndpoint(ep.SnapshotsIncremental),
		Body:       opts,
		Operation:  operation.IncrementalBackup,
		OkStatuses: []int{http.StatusOK, http.StatusCreated},
	})
}

// SnapshotImportResult holds the result of an import operation.
type SnapshotImportResult struct {
	Success    bool   `json:"success"`                 // external key (Riseup Asia snapshot API)
	SnapshotId int64  `json:"snapshot_id,omitempty"`   // external key
	Message    string `json:"message,omitempty"`       // external key
}

// ImportSnapshot uploads a ZIP file to import as a snapshot on the remote site.
func (c *Client) ImportSnapshot(zipPath string) apperror.Result[SnapshotImportResult] {
	endpoint := snapshotEndpoint(ep.SnapshotsImport)

	mp, err := buildImportMultipart(zipPath)
	if err != nil {
		return apperror.FailWrap[SnapshotImportResult](err, apperror.ErrInternal, operation.ImportSnapshot.Value())
	}

	return c.executeImportRequest(endpoint, mp.Body, mp.ContentType)
}

// buildImportMultipart creates the multipart body for a snapshot import.
func buildImportMultipart(zipPath string) (*multipartResult, error) {
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

	return &multipartResult{Body: body, ContentType: writer.FormDataContentType()}, nil
}

// executeImportRequest sends the multipart import and parses the response.
func (c *Client) executeImportRequest(endpoint string, body *bytes.Buffer, contentType string) apperror.Result[SnapshotImportResult] {
	mpInput := multipartInput{
		Method:      httpmethod.Post,
		Endpoint:    endpoint,
		Body:        body,
		ContentType: contentType,
	}

	resp, err := c.requestMultipart(mpInput)
	if err != nil {
		return apperror.FailWrap[SnapshotImportResult](err, apperror.ErrInternal, "failed to import snapshot")
	}

	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if isErrorStatus(resp.StatusCode, []int{http.StatusOK, http.StatusCreated}) {
		errorInput := apiCallInput{
			Method:    httpmethod.Post,
			Endpoint:  endpoint,
			Operation: operation.ImportSnapshot,
		}

		return apperror.Fail[SnapshotImportResult](c.buildCallError(errorInput, resp.StatusCode, bodyBytes))
	}

	return decodeAPIResponse[SnapshotImportResult](bodyBytes, operation.ImportSnapshot.Value())
}

// SnapshotCleanupOptions holds options for snapshot cleanup.
type SnapshotCleanupOptions struct {
	DryRun bool `json:",omitempty"`
}

// SnapshotCleanupResult holds the result of a cleanup operation.
// Matches PHP buildCleanupResponse() PascalCase keys from ResponseKeyType.
type SnapshotCleanupResult struct {
	IsSuccess bool
	Retention CleanupRetentionResult
	Orphans   CleanupOrphansResult
	Stuck     CleanupStuckResult
	Duration  float64
	IsDryRun  bool
	Errors    []string
}

// CleanupRetentionResult holds retention-phase cleanup details.
type CleanupRetentionResult struct {
	Deleted       int
	SkippedMaster int
	BytesFreed    int64
	Details       []any
}

// CleanupOrphansResult holds orphan-phase cleanup details.
type CleanupOrphansResult struct {
	Removed    int
	BytesFreed int64
	Files      []string
}

// CleanupStuckResult holds stuck-phase cleanup details.
type CleanupStuckResult struct {
	Cleaned int
	Ids     []int64
}

// CleanupSnapshots triggers cleanup of old, orphan, and stuck snapshots.
func (c *Client) CleanupSnapshots(opts SnapshotCleanupOptions) apperror.Result[SnapshotCleanupResult] {
	return doAPICall[SnapshotCleanupResult](c, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsCleanup),
		Body:      opts,
		Operation: operation.SnapshotCleanup,
	})
}
