package site

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

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

	apiErr, ok := err.(*wordpress.APIError)
	if !ok {
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

// logRemoteAction logs a remote plugin action to session and WebSocket.
func (s *Service) logRemoteAction(sessionId string, siteId int64, action, level, step, message string, details json.RawMessage) {
	s.emitRemoteActionToSession(sessionId, siteId, action, level, step, message, details)
	logCtx := s.resolveRemoteActionLogContext(siteId, details)
	s.emitRemoteActionToLogger(level, message, siteId, action, step, logCtx)
}

// emitRemoteActionToSession sends logs to session service and WebSocket.
func (s *Service) emitRemoteActionToSession(sessionId string, siteId int64, action, level, step, message string, details json.RawMessage) {
	if s.sessionService != nil && sessionId != "" {
		s.sessionService.Log(sessionId, level, step, message, details)
	}
	if s.wsHub != nil {
		s.wsHub.BroadcastRemotePluginLogWithSession(RemotePluginLogInput{
			SiteID: siteId, Action: action, SessionID: sessionId,
			Level: level, Step: step, Message: message, Details: details,
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

// emitRemoteActionToLogger writes the log entry at the appropriate level.
func (s *Service) emitRemoteActionToLogger(level, message string, siteId int64, action, step string, ctx remoteActionResolvedContext) {
	logFields := buildRemoteActionLogFields(siteId, action, step, ctx)

	if level == loglevel.Error.String() {
		s.log.Error(message, logFields...)
	} else {
		s.log.Debug(message, logFields...)
	}
}

// buildRemoteActionLogFields constructs the structured log fields.
func buildRemoteActionLogFields(siteId int64, action, step string, ctx remoteActionResolvedContext) []any {
	logFields := []any{"site", ctx.SiteName}
	if ctx.SiteUrl != "" {
		logFields = append(logFields, "siteUrl", ctx.SiteUrl)
	}
	logFields = append(logFields, "siteId", siteId, "action", action, "step", step)
	if ctx.PluginSlug != "" {
		logFields = append(logFields, "pluginSlug", ctx.PluginSlug)
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
func (s *Service) fetchAndAttachRemotePhpErrors(client *wordpress.Client, sessionId string, siteId int64, action, pluginSlug, siteName, siteUrl string, errDetails *ExtractedErrorDetails) {
	s.fetchAndAttachPhpErrorSessions(client, sessionId, siteId, action, errDetails)
	s.fetchAndAttachPhpStackTrace(client, sessionId, siteId, action, errDetails)
}

// fetchAndAttachPhpErrorSessions pulls recent PHP error entries from the remote site.
func (s *Service) fetchAndAttachPhpErrorSessions(client *wordpress.Client, sessionId string, siteId int64, action string, errDetails *ExtractedErrorDetails) {
	s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_errors", "Pulling recent PHP error sessions from remote site...", nil)

	result, fetchErr := client.FetchRemoteErrorSessions("error", "", 0, 10, 0)
	if fetchErr != nil {
		s.logRemoteAction(sessionId, siteId, action, "warn", "fetch_php_errors", fmt.Sprintf("Could not fetch remote PHP errors: %s", fetchErr.Error()), nil)
		return
	}

	if result == nil || len(result.Entries) == 0 {
		s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_errors", "No recent PHP error sessions found on remote site", nil)
		return
	}

	s.attachPhpErrorEntries(sessionId, siteId, action, result, errDetails)
}

// attachPhpErrorEntries collects and attaches PHP error entries to the error details.
func (s *Service) attachPhpErrorEntries(sessionId string, siteId int64, action string, result *wordpress.RemoteErrorSessionsResult, errDetails *ExtractedErrorDetails) {
	phpErrors := collectPhpErrorEntries(result.Entries)
	errDetails.RemotePhpErrors = phpErrors
	errDetails.RemotePhpErrorCount = len(result.Entries)
	if result.Flash.HasUnseen {
		errDetails.RemotePhpFlashUnseen = result.Flash.UnseenCount
	}

	s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_errors", fmt.Sprintf("Retrieved %d recent PHP error(s) from remote site", len(result.Entries)), session.ToJSON(PhpErrorCountDetail{PhpErrorCount: len(result.Entries)}))
	s.logPhpErrorsToSession(sessionId, result.Entries)
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
		s.sessionService.Log(sessionId, "error", "remote_php_error", entry.Message, session.ToJSON(PhpErrorDetail{PhpFile: entry.File, PhpLine: derefInt(entry.Line), PhpLevel: entry.Level, PhpCreated: entry.CreatedAt}))
	}
}

// fetchAndAttachPhpStackTrace pulls the PHP stacktrace.txt from the remote site.
func (s *Service) fetchAndAttachPhpStackTrace(client *wordpress.Client, sessionId string, siteId int64, action string, errDetails *ExtractedErrorDetails) {
	s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_stacktrace", "Pulling PHP stacktrace.txt from remote site...", nil)

	logsResult, logsErr := client.FetchRemoteErrorLogs()
	if logsErr != nil {
		s.logRemoteAction(sessionId, siteId, action, "warn", "fetch_php_stacktrace", fmt.Sprintf("Could not fetch remote error logs: %s", logsErr.Error()), nil)
		return
	}

	if !hasStackTraceContent(logsResult) {
		s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_stacktrace", "No stacktrace.txt content available on remote site", nil)
		return
	}

	s.applyStackTraceContent(sessionId, siteId, action, logsResult, errDetails)
}

// hasStackTraceContent checks if the logs result contains a valid stack trace.
func hasStackTraceContent(logsResult *wordpress.RemoteErrorLogsResult) bool {
	return logsResult != nil && logsResult.StackTraceLog != nil && logsResult.StackTraceLog.Exists && logsResult.StackTraceLog.Content != ""
}

// applyStackTraceContent copies stack trace content to error details and logs it.
func (s *Service) applyStackTraceContent(sessionId string, siteId int64, action string, logsResult *wordpress.RemoteErrorLogsResult, errDetails *ExtractedErrorDetails) {
	stLog := logsResult.StackTraceLog
	errDetails.RemotePhpStackTrace = stLog.Content
	errDetails.RemotePhpStackTraceLines = stLog.Lines

	s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_stacktrace",
		fmt.Sprintf("Retrieved PHP stacktrace.txt (%d lines, %d bytes)", stLog.Lines, stLog.TotalSize),
		session.ToJSON(StackTraceLogDetails{Lines: stLog.Lines, TotalSize: int(stLog.TotalSize), Truncated: stLog.Truncated}))

	if s.sessionService != nil && sessionId != "" {
		s.sessionService.Log(sessionId, "info", "remote_php_stacktrace", "PHP stacktrace.txt content from remote site",
			session.ToJSON(StackTraceContentDetails{Content: stLog.Content, Lines: stLog.Lines, Truncated: stLog.Truncated}))
	}
}
