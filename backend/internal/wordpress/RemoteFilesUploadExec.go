// Package wordpress — Onboard ZIP upload execution and response parsing.
package wordpress

import (
	"encoding/json"
	"fmt"
	"io"
	"path/filepath"

	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
	"wp-plugin-publish/internal/enums/stagestatustype"
	"wp-plugin-publish/pkg/apperror"
)

// executeOnboardZipUpload sends the multipart upload and parses the response.
func (c *Client) executeOnboardZipUpload(endpoint string, form *zipMultipartForm, zipPath, pluginSlug string) apperror.Result[*OnboardUploadResult] {
	url := BuildWpJsonUrl(c.baseUrl, endpoint)

	c.reportOnboardUploadStart(url, endpoint, form, zipPath)

	mpResult := c.sendMultipartUpload(endpoint, form)
	if mpResult.HasError() {
		return apperror.Fail[*OnboardUploadResult](mpResult.AppError())
	}

	mpResp := mpResult.Value()
	c.reportOnboardUploadResponse(mpResp)

	return c.parseOnboardUploadResponse(mpResp, endpoint, url, pluginSlug)
}

// reportOnboardUploadStart logs the upload start progress.
func (c *Client) reportOnboardUploadStart(url, endpoint string, form *zipMultipartForm, zipPath string) {
	uploadProgress := ZipUploadProgress{
		ZipSize:  form.FileSize,
		ZipFile:  filepath.Base(zipPath),
		Endpoint: endpoint,
	}
	uploadStartEvent := ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("POSTing %d bytes to %s", form.FileSize, url),
		Details: toProgress(uploadProgress),
	}
	c.progress(uploadStartEvent)
}

// reportOnboardUploadResponse logs the upload response progress.
func (c *Client) reportOnboardUploadResponse(mpResp *multipartResponse) {
	responseProgress := ResponseProgress{
		Status: mpResp.StatusCode,
		Body:   truncateBody(mpResp.Body, 500),
	}
	uploadRespEvent := ProgressEvent{
		Step:    "upload",
		Status:  stagestatustype.Running.String(),
		Message: fmt.Sprintf("Upload response: %d", mpResp.StatusCode),
		Details: toProgress(responseProgress),
	}
	c.progress(uploadRespEvent)
}

// multipartResponse holds the result of a multipart HTTP request.
type multipartResponse struct {
	StatusCode int
	Body       string
}

// sendMultipartUpload sends a multipart POST via the standardized requestMultipart helper.
func (c *Client) sendMultipartUpload(endpoint string, form *zipMultipartForm) apperror.Result[*multipartResponse] {
	input := multipartInput{
		Method:      httpmethod.Post,
		Endpoint:    endpoint,
		Body:        form.Body,
		ContentType: form.ContentType,
	}

	resp, appErr := c.requestMultipart(input)
	if appErr != nil {
		return apperror.Fail[*multipartResponse](appErr)
	}
	defer resp.Body.Close()

	respBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return apperror.FailWrap[*multipartResponse](err, apperror.ErrInternal, "failed to read multipart response body")
	}

	return apperror.Ok(&multipartResponse{StatusCode: resp.StatusCode, Body: string(respBytes)})
}

// parseOnboardUploadResponse validates the status code and unmarshals the result.
func (c *Client) parseOnboardUploadResponse(mpResp *multipartResponse, endpoint, url, pluginSlug string) apperror.Result[*OnboardUploadResult] {
	isSuccess := mpResp.StatusCode == HttpStatusOk.Int() ||
		mpResp.StatusCode == HttpStatusCreated.Int()
	isFailed := !isSuccess

	if isFailed {
		return apperror.Fail[*OnboardUploadResult](
			apperror.New(apperror.ErrWPPluginUpload, "upload plugin zip failed").
				WithEndpoint(endpoint).
				WithUrl(url).
				WithSlug(pluginSlug).
				WithValue("statusCode", fmt.Sprintf("%d", mpResp.StatusCode)).
				WithValue("responseBody", truncateBody(mpResp.Body, 8192)))
	}

	var result OnboardUploadResult
	unmarshalErr := json.Unmarshal([]byte(mpResp.Body), &result)
	if unmarshalErr != nil {
		result.Success = true
		result.Message = "Upload completed"
	}

	return apperror.Ok(&result)
}

// truncateBody truncates a string to maxLen for error messages.
func truncateBody(body string, maxLen int) string {
	isTooLong := len(body) > maxLen

	if isTooLong {
		return body[:maxLen] + "..."
	}

	return body
}
