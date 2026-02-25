// Package wordpress provides snapshot management via the Riseup Asia Uploader REST API.
// All endpoints use fixed paths with IDs passed in JSON request bodies.
package wordpress

import (
	"encoding/json"
	"io"
	"net/http"

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

// SnapshotIdRequest holds a snapshot Id for POST endpoints.
type SnapshotIdRequest struct {
	Id int64 `json:"id"` // external key (Riseup Asia snapshot API)
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
