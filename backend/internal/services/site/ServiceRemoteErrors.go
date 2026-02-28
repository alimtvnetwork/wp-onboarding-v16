package site

import (
	"context"
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
)

// buildPhpStackFrames converts typed PHP stack frames into session StackFrame structs
func (s *Service) buildPhpStackFrames(details *ExtractedErrorDetails) []session.StackFrame {
	frames := make([]session.StackFrame, 0, len(details.StackTraceFrames))
	for _, f := range details.StackTraceFrames {
		frames = append(frames, session.StackFrame{Function: f.Function, File: f.File, Line: f.Line, Class: f.Class})
	}

	return frames
}

// extractErrorDetails extracts PHP stack trace frames and other details from WordPress API errors
func (s *Service) extractErrorDetails(err error) *ExtractedErrorDetails {
	details := &ExtractedErrorDetails{Error: err.Error()}

	apiErr := wordpress.ExtractAPIError(err)
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
	if apiErr.StackTrace != "" {
		details.StackTrace = apiErr.StackTrace
	}
	if apiErr.PluginSlugIn != "" {
		details.PluginSlugIn = apiErr.PluginSlugIn
	}
	if apiErr.PluginIDUsed != "" {
		details.PluginIdUsed = apiErr.PluginIDUsed
	}
}

// parseErrorResponseEnvelope parses the JSON response body for structured error details.
func (s *Service) parseErrorResponseEnvelope(details *ExtractedErrorDetails, responseBody string) {
	var envResp errorResponseEnvelope
	if json.Unmarshal([]byte(responseBody), &envResp) != nil {
		return
	}

	applyEnvelopeErrors(details, &envResp.Errors)
	s.parseLegacyStackFrames(details, &envResp)
}

// applyEnvelopeErrors copies envelope error fields into the details struct.
func applyEnvelopeErrors(details *ExtractedErrorDetails, errors *envelopeErrors) {
	if errors.BackendMessage != "" {
		details.ErrorMessage = errors.BackendMessage
	}
	if len(errors.DelegatedServiceErrorStack) > 0 {
		details.DelegatedServiceErrorStack = errors.DelegatedServiceErrorStack
	}
	if len(errors.Backend) > 0 {
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
		parsed = append(parsed, PhpStackFrame{Function: fm.Function, File: fm.File, Line: fm.Line, Class: fm.Class})
	}
	details.StackTraceFrames = parsed
	if envResp.ErrorLegacy.Details.FileFull != "" {
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
	s.emitRemoteActionToLogger(loggerEmitInput{Level: input.Level, Message: input.Message, SiteID: ref.SiteID, Action: ref.Action, Step: input.Step, Ctx: logCtx})
}

// emitRemoteActionToSession sends logs to session service and WebSocket.
func (s *Service) emitRemoteActionToSession(ref *remoteActionRef, input RemoteActionLogInput) {
	if s.sessionService != nil && ref.SessionID != "" {
		s.sessionService.Log(session.LogInput{SessionID: ref.SessionID, Level: input.Level, Step: input.Step, Message: input.Message, Details: input.Details})
	}
	if s.wsHub != nil {
		s.wsHub.BroadcastRemotePluginLogWithSession(RemotePluginLogInput{
			SiteID: ref.SiteID, Action: ref.Action, SessionID: ref.SessionID,
			Level: input.Level, Step: input.Step, Message: input.Message, Details: input.Details,
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

	if resolved.SiteName == "" {
		resolved.SiteName = fmt.Sprintf("site#%d", siteId)
	}

	return resolved
}

// parseRemoteActionLogDetails extracts context from JSON details.
func parseRemoteActionLogDetails(details json.RawMessage) remoteActionResolvedContext {
	var logCtx remoteActionLogContext
	if len(details) > 0 {
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
	if (ctx.SiteName != "" && ctx.SiteUrl != "") || siteId <= 0 {
		return
	}
	siteResult := s.GetById(context.Background(), siteId)
	if siteResult.IsSafe() {
		site := siteResult.Value()
		if ctx.SiteName == "" {
			ctx.SiteName = site.Name
		}
		if ctx.SiteUrl == "" {
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

	if input.Level == loglevel.Error.String() {
		s.log.Error(input.Message, logFields...)
	} else {
		s.log.Debug(input.Message, logFields...)
	}
}

// buildRemoteActionLogFields constructs the structured log fields.
func buildRemoteActionLogFields(input loggerEmitInput) []any {
	logFields := []any{"site", input.Ctx.SiteName}
	if input.Ctx.SiteUrl != "" {
		logFields = append(logFields, "siteUrl", input.Ctx.SiteUrl)
	}
	logFields = append(logFields, "siteId", input.SiteID, "action", input.Action, "step", input.Step)
	if input.Ctx.PluginSlug != "" {
		logFields = append(logFields, "pluginSlug", input.Ctx.PluginSlug)
	}

	return logFields
}

// endRemoteSession ends the session if service is available
func (s *Service) endRemoteSession(sessionId, status, errorMsg string) {
	if s.sessionService != nil && sessionId != "" {
		s.sessionService.EndSession(sessionId, status, errorMsg)
	}
}
