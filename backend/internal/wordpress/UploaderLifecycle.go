package wordpress

import (
	"encoding/json"
	"fmt"
	"path/filepath"
	"strings"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
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
func (c *Client) pluginLifecycleAction(input pluginLifecycleInput) *apperror.AppError {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(input.Slug)
	endpoint := "/" + namespace + input.Endpoint.String()

	callInput := apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: normalizedSlug},
		Operation:  input.OperationName + " via RiseupAsia Uploader",
		PluginSlug: normalizedSlug,
		ErrorCode:  input.ErrorCode,
	}
	_, err := c.doAPICallRaw(callInput)
	return err
}

// PluginExistsResult holds the result of a plugin existence check.
type PluginExistsResult struct {
	Exists     bool
	Status     string
	PluginFile string
}

// CheckPluginExistsViaUploader checks if a plugin slug is installed on the remote site.
func (c *Client) CheckPluginExistsViaUploader(slug string) (*PluginExistsResult, *apperror.AppError) {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + ep.PluginExists.String()

	callInput := apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: normalizedSlug},
		Operation:  "check plugin exists via RiseupAsia Uploader",
		PluginSlug: normalizedSlug,
		ErrorCode:  apperror.ErrWPConnection,
	}
	data, err := c.doAPICallRaw(callInput)
	if err != nil {
		return nil, err
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
func parsePluginExistsResponse(data []byte) (*PluginExistsResult, *apperror.AppError) {
	if results, ok := UnwrapResults[pluginExistsResult](data); ok && len(results) > 0 {
		result := &PluginExistsResult{
			Exists:     results[0].Exists,
			Status:     results[0].Status,
			PluginFile: results[0].PluginFile,
		}
		return result, nil
	}

	return parsePluginExistsLegacy(data)
}

// parsePluginExistsLegacy decodes the legacy flat format for plugin-exists.
func parsePluginExistsLegacy(data []byte) (*PluginExistsResult, *apperror.AppError) {
	var legacy pluginExistsResult
	if err := json.Unmarshal(data, &legacy); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, "decode plugin exists response")
	}

	result := &PluginExistsResult{
		Exists:     legacy.Exists,
		Status:     legacy.Status,
		PluginFile: legacy.PluginFile,
	}
	return result, nil
}

// EnablePluginViaUploader enables (activates) a plugin via the RiseupAsia Uploader.
func (c *Client) EnablePluginViaUploader(slug string) *apperror.AppError {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Enable,
		OperationName: "enable plugin",
		ErrorCode:     apperror.ErrWPPluginActivate,
	})
}

// DisablePluginViaUploader disables (deactivates) a plugin via the RiseupAsia Uploader.
func (c *Client) DisablePluginViaUploader(slug string) *apperror.AppError {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Disable,
		OperationName: "disable plugin",
		ErrorCode:     apperror.ErrWPPluginActivate,
	})
}

// DeletePluginViaUploader deletes a plugin via the RiseupAsia Uploader.
func (c *Client) DeletePluginViaUploader(slug string) *apperror.AppError {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:          slug,
		Endpoint:      ep.Delete,
		OperationName: "delete plugin",
		ErrorCode:     apperror.ErrWPConnection,
	})
}

// ListPluginsViaUploader lists all plugins via the RiseupAsia Uploader.
func (c *Client) ListPluginsViaUploader() ([]UploaderPluginInfo, *apperror.AppError) {
	namespace := c.resolveNamespace()
	endpoint := BuildNamespacedEndpoint(namespace, ep.Plugins)

	callInput := apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "list plugins",
		ErrorCode: apperror.ErrWPPluginList,
	}
	data, err := c.doAPICallRaw(callInput)
	if err != nil {
		return nil, err
	}

	return parsePluginListResponse(data)
}

// parsePluginListResponse tries envelope format, then legacy flat format.
func parsePluginListResponse(data []byte) ([]UploaderPluginInfo, *apperror.AppError) {
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
func (c *Client) ListPluginFilesViaUploader(slug string) ([]UploaderFileInfo, *apperror.AppError) {
	endpoint := "/" + c.resolveNamespace() + ep.Files.String()

	callInput := apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: slug},
		Operation:  "list plugin files",
		PluginSlug: slug,
		ErrorCode:  apperror.ErrWPPluginGet,
	}
	data, err := c.doAPICallRaw(callInput)
	if err != nil {
		return nil, err
	}

	result, decodeErr := decodeAPIResponseTyped[listFilesResult](data, "plugin files list")
	if decodeErr != nil {
		return nil, decodeErr
	}
	return result.Files, nil
}

// decodeAPIResponseTyped unmarshals raw JSON bytes into *T, returning *apperror.AppError on failure.
func decodeAPIResponseTyped[T any](data []byte, label string) (*T, *apperror.AppError) {
	var result T
	if err := json.Unmarshal(data, &result); err != nil {
		return nil, apperror.Wrap(err, apperror.ErrInternal, fmt.Sprintf("decode %s response", label))
	}
	return &result, nil
}
