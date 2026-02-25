package wordpress

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"

	action "wp-plugin-publish/internal/enums/action"
	contenttype "wp-plugin-publish/internal/enums/content_type"
	ep "wp-plugin-publish/internal/enums/endpoint"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	"wp-plugin-publish/pkg/apperror"
)

// ReplaceFileViaUploader replaces a single file in a plugin via the RiseupAsia Uploader.
func (c *Client) ReplaceFileViaUploader(slug, relPath string, content []byte, isBase64 bool) error {
	namespace := c.resolveNamespace()

	endpoint := "/" + namespace + ep.Files.String()

	// Always use base64 encoding for RiseupAsia Uploader
	contentStr := base64.StdEncoding.EncodeToString(content)

	body := map[string]string{
		"plugin":  slug,
		"path":    relPath,
		"content": contentStr,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "marshal replace file body")
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "create replace file request").
			WithURL(url)
	}

	c.setStandardHeaders(req, contenttype.JSON.String())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "replace file request failed").
			WithPath(relPath)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		respBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "replace file via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}

// DeleteFileViaUploader deletes a single file from a plugin via the Riseup Asia Uploader.
func (c *Client) DeleteFileViaUploader(slug, relPath string) error {
	namespace := c.resolveNamespace()

	endpoint := "/" + namespace + ep.Files.String()

	body := map[string]string{"plugin": slug, "path": relPath, "action": "delete"}
	jsonBody, _ := json.Marshal(body)

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return apperror.Wrap(err, apperror.ErrInternal, "create delete file request").
			WithURL(url)
	}

	c.setStandardHeaders(req, contenttype.JSON.String())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return apperror.Wrap(err, apperror.ErrWPConnection, "delete file request failed").
			WithPath(relPath)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		respBytes, _ := io.ReadAll(resp.Body)
		return &APIError{
			Operation:    "delete file via Riseup Asia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(respBytes), 8192),
			PluginSlugIn: slug,
		}
	}

	return nil
}

// =============================================================================
// DELTA SYNC TYPES AND METHODS
// =============================================================================

// SyncFile represents a single file in a sync request.
type SyncFile struct {
	Path    string `json:"path"`              // external key (Riseup Asia Uploader API)
	Content string `json:"content,omitempty"` // external key (base64 encoded)
	Action  string `json:"action"`            // external key ("replace" or "delete")
}

// SyncFileResult represents the result of syncing a single file.
type SyncFileResult struct {
	Path   string `json:"path"`             // external key (Riseup Asia Uploader API)
	Action string `json:"action"`           // external key
	Status string `json:"status"`           // external key
	Reason string `json:"reason,omitempty"` // external key
}

// SyncResult represents the result of a delta sync operation.
type SyncResult struct {
	Success      bool             `json:"success"`       // external key (Riseup Asia Uploader API)
	FilesUpdated int              `json:"files_updated"` // external key
	FilesDeleted int              `json:"files_deleted"` // external key
	FilesIgnored int              `json:"files_ignored"` // external key
	IgnoredFiles []string         `json:"ignored_files"` // external key
	Results      []SyncFileResult `json:"results"`       // external key
}

// SyncPluginFilesViaUploader performs a delta sync of multiple files to a plugin.
func (c *Client) SyncPluginFilesViaUploader(slug string, files []SyncFile) (*SyncResult, error) {
	namespace := c.resolveNamespace()

	c.progress(action.Sync.String(), stagestatus.Running.String(), fmt.Sprintf("Syncing %d files to %s...", len(files), slug), ProgressDetails{
		"slug":      slug,
		"fileCount": len(files),
		"namespace": namespace,
	})

	endpoint := "/" + namespace + ep.Sync.String()

	body := ProgressDetails{
		"plugin": slug,
		"files":  files,
	}

	jsonBody, err := json.Marshal(body)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "marshal sync request")
	}

	url := fmt.Sprintf("%s/wp-json%s", c.baseURL, endpoint)
	req, err := http.NewRequest("POST", url, bytes.NewReader(jsonBody))
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "create sync request").
			WithURL(url)
	}

	c.setStandardHeaders(req, contenttype.JSON.String())

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, apperror.Wrap(err, apperror.ErrWPConnection, "sync request failed").
			WithSlug(slug)
	}
	defer resp.Body.Close()

	respBytes, _ := io.ReadAll(resp.Body)
	respBody := string(respBytes)

	c.progress(action.Sync.String(), stagestatus.Running.String(), fmt.Sprintf("Sync response: %d", resp.StatusCode), ProgressDetails{
		"status": resp.StatusCode,
		"body":   truncateBody(respBody, 500),
	})

	if resp.StatusCode != http.StatusOK {
		return nil, &APIError{
			Operation:    "sync plugin files via Riseup Asia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          url,
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(respBody, 8192),
			PluginSlugIn: slug,
		}
	}

	var result SyncResult
	if err := json.Unmarshal(respBytes, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode sync result")
	}

	return &result, nil
}
