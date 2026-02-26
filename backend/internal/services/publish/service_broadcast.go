package publish

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"wp-plugin-publish/internal/enums/log_level"
	"wp-plugin-publish/internal/enums/stage_status"
	enumstatus "wp-plugin-publish/internal/enums/status"
	"wp-plugin-publish/internal/ws"
)

// runStage executes a stage and captures timing/result
func (s *Service) runStage(name string, fn func() error) Stage {
	start := time.Now()
	err := fn()
	return buildStage(name, start, err)
}

// runStageWithSession executes a stage with session logging and captures timing/result
func (s *Service) runStageWithSession(sessionID, name string, fn func() error) Stage {
	start := time.Now()
	s.sessionLogStageStart(sessionID, strings.ToUpper(name))

	err := fn()
	stage := buildStage(name, start, err)

	s.sessionLogStageEnd(sessionID, strings.ToUpper(name), stage.Status, stage.Duration)
	return stage
}

// buildStage creates a Stage from a name, start time, and error.
func buildStage(name string, start time.Time, err error) Stage {
	stage := Stage{Name: name, Duration: time.Since(start).Milliseconds()}
	if err != nil {
		stage.Status = stagestatus.Failed
		stage.Message = err.Error()
	} else {
		stage.Status = stagestatus.Completed
	}
	return stage
}

// broadcastProgress sends a WebSocket progress event with detailed step info
func (s *Service) broadcastProgress(pluginID, siteID int64, step string, progress int, message string) {
	if s.wsHub == nil {
		return
	}

	eventType := resolveProgressEvent(step)
	stage := mapStepToStage(step)
	status := mapStepToStatus(step)

	ws.Broadcast(s.wsHub, eventType, ws.PublishStageProgressData{
		PluginID: pluginID, SiteID: siteID, Stage: stage, Step: step,
		Status: status, Progress: progress, Total: 100, Message: message,
	})

	s.emitProgressLog(pluginID, siteID, step, stage, message, progress)
}

// emitProgressLog logs the progress event.
func (s *Service) emitProgressLog(pluginID, siteID int64, step, stage, message string, progress int) {
	logLevel := loglevel.Info.String()
	if step == stagestatus.Failed.String() {
		logLevel = loglevel.Error.String()
	}
	s.wsHub.BroadcastPublishLog(ws.OperationLogInput{
		PluginID: pluginID, SiteID: siteID,
		Entry: ws.OperationLogEntry{Level: logLevel, Step: stage, Message: message},
	})
	s.log.Debug("Publish progress", "pluginId", pluginID, "siteId", siteID, "step", step, "stage", stage, "progress", progress, "message", message)
}

// broadcastProgressWithSession sends a WebSocket progress event with session ID
func (s *Service) broadcastProgressWithSession(pluginID, siteID int64, sessionID, step string, progress int, message string) {
	if s.wsHub == nil {
		return
	}

	eventType := resolveProgressEvent(step)
	stage := mapStepToStage(step)
	status := mapStepToStatus(step)

	ws.BroadcastWithSession(s.wsHub, eventType, ws.PublishStageProgressData{
		PluginID: pluginID, SiteID: siteID, Stage: stage, Step: step,
		Status: status, Progress: progress, Total: 100, Message: message,
	}, sessionID)

	s.emitSessionProgressLog(pluginID, siteID, sessionID, step, stage, message, progress)
}

// emitSessionProgressLog logs a session-scoped progress event.
func (s *Service) emitSessionProgressLog(pluginID, siteID int64, sessionID, step, stage, message string, progress int) {
	logLevel := loglevel.Info.String()
	if step == stagestatus.Failed.String() {
		logLevel = loglevel.Error.String()
	}
	s.wsHub.BroadcastPublishLogWithSession(ws.OperationLogInput{
		PluginID: pluginID, SiteID: siteID, SessionID: sessionID,
		Entry: ws.OperationLogEntry{Level: logLevel, Step: stage, Message: message},
	})
	s.sessionLog(sessionID, logLevel, stage, message, nil)
	s.log.Debug("Publish progress", "pluginId", pluginID, "siteId", siteID, "sessionId", sessionID, "step", step, "stage", stage, "progress", progress, "message", message)
}

// resolveProgressEvent maps step to ws event type.
func resolveProgressEvent(step string) string {
	switch step {
	case stagestatus.Started.String():
		return ws.EventPublishStarted
	case stagestatus.Completed.String(), stagestatus.Failed.String():
		return ws.EventPublishComplete
	default:
		return ws.EventPublishProgress
	}
}

// broadcastStageStatus explicitly marks a publish stage as success/error
func (s *Service) broadcastStageStatus(pluginID, siteID int64, stage, status string, progress int, message string, details json.RawMessage) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.PublishStageStatusData{
		PluginID: pluginID, SiteID: siteID, Stage: stage, Step: stage,
		Status: status, Progress: progress, Total: 100, Message: message, Details: details,
	})

	level := loglevel.Info.String()
	if status == loglevel.Error.String() {
		level = loglevel.Error.String()
	}
	s.wsHub.BroadcastPublishLog(ws.OperationLogInput{
		PluginID: pluginID, SiteID: siteID,
		Entry: ws.OperationLogEntry{Level: level, Step: stage, Message: message, Details: details},
	})
}

// broadcastStageLog sends a detailed log entry with structured context
func (s *Service) broadcastStageLog(pluginID, siteID int64, sessionID, level, stage string, ctx StageContext) {
	message := ctx.What
	if ctx.Result != "" {
		message = fmt.Sprintf("%s → %s", ctx.What, ctx.Result)
	}

	detailsJSON, _ := json.Marshal(ctx)
	s.broadcastDetailedLog(pluginID, siteID, level, stage, message, detailsJSON)
	s.sessionLog(sessionID, level, stage, message, detailsJSON)
}

// broadcastDetailedLog sends a detailed log entry with structured data
func (s *Service) broadcastDetailedLog(pluginID, siteID int64, level, step, message string, details json.RawMessage) {
	if s.wsHub == nil {
		return
	}
	s.wsHub.BroadcastPublishLog(ws.OperationLogInput{
		PluginID: pluginID, SiteID: siteID,
		Entry: ws.OperationLogEntry{Level: level, Step: step, Message: message, Details: details},
	})

	pluginName, siteName, siteURL := s.resolveNames(pluginID, siteID, details)
	s.logWithLevel(level, message, pluginName, siteName, siteURL, pluginID, siteID, step)
}

// logWithLevel dispatches a log message at the appropriate level.
func (s *Service) logWithLevel(level, message, pluginName, siteName, siteURL string, pluginID, siteID int64, step string) {
	logFields := buildLogFields(pluginName, siteName, siteURL, pluginID, siteID, step)
	switch level {
	case "error":
		s.log.Error(message, logFields...)
	case "warn":
		s.log.Warn(message, logFields...)
	case "debug":
		s.log.Debug(message, logFields...)
	default:
		s.log.Info(message, logFields...)
	}
}

// buildLogFields constructs log fields for detailed log messages.
func buildLogFields(pluginName, siteName, siteURL string, pluginID, siteID int64, step string) []any {
	fields := []any{"plugin", pluginName, "site", siteName}
	if siteURL != "" {
		fields = append(fields, "siteUrl", siteURL)
	}
	return append(fields, "pluginId", pluginID, "siteId", siteID, "step", step)
}

// broadcastStageComplete sends a stage_complete event for frontend tracking
func (s *Service) broadcastStageComplete(pluginID, siteID int64, sessionID, stageName, status string, durationMs int64, details json.RawMessage) {
	if s.wsHub == nil {
		return
	}
	ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.PublishStageCompleteData{
		Type: "stage_complete", SessionID: sessionID, Stage: stageName,
		Status: status, Duration: durationMs, PluginID: pluginID, SiteID: siteID, Details: details,
	})
}

// resolveNames looks up plugin/site names from details or DB
func (s *Service) resolveNames(pluginID, siteID int64, details json.RawMessage) (string, string, string) {
	pluginName, siteName, siteURL := parseNameDetails(details)
	pluginName = s.resolvePluginName(pluginID, pluginName)
	siteName, siteURL = s.resolveSiteNames(siteID, siteName, siteURL)
	return pluginName, siteName, siteURL
}

// parseNameDetails extracts names from JSON details.
func parseNameDetails(details json.RawMessage) (string, string, string) {
	var parsed struct {
		PluginName string `json:",omitempty"`
		SiteName   string `json:",omitempty"`
		SiteURL    string `json:",omitempty"`
	}
	if len(details) > 0 {
		_ = json.Unmarshal(details, &parsed)
	}
	return parsed.PluginName, parsed.SiteName, parsed.SiteURL
}

// resolvePluginName fetches plugin name from DB if not provided.
func (s *Service) resolvePluginName(pluginID int64, name string) string {
	if name == "" && pluginID > 0 {
		if pResult := s.pluginService.GetByID(context.Background(), pluginID); pResult.IsSafe() {
			return pResult.Value().Name
		}
	}
	if name == "" {
		return fmt.Sprintf("plugin#%d", pluginID)
	}
	return name
}

// resolveSiteNames fetches site name/URL from DB if not provided.
func (s *Service) resolveSiteNames(siteID int64, name, url string) (string, string) {
	if (name == "" || url == "") && siteID > 0 {
		if siteInfo, _, err := s.getSiteCredentials(context.Background(), siteID); err == nil {
			if name == "" {
				name = siteInfo.Name
			}
			if url == "" {
				url = siteInfo.URL
			}
		}
	}
	if name == "" {
		name = fmt.Sprintf("site#%d", siteID)
	}
	return name, url
}

// mapStepToStage maps step names to stage names for frontend compatibility
func mapStepToStage(step string) string {
	switch step {
	case stagestatus.Started.String():
		return "backup"
	case "packaging":
		return "package"
	case "uploading":
		return "upload"
	case "activating":
		return "activate"
	case "cleanup":
		return "cleanup"
	default:
		return step
	}
}

// mapStepToStatus maps step names to status strings
func mapStepToStatus(step string) string {
	switch step {
	case stagestatus.Completed.String():
		return enumstatus.Success.String()
	case stagestatus.Failed.String():
		return loglevel.Error.String()
	default:
		return stagestatus.Running.String()
	}
}

// Session logging helper methods

func (s *Service) sessionLog(sessionID, level, step, message string, details json.RawMessage) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.Log(sessionID, level, step, message, details)
}

func (s *Service) sessionLogStageStart(sessionID, stageName string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.LogStageStart(sessionID, stageName)
}

func (s *Service) sessionLogStageEnd(sessionID, stageName string, status stagestatus.Variant, durationMs int64) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.LogStageEnd(sessionID, stageName, status.String(), durationMs)
}

func (s *Service) endSession(sessionID, status, errorMsg string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.EndSession(sessionID, status, errorMsg)
}
