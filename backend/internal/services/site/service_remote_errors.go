package site

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

	loglevel "wp-plugin-publish/internal/enums/log_level"
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
	if s.sessionService != nil && sessionId != "" {
		s.sessionService.Log(sessionId, level, step, message, details)
	}
	if s.wsHub != nil {
		s.wsHub.BroadcastRemotePluginLogWithSession(siteId, action, sessionId, level, step, message, details)
	}

	var logCtx remoteActionLogContext
	if len(details) > 0 {
		_ = json.Unmarshal(details, &logCtx)
	}

	siteName := logCtx.SiteName
	siteUrl := logCtx.SiteUrl
	pluginSlug := logCtx.PluginSlug
	if (siteName == "" || siteUrl == "") && siteId > 0 {
		if siteResult := s.GetById(context.Background(), siteId); siteResult.IsSafe() {
			site := siteResult.Value()
			if siteName == "" {
				siteName = site.Name
			}
			if siteUrl == "" {
				siteUrl = site.Url
			}
		}
	}
	if siteName == "" {
		siteName = fmt.Sprintf("site#%d", siteId)
	}

	logFields := []any{"site", siteName}
	if siteUrl != "" {
		logFields = append(logFields, "siteUrl", siteUrl)
	}
	logFields = append(logFields, "siteId", siteId, "action", action, "step", step)
	if pluginSlug != "" {
		logFields = append(logFields, "pluginSlug", pluginSlug)
	}

	if level == loglevel.Error.String() {
		s.log.Error(message, logFields...)
	} else {
		s.log.Debug(message, logFields...)
	}
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

	phpErrors := make([]PhpErrorEntry, 0, len(result.Entries))
	for _, entry := range result.Entries {
		phpErr := PhpErrorEntry{Id: entry.ID, Level: entry.Level, Message: entry.Message, File: entry.File, Line: derefInt(entry.Line), CreatedAt: entry.CreatedAt}
		if len(entry.StackTraceFrames) > 0 {
			if raw, marshalErr := json.Marshal(entry.StackTraceFrames); marshalErr == nil {
				phpErr.StackTraceFrames = raw
			}
		}
		phpErrors = append(phpErrors, phpErr)
	}
	errDetails.RemotePhpErrors = phpErrors
	errDetails.RemotePhpErrorCount = len(result.Entries)
	if result.Flash.HasUnseen {
		errDetails.RemotePhpFlashUnseen = result.Flash.UnseenCount
	}

	s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_errors", fmt.Sprintf("Retrieved %d recent PHP error(s) from remote site", len(result.Entries)), session.ToJSON(PhpErrorCountDetail{PhpErrorCount: len(result.Entries)}))

	if s.sessionService != nil && sessionId != "" {
		for _, entry := range result.Entries {
			s.sessionService.Log(sessionId, "error", "remote_php_error", entry.Message, session.ToJSON(PhpErrorDetail{PhpFile: entry.File, PhpLine: derefInt(entry.Line), PhpLevel: entry.Level, PhpCreated: entry.CreatedAt}))
		}
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

	if logsResult == nil || logsResult.StackTraceLog == nil || !logsResult.StackTraceLog.Exists || logsResult.StackTraceLog.Content == "" {
		s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_stacktrace", "No stacktrace.txt content available on remote site", nil)
		return
	}

	errDetails.RemotePhpStackTrace = logsResult.StackTraceLog.Content
	errDetails.RemotePhpStackTraceLines = logsResult.StackTraceLog.Lines
	s.logRemoteAction(sessionId, siteId, action, "info", "fetch_php_stacktrace", fmt.Sprintf("Retrieved PHP stacktrace.txt (%d lines, %d bytes)", logsResult.StackTraceLog.Lines, logsResult.StackTraceLog.TotalSize), session.ToJSON(StackTraceLogDetails{Lines: logsResult.StackTraceLog.Lines, TotalSize: int(logsResult.StackTraceLog.TotalSize), Truncated: logsResult.StackTraceLog.Truncated}))
	if s.sessionService != nil && sessionId != "" {
		s.sessionService.Log(sessionId, "info", "remote_php_stacktrace", "PHP stacktrace.txt content from remote site", session.ToJSON(StackTraceContentDetails{Content: logsResult.StackTraceLog.Content, Lines: logsResult.StackTraceLog.Lines, Truncated: logsResult.StackTraceLog.Truncated}))
	}
}
