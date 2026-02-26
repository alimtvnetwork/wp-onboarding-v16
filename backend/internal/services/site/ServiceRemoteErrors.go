package site

import (
	"context"
	"encoding/json"
	"fmt"

	"wp-plugin-publish/internal/enums/log_level"
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

	if envResp.Errors.BackendMessage != "" {
		details.ErrorMessage = envResp.Errors.BackendMessage
	}
	if len(envResp.Errors.DelegatedServiceErrorStack) > 0 {
		details.DelegatedServiceErrorStack = envResp.Errors.DelegatedServiceErrorStack
	}
	if len(envResp.Errors.Backend) > 0 {
		details.PhpBackendStack = envResp.Errors.Backend
	}

	s.parseLegacyStackFrames(details, &envResp)
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
	var logCtx remoteActionLogContext
	if len(details) > 0 {
		_ = json.Unmarshal(details, &logCtx)
	}

	resolved := remoteActionResolvedContext{
		SiteName:   logCtx.SiteName,
		SiteUrl:    logCtx.SiteUrl,
		PluginSlug: logCtx.PluginSlug,
	}

	s.fillMissingSiteContext(siteId, &resolved)

	if resolved.SiteName == "" {
		resolved.SiteName = fmt.Sprintf("site#%d", siteId)
	}

	return resolved
}

// fillMissingSiteContext loads site info from DB if name or URL is missing.
func (s *Service) fillMissingSiteContext(siteId int64, ctx *remoteActionResolvedContext) {
	if (ctx.SiteName != "" && ctx.SiteUrl != "") || siteId <= 0 {
		return
	}
	if siteResult := s.GetById(context.Background(), siteId); siteResult.IsSafe() {
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

// fetchAndAttachRemotePhpErrors pulls recent PHP error sessions from the remote WordPress site
func (s *Service) fetchAndAttachRemotePhpErrors(ref *remoteActionRef, errDetails *ExtractedErrorDetails) {
	s.fetchAndAttachPhpErrorSessions(ref, errDetails)
	s.fetchAndAttachPhpStackTrace(ref, errDetails)
}

// fetchAndAttachPhpErrorSessions pulls recent PHP error entries from the remote site.
func (s *Service) fetchAndAttachPhpErrorSessions(ref *remoteActionRef, errDetails *ExtractedErrorDetails) {
	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_errors", Message: "Pulling recent PHP error sessions from remote site..."})

	result, fetchErr := ref.Client.FetchRemoteErrorSessions(wordpress.ErrorSessionsInput{Level: "error", Limit: 10})
	if fetchErr != nil {
		s.logRemoteAction(ref, RemoteActionLogInput{Level: "warn", Step: "fetch_php_errors", Message: fmt.Sprintf("Could not fetch remote PHP errors: %s", fetchErr.Error())})

		return
	}

	if result == nil || len(result.Entries) == 0 {
		s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_errors", Message: "No recent PHP error sessions found on remote site"})

		return
	}

	s.attachPhpErrorEntries(ref, result, errDetails)
}

// attachPhpErrorEntries collects and attaches PHP error entries to the error details.
func (s *Service) attachPhpErrorEntries(ref *remoteActionRef, result *wordpress.RemoteErrorSessionsResult, errDetails *ExtractedErrorDetails) {
	phpErrors := collectPhpErrorEntries(result.Entries)
	errDetails.RemotePhpErrors = phpErrors
	errDetails.RemotePhpErrorCount = len(result.Entries)
	if result.Flash.HasUnseen {
		errDetails.RemotePhpFlashUnseen = result.Flash.UnseenCount
	}

	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_errors", Message: fmt.Sprintf("Retrieved %d recent PHP error(s) from remote site", len(result.Entries)), Details: session.ToJSON(PhpErrorCountDetail{PhpErrorCount: len(result.Entries)})})
	s.logPhpErrorsToSession(ref.SessionID, result.Entries)
}

// collectPhpErrorEntries converts remote error entries to PhpErrorEntry slice.
func collectPhpErrorEntries(entries []wordpress.RemoteErrorSessionEntry) []PhpErrorEntry {
	phpErrors := make([]PhpErrorEntry, 0, len(entries))
	for _, entry := range entries {
		phpErr := PhpErrorEntry{Id: entry.ID, Level: entry.Level, Message: entry.Message, File: entry.File, Line: derefInt(entry.Line), CreatedAt: entry.CreatedAt}
		if len(entry.StackTraceFrames) > 0 {
			if raw, marshalErr := json.Marshal(entry.StackTraceFrames); marshalErr == nil {
				phpErr.StackTraceFrames = raw
			}
		}
		phpErrors = append(phpErrors, phpErr)
	}

	return phpErrors
}

// logPhpErrorsToSession writes individual PHP errors to the session log.
func (s *Service) logPhpErrorsToSession(sessionId string, entries []wordpress.RemoteErrorSessionEntry) {
	if s.sessionService == nil || sessionId == "" {
		return
	}
	for _, entry := range entries {
		s.sessionService.Log(session.LogInput{SessionID: sessionId, Level: "error", Step: "remote_php_error", Message: entry.Message, Details: session.ToJSON(PhpErrorDetail{PhpFile: entry.File, PhpLine: derefInt(entry.Line), PhpLevel: entry.Level, PhpCreated: entry.CreatedAt})})
	}
}

// fetchAndAttachPhpStackTrace pulls the PHP stacktrace.txt from the remote site.
func (s *Service) fetchAndAttachPhpStackTrace(ref *remoteActionRef, errDetails *ExtractedErrorDetails) {
	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_stacktrace", Message: "Pulling PHP stacktrace.txt from remote site..."})

	logsResult, logsErr := ref.Client.FetchRemoteErrorLogs()
	if logsErr != nil {
		s.logRemoteAction(ref, RemoteActionLogInput{Level: "warn", Step: "fetch_php_stacktrace", Message: fmt.Sprintf("Could not fetch remote error logs: %s", logsErr.Error())})

		return
	}

	if !hasStackTraceContent(logsResult) {
		s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_stacktrace", Message: "No stacktrace.txt content available on remote site"})

		return
	}

	s.applyStackTraceContent(ref, logsResult, errDetails)
}

// hasStackTraceContent checks if the logs result contains a valid stack trace.
func hasStackTraceContent(logsResult *wordpress.RemoteErrorLogsResult) bool {
	return logsResult != nil && logsResult.StackTraceLog != nil && logsResult.StackTraceLog.Exists && logsResult.StackTraceLog.Content != ""
}

// applyStackTraceContent copies stack trace content to error details and logs it.
func (s *Service) applyStackTraceContent(ref *remoteActionRef, logsResult *wordpress.RemoteErrorLogsResult, errDetails *ExtractedErrorDetails) {
	stLog := logsResult.StackTraceLog
	errDetails.RemotePhpStackTrace = stLog.Content
	errDetails.RemotePhpStackTraceLines = stLog.Lines

	s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_stacktrace",
		Message: fmt.Sprintf("Retrieved PHP stacktrace.txt (%d lines, %d bytes)", stLog.Lines, stLog.TotalSize),
		Details: session.ToJSON(StackTraceLogDetails{Lines: stLog.Lines, TotalSize: int(stLog.TotalSize), Truncated: stLog.Truncated})})

	if s.sessionService != nil && ref.SessionID != "" {
		s.sessionService.Log(session.LogInput{SessionID: ref.SessionID, Level: "info", Step: "remote_php_stacktrace", Message: "PHP stacktrace.txt content from remote site",
			Details: session.ToJSON(StackTraceContentDetails{Content: stLog.Content, Lines: stLog.Lines, Truncated: stLog.Truncated})})
	}
}
