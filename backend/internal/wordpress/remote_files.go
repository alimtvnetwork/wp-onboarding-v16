package wordpress

import (
	"context"
	"encoding/json"
	"fmt"
	"time"
)

// RemoteFile represents a file in a remote WordPress plugin
type RemoteFile struct {
	Path       string    `json:"path"`
	Hash       string    `json:"hash"`
	Size       int64     `json:"size"`
	ModifiedAt time.Time `json:"modifiedAt"`
}

// GetPluginFiles retrieves the list of files for a remote plugin
// This requires the plugins-onboard companion plugin to be installed
func (c *Client) GetPluginFiles(ctx context.Context, slug string) ([]RemoteFile, error) {
	resp, err := c.request("GET", "/plugins-onboard/v1/files/"+slug, nil)
	if err != nil {
		return nil, fmt.Errorf("failed to get plugin files: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode == 404 {
		return nil, fmt.Errorf("plugin not found on remote: %s", slug)
	}

	if resp.StatusCode != 200 {
		return nil, fmt.Errorf("failed to get plugin files: status %d", resp.StatusCode)
	}

	var files []RemoteFile
	if err := json.NewDecoder(resp.Body).Decode(&files); err != nil {
		return nil, fmt.Errorf("failed to decode plugin files: %w", err)
	}

	return files, nil
}
