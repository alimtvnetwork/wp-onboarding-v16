// Package site — remote PHP error fetching and attachment
package site

import (
	"encoding/json"
	"fmt"

	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/wordpress"
)

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

	if s.isPhpErrorResultEmpty(ref, result) {
		return
	}

	s.attachPhpErrorEntries(ref, result, errDetails)
}

// isPhpErrorResultEmpty checks if the result is empty and logs if so.
func (s *Service) isPhpErrorResultEmpty(ref *remoteActionRef, result *wordpress.RemoteErrorSessionsResult) bool {
	isResultMissing := result == nil
	isEntriesEmpty := !isResultMissing && len(result.Entries) == 0
	isEmpty := isResultMissing || isEntriesEmpty

	if isEmpty {
		s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_errors", Message: "No recent PHP error sessions found on remote site"})

		return true
	}

	return false
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
			raw, marshalErr := json.Marshal(entry.StackTraceFrames)
			if marshalErr == nil {
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

	s.applyStackTraceIfPresent(ref, logsResult, errDetails)
}

// applyStackTraceIfPresent applies the stack trace if it exists, otherwise logs absence.
func (s *Service) applyStackTraceIfPresent(ref *remoteActionRef, logsResult *wordpress.RemoteErrorLogsResult, errDetails *ExtractedErrorDetails) {
	if isStackTraceMissing(logsResult) {
		s.logRemoteAction(ref, RemoteActionLogInput{Level: "info", Step: "fetch_php_stacktrace", Message: "No stacktrace.txt content available on remote site"})

		return
	}

	s.applyStackTraceContent(ref, logsResult, errDetails)
}

// hasStackTraceContent checks if the logs result contains a valid stack trace.
func hasStackTraceContent(logsResult *wordpress.RemoteErrorLogsResult) bool {
	return logsResult != nil && logsResult.StackTraceLog != nil && logsResult.StackTraceLog.Exists && logsResult.StackTraceLog.Content != ""
}

// isStackTraceMissing returns true if the logs result does NOT contain a valid stack trace.
func isStackTraceMissing(logsResult *wordpress.RemoteErrorLogsResult) bool {
	return !hasStackTraceContent(logsResult)
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
