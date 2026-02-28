package wordpress

import (
	"fmt"

	action "wp-plugin-publish/internal/enums/actiontype"
	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
)

// =============================================================================
// EXPORT TYPES AND METHODS
// =============================================================================

// ExportPluginResult holds the response from the export-plugin endpoint.
type ExportPluginResult struct {
	Success   bool   `json:"success"`    // external key (Riseup Asia Uploader API)
	PluginZip string `json:"plugin_zip"` // external key (base64 encoded)
	Slug      string `json:"slug"`       // external key
	FileCount int    `json:"file_count"` // external key
	Size      int    `json:"size"`       // external key
}

// ExportPlugin fetches an arbitrary plugin as a base64-encoded ZIP from the remote site.
func (c *Client) ExportPlugin(slug string) apperror.Result[*ExportPluginResult] {
	namespace := c.resolveNamespace()
	isNamespaceMissing := namespace == ""

	if isNamespaceMissing {
		return apperror.FailNew[*ExportPluginResult](apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	endpoint := "/" + namespace + ep.ExportPlugin.String()
	rawResult := c.doAPICallRaw(apiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: slug},
		Operation:  "export plugin for rollback",
		PluginSlug: slug,
		ErrorCode:  apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*ExportPluginResult](rawResult.AppError())
	}

	decodeResult := decodeApiResponse[ExportPluginResult](rawResult.Value(), "export plugin")
	if decodeResult.HasError() {
		return apperror.Fail[*ExportPluginResult](decodeResult.AppError())
	}

	val := decodeResult.Value()

	return apperror.Ok(&val)
}

// ExportSelfResult represents the result of exporting the uploader plugin.
type ExportSelfResult struct {
	Success    bool   `json:"success"`    // external key (Riseup Asia Uploader API)
	PluginName string `json:"pluginName"` // external key
	Version    string `json:"version"`    // external key
	PluginSlug string `json:"pluginSlug"` // external key
	PluginZip  string `json:"pluginZip"`  // external key (base64 encoded)
	Checksum   string `json:"checksum"`   // external key
	FileCount  int    `json:"fileCount"`  // external key
}

// ExportSelfFromSite fetches the Riseup Asia Uploader plugin as a ZIP from a site.
func (c *Client) ExportSelfFromSite() apperror.Result[*ExportSelfResult] {
	namespace := c.resolveNamespace()
	isNamespaceMissing := namespace == ""

	if isNamespaceMissing {
		return apperror.FailNew[*ExportSelfResult](apperror.ErrWPConnection, "Riseup Asia Uploader not available on site")
	}

	c.reportExportSelfStart()

	result := c.callExportSelf(namespace)
	if result.HasError() {
		return result
	}

	c.reportExportSelfComplete(result.Value())

	return result
}

// reportExportSelfStart logs the export self start progress.
func (c *Client) reportExportSelfStart() {
	c.progress(ProgressEvent{
		Step: action.ExportSelf.String(), Status: stagestatus.Running.String(),
		Message: "Exporting Riseup Asia Uploader plugin...",
	})
}

// callExportSelf sends the export-self API call.
func (c *Client) callExportSelf(namespace string) apperror.Result[*ExportSelfResult] {
	endpoint := BuildNamespacedEndpoint(namespace, ep.ExportSelf)

	rawResult := c.doAPICallRaw(apiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: "export self via Riseup Asia Uploader",
		ErrorCode: apperror.ErrWPConnection,
	})
	if rawResult.HasError() {
		return apperror.Fail[*ExportSelfResult](rawResult.AppError())
	}

	decodeResult := decodeApiResponse[ExportSelfResult](rawResult.Value(), "export self")
	if decodeResult.HasError() {
		return apperror.Fail[*ExportSelfResult](decodeResult.AppError())
	}

	val := decodeResult.Value()

	return apperror.Ok(&val)
}

// reportExportSelfComplete logs the export self completion progress.
func (c *Client) reportExportSelfComplete(result *ExportSelfResult) {
	c.progress(ProgressEvent{
		Step: action.ExportSelf.String(), Status: stagestatus.Completed.String(),
		Message: fmt.Sprintf("Exported %s v%s (%d files)", result.PluginName, result.Version, result.FileCount),
	})
}
