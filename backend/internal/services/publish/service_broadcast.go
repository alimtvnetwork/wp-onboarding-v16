package publish

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	enumstatus "wp-plugin-publish/internal/enums/status"
	"wp-plugin-publish/internal/ws"
)

// runStage executes a stage and captures timing/result
func (s *Service) runStage(name string, fn func() error) Stage {
	start := time.Now()
	stage := Stage{
		Name:   name,
		Status: stagestatus.Running,
	}

	err := fn()
	stage.Duration = time.Since(start).Milliseconds()

	if err != nil {
		stage.Status = stagestatus.Failed
		stage.Message = err.Error()
	} else {
		stage.Status = stagestatus.Completed
	}

	return stage
}

// runStageWithSession executes a stage with session logging and captures timing/result
func (s *Service) runStageWithSession(sessionID, name string, fn func() error) Stage {
	start := time.Now()
	stage := Stage{
		Name:   name,
		Status: stagestatus.Running,
	}

	if s.sessionService != nil && sessionID != "" {
		s.sessionService.LogStageStart(sessionID, strings.ToUpper(name))
	}

	err := fn()
	stage.Duration = time.Since(start).Milliseconds()

	if err != nil {
		stage.Status = stagestatus.Failed
		stage.Message = err.Error()
	} else {
		stage.Status = stagestatus.Completed
	}

	if s.sessionService != nil && sessionID != "" {
		s.sessionService.LogStageEnd(sessionID, strings.ToUpper(name), stage.Status, stage.Duration)
	}

	return stage
}

// broadcastProgress sends a WebSocket progress event with detailed step info
func (s *Service) broadcastProgress(pluginID, siteID int64, step string, progress int, message string) {
	if s.wsHub == nil {
		return
	}

	eventType := ws.EventPublishProgress
	if step == stagestatus.Started.String() {
		eventType = ws.EventPublishStarted
	} else if step == stagestatus.Completed.String() || step == stagestatus.Failed.String() {
		eventType = ws.EventPublishComplete
	}

	stage := mapStepToStage(step)
	status := mapStepToStatus(step)

	ws.Broadcast(s.wsHub, eventType, ws.PublishStageProgressData{
		PluginID: pluginID,
		SiteID:   siteID,
		Stage:    stage,
		Step:     step,
		Status:   status,
		Progress: progress,
		Total:    100,
		Message:  message,
	})

	logLevel := loglevel.Info.String()
	if step == stagestatus.Failed.String() {
		logLevel = loglevel.Error.String()
	}
	s.wsHub.BroadcastPublishLog(pluginID, siteID, logLevel, stage, message, nil)
	s.log.Debug("Publish progress", "pluginId", pluginID, "siteId", siteID, "step", step, "stage", stage, "progress", progress, "message", message)
}

// broadcastProgressWithSession sends a WebSocket progress event with session ID
func (s *Service) broadcastProgressWithSession(pluginID, siteID int64, sessionID, step string, progress int, message string) {
	if s.wsHub == nil {
		return
	}

	eventType := ws.EventPublishProgress
	if step == stagestatus.Started.String() {
		eventType = ws.EventPublishStarted
	} else if step == stagestatus.Completed.String() || step == stagestatus.Failed.String() {
		eventType = ws.EventPublishComplete
	}

	stage := mapStepToStage(step)
	status := mapStepToStatus(step)

	ws.BroadcastWithSession(s.wsHub, eventType, ws.PublishStageProgressData{
		PluginID: pluginID,
		SiteID:   siteID,
		Stage:    stage,
		Step:     step,
		Status:   status,
		Progress: progress,
		Total:    100,
		Message:  message,
	}, sessionID)

	logLevel := loglevel.Info.String()
	if step == stagestatus.Failed.String() {
		logLevel = loglevel.Error.String()
	}
	s.wsHub.BroadcastPublishLogWithSession(pluginID, siteID, sessionID, logLevel, stage, message, nil)
	s.sessionLog(sessionID, logLevel, stage, message, nil)
	s.log.Debug("Publish progress", "pluginId", pluginID, "siteId", siteID, "sessionId", sessionID, "step", step, "stage", stage, "progress", progress, "message", message)
}

// broadcastStageStatus explicitly marks a publish stage as success/error
func (s *Service) broadcastStageStatus(pluginID, siteID int64, stage string, status string, progress int, message string, details json.RawMessage) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.PublishStageStatusData{
		PluginID: pluginID,
		SiteID:   siteID,
		Stage:    stage,
		Step:     stage,
		Status:   status,
		Progress: progress,
		Total:    100,
		Message:  message,
		Details:  details,
	})

	level := loglevel.Info.String()
	if status == loglevel.Error.String() {
		level = loglevel.Error.String()
	}
	s.wsHub.BroadcastPublishLog(pluginID, siteID, level, stage, message, details)
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
	s.wsHub.BroadcastPublishLog(pluginID, siteID, level, step, message, details)

	pluginName, siteName, siteURL := s.resolveNames(pluginID, siteID, details)

	logFields := []any{
		"plugin", pluginName,
		"site", siteName,
	}
	if siteURL != "" {
		logFields = append(logFields, "siteUrl", siteURL)
	}
	logFields = append(logFields, "pluginId", pluginID, "siteId", siteID, "step", step)

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

// broadcastStageComplete sends a stage_complete event for frontend tracking
func (s *Service) broadcastStageComplete(pluginID, siteID int64, sessionID, stageName, status string, durationMs int64, details json.RawMessage) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.PublishStageCompleteData{
		Type:      "stage_complete",
		SessionID: sessionID,
		Stage:     stageName,
		Status:    status,
		Duration:  durationMs,
		PluginID:  pluginID,
		SiteID:    siteID,
		Details:   details,
	})
}

// resolveNames looks up plugin/site names from details or DB
func (s *Service) resolveNames(pluginID, siteID int64, details json.RawMessage) (string, string, string) {
	// Parse details into a typed struct for name resolution
	var parsed struct {
		PluginName string `json:",omitempty"`
		SiteName   string `json:",omitempty"`
		SiteURL    string `json:",omitempty"`
	}
	if len(details) > 0 {
		_ = json.Unmarshal(details, &parsed)
	}

	pluginName := parsed.PluginName
	siteName := parsed.SiteName
	siteURL := parsed.SiteURL

	if pluginName == "" && pluginID > 0 {
		if pResult := s.pluginService.GetByID(context.Background(), pluginID); pResult.IsSafe() {
			pluginName = pResult.Value().Name
		}
	}
	if pluginName == "" {
		pluginName = fmt.Sprintf("plugin#%d", pluginID)
	}

	if (siteName == "" || siteURL == "") && siteID > 0 {
		if siteInfo, _, err := s.getSiteCredentials(context.Background(), siteID); err == nil {
			if siteName == "" {
				siteName = siteInfo.Name
			}
			if siteURL == "" {
				siteURL = siteInfo.URL
			}
		}
	}
	if siteName == "" {
		siteName = fmt.Sprintf("site#%d", siteID)
	}

	return pluginName, siteName, siteURL
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

// sessionLog writes a log entry to the session file
func (s *Service) sessionLog(sessionID, level, step, message string, details json.RawMessage) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.Log(sessionID, level, step, message, details)
}

// sessionLogStageStart writes a stage header to the session log
func (s *Service) sessionLogStageStart(sessionID, stageName string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.LogStageStart(sessionID, stageName)
}

// sessionLogStageEnd writes a stage completion marker
func (s *Service) sessionLogStageEnd(sessionID, stageName, status string, durationMs int64) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.LogStageEnd(sessionID, stageName, status, durationMs)
}

// endSession marks a session as complete
func (s *Service) endSession(sessionID, status, errorMsg string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.EndSession(sessionID, status, errorMsg)
}
