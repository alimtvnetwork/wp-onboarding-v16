// Package wordpress provides snapshot management via the Riseup Asia Uploader REST API.
// All endpoints use fixed paths with IDs passed in JSON request bodies.
package wordpress

import (
	"bytes"
	"encoding/json"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/pkg/apperror"
)

// SnapshotRecord represents a database snapshot record from the WordPress plugin.
type SnapshotRecord struct {
	Id        int64  `json:"id"`        // external key (Riseup Asia snapshot API)
	Sequence  int    `json:"sequence"`  // external key
	Filename  string `json:"filename"`  // external key
	Scope     string `json:"scope"`     // external key
	Provider  string `json:"provider"`  // external key
	Status    string `json:"status"`    // external key
	FileSize  int64  `json:"fileSize"`  // external key
	TotalRows int    `json:"totalRows"` // external key
	Tables    string `json:"tables"`    // external key
	CreatedAt string `json:"createdAt"` // external key
	Error     string `json:"error,omitempty"` // external key
}

// SnapshotSettings represents snapshot configuration on the WordPress site.
type SnapshotSettings struct {
	Provider      string `json:"provider"`            // external key (Riseup Asia snapshot API)
	Schedule      string `json:"schedule"`            // external key
	ScheduleTime  string `json:"scheduleTime,omitempty"` // external key
	ScheduleDay   string `json:"scheduleDay,omitempty"`  // external key
	Scope         string `json:"scope"`               // external key
	RetentionType string `json:"retentionType"`       // external key
	RetentionDays int    `json:"retentionDays,omitempty"` // external key
	RetentionMax  int    `json:"retentionMax,omitempty"`  // external key
	PreRestore    bool   `json:"preRestoreBackup"`    // external key
	BatchSize     int    `json:"batchSize,omitempty"` // external key
}

// SnapshotProvider represents an available snapshot provider.
type SnapshotProvider struct {
	Id        string `json:"id"`        // external key (Riseup Asia snapshot API)
	Name      string `json:"name"`      // external key
	Available bool   `json:"available"` // external key
	Priority  int    `json:"priority"`  // external key
}

// SnapshotStorageStats represents storage statistics.
type SnapshotStorageStats struct {
	TotalSnapshots int    `json:"totalSnapshots"` // external key (Riseup Asia snapshot API)
	TotalSize      int64  `json:"totalSize"`      // external key
	TotalSizeHuman string `json:"totalSizeHuman"` // external key
	DiskFreeSpace  int64  `json:"diskFreeSpace"`  // external key
	OldestAt       string `json:"oldestAt,omitempty"` // external key
	NewestAt       string `json:"newestAt,omitempty"` // external key
}

// AvailableTable represents a database table available for snapshotting.
type AvailableTable struct {
	Name   string `json:"name"`   // external key (Riseup Asia snapshot API)
	Rows   int    `json:"rows"`   // external key
	Size   int64  `json:"size"`   // external key
	IsCore bool   `json:"isCore"` // external key
}

// snapshotEndpoint builds the full endpoint path for snapshot operations using fixed paths.
func snapshotEndpoint(path ep.Variant) string {
	return "/" + RiseupAsiaNamespace + path.String()
}

// GetSnapshots lists all snapshots on the remote site.
func (c *Client) GetSnapshots() ([]SnapshotRecord, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsList)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshots")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshots",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result struct {
		Snapshots []SnapshotRecord `json:"snapshots"` // external key
	}
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		// Try decoding as plain array
		var snapshots []SnapshotRecord
		resp2, _ := c.request("GET", endpoint, nil)
		if resp2 != nil {
			defer resp2.Body.Close()
			if err2 := json.NewDecoder(resp2.Body).Decode(&snapshots); err2 == nil {
				return snapshots, nil
			}
		}
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode snapshots response")
	}

	return result.Snapshots, nil
}

// SnapshotIdRequest holds a snapshot Id for POST endpoints.
type SnapshotIdRequest struct {
	Id int64 `json:"id"` // external key (Riseup Asia snapshot API)
}

// GetSnapshot returns details for a specific snapshot (ID in JSON body).
func (c *Client) GetSnapshot(snapshotId int64) (*SnapshotRecord, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsInfo)
	reqBody := SnapshotIdRequest{Id: snapshotId}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshot")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var snapshot SnapshotRecord
	if err := json.NewDecoder(resp.Body).Decode(&snapshot); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode snapshot response")
	}

	return &snapshot, nil
}

// SnapshotCreateOptions holds options for creating a snapshot.
type SnapshotCreateOptions struct {
	Scope  string   `json:"scope,omitempty"`  // external key (Riseup Asia snapshot API)
	Tables []string `json:"tables,omitempty"` // external key
	Type   string   `json:"type,omitempty"`   // external key
}

// SnapshotCreateResult holds the result of a create snapshot request.
type SnapshotCreateResult struct {
	Success    bool   `json:"success"`              // external key (Riseup Asia snapshot API)
	SnapshotId int64  `json:"snapshotId,omitempty"` // external key
	Message    string `json:"message,omitempty"`    // external key
	Status     string `json:"status,omitempty"`     // external key
}

// CreateSnapshot triggers a new snapshot on the remote site.
func (c *Client) CreateSnapshot(opts SnapshotCreateOptions) (*SnapshotCreateResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsSchedule)
	resp, err := c.request("POST", endpoint, opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create snapshot")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != HttpStatusOk.Int() && resp.StatusCode != HttpStatusCreated.Int() {
		return nil, &APIError{
			Operation:    "create snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotCreateResult
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode create snapshot response")
	}

	return &result, nil
}

// DeleteSnapshot removes a snapshot from the remote site (ID in JSON body).
func (c *Client) DeleteSnapshot(snapshotId int64) error {
	endpoint := snapshotEndpoint(ep.SnapshotsDelete)
	reqBody := SnapshotIdRequest{Id: snapshotId}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to delete snapshot")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() && resp.StatusCode != HttpStatusNoContent.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "delete snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	return nil
}

// SnapshotRestoreOptions holds options for restoring a snapshot.
type SnapshotRestoreOptions struct {
	Id      int64 `json:"id"`      // external key (Riseup Asia snapshot API)
	Confirm bool  `json:"confirm"` // external key
}

// SnapshotRestoreResult holds the result of a restore request.
type SnapshotRestoreResult struct {
	Success bool   `json:"success"`           // external key (Riseup Asia snapshot API)
	Message string `json:"message,omitempty"` // external key
	Status  string `json:"status,omitempty"`  // external key
}

// RestoreSnapshot triggers a restore from a snapshot on the remote site (ID in JSON body).
func (c *Client) RestoreSnapshot(snapshotId int64) (*SnapshotRestoreResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsRestore)
	reqBody := SnapshotRestoreOptions{
		Id:      snapshotId,
		Confirm: true,
	}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to restore snapshot")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != HttpStatusOk.Int() {
		return nil, &APIError{
			Operation:    "restore snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotRestoreResult
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode restore response")
	}

	return &result, nil
}

// GetSnapshotSettings fetches snapshot settings from the remote site.
func (c *Client) GetSnapshotSettings() (*SnapshotSettings, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsSettings)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshot settings")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshot settings",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var settings SnapshotSettings
	if err := json.NewDecoder(resp.Body).Decode(&settings); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode snapshot settings")
	}

	return &settings, nil
}

// UpdateSnapshotSettings updates snapshot settings on the remote site.
func (c *Client) UpdateSnapshotSettings(settings SnapshotSettings) (*SnapshotSettings, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsSettings)
	resp, err := c.request("POST", endpoint, settings)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to update snapshot settings")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "update snapshot settings",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotSettings
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode updated settings")
	}

	return &result, nil
}

// ExportSnapshot returns the raw HTTP response for a snapshot export (ZIP download).
// The caller is responsible for closing the response body.
func (c *Client) ExportSnapshot(snapshotId int64) (*http.Response, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsExport)
	reqBody := SnapshotIdRequest{Id: snapshotId}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to export snapshot")
	}

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		resp.Body.Close()
		return nil, &APIError{
			Operation:    "export snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	return resp, nil
}

// SnapshotDownloadResult holds the result of a snapshot download request.
type SnapshotDownloadResult struct {
	Success          bool   `json:"success"`                    // external key (Riseup Asia snapshot API)
	Url              string `json:"url"`                        // external key
	Filename         string `json:"filename"`                   // external key
	Size             int64  `json:"size"`                       // external key
	Cached           bool   `json:"cached"`                     // external key
	IncludedIDs      []int  `json:"includedIds,omitempty"`      // external key
	IncrementalCount int    `json:"incrementalCount,omitempty"` // external key
}

// DownloadSnapshotZip requests a cached ZIP build/download for a snapshot via POST /snapshots/download.
func (c *Client) DownloadSnapshotZip(snapshotId int64) (*SnapshotDownloadResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsDownload)
	reqBody := SnapshotIdRequest{Id: snapshotId}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to request snapshot download")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "download snapshot zip",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result SnapshotDownloadResult
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode download response")
	}
	return &result, nil
}

// StreamSnapshotZip downloads the actual ZIP file from the WordPress download-file endpoint.
// Returns the raw HTTP response; caller must close the body.
func (c *Client) StreamSnapshotZip(downloadURL string) (*http.Response, error) {
	resp, err := c.rawGet(downloadURL)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to stream snapshot ZIP")
	}
	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		resp.Body.Close()
		return nil, &APIError{
			Operation:    "stream snapshot zip",
			Method:       "GET",
			Endpoint:     downloadURL,
			Url:          downloadURL,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}
	return resp, nil
}

// GetSnapshotProviders returns available snapshot providers on the remote site.
func (c *Client) GetSnapshotProviders() ([]SnapshotProvider, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsProviders)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshot providers")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshot providers",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var providers []SnapshotProvider
	if err := json.NewDecoder(resp.Body).Decode(&providers); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode providers response")
	}

	return providers, nil
}

// GetAvailableTables returns the list of database tables available for snapshotting.
func (c *Client) GetAvailableTables() ([]AvailableTable, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsTables)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch available tables")
	}
	defer resp.Body.Close()

	if resp.StatusCode != HttpStatusOk.Int() {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get available tables",
			Method:       "GET",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	bodyBytes, _ := io.ReadAll(resp.Body)

	// Try {success: true, tables: [...]} wrapper
	var wrapper struct {
		Tables []AvailableTable `json:"tables"` // external key
	}
	if err := json.Unmarshal(bodyBytes, &wrapper); err == nil && len(wrapper.Tables) > 0 {
		return wrapper.Tables, nil
	}

	// Try plain array
	var tables []AvailableTable
	if err := json.Unmarshal(bodyBytes, &tables); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode tables response")
	}
	return tables, nil
}

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
// The zipPath is the local file path to the ZIP archive.
func (c *Client) ImportSnapshot(zipPath string) (*SnapshotImportResult, error) {
	endpoint := snapshotEndpoint(ep.SnapshotsImport)

	// Open the file
	file, err := os.Open(zipPath)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to open import ZIP file")
	}
	defer file.Close()

	// Create multipart form
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
	DryRun bool `json:"dry_run,omitempty"`
}

// SnapshotCleanupResult holds the result of a cleanup operation.
type SnapshotCleanupResult struct {
	Success        bool `json:"success"`
	OrphansCleaned int  `json:"orphans_cleaned,omitempty"`
	StuckCleaned   int  `json:"stuck_cleaned,omitempty"`
	AgedCleaned    int  `json:"aged_cleaned,omitempty"`
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
