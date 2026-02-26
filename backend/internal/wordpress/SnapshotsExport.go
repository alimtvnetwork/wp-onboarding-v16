package wordpress

import (
	"encoding/json"
	"io"
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/internal/enums/http_method"
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
func (c *Client) ExportSnapshot(snapshotId int64) (*http.Response, error) {
	return c.doAPICallStream(apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsExport),
		Body:      SnapshotIdRequest{Id: snapshotId},
		Operation: "export snapshot",
	})
}

// DownloadSnapshotZip requests a cached ZIP build/download for a snapshot.
func (c *Client) DownloadSnapshotZip(snapshotId int64) (*SnapshotDownloadResult, error) {
	return doAPICall[SnapshotDownloadResult](c, apiCallInput{
		Method:    httpmethod.Post,
		Endpoint:  snapshotEndpoint(ep.SnapshotsDownload),
		Body:      SnapshotIdRequest{Id: snapshotId},
		Operation: "download snapshot zip",
	})
}

// StreamSnapshotZip downloads the actual ZIP file from the WordPress download-file endpoint.
// Returns the raw HTTP response; caller must close the body.
func (c *Client) StreamSnapshotZip(downloadURL string) (*http.Response, error) {
	resp, err := c.rawGet(downloadURL)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to stream snapshot ZIP")
	}

	if resp.StatusCode != HttpStatusOk.Int() {
		return nil, buildStreamZipError(resp, downloadURL)
	}

	return resp, nil
}

// buildStreamZipError reads the response body and constructs an APIError for a failed stream.
func buildStreamZipError(resp *http.Response, downloadURL string) *APIError {
	bodyBytes, _ := io.ReadAll(resp.Body)
	resp.Body.Close()

	return &APIError{
		Operation:    "stream snapshot zip",
		Method:       httpmethod.Get.Value(),
		Endpoint:     downloadURL,
		Url:          downloadURL,
		StatusCode:   resp.StatusCode,
		ResponseBody: truncateBody(string(bodyBytes), 8192),
	}
}

// GetSnapshotProviders returns available snapshot providers on the remote site.
func (c *Client) GetSnapshotProviders() ([]SnapshotProvider, error) {
	data, err := c.doAPICallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  snapshotEndpoint(ep.SnapshotsProviders),
		Operation: "get snapshot providers",
	})
	if err != nil {
		return nil, err
	}

	var providers []SnapshotProvider
	if err := json.Unmarshal(data, &providers); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode providers response")
	}
	return providers, nil
}

// AvailableTable represents a database table available for snapshotting.
type AvailableTable struct {
	Name   string `json:"name"`   // external key (Riseup Asia snapshot API)
	Rows   int    `json:"rows"`   // external key
	Size   int64  `json:"size"`   // external key
	IsCore bool   `json:"isCore"` // external key
}

// GetAvailableTables returns the list of database tables available for snapshotting.
func (c *Client) GetAvailableTables() ([]AvailableTable, error) {
	data, err := c.doAPICallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  snapshotEndpoint(ep.SnapshotsTables),
		Operation: "get available tables",
	})
	if err != nil {
		return nil, err
	}

	return parseAvailableTablesResponse(data)
}

// parseAvailableTablesResponse tries wrapped format, then plain array.
func parseAvailableTablesResponse(data []byte) ([]AvailableTable, error) {
	var wrapper struct {
		Tables []AvailableTable `json:"tables"` // external key
	}
	if err := json.Unmarshal(data, &wrapper); err == nil && len(wrapper.Tables) > 0 {
		return wrapper.Tables, nil
	}

	var tables []AvailableTable
	if err := json.Unmarshal(data, &tables); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode tables response")
	}

	return tables, nil
}
