package publish

import (
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	"wp-plugin-publish/internal/ws"
)

// ─── Stage Status & Completion ───────────────────────────────────────────────

// broadcastStageStatus explicitly marks a publish stage as success/error
func (s *Service) broadcastStageStatus(input StageStatusInput) {
	isHubMissing := s.wsHub == nil

	if isHubMissing {
		return
	}

	stageData := buildStageStatusData(input)
	ws.Broadcast(s.wsHub, ws.EventPublishProgress, stageData)

	s.emitStageStatusLog(input)
}

// buildStageStatusData constructs the WebSocket stage status data.
func buildStageStatusData(input StageStatusInput) ws.PublishStageStatusData {
	return ws.PublishStageStatusData{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Stage:    input.Stage.Value(),
		Step:     input.Stage.Value(),
		Status:   input.Status,
		Progress: input.Progress,
		Total:    100,
		Message:  input.Message,
		Details:  input.Details,
	}
}

// emitStageStatusLog writes the operation log entry for stage status.
func (s *Service) emitStageStatusLog(input StageStatusInput) {
	level := resolveStageStatusLevel(input.Status)

	logEntry := buildStageStatusLogEntry(input, level)
	s.wsHub.BroadcastPublishLog(logEntry)
}

// resolveStageStatusLevel returns error level if status is error, info otherwise.
func resolveStageStatusLevel(status string) loglevel.Variant {
	isErrorStatus := status == loglevel.Error.Lower()

	if isErrorStatus {
		return loglevel.Error
	}

	return loglevel.Info
}

// buildStageStatusLogEntry constructs an operation log entry for stage status.
func buildStageStatusLogEntry(input StageStatusInput, level loglevel.Variant) ws.OperationLogInput {
	return ws.OperationLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Entry: ws.OperationLogEntry{
			Level:   level.Lower(),
			Step:    input.Stage.Value(),
			Message: input.Message,
			Details: input.Details,
		},
	}
}

// broadcastStageComplete sends a stage_complete event for frontend tracking
func (s *Service) broadcastStageComplete(input StageCompleteInput) {
	isHubMissing := s.wsHub == nil

	if isHubMissing {
		return
	}

	completeData := ws.PublishStageCompleteData{
		Type:      "stage_complete",
		SessionId: input.SessionId,
		Stage:     input.StageName.Value(),
		Status:    input.Status,
		Duration:  input.DurationMs,
		PluginId:  input.PluginId,
		SiteId:    input.SiteId,
		Details:   input.Details,
	}
	ws.Broadcast(s.wsHub, ws.EventPublishProgress, completeData)
}

// ─── Detailed Logging ────────────────────────────────────────────────────────

// broadcastStageLog sends a detailed log entry with structured context
func (s *Service) broadcastStageLog(input StageLogInput) {
	message := resolveStageLogMessage(input.Ctx)
	detailsJson, _ := json.Marshal(input.Ctx)

	s.broadcastStageLogDetailed(input, message, detailsJson)
	s.broadcastStageLogSession(input, message, detailsJson)
}

// broadcastStageLogDetailed sends the detailed log entry for a stage.
func (s *Service) broadcastStageLogDetailed(input StageLogInput, message string, details json.RawMessage) {
	detailedLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    input.Level,
		Step:     input.Stage,
		Message:  message,
		Details:  details,
	}
	s.broadcastDetailedLog(detailedLog)
}

// broadcastStageLogSession sends the session log entry for a stage.
func (s *Service) broadcastStageLogSession(input StageLogInput, message string, details json.RawMessage) {
	sessionLog := sessionLogInput{
		SessionId: input.SessionId,
		Level:     input.Level,
		Step:      input.Stage,
		Message:   message,
		Details:   details,
	}
	s.sessionLog(sessionLog)
}

// resolveStageLogMessage builds the message string from stage context.
func resolveStageLogMessage(ctx StageContext) string {
	hasResult := ctx.Result != ""

	if hasResult {
		return fmt.Sprintf("%s → %s", ctx.What, ctx.Result)
	}

	return ctx.What
}

// broadcastDetailedLog sends a detailed log entry with structured data
func (s *Service) broadcastDetailedLog(input DetailedLogInput) {
	isHubMissing := s.wsHub == nil

	if isHubMissing {
		return
	}

	s.emitDetailedLogEntry(input)
	s.emitDetailedLogToLogger(input)
}

// emitDetailedLogEntry broadcasts the log entry to WebSocket.
func (s *Service) emitDetailedLogEntry(input DetailedLogInput) {
	logEntry := ws.OperationLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Entry: ws.OperationLogEntry{
			Level:   input.Level.Lower(),
			Step:    input.Step.Value(),
			Message: input.Message,
			Details: input.Details,
		},
	}
	s.wsHub.BroadcastPublishLog(logEntry)
}

// emitDetailedLogToLogger writes the detailed log to the structured logger.
func (s *Service) emitDetailedLogToLogger(input DetailedLogInput) {
	names := s.resolveNames(input.PluginId, input.SiteId, input.Details)

	ctx := logContext{
		PluginName: names.PluginName,
		SiteName:   names.SiteName,
		SiteUrl:    names.SiteUrl,
		PluginId:   input.PluginId,
		SiteId:     input.SiteId,
		Step:       input.Step,
	}
	s.logWithLevel(input.Level, input.Message, ctx)
}
