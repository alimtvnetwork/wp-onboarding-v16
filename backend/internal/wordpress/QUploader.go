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

	callResp := c.doApiCallWithStatus(apiCallInput{
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
	normalizedSlug := normalizePluginSlug(slug)
	endpoint := "/" + QUploadNamespace + ep.Enable.String()

	callInput := apiCallInput{
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
