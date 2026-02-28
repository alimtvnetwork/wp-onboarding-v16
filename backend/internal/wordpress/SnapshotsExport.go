package wordpress

import (
	"encoding/json"
	"io"
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	"wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

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

// ExportSnapshot returns the raw HTTP response for a snapshot export (ZIP download).
// The caller is responsible for closing the response body.
func (c *Client) ExportSnapshot(snapshotId int64) apperror.Result[*http.Response] {
	return c.doAPICallStream(apiCallInput{
		Method:    httpmethodtype.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsExport),
		Body:      SnapshotIdRequest{Id: snapshotId},
		Operation: operationtype.ExportSnapshot,
	})
}

// DownloadSnapshotZip requests a cached ZIP build/download for a snapshot.
func (c *Client) DownloadSnapshotZip(snapshotId int64) apperror.Result[SnapshotDownloadResult] {
	return doAPICall[SnapshotDownloadResult](c, apiCallInput{
		Method:    httpmethodtype.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsDownload),
		Body:      SnapshotIdRequest{Id: snapshotId},
		Operation: operationtype.DownloadSnapshotZip,
	})
}

// StreamSnapshotZip downloads the actual ZIP file from the WordPress download-file endpoint.
// Returns the raw HTTP response; caller must close the body.
func (c *Client) StreamSnapshotZip(downloadURL string) apperror.Result[*http.Response] {
	resp, appErr := c.rawGet(downloadURL)
	if appErr != nil {

		return apperror.Fail[*http.Response](appErr)
	}

	if resp.StatusCode != HttpStatusOk.Int() {
		return apperror.Fail[*http.Response](buildStreamZipAppError(resp, downloadURL))
	}

	return apperror.Ok(resp)
}

// buildStreamZipAppError reads the response body and constructs an AppError for a failed stream.
func buildStreamZipAppError(resp *http.Response, downloadURL string) *apperror.AppError {
	bodyBytes, _ := io.ReadAll(resp.Body)
	resp.Body.Close()

	apiErr := &APIError{
		Operation:    operationtype.StreamSnapshotZip.Value(),
		Method:       httpmethodtype.Get.Value(),
...
	return apperror.Wrap(apiErr, apperror.ErrWPConnection, operationtype.StreamSnapshotZip.Value())
}

// GetSnapshotProviders returns available snapshot providers on the remote site.
func (c *Client) GetSnapshotProviders() apperror.Result[[]SnapshotProvider] {
	rawResult := c.doAPICallRaw(apiCallInput{
		Method:    httpmethodtype.Get,
		Endpoint:  snapshotEndpoint(ep.SnapshotsProviders),
		Operation: operationtype.GetSnapshotProviders,
	})
	if rawResult.HasError() {
		return apperror.Fail[[]SnapshotProvider](rawResult.AppError())
	}

	var providers []SnapshotProvider
	err := json.Unmarshal(rawResult.Value(), &providers)

	if err != nil {
		return apperror.FailWrap[[]SnapshotProvider](err, apperror.ErrInternal, "failed to decode providers response")
	}

	return apperror.Ok(providers)
}

// AvailableTable represents a database table available for snapshotting.
type AvailableTable struct {
	Name   string `json:"name"`   // external key (Riseup Asia snapshot API)
	Rows   int    `json:"rows"`   // external key
	Size   int64  `json:"size"`   // external key
	IsCore bool   `json:"isCore"` // external key
}

// GetAvailableTables returns the list of database tables available for snapshotting.
func (c *Client) GetAvailableTables() apperror.Result[[]AvailableTable] {
	rawResult := c.doAPICallRaw(apiCallInput{
		Method:    httpmethodtype.Get,
		Endpoint:  snapshotEndpoint(ep.SnapshotsTables),
		Operation: operationtype.GetAvailableTables,
	})
	if rawResult.HasError() {
		return apperror.Fail[[]AvailableTable](rawResult.AppError())
	}

	return parseAvailableTablesResult(rawResult.Value())
}

// parseAvailableTablesResult tries wrapped format, then plain array.
func parseAvailableTablesResult(data []byte) apperror.Result[[]AvailableTable] {
	var wrapper struct {
		Tables []AvailableTable `json:"tables"` // external key
	}
	wrapperErr := json.Unmarshal(data, &wrapper)
	isWrappedFormat := wrapperErr == nil && len(wrapper.Tables) > 0

	if isWrappedFormat {
		return apperror.Ok(wrapper.Tables)
	}

	var tables []AvailableTable
	tablesErr := json.Unmarshal(data, &tables)

	if tablesErr != nil {
		return apperror.FailWrap[[]AvailableTable](tablesErr, apperror.ErrInternal, "failed to decode tables response")
	}

	return apperror.Ok(tables)
}
