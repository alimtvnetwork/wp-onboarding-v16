// Package wordpress provides snapshot management via the Riseup Asia Uploader REST API.
// All endpoints use fixed paths with IDs passed in JSON request bodies.
package wordpress

import (
	"encoding/json"
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
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
func (c *Client) GetSnapshots() apperror.Result[[]SnapshotRecord] {
	endpoint := snapshotEndpoint(ep.SnapshotsList)
	rawResult := c.doApiCallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "get snapshots",
	})
	if rawResult.HasError() {
		return apperror.Fail[[]SnapshotRecord](rawResult.AppError())
	}

	return parseSnapshotsResponse(c, endpoint, rawResult.Value())
}

// parseSnapshotsResponse tries wrapped format first, then array fallback.
func parseSnapshotsResponse(c *Client, endpoint string, data []byte) apperror.Result[[]SnapshotRecord] {
	var result struct {
		Snapshots []SnapshotRecord `json:"snapshots"` // external key
	}
	unmarshalErr := json.Unmarshal(data, &result)

	if unmarshalErr == nil {
		return apperror.Ok(result.Snapshots)
	}

	return trySnapshotArrayFallback(c, endpoint)
}

// trySnapshotArrayFallback re-fetches and tries to decode as a plain array.
func trySnapshotArrayFallback(c *Client, endpoint string) apperror.Result[[]SnapshotRecord] {
	fallbackResult := c.doApiCallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "get snapshots (array fallback)",
	})
	if fallbackResult.HasError() {
		return apperror.Fail[[]SnapshotRecord](
			apperror.Wrap(fallbackResult.AppError(), apperror.ErrInternal, "failed to decode snapshots response"))
	}

	var snapshots []SnapshotRecord
	unmarshalErr := json.Unmarshal(fallbackResult.Value(), &snapshots)

	if unmarshalErr != nil {
		return apperror.FailWrap[[]SnapshotRecord](unmarshalErr, apperror.ErrInternal, "failed to decode snapshots response")
	}

	return apperror.Ok(snapshots)
}

// GetSnapshot returns details for a specific snapshot (ID in JSON body).
func (c *Client) GetSnapshot(snapshotId int64) apperror.Result[SnapshotRecord] {
	return doApiCall[SnapshotRecord](c, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsInfo),
		Body:      SnapshotIdRequest{Id: snapshotId},
		Operation: "get snapshot",
	})
}

// CreateSnapshot triggers a new snapshot on the remote site.
func (c *Client) CreateSnapshot(opts SnapshotCreateOptions) apperror.Result[SnapshotCreateResult] {
	return doApiCall[SnapshotCreateResult](c, apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   snapshotEndpoint(ep.SnapshotsSchedule),
		Body:       opts,
		Operation:  "create snapshot",
		OkStatuses: []int{http.StatusOK, http.StatusCreated},
	})
}

// DeleteSnapshot removes a snapshot from the remote site (ID in JSON body).
func (c *Client) DeleteSnapshot(snapshotId int64) *apperror.AppError {
	rawResult := c.doApiCallRaw(apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   snapshotEndpoint(ep.SnapshotsDelete),
		Body:       SnapshotIdRequest{Id: snapshotId},
		Operation:  "delete snapshot",
		OkStatuses: []int{http.StatusOK, http.StatusNoContent},
	})

	return rawResult.AppError()
}

// RestoreSnapshot triggers a restore from a snapshot on the remote site (ID in JSON body).
func (c *Client) RestoreSnapshot(snapshotId int64) apperror.Result[SnapshotRestoreResult] {
	return doApiCall[SnapshotRestoreResult](c, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsRestore),
		Body:      SnapshotRestoreOptions{Id: snapshotId, Confirm: true},
		Operation: "restore snapshot",
	})
}

// GetSnapshotSettings fetches snapshot settings from the remote site.
func (c *Client) GetSnapshotSettings() apperror.Result[SnapshotSettings] {
	return doApiCall[SnapshotSettings](c, apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  snapshotEndpoint(ep.SnapshotsSettings),
		Operation: "get snapshot settings",
	})
}

// UpdateSnapshotSettings updates snapshot settings on the remote site.
func (c *Client) UpdateSnapshotSettings(settings SnapshotSettings) apperror.Result[SnapshotSettings] {
	return doApiCall[SnapshotSettings](c, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsSettings),
		Body:      settings,
		Operation: "update snapshot settings",
	})
}
