// Package wordpress provides snapshot management via the Riseup Asia Uploader REST API.
package wordpress

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"

	"wp-plugin-publish/pkg/apperror"
)

// SnapshotRecord represents a database snapshot record from the WordPress plugin.
type SnapshotRecord struct {
	ID        int64  `json:"id"`
	Sequence  int    `json:"sequence"`
	Filename  string `json:"filename"`
	Scope     string `json:"scope"`
	Provider  string `json:"provider"`
	Status    string `json:"status"`
	FileSize  int64  `json:"file_size"`
	TotalRows int    `json:"total_rows"`
	Tables    string `json:"tables"`
	CreatedAt string `json:"created_at"`
	Error     string `json:"error,omitempty"`
}

// SnapshotSettings represents snapshot configuration on the WordPress site.
type SnapshotSettings struct {
	Provider      string `json:"provider"`
	Schedule      string `json:"schedule"`
	ScheduleTime  string `json:"schedule_time,omitempty"`
	ScheduleDay   string `json:"schedule_day,omitempty"`
	Scope         string `json:"scope"`
	RetentionType string `json:"retention_type"`
	RetentionDays int    `json:"retention_days,omitempty"`
	RetentionMax  int    `json:"retention_max,omitempty"`
	PreRestore    bool   `json:"pre_restore_backup"`
	BatchSize     int    `json:"batch_size,omitempty"`
}

// SnapshotProvider represents an available snapshot provider.
type SnapshotProvider struct {
	ID        string `json:"id"`
	Name      string `json:"name"`
	Available bool   `json:"available"`
	Priority  int    `json:"priority"`
}

// SnapshotStorageStats represents storage statistics.
type SnapshotStorageStats struct {
	TotalSnapshots int    `json:"total_snapshots"`
	TotalSize      int64  `json:"total_size"`
	TotalSizeHuman string `json:"total_size_human"`
	DiskFreeSpace  int64  `json:"disk_free_space"`
	OldestAt       string `json:"oldest_at,omitempty"`
	NewestAt       string `json:"newest_at,omitempty"`
}

// AvailableTable represents a database table available for snapshotting.
type AvailableTable struct {
	Name   string `json:"name"`
	Rows   int    `json:"rows"`
	Size   int64  `json:"size"`
	IsCore bool   `json:"is_core"`
}

// riseupSnapshotEndpoint builds the full endpoint path for snapshot operations.
func riseupSnapshotEndpoint(path string) string {
	return fmt.Sprintf("/%s/snapshots%s", RiseupAsiaNamespace, path)
}

// GetSnapshots lists all snapshots on the remote site.
func (c *Client) GetSnapshots() ([]SnapshotRecord, error) {
	endpoint := riseupSnapshotEndpoint("")
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshots")
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshots",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result struct {
		Snapshots []SnapshotRecord `json:"snapshots"`
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

// GetSnapshot returns details for a specific snapshot.
func (c *Client) GetSnapshot(snapshotID int64) (*SnapshotRecord, error) {
	endpoint := riseupSnapshotEndpoint(fmt.Sprintf("/%d", snapshotID))
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshot")
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshot",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
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
func (c *Client) CreateSnapshot(opts map[string]interface{}) (map[string]interface{}, error) {
	endpoint := riseupSnapshotEndpoint("/schedule")
	resp, err := c.request("POST", endpoint, opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to create snapshot")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != 200 && resp.StatusCode != 201 {
		return nil, &APIError{
			Operation:    "create snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result map[string]interface{}
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode create snapshot response")
	}

	return result, nil
}

// DeleteSnapshot removes a snapshot from the remote site.
func (c *Client) DeleteSnapshot(snapshotID int64) error {
	endpoint := riseupSnapshotEndpoint(fmt.Sprintf("/%d", snapshotID))
	resp, err := c.request("DELETE", endpoint, nil)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "failed to delete snapshot")
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 && resp.StatusCode != 204 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "delete snapshot",
			Method:       "DELETE",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	return nil
}

// RestoreSnapshot triggers a restore from a snapshot on the remote site.
func (c *Client) RestoreSnapshot(snapshotID int64, opts map[string]interface{}) (map[string]interface{}, error) {
	endpoint := riseupSnapshotEndpoint(fmt.Sprintf("/%d/restore", snapshotID))
	if opts == nil {
		opts = map[string]interface{}{"confirm": true}
	}
	resp, err := c.request("POST", endpoint, opts)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to restore snapshot")
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != 200 {
		return nil, &APIError{
			Operation:    "restore snapshot",
			Method:       "POST",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	var result map[string]interface{}
	if err := json.Unmarshal(bodyBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to decode restore response")
	}

	return result, nil
}

// GetSnapshotSettings fetches snapshot settings from the remote site.
func (c *Client) GetSnapshotSettings() (*SnapshotSettings, error) {
	endpoint := riseupSnapshotEndpoint("/settings")
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshot settings")
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshot settings",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
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
func (c *Client) UpdateSnapshotSettings(settings map[string]interface{}) (*SnapshotSettings, error) {
	endpoint := riseupSnapshotEndpoint("/settings")
	resp, err := c.request("PUT", endpoint, settings)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to update snapshot settings")
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "update snapshot settings",
			Method:       "PUT",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
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
func (c *Client) ExportSnapshot(snapshotID int64) (*http.Response, error) {
	endpoint := riseupSnapshotEndpoint(fmt.Sprintf("/%d/export", snapshotID))
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to export snapshot")
	}

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		resp.Body.Close()
		return nil, &APIError{
			Operation:    "export snapshot",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	return resp, nil
}

// GetSnapshotProviders returns available snapshot providers on the remote site.
func (c *Client) GetSnapshotProviders() ([]SnapshotProvider, error) {
	endpoint := riseupSnapshotEndpoint("/providers")
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch snapshot providers")
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get snapshot providers",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
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
	endpoint := riseupSnapshotEndpoint("/tables")
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "failed to fetch available tables")
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		bodyBytes, _ := io.ReadAll(resp.Body)
		return nil, &APIError{
			Operation:    "get available tables",
			Method:       "GET",
			Endpoint:     endpoint,
			URL:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 8192),
		}
	}

	bodyBytes, _ := io.ReadAll(resp.Body)

	// Try {success: true, tables: [...]} wrapper
	var wrapper struct {
		Tables []AvailableTable `json:"tables"`
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
