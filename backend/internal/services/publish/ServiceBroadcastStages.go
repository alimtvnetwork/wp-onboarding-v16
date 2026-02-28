package publish

import (
	"context"
	"encoding/json"
	"fmt"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
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
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
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
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
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
		SessionID: input.SessionId,
		Stage:     input.StageName.Value(),
		Status:    input.Status,
		Duration:  input.DurationMs,
		PluginID:  input.PluginId,
		SiteID:    input.SiteId,
		Details:   input.Details,
	}
	ws.Broadcast(s.wsHub, ws.EventPublishProgress, completeData)
}

// ─── Detailed Logging ────────────────────────────────────────────────────────

// broadcastStageLog sends a detailed log entry with structured context
func (s *Service) broadcastStageLog(input StageLogInput) {
	message := resolveStageLogMessage(input.Ctx)
	detailsJSON, _ := json.Marshal(input.Ctx)

	s.broadcastStageLogDetailed(input, message, detailsJSON)
	s.broadcastStageLogSession(input, message, detailsJSON)
}

// broadcastStageLogDetailed sends the detailed log entry for a stage.
func (s *Service) broadcastStageLogDetailed(input StageLogInput, message string, details json.RawMessage) {
	s.broadcastDetailedLog(DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    input.Level,
		Step:     input.Stage,
		Message:  message,
		Details:  details,
	})
}

// broadcastStageLogSession sends the session log entry for a stage.
func (s *Service) broadcastStageLogSession(input StageLogInput, message string, details json.RawMessage) {
	s.sessionLog(sessionLogInput{
		SessionId: input.SessionId,
		Level:     input.Level,
		Step:      input.Stage,
		Message:   message,
		Details:   details,
	})
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
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
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
		SiteUrl:    names.SiteURL,
		PluginId:   input.PluginId,
		SiteId:     input.SiteId,
		Step:       input.Step,
	}
	s.logWithLevel(input.Level, input.Message, ctx)
}

// logContext holds resolved names for structured log output.
type logContext struct {
	PluginName string
	SiteName   string
	SiteUrl    string
	PluginId   int64
	SiteId     int64
	Step       publishstep.Variant
}

// logWithLevel dispatches a log message at the appropriate level.
func (s *Service) logWithLevel(level loglevel.Variant, message string, ctx logContext) {
	logFields := buildLogFields(ctx)
	switch {
	case level.IsError():
		s.log.Error(message, logFields...)
	case level.IsWarn():
		s.log.Warn(message, logFields...)
	case level.IsDebug():
		s.log.Debug(message, logFields...)
	default:
		s.log.Info(message, logFields...)
	}
}

// buildLogFields constructs log fields for detailed log messages.
func buildLogFields(ctx logContext) []any {
	fields := []any{"plugin", ctx.PluginName, "site", ctx.SiteName}
	hasSiteUrl := ctx.SiteUrl != ""

	if hasSiteUrl {
		fields = append(fields, "siteUrl", ctx.SiteUrl)
	}

	return append(fields, "pluginId", ctx.PluginId, "siteId", ctx.SiteId, "step", ctx.Step.Value())
}

// ─── Name Resolution ─────────────────────────────────────────────────────────

// resolvedNames holds the resolved plugin, site name and URL.
type resolvedNames struct {
	PluginName string
	SiteName   string
	SiteURL    string
}

// resolveNames looks up plugin/site names from details or DB
func (s *Service) resolveNames(pluginId, siteId int64, details json.RawMessage) *resolvedNames {
	parsed := parseNameDetails(details)
	pluginName := s.resolvePluginName(pluginId, parsed.PluginName)
	siteName, siteUrl := s.resolveSiteNames(siteId, parsed.SiteName, parsed.SiteURL)

	return &resolvedNames{PluginName: pluginName, SiteName: siteName, SiteURL: siteUrl}
}

// parseNameDetails extracts names from JSON details.
func parseNameDetails(details json.RawMessage) *resolvedNames {
	var parsed struct {
		PluginName string `json:",omitempty"`
		SiteName   string `json:",omitempty"`
		SiteURL    string `json:",omitempty"`
	}
	hasDetails := len(details) > 0

	if hasDetails {
		_ = json.Unmarshal(details, &parsed)
	}

	return &resolvedNames{PluginName: parsed.PluginName, SiteName: parsed.SiteName, SiteURL: parsed.SiteURL}
}

// resolvePluginName fetches plugin name from DB if not provided.
func (s *Service) resolvePluginName(pluginId int64, name string) string {
	isNameMissing := name == ""
	hasPluginId   := pluginId > 0

	if isNameMissing && hasPluginId {
		pResult := s.pluginService.GetByID(context.Background(), pluginId)
		if pResult.IsSafe() {
			return pResult.Value().Name
		}
	}
	isNameMissing := name == ""

	if isNameMissing {
		return fmt.Sprintf("plugin#%d", pluginId)
	}

	return name
}

// resolveSiteNames fetches site name/URL from DB if not provided.
func (s *Service) resolveSiteNames(siteId int64, name, url string) (string, string) {
	isNameMissing := name == ""
	isUrlMissing  := url == ""
	hasIncomplete := isNameMissing || isUrlMissing
	hasSiteId     := siteId > 0

	if hasIncomplete && hasSiteId {
		creds, err := s.getSiteCredentials(context.Background(), siteId)
		if err == nil {
			if isNameMissing {
				name = creds.Site.Name
			}

			if isUrlMissing {
				url = creds.Site.URL
			}
		}
	}
	isNameMissing := name == ""

	if isNameMissing {
		name = fmt.Sprintf("site#%d", siteId)
	}

	return name, url
}
