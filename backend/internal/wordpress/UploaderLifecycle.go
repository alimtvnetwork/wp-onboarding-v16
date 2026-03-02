package wordpress

import (
	"encoding/json"
	"fmt"
	"path/filepath"
	"strings"

	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// normalizePluginSlug extracts the folder-level slug from a full WordPress plugin
// identifier like "broken-link-checker/broken-link-checker.php".
func normalizePluginSlug(slug string) string {
	if strings.Contains(slug, "/") {
		dir := filepath.Dir(slug)
		isDirValid := dir != "." && dir != ""

		if isDirValid {
			return dir
		}
	}
	slug = strings.TrimSuffix(slug, ".php")
	return slug
}

// pluginLifecycleInput holds the parameters for a plugin lifecycle action.
type pluginLifecycleInput struct {
	Slug      string
	Endpoint  ep.Variant
	Operation operationtype.Variant
	ErrorCode apperror.ErrorCode
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
		Operation:  input.Operation,
		PluginSlug: normalizedSlug,
		ErrorCode:  input.ErrorCode,
	}
	rawResult := c.doApiCallRaw(callInput)

	return rawResult.AppError()
}

// PluginExistsResult holds the result of a plugin existence check.
type PluginExistsResult struct {
	Exists     bool
	Status     string
	PluginFile string
}

// CheckPluginExistsViaUploader checks if a plugin slug is installed on the remote site.
func (c *Client) CheckPluginExistsViaUploader(slug string) apperror.Result[*PluginExistsResult] {
	namespace := c.resolveNamespace()
	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + namespace + ep.PluginExists.String()

	callInput := apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: normalizedSlug},
		Operation:  operationtype.CheckPluginExists,
		PluginSlug: normalizedSlug,
		ErrorCode:  apperror.ErrWPConnection,
	}
	rawResult := c.doApiCallRaw(callInput)
	if rawResult.HasError() {
		return apperror.Fail[*PluginExistsResult](rawResult.AppError())
	}

	return parsePluginExistsResponse(rawResult.Value())
}

// pluginExistsResult is the typed struct for the plugin-exists envelope response.
type pluginExistsResult struct {
	PluginSlug string `json:"pluginSlug"` // external key (Riseup Asia Uploader API)
	Exists     bool   `json:"exists"`     // external key
	Status     string `json:"status"`     // external key
	PluginFile string `json:"pluginFile"` // external key
}

// parsePluginExistsResponse tries envelope format, then legacy flat format.
func parsePluginExistsResponse(data []byte) apperror.Result[*PluginExistsResult] {
	results, ok := UnwrapResults[pluginExistsResult](data)
	isEnvelopeMatch := ok && len(results) > 0

	if isEnvelopeMatch {
		result := &PluginExistsResult{
			Exists:     results[0].Exists,
			Status:     results[0].Status,
			PluginFile: results[0].PluginFile,
		}

		return apperror.Ok(result)
	}

	return parsePluginExistsLegacy(data)
}

// parsePluginExistsLegacy decodes the legacy flat format for plugin-exists.
func parsePluginExistsLegacy(data []byte) apperror.Result[*PluginExistsResult] {
	var legacy pluginExistsResult
	err := json.Unmarshal(data, &legacy)

	if err != nil {
		return apperror.FailWrap[*PluginExistsResult](err, apperror.ErrInternal, "decode plugin exists response")
	}

	result := &PluginExistsResult{
		Exists:     legacy.Exists,
		Status:     legacy.Status,
		PluginFile: legacy.PluginFile,
	}
	return apperror.Ok(result)
}

// EnablePluginViaUploader enables (activates) a plugin via the RiseupAsia Uploader.
func (c *Client) EnablePluginViaUploader(slug string) *apperror.AppError {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:      slug,
		Endpoint:  ep.Enable,
		Operation: operationtype.EnablePlugin,
		ErrorCode: apperror.ErrWPPluginActivate,
	})
}

// DisablePluginViaUploader disables (deactivates) a plugin via the RiseupAsia Uploader.
func (c *Client) DisablePluginViaUploader(slug string) *apperror.AppError {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:      slug,
		Endpoint:  ep.Disable,
		Operation: operationtype.DisablePlugin,
		ErrorCode: apperror.ErrWPPluginActivate,
	})
}

// DeletePluginViaUploader deletes a plugin via the RiseupAsia Uploader.
func (c *Client) DeletePluginViaUploader(slug string) *apperror.AppError {
	return c.pluginLifecycleAction(pluginLifecycleInput{
		Slug:      slug,
		Endpoint:  ep.Delete,
		Operation: operationtype.DeletePlugin,
		ErrorCode: apperror.ErrWPConnection,
	})
}

// ListPluginsViaUploader lists all plugins via the RiseupAsia Uploader.
func (c *Client) ListPluginsViaUploader() apperror.Result[[]UploaderPluginInfo] {
	namespace := c.resolveNamespace()
	endpoint := BuildNamespacedEndpoint(namespace, ep.Plugins)

	callInput := apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.ListPlugins,
		ErrorCode: apperror.ErrWPPluginList,
	}
	rawResult := c.doApiCallRaw(callInput)
	if rawResult.HasError() {
		return apperror.Fail[[]UploaderPluginInfo](rawResult.AppError())
	}

	return parsePluginListResponse(rawResult.Value())
}

// parsePluginListResponse tries envelope format, then legacy flat format.
func parsePluginListResponse(data []byte) apperror.Result[[]UploaderPluginInfo] {
	plugins, ok := UnwrapResults[UploaderPluginInfo](data)

	if ok {
		return apperror.Ok(plugins)
	}

	var response struct {
		Success bool                 `json:"success"` // external key (Riseup Asia Uploader API)
		Count   int                  `json:"count"`   // external key
		Plugins []UploaderPluginInfo `json:"plugins"` // external key
	}
	err := json.Unmarshal(data, &response)

	if err != nil {
		return apperror.FailWrap[[]UploaderPluginInfo](err, apperror.ErrInternal, "decode plugins response")
	}

	return apperror.Ok(response.Plugins)
}

// listFilesResult is the response shape from the files list endpoint.
type listFilesResult struct {
	Success bool               `json:"success"` // external key (Riseup Asia Uploader API)
	Slug    string             `json:"slug"`    // external key
	Count   int                `json:"count"`   // external key
	Files   []UploaderFileInfo `json:"files"`   // external key
}

// ListPluginFilesViaUploader lists files in a plugin via the RiseupAsia Uploader.
func (c *Client) ListPluginFilesViaUploader(slug string) apperror.Result[[]UploaderFileInfo] {
	endpoint := "/" + c.resolveNamespace() + ep.Files.String()

	callInput := apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: slug},
		Operation:  operationtype.ListPluginFiles,
		PluginSlug: slug,
		ErrorCode:  apperror.ErrWPPluginGet,
	}
	rawResult := c.doApiCallRaw(callInput)
	if rawResult.HasError() {
		return apperror.Fail[[]UploaderFileInfo](rawResult.AppError())
	}

	decodeResult := decodeApiResponse[listFilesResult](rawResult.Value(), "plugin files list")
	if decodeResult.HasError() {
		return apperror.Fail[[]UploaderFileInfo](decodeResult.AppError())
	}

	return apperror.Ok(decodeResult.Value().Files)
}

// decodeApiResponseTyped unmarshals raw JSON bytes into *T, returning apperror.Result.
func decodeApiResponseTyped[T any](data []byte, label string) apperror.Result[*T] {
	var result T
	err := json.Unmarshal(data, &result)

	if err != nil {
		return apperror.FailWrap[*T](err, apperror.ErrInternal, fmt.Sprintf("decode %s response", label))
	}
	return apperror.Ok(&result)
}
