package site

import (
	"encoding/json"

	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
	"wp-plugin-publish/pkg/apperror"
)

// buildPhpStackFrames converts typed PHP stack frames into session StackFrame structs
func (s *Service) buildPhpStackFrames(details *ExtractedErrorDetails) []session.StackFrame {
	frames := make([]session.StackFrame, 0, len(details.StackTraceFrames))

	for _, f := range details.StackTraceFrames {
		frames = append(frames, session.StackFrame{
			Function: f.Function,
			File:     f.File,
			Line:     f.Line,
			Class:    f.Class,
		})
	}

	return frames
}

// extractErrorDetails extracts PHP stack trace frames and other details from WordPress API errors.
// Accepts *apperror.AppError and unwraps the cause chain to find the underlying WordPress ApiError.
func (s *Service) extractErrorDetails(appErr *apperror.AppError) *ExtractedErrorDetails {
	details := &ExtractedErrorDetails{Error: appErr.Error()}

	// Unwrap the cause chain to find the original WordPress ApiError
	cause := appErr.Unwrap()
	apiErr := wordpress.ExtractApiError(cause)
	isApiErrorMissing := apiErr == nil

	if isApiErrorMissing {
		return details
	}

	s.populateApiErrorFields(details, apiErr)
	s.parseErrorResponseEnvelope(details, apiErr.ResponseBody)

	return details
}

// populateApiErrorFields copies API error fields into the details struct.
func (s *Service) populateApiErrorFields(details *ExtractedErrorDetails, apiErr *wordpress.ApiError) {
	copyRequiredApiFields(details, apiErr)
	copyOptionalApiFields(details, apiErr)
}

// copyRequiredApiFields copies the always-present API error fields.
func copyRequiredApiFields(details *ExtractedErrorDetails, apiErr *wordpress.ApiError) {
	details.Method = apiErr.Method
	details.Endpoint = apiErr.Endpoint
	details.Url = apiErr.URL
	details.StatusCode = apiErr.StatusCode
	details.RequestBody = apiErr.RequestBody
	details.ResponseBody = apiErr.ResponseBody
}

// copyOptionalApiFields copies conditionally-present API error fields.
func copyOptionalApiFields(details *ExtractedErrorDetails, apiErr *wordpress.ApiError) {
	hasStackTrace := apiErr.StackTrace != ""

	if hasStackTrace {
		details.StackTrace = apiErr.StackTrace
	}

	hasPluginSlugIn := apiErr.PluginSlugIn != ""

	if hasPluginSlugIn {
		details.PluginSlugIn = apiErr.PluginSlugIn
	}

	hasPluginIdUsed := apiErr.PluginIdUsed != ""

	if hasPluginIdUsed {
		details.PluginIdUsed = apiErr.PluginIdUsed
	}
}

// parseErrorResponseEnvelope parses the JSON response body for structured error details.
func (s *Service) parseErrorResponseEnvelope(details *ExtractedErrorDetails, responseBody string) {
	var envResp errorResponseEnvelope

	isUnmarshalFailed := json.Unmarshal([]byte(responseBody), &envResp) != nil

	if isUnmarshalFailed {
		return
	}

	applyEnvelopeErrors(details, &envResp.Errors)
	s.parseLegacyStackFrames(details, &envResp)
}

// applyEnvelopeErrors copies envelope error fields into the details struct.
func applyEnvelopeErrors(details *ExtractedErrorDetails, errors *envelopeErrors) {
	hasBackendMessage := errors.BackendMessage != ""
	if hasBackendMessage {
		details.ErrorMessage = errors.BackendMessage
	}

	hasDelegatedStack := len(errors.DelegatedServiceErrorStack) > 0
	if hasDelegatedStack {
		details.DelegatedServiceErrorStack = errors.DelegatedServiceErrorStack
	}

	hasBackendStack := len(errors.Backend) > 0
	if hasBackendStack {
		details.PhpBackendStack = errors.Backend
	}
}

// parseLegacyStackFrames extracts PHP stack trace frames from the legacy error format.
func (s *Service) parseLegacyStackFrames(details *ExtractedErrorDetails, envResp *errorResponseEnvelope) {
	if envResp.ErrorLegacy.Details.StackTraceFrames == nil {
		return
	}

	details.StackTraceFrames = convertLegacyFrames(envResp.ErrorLegacy.Details.StackTraceFrames)
	applyLegacyErrorLocation(details, &envResp.ErrorLegacy.Details)
}

// convertLegacyFrames maps raw legacy frames to typed PhpStackFrame slice.
func convertLegacyFrames(rawFrames []legacyStackFrame) []PhpStackFrame {
	parsed := make([]PhpStackFrame, 0, len(rawFrames))

	for _, fm := range rawFrames {
		parsed = append(parsed, PhpStackFrame{
			Function: fm.Function,
			File:     fm.File,
			Line:     fm.Line,
			Class:    fm.Class,
		})
	}

	return parsed
}

// applyLegacyErrorLocation copies error file and line from legacy details.
func applyLegacyErrorLocation(details *ExtractedErrorDetails, legacy *errorLegacyDetails) {
	hasErrorFile := legacy.FileFull != ""

	if hasErrorFile {
		details.ErrorFile = legacy.FileFull
	}

	details.ErrorLine = legacy.Line
}

// RemoteActionLogInput bundles parameters for logging a remote action event.
type RemoteActionLogInput struct {
	Level   string
	Step    string
	Message string
	Details json.RawMessage
}
