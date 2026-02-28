// Package wordpress — upload error construction helpers for UploadPluginViaUploader.
package wordpress

import (
	"fmt"

	httpmethod "wp-plugin-publish/internal/enums/httpmethodtype"
)

// uploadApiErrorInput bundles parameters for buildUploadApiError.
type uploadApiErrorInput struct {
	AbsZipPath      string
	UploadUrl       string
	UploadEndpoint  string
	StatusCode      int
	RespBytes       []byte
	RespBody        string
	StackTraceDepth int
}

// buildUploadApiError constructs a detailed ApiError for upload failures.
func buildUploadApiError(input uploadApiErrorInput) *ApiError {
	stackTrace := captureStackTraceN(4, input.StackTraceDepth)
	diagnosticBody := buildUploadDiagnosticBody(input.RespBody)

	fmt.Printf("[UPLOAD ERROR] POST %s\n  ZIP: %s\n  Status: %d\n  Response: %s\n--- Stack Trace ---\n%s--- End Stack Trace ---\n",
		input.UploadUrl, input.AbsZipPath, input.StatusCode, truncateBody(input.RespBody, 4000), stackTrace)

	return &ApiError{
		Operation:    "upload plugin via RiseupAsia Uploader",
		Method:       httpmethod.Post.Value(),
		Endpoint:     input.UploadEndpoint,
		Url:          input.UploadUrl,
		StatusCode:   input.StatusCode,
		ResponseBody: diagnosticBody + ExtractPhpStackTrace(input.RespBytes),
		StackTrace:   stackTrace,
	}
}

// buildUploadDiagnosticBody returns a truncated response body or a descriptive empty-body message.
func buildUploadDiagnosticBody(respBody string) string {
	body := truncateBody(respBody, 8192)
	isBodyPresent := body != ""

	if isBodyPresent {
		return body
	}

	return "[EMPTY RESPONSE BODY - The WordPress server returned no content. " +
		"This typically indicates a fatal PHP error that crashed before the error handler could respond. " +
		"Check the WordPress debug.log, PHP error log, or wp-content/uploads/riseup-asia-uploader/fatal-errors.log for details.]"
}
