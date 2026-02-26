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
	callInput := apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   snapshotEndpoint(ep.SnapshotsFullBackup),
		Body:       opts,
		Operation:  "full backup",
		OkStatuses: []int{http.StatusOK, http.StatusCreated},
	}
	return doAPICall[SnapshotBackupResult](c, callInput)
}

// IncrementalBackup triggers an incremental backup against the latest master snapshot.
func (c *Client) IncrementalBackup(opts SnapshotBackupOptions) (*SnapshotBackupResult, error) {
	callInput := apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   snapshotEndpoint(ep.SnapshotsIncremental),
		Body:       opts,
		Operation:  "incremental backup",
		OkStatuses: []int{http.StatusOK, http.StatusCreated},
	}
	return doAPICall[SnapshotBackupResult](c, callInput)
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

	mp, err := buildImportMultipart(zipPath)
	if err != nil {
		return nil, err
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
func (c *Client) executeImportRequest(endpoint string, body *bytes.Buffer, contentType string) (*SnapshotImportResult, error) {
	mpInput := multipartInput{
		Method:      httpmethod.Post,
		Endpoint:    endpoint,
		Body:        body,
		ContentType: contentType,
	}

	resp, err := c.requestMultipart(mpInput)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to import snapshot")
	}

	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if isErrorStatus(resp.StatusCode, []int{http.StatusOK, http.StatusCreated}) {
		errorInput := apiCallInput{
			Method:    httpmethod.Post,
			Endpoint:  endpoint,
			Operation: "import snapshot",
		}

		return nil, c.buildCallError(errorInput, resp.StatusCode, bodyBytes)
	}

	return decodeAPIResponse[SnapshotImportResult](bodyBytes, "import snapshot")
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
	callInput := apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsCleanup),
		Body:      opts,
		Operation: "snapshot cleanup",
	}
	return doAPICall[SnapshotCleanupResult](c, callInput)
}
