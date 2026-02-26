package wordpress

import (
	"fmt"
	"io"
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

	_, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body: PluginSlugRequest{Plugin: normalizedSlug}, Operation: input.OperationName + " via RiseupAsia Uploader",
		PluginSlug: normalizedSlug, ErrorCode: input.ErrorCode,
	})
	return err
}

// CheckPluginExistsViaUploader checks if a plugin slug is installed on the remote site.
func (c *Client) CheckPluginExistsViaUploader(slug string) (bool, string, string, error) {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + ep.PluginExists.String()

	data, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body: PluginSlugRequest{Plugin: normalizedSlug}, Operation: "check plugin exists via RiseupAsia Uploader",
		PluginSlug: normalizedSlug, ErrorCode: apperror.ErrWPConnection,
	})
	if err != nil {
		return false, "", "", err
	}

	return parsePluginExistsResponse(data)
}

// pluginExistsResult is the typed struct for the plugin-exists envelope response.
type pluginExistsResult struct {
	PluginSlug string `json:"pluginSlug"` // external key (Riseup Asia Uploader API)
	Exists     bool   `json:"exists"`     // external key
	Status     string `json:"status"`     // external key
	PluginFile string `json:"pluginFile"` // external key
}

// parsePluginExistsResponse tries envelope format, then legacy flat format.
func parsePluginExistsResponse(data []byte) (bool, string, string, error) {
	if results, ok := UnwrapResults[pluginExistsResult](data); ok && len(results) > 0 {
		return results[0].Exists, results[0].Status, results[0].PluginFile, nil
	}

	return parsePluginExistsLegacy(data)
}

// parsePluginExistsLegacy decodes the legacy flat format for plugin-exists.
func parsePluginExistsLegacy(data []byte) (bool, string, string, error) {
	var legacy pluginExistsResult
	if err := json.Unmarshal(data, &legacy); err != nil {
		return false, "", "", apperror.Wrap(err, apperror.ErrInternal, "decode plugin exists response")
	}

	return legacy.Exists, legacy.Status, legacy.PluginFile, nil
}

// EnablePluginViaUploader enables (activates) a plugin via the RiseupAsia Uploader.
func (c *Client) EnablePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug: slug, Endpoint: ep.Enable,
		OperationName: "enable plugin", ErrorCode: apperror.ErrWPPluginActivate,
	})
}

// DisablePluginViaUploader disables (deactivates) a plugin via the RiseupAsia Uploader.
func (c *Client) DisablePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug: slug, Endpoint: ep.Disable,
		OperationName: "disable plugin", ErrorCode: apperror.ErrWPPluginActivate,
	})
}

// DeletePluginViaUploader deletes a plugin via the RiseupAsia Uploader.
func (c *Client) DeletePluginViaUploader(slug string) error {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug: slug, Endpoint: ep.Delete,
		OperationName: "delete plugin", ErrorCode: apperror.ErrWPConnection,
	})
}

// ListPluginsViaUploader lists all plugins via the RiseupAsia Uploader.
func (c *Client) ListPluginsViaUploader() ([]UploaderPluginInfo, error) {
	namespace := c.resolveNamespace()
	endpoint := fmt.Sprintf("/%s%s", namespace, ep.Plugins)

	data, err := c.doAPICallRaw(apiCallInput{
		Method: "GET", Endpoint: endpoint, Operation: "list plugins",
		ErrorCode: apperror.ErrWPPluginList,
	})
	if err != nil {
		return nil, err
	}

	return parsePluginListResponse(data)
}

// parsePluginListResponse tries envelope format, then legacy flat format.
func parsePluginListResponse(data []byte) ([]UploaderPluginInfo, error) {
	if plugins, ok := UnwrapResults[UploaderPluginInfo](data); ok {
		return plugins, nil
	}

	var response struct {
		Success bool                 `json:"success"` // external key (Riseup Asia Uploader API)
		Count   int                  `json:"count"`   // external key
		Plugins []UploaderPluginInfo `json:"plugins"` // external key
	}
	if err := json.Unmarshal(data, &response); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode plugins response")
	}

	return response.Plugins, nil
}

// listFilesResult is the response shape from the files list endpoint.
type listFilesResult struct {
	Success bool               `json:"success"` // external key (Riseup Asia Uploader API)
	Slug    string             `json:"slug"`    // external key
	Count   int                `json:"count"`   // external key
	Files   []UploaderFileInfo `json:"files"`   // external key
}

// ListPluginFilesViaUploader lists files in a plugin via the RiseupAsia Uploader.
func (c *Client) ListPluginFilesViaUploader(slug string) ([]UploaderFileInfo, error) {
	endpoint := "/" + c.resolveNamespace() + ep.Files.String()

	data, err := c.doAPICallRaw(apiCallInput{
		Method: "POST", Endpoint: endpoint,
		Body: PluginSlugRequest{Plugin: slug}, Operation: "list plugin files",
		PluginSlug: slug, ErrorCode: apperror.ErrWPPluginGet,
	})
	if err != nil {
		return nil, err
	}

	result, err := decodeAPIResponse[listFilesResult](data, "plugin files list")
	if err != nil {
		return nil, err
	}
	return result.Files, nil
}
