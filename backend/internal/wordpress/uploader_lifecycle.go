package wordpress

import (
	"encoding/json"
	"io"
	"net/http"
	"path/filepath"
	"strings"

	ep "wp-plugin-publish/internal/enums/endpoint"
	"wp-plugin-publish/pkg/apperror"
)

// normalizePluginSlug extracts the folder-level slug from a full WordPress plugin
// identifier like "broken-link-checker/broken-link-checker.php".
func normalizePluginSlug(slug string) string {
	if strings.Contains(slug, "/") {
		dir := filepath.Dir(slug)
		if dir != "." && dir != "" {
			return dir
		}
	}
	slug = strings.TrimSuffix(slug, ".php")
	return slug
}

// pluginLifecycleInput holds the parameters for a plugin lifecycle action.
type pluginLifecycleInput struct {
	Slug          string
	Endpoint      ep.Variant
	OperationName string
	ErrorCode     string
}

// pluginLifecycleAction is the shared implementation for Enable, Disable, and Delete.
func (c *Client) pluginLifecycleAction(input pluginLifecycleInput) error {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(input.Slug)

	endpoint := "/" + namespace + input.Endpoint.String()
	reqBody := map[string]string{"plugin": normalizedSlug}
	reqBodyJSON, _ := json.Marshal(reqBody)
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return apperror.Wrap(err, input.ErrorCode, input.OperationName+" request failed").
			WithSlug(normalizedSlug)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return &APIError{
			Operation:    input.OperationName + " via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			RequestBody:  string(reqBodyJSON),
			ResponseBody: truncateBody(string(bodyBytes), 8192),
			PluginSlugIn: normalizedSlug,
		}
	}

	return nil
}

// CheckPluginExistsViaUploader checks if a plugin slug is installed on the remote site.
func (c *Client) CheckPluginExistsViaUploader(slug string) (bool, string, string, error) {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + ep.PluginExists.String()
	reqBody := map[string]string{"plugin": normalizedSlug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return false, "", "", apperror.Wrap(err, apperror.ErrWPConnection, "check plugin exists request failed").
			WithSlug(normalizedSlug)
	}
	defer resp.Body.Close()

	bodyBytes, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != http.StatusOK {
		return false, "", "", &APIError{
			Operation:    "check plugin exists via RiseupAsia Uploader",
			Method:       "POST",
			Endpoint:     endpoint,
			Url:          c.fullURL(endpoint),
			StatusCode:   resp.StatusCode,
			ResponseBody: truncateBody(string(bodyBytes), 4096),
			PluginSlugIn: normalizedSlug,
		}
	}

	// Try envelope format
	type existsResult struct {
		PluginSlug string `json:"pluginSlug"` // external key (Riseup Asia Uploader API)
		Exists     bool   `json:"exists"`     // external key
		Status     string `json:"status"`     // external key
		PluginFile string `json:"pluginFile"` // external key
	}
	if results, ok := UnwrapResults[existsResult](bodyBytes); ok && len(results) > 0 {
		return results[0].Exists, results[0].Status, results[0].PluginFile, nil
	}

	// Legacy fallback
	var legacy struct {
		Exists     bool   `json:"exists"`     // external key (Riseup Asia Uploader API)
		Status     string `json:"status"`     // external key
		PluginFile string `json:"pluginFile"` // external key
	}
	if err := json.Unmarshal(bodyBytes, &legacy); err != nil {
		return false, "", "", apperror.Wrap(err, apperror.ErrInternal, "decode plugin exists response")
	}
	return legacy.Exists, legacy.Status, legacy.PluginFile, nil
}

// EnablePluginViaUploader enables (activates) a plugin via the RiseupAsia Uploader.
func (c *Client) EnablePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Enable,
		OperationName: "enable plugin",
		ErrorCode:     apperror.ErrWPPluginActivate,
	})
}

// DisablePluginViaUploader disables (deactivates) a plugin via the RiseupAsia Uploader.
func (c *Client) DisablePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Disable,
		OperationName: "disable plugin",
		ErrorCode:     apperror.ErrWPPluginActivate,
	})
}

// DeletePluginViaUploader deletes a plugin via the RiseupAsia Uploader.
func (c *Client) DeletePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Delete,
		OperationName: "delete plugin",
		ErrorCode:     apperror.ErrWPConnection,
	})
}

// ListPluginsViaUploader lists all plugins via the RiseupAsia Uploader.
func (c *Client) ListPluginsViaUploader() ([]UploaderPluginInfo, error) {
	namespace := c.resolveNamespace()

	endpoint := fmt.Sprintf("/%s%s", namespace, ep.Plugins)
	resp, err := c.request("GET", endpoint, nil)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, apperror.New(apperror.ErrWPPluginList, "list plugins failed").
			WithStatusCode(resp.StatusCode)
	}

	var response struct {
		Success bool                 `json:"success"` // external key (Riseup Asia Uploader API)
		Count   int                  `json:"count"`   // external key
		Plugins []UploaderPluginInfo `json:"plugins"` // external key
	}

	respBody, _ := io.ReadAll(resp.Body)

	// Try envelope format first
	if plugins, ok := UnwrapResults[UploaderPluginInfo](respBody); ok {
		return plugins, nil
	}

	// Fall back to legacy flat format
	if err := json.Unmarshal(respBody, &response); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode plugins response")
	}

	return response.Plugins, nil
}

// ListPluginFilesViaUploader lists files in a plugin via the RiseupAsia Uploader.
func (c *Client) ListPluginFilesViaUploader(slug string) ([]UploaderFileInfo, error) {
	namespace := c.resolveNamespace()

	endpoint := "/" + namespace + ep.Files.String()
	reqBody := map[string]string{"plugin": slug}
	resp, err := c.request("POST", endpoint, reqBody)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, apperror.New(apperror.ErrWPPluginGet, "list plugin files failed").
			WithStatusCode(resp.StatusCode).
			WithSlug(slug)
	}

	var response struct {
		Success bool               `json:"success"` // external key (Riseup Asia Uploader API)
		Slug    string             `json:"slug"`    // external key
		Count   int                `json:"count"`   // external key
		Files   []UploaderFileInfo `json:"files"`   // external key
	}
	if err := json.NewDecoder(resp.Body).Decode(&response); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode files response")
	}

	return response.Files, nil
}
