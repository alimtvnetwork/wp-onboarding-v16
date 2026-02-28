package site

import (
	"context"
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
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
// Accepts *apperror.AppError and unwraps the cause chain to find the underlying WordPress APIError.
func (s *Service) extractErrorDetails(appErr *apperror.AppError) *ExtractedErrorDetails {
	details := &ExtractedErrorDetails{Error: appErr.Error()}

	// Unwrap the cause chain to find the original WordPress APIError
	cause := appErr.Unwrap()
	apiErr := wordpress.ExtractAPIError(cause)

	if apiErr == nil {

		return details
	}

	s.populateApiErrorFields(details, apiErr)
	s.parseErrorResponseEnvelope(details, apiErr.ResponseBody)

	return details
}

// populateApiErrorFields copies API error fields into the details struct.
func (s *Service) populateApiErrorFields(details *ExtractedErrorDetails, apiErr *wordpress.APIError) {
	details.Method = apiErr.Method
	details.Endpoint = apiErr.Endpoint
	details.Url = apiErr.URL
	details.StatusCode = apiErr.StatusCode
	details.RequestBody = apiErr.RequestBody
	details.ResponseBody = apiErr.ResponseBody

	hasStackTrace := apiErr.StackTrace != ""
	if hasStackTrace {
		details.StackTrace = apiErr.StackTrace
	}

	hasPluginSlugIn := apiErr.PluginSlugIn != ""
	if hasPluginSlugIn {
		details.PluginSlugIn = apiErr.PluginSlugIn
	}

	hasPluginIDUsed := apiErr.PluginIDUsed != ""
	if hasPluginIDUsed {
		details.PluginIdUsed = apiErr.PluginIDUsed
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

	parsed := make([]PhpStackFrame, 0, len(envResp.ErrorLegacy.Details.StackTraceFrames))

	for _, fm := range envResp.ErrorLegacy.Details.StackTraceFrames {
		parsed = append(parsed, PhpStackFrame{
			Function: fm.Function,
			File:     fm.File,
			Line:     fm.Line,
			Class:    fm.Class,
		})
	}

	details.StackTraceFrames = parsed

	hasErrorFile := envResp.ErrorLegacy.Details.FileFull != ""
	if hasErrorFile {
		details.ErrorFile = envResp.ErrorLegacy.Details.FileFull
	}

	details.ErrorLine = envResp.ErrorLegacy.Details.Line
}

// RemoteActionLogInput bundles parameters for logging a remote action event.
type RemoteActionLogInput struct {
	Level   string
	Step    string
	Message string
	Details json.RawMessage
}

// logRemoteAction logs a remote plugin action to session and WebSocket.
func (s *Service) logRemoteAction(ref *remoteActionRef, input RemoteActionLogInput) {
	s.emitRemoteActionToSession(ref, input)
	logCtx := s.resolveRemoteActionLogContext(ref.SiteID, input.Details)
	s.emitRemoteActionToLogger(loggerEmitInput{
		Level:   input.Level,
		Message: input.Message,
		SiteID:  ref.SiteID,
		Action:  ref.Action,
		Step:    input.Step,
		Ctx:     logCtx,
	})
}

// emitRemoteActionToSession sends logs to session service and WebSocket.
func (s *Service) emitRemoteActionToSession(ref *remoteActionRef, input RemoteActionLogInput) {
	hasSessionService := s.sessionService != nil
	hasSessionID := ref.SessionID != ""
	isSessionLoggable := hasSessionService && hasSessionID

	if isSessionLoggable {
		s.sessionService.Log(session.LogInput{
			SessionID: ref.SessionID,
			Level:     input.Level,
			Step:      input.Step,
			Message:   input.Message,
			Details:   input.Details,
		})
	}

	hasWsHub := s.wsHub != nil

	if hasWsHub {
		s.wsHub.BroadcastRemotePluginLogWithSession(RemotePluginLogInput{
			SiteID:    ref.SiteID,
			Action:    ref.Action,
			SessionID: ref.SessionID,
			Level:     input.Level,
			Step:      input.Step,
			Message:   input.Message,
			Details:   input.Details,
		})
	}
}

// remoteActionResolvedContext holds resolved context for log output.
type remoteActionResolvedContext struct {
	SiteName   string
	SiteUrl    string
	PluginSlug string
}

// resolveRemoteActionLogContext extracts and resolves log context from details JSON.
func (s *Service) resolveRemoteActionLogContext(siteId int64, details json.RawMessage) remoteActionResolvedContext {
	resolved := parseRemoteActionLogDetails(details)

	s.fillMissingSiteContext(siteId, &resolved)

	isSiteNameMissing := resolved.SiteName == ""
	if isSiteNameMissing {
		resolved.SiteName = fmt.Sprintf("site#%d", siteId)
	}

	return resolved
}

// parseRemoteActionLogDetails extracts context from JSON details.
func parseRemoteActionLogDetails(details json.RawMessage) remoteActionResolvedContext {
	var logCtx remoteActionLogContext

	hasDetails := len(details) > 0
	if hasDetails {
		_ = json.Unmarshal(details, &logCtx)
	}

	return remoteActionResolvedContext{
		SiteName:   logCtx.SiteName,
		SiteUrl:    logCtx.SiteUrl,
		PluginSlug: logCtx.PluginSlug,
	}
}

// fillMissingSiteContext loads site info from DB if name or URL is missing.
func (s *Service) fillMissingSiteContext(siteId int64, ctx *remoteActionResolvedContext) {
	hasName := ctx.SiteName != ""
	hasUrl := ctx.SiteUrl != ""
	isFullyResolved := hasName && hasUrl
	isInvalidSiteId := siteId <= 0
	isSkippable := isFullyResolved || isInvalidSiteId

	if isSkippable {

		return
	}

	siteResult := s.GetById(context.Background(), siteId)

	if siteResult.IsSafe() {
		site := siteResult.Value()

		isSiteNameMissing := ctx.SiteName == ""
		if isSiteNameMissing {
			ctx.SiteName = site.Name
		}

		isSiteUrlMissing := ctx.SiteUrl == ""
		if isSiteUrlMissing {
			ctx.SiteUrl = site.Url
		}
	}
}

// loggerEmitInput bundles parameters for emitRemoteActionToLogger.
type loggerEmitInput struct {
	Level   string
	Message string
	SiteID  int64
	Action  string
	Step    string
	Ctx     remoteActionResolvedContext
}

// emitRemoteActionToLogger writes the log entry at the appropriate level.
func (s *Service) emitRemoteActionToLogger(input loggerEmitInput) {
	logFields := buildRemoteActionLogFields(input)

	isErrorLevel := input.Level == loglevel.Error.String()

	if isErrorLevel {
		s.log.Error(input.Message, logFields...)
	} else {
		s.log.Debug(input.Message, logFields...)
	}
}

// buildRemoteActionLogFields constructs the structured log fields.
func buildRemoteActionLogFields(input loggerEmitInput) []any {
	logFields := []any{"site", input.Ctx.SiteName}

	hasSiteUrl := input.Ctx.SiteUrl != ""
	if hasSiteUrl {
		logFields = append(logFields, "siteUrl", input.Ctx.SiteUrl)
	}

	logFields = append(logFields, "siteId", input.SiteID, "action", input.Action, "step", input.Step)

	hasPluginSlug := input.Ctx.PluginSlug != ""
	if hasPluginSlug {
		logFields = append(logFields, "pluginSlug", input.Ctx.PluginSlug)
	}

	return logFields
}

// endRemoteSession ends the session if service is available
func (s *Service) endRemoteSession(sessionId, status, errorMsg string) {
	hasSessionService := s.sessionService != nil
	hasSessionID := sessionId != ""
	isSessionEndable := hasSessionService && hasSessionID

	if isSessionEndable {
		s.sessionService.EndSession(sessionId, status, errorMsg)
	}
}
