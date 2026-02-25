package wordpress

import (
	"encoding/json"
	"io"
	"net/http"

	ep "wp-plugin-publish/internal/enums/endpoint"
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

// DownloadSnapshotZip requests a cached ZIP build/download for a snapshot.
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

// AvailableTable represents a database table available for snapshotting.
type AvailableTable struct {
	Name   string `json:"name"`   // external key (Riseup Asia snapshot API)
	Rows   int    `json:"rows"`   // external key
	Size   int64  `json:"size"`   // external key
	IsCore bool   `json:"isCore"` // external key
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
