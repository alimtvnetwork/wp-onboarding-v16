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
	startLog := RemoteActionLogInput{
		Level:   "info",
		Step:    "fetch_php_errors",
		Message: "Pulling recent PHP error sessions from remote site...",
	}

	s.logRemoteAction(ref, startLog)

	fetchInput := wordpress.ErrorSessionsInput{
		Level: "error",
		Limit: 10,
	}

	result, fetchErr := ref.Client.FetchRemoteErrorSessions(fetchInput)
	if fetchErr != nil {
		errLog := RemoteActionLogInput{
			Level:   "warn",
			Step:    "fetch_php_errors",
			Message: fmt.Sprintf("Could not fetch remote PHP errors: %s", fetchErr.Error()),
		}

		s.logRemoteAction(ref, errLog)

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
		emptyLog := RemoteActionLogInput{
			Level:   "info",
			Step:    "fetch_php_errors",
			Message: "No recent PHP error sessions found on remote site",
		}

		s.logRemoteAction(ref, emptyLog)

		return true
	}

	return false
}

// attachPhpErrorEntries collects and attaches PHP error entries to the error details.
func (s *Service) attachPhpErrorEntries(ref *remoteActionRef, result *wordpress.RemoteErrorSessionsResult, errDetails *ExtractedErrorDetails) {
	populatePhpErrorDetails(result, errDetails)
	s.logPhpErrorAttachment(ref, result)
}

// populatePhpErrorDetails fills error details with collected PHP errors and flash state.
func populatePhpErrorDetails(result *wordpress.RemoteErrorSessionsResult, errDetails *ExtractedErrorDetails) {
	errDetails.RemotePhpErrors = collectPhpErrorEntries(result.Entries)
	errDetails.RemotePhpErrorCount = len(result.Entries)

	if result.Flash.HasUnseen {
		errDetails.RemotePhpFlashUnseen = result.Flash.UnseenCount
	}
}

// logPhpErrorAttachment logs the PHP error retrieval and writes entries to session.
func (s *Service) logPhpErrorAttachment(ref *remoteActionRef, result *wordpress.RemoteErrorSessionsResult) {
	countDetail := PhpErrorCountDetail{
		PhpErrorCount: len(result.Entries),
	}

	successLog := RemoteActionLogInput{
		Level:   "info",
		Step:    "fetch_php_errors",
		Message: fmt.Sprintf("Retrieved %d recent PHP error(s) from remote site", len(result.Entries)),
		Details: session.ToJson(countDetail),
	}

	s.logRemoteAction(ref, successLog)
	s.logPhpErrorsToSession(ref.SessionId, result.Entries)
}

// collectPhpErrorEntries converts remote error entries to PhpErrorEntry slice.
func collectPhpErrorEntries(entries []wordpress.RemoteErrorSessionEntry) []PhpErrorEntry {
	phpErrors := make([]PhpErrorEntry, 0, len(entries))

	for _, entry := range entries {
		phpErrors = append(phpErrors, buildPhpErrorEntry(entry))
	}

	return phpErrors
}

// buildPhpErrorEntry converts a single remote error entry to a PhpErrorEntry.
func buildPhpErrorEntry(entry wordpress.RemoteErrorSessionEntry) PhpErrorEntry {
	phpErr := PhpErrorEntry{
		Id:        entry.ID,
		Level:     entry.Level,
		Message:   entry.Message,
		File:      entry.File,
		Line:      derefInt(entry.Line),
		CreatedAt: entry.CreatedAt,
	}

	marshalStackFrames(&phpErr, entry.StackTraceFrames)

	return phpErr
}

// marshalStackFrames serializes stack trace frames into the PHP error entry.
func marshalStackFrames(phpErr *PhpErrorEntry, frames []wordpress.RemoteStackTraceFrame) {
	hasNoFrames := len(frames) == 0

	if hasNoFrames {
		return
	}

	raw, marshalErr := json.Marshal(frames)

	if marshalErr == nil {
		phpErr.StackTraceFrames = raw
	}
}

// logPhpErrorsToSession writes individual PHP errors to the session log.
func (s *Service) logPhpErrorsToSession(sessionId string, entries []wordpress.RemoteErrorSessionEntry) {
	if s.sessionService == nil || sessionId == "" {
		return
	}

	for _, entry := range entries {
		s.sessionService.Log(buildPhpErrorLogInput(sessionId, entry))
	}
}

// buildPhpErrorLogInput creates a session log input for a single PHP error entry.
func buildPhpErrorLogInput(sessionId string, entry wordpress.RemoteErrorSessionEntry) session.LogInput {
	detail := PhpErrorDetail{
		PhpFile: entry.File, PhpLine: derefInt(entry.Line),
		PhpLevel: entry.Level, PhpCreated: entry.CreatedAt,
	}

	return session.LogInput{
		SessionId: sessionId,
		Level:     "error",
		Step:      "remote_php_error",
		Message:   entry.Message,
		Details:   session.ToJson(detail),
	}
}

// fetchAndAttachPhpStackTrace pulls the PHP stacktrace.txt from the remote site.
func (s *Service) fetchAndAttachPhpStackTrace(ref *remoteActionRef, errDetails *ExtractedErrorDetails) {
	startLog := RemoteActionLogInput{
		Level:   "info",
		Step:    "fetch_php_stacktrace",
		Message: "Pulling PHP stacktrace.txt from remote site...",
	}

	s.logRemoteAction(ref, startLog)

	logsResult, logsErr := ref.Client.FetchRemoteErrorLogs()
	if logsErr != nil {
		errLog := RemoteActionLogInput{
			Level:   "warn",
			Step:    "fetch_php_stacktrace",
			Message: fmt.Sprintf("Could not fetch remote error logs: %s", logsErr.Error()),
		}

		s.logRemoteAction(ref, errLog)

		return
	}

	s.applyStackTraceIfPresent(ref, logsResult, errDetails)
}

// applyStackTraceIfPresent applies the stack trace if it exists, otherwise logs absence.
func (s *Service) applyStackTraceIfPresent(ref *remoteActionRef, logsResult *wordpress.RemoteErrorLogsResult, errDetails *ExtractedErrorDetails) {
	if isStackTraceMissing(logsResult) {
		absentLog := RemoteActionLogInput{
			Level:   "info",
			Step:    "fetch_php_stacktrace",
			Message: "No stacktrace.txt content available on remote site",
		}

		s.logRemoteAction(ref, absentLog)

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

	logDetails := StackTraceLogDetails{
		Lines: stLog.Lines, TotalSize: int(stLog.TotalSize), Truncated: stLog.Truncated,
	}

	s.logRemoteAction(ref, RemoteActionLogInput{
		Level:   "info",
		Step:    "fetch_php_stacktrace",
		Message: fmt.Sprintf("Retrieved PHP stacktrace.txt (%d lines, %d bytes)", stLog.Lines, stLog.TotalSize),
		Details: session.ToJson(logDetails),
	})

	s.logStackTraceToSession(ref, stLog)
}

// logStackTraceToSession writes the stack trace content to the session log.
func (s *Service) logStackTraceToSession(ref *remoteActionRef, stLog *wordpress.StackTraceLog) {
	isSessionUnavailable := s.sessionService == nil || ref.SessionId == ""

	if isSessionUnavailable {
		return
	}

	contentDetail := StackTraceContentDetails{
		Content: stLog.Content, Lines: stLog.Lines, Truncated: stLog.Truncated,
	}

	s.sessionService.Log(session.LogInput{
		SessionId: ref.SessionId,
		Level:     "info",
		Step:      "remote_php_stacktrace",
		Message:   "PHP stacktrace.txt content from remote site",
		Details:   session.ToJson(contentDetail),
	})
}
