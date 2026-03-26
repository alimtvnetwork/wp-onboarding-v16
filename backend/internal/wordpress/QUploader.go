// Package wordpress — QUpload (Quick Upload) fallback uploader.
// QUpload provides an alternative upload transport when the
// primary Riseup Asia Uploader is unavailable or fails.
package wordpress

import (
	ep "wp-plugin-publish/internal/enums/endpointtype"
	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	operationtype "wp-plugin-publish/internal/enums/operationtype"
	"wp-plugin-publish/pkg/apperror"
)

// CheckQUploadAvailable checks if the QUpload plugin is installed and reachable.
// Probes the qupload-api/v1/status endpoint.
func (c *Client) CheckQUploadAvailable() apperror.Result[*UploaderAvailability] {
	endpoint := "/" + QUploadNamespace + ep.Status.String()

	callResp := c.doApiCallWithStatus(ApiCallInput{
		Method:    httpmethod.Get,
		Endpoint:  endpoint,
		Operation: operationtype.CheckUploaderNamespace,
	})

	if callResp.HasError() {
		return apperror.Ok(&UploaderAvailability{Available: false})
	}

	resp := callResp.Value()
	isOk := resp.StatusCode == HttpStatusOk.Int()
	isUnauth := resp.StatusCode == HttpStatusUnauthorized.Int()
	isForbidden := resp.StatusCode == HttpStatusForbidden.Int()
	isAvailable := isOk || isUnauth || isForbidden

	if isAvailable {
		return apperror.Ok(&UploaderAvailability{
			Available: true,
			Namespace: QUploadNamespace,
		})
	}

	return apperror.Ok(&UploaderAvailability{Available: false})
}

// UploadPluginViaQUpload uploads a plugin ZIP via QUpload as a fallback.
// Uses the same multipart/form-data pattern as Riseup Asia Uploader.
func (c *Client) UploadPluginViaQUpload(input UploadInput) apperror.Result[*UploaderUploadResult] {
	uc, err := c.prepareUploadContext(input.ZipPath, input.Slug)
	if err != nil {
		return apperror.Fail[*UploaderUploadResult](err)
	}
	defer uc.ZipFile.Close()

	// Override namespace and endpoint to use QUpload
	uc.Namespace = QUploadNamespace
	uc.UploadEndpoint = "/" + QUploadNamespace + ep.Upload.String()
	uc.UploadUrl = BuildWpJsonUrl(c.baseUrl, uc.UploadEndpoint)

	c.reportUploadInitProgress(uc)

	mp, mpErr := buildMultipartBody(uc, input.IsActivate, input.UploadSource)
	if mpErr != nil {
		return apperror.Fail[*UploaderUploadResult](mpErr)
	}

	c.reportMultipartBodyReady(uc, input, mp)

	return c.executeUploadHttp(uc, mp.Body, mp.ContentType)
}

// EnablePluginViaQUpload activates a plugin via QUpload's activate endpoint.
func (c *Client) EnablePluginViaQUpload(slug string) *apperror.AppError {
	normalizedSlug := pluginutil.ExtractFolderSlug(slug)
	endpoint := "/" + QUploadNamespace + ep.Enable.String()

	callInput := ApiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: normalizedSlug},
		Operation:  operationtype.EnablePlugin,
		PluginSlug: normalizedSlug,
		ErrorCode:  apperror.ErrWPPluginActivate,
	}
	rawResult := c.doApiCallRaw(callInput)

	return rawResult.AppError()
}

// DisablePluginViaQUpload deactivates a plugin via QUpload's deactivate endpoint.
func (c *Client) DisablePluginViaQUpload(slug string) *apperror.AppError {
	normalizedSlug := pluginutil.ExtractFolderSlug(slug)
	endpoint := "/" + QUploadNamespace + ep.Disable.String()

	callInput := ApiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: normalizedSlug},
		Operation:  operationtype.DisablePlugin,
		PluginSlug: normalizedSlug,
		ErrorCode:  apperror.ErrWPPluginActivate,
	}
	rawResult := c.doApiCallRaw(callInput)

	return rawResult.AppError()
}

// ListPluginsViaQUpload lists all plugins via QUpload's plugins endpoint.
func (c *Client) ListPluginsViaQUpload() apperror.Result[[]UploaderPluginInfo] {
	endpoint := "/" + QUploadNamespace + ep.Plugins.String()

	callInput := ApiCallInput{
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

// DeletePluginViaQUpload deletes a plugin via QUpload's delete endpoint.
func (c *Client) DeletePluginViaQUpload(slug string) *apperror.AppError {
	normalizedSlug := pluginutil.ExtractFolderSlug(slug)
	endpoint := "/" + QUploadNamespace + ep.Delete.String()

	callInput := ApiCallInput{
		Method:     httpmethod.Post,
		Endpoint:   endpoint,
		Body:       PluginSlugRequest{Plugin: normalizedSlug},
		Operation:  operationtype.DeletePlugin,
		PluginSlug: normalizedSlug,
		ErrorCode:  apperror.ErrWPConnection,
	}
	rawResult := c.doApiCallRaw(callInput)

	return rawResult.AppError()
}
