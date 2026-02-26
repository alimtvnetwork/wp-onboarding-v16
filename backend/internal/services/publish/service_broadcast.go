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

// ─── Input Structs ───────────────────────────────────────────────────────────

// ProgressInput bundles parameters for broadcastProgress.
type ProgressInput struct {
	PluginID  int64
	SiteID    int64
	SessionID string // optional — when set, broadcasts with session scope
	Step      string
	Progress  int
	Message   string
}

// StageStatusInput bundles parameters for broadcastStageStatus.
type StageStatusInput struct {
	PluginID int64
	SiteID   int64
	Stage    string
	Status   string
	Progress int
	Message  string
	Details  json.RawMessage
}

// StageLogInput bundles parameters for broadcastStageLog.
type StageLogInput struct {
	PluginID  int64
	SiteID    int64
	SessionID string
	Level     string
	Stage     string
	Ctx       StageContext
}

// DetailedLogInput bundles parameters for broadcastDetailedLog.
type DetailedLogInput struct {
	PluginID int64
	SiteID   int64
	Level    string
	Step     string
	Message  string
	Details  json.RawMessage
}

// StageCompleteInput bundles parameters for broadcastStageComplete.
type StageCompleteInput struct {
	PluginID   int64
	SiteID     int64
	SessionID  string
	StageName  string
	Status     string
	DurationMs int64
	Details    json.RawMessage
}

// logContext holds resolved names for structured log output.
type logContext struct {
	PluginName string
	SiteName   string
	SiteURL    string
	PluginID   int64
	SiteID     int64
	Step       string
}

// ─── Stage Execution ─────────────────────────────────────────────────────────

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

	s.sessionLogStageEnd(sessionStageEndInput{SessionID: sessionID, StageName: strings.ToUpper(name), Status: stage.Status, DurationMs: stage.Duration})

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

// ─── Progress Broadcasting ───────────────────────────────────────────────────

// broadcastProgress sends a WebSocket progress event with detailed step info.
func (s *Service) broadcastProgress(input ProgressInput) {
	if s.wsHub == nil {
		return
	}

	eventType := resolveProgressEvent(input.Step)
	stage := mapStepToStage(input.Step)
	status := mapStepToStatus(input.Step)

	data := ws.PublishStageProgressData{
		PluginID: input.PluginID, SiteID: input.SiteID, Stage: stage, Step: input.Step,
		Status: status, Progress: input.Progress, Total: 100, Message: input.Message,
	}

	if input.SessionID != "" {
		ws.BroadcastWithSession(s.wsHub, eventType, data, input.SessionID)
		s.emitSessionProgressLog(input, stage)
	} else {
		ws.Broadcast(s.wsHub, eventType, data)
		s.emitProgressLog(input, stage)
	}
}

// emitProgressLog logs a non-session progress event.
func (s *Service) emitProgressLog(input ProgressInput, stage string) {
	logLevel := resolveStepLogLevel(input.Step)
	s.wsHub.BroadcastPublishLog(ws.OperationLogInput{
		PluginID: input.PluginID, SiteID: input.SiteID,
		Entry: ws.OperationLogEntry{Level: logLevel, Step: stage, Message: input.Message},
	})
	s.log.Debug("Publish progress", "pluginId", input.PluginID, "siteId", input.SiteID, "step", input.Step, "stage", stage, "progress", input.Progress, "message", input.Message)
}

// emitSessionProgressLog logs a session-scoped progress event.
func (s *Service) emitSessionProgressLog(input ProgressInput, stage string) {
	logLevel := resolveStepLogLevel(input.Step)
	s.wsHub.BroadcastPublishLogWithSession(ws.OperationLogInput{
		PluginID: input.PluginID, SiteID: input.SiteID, SessionID: input.SessionID,
		Entry: ws.OperationLogEntry{Level: logLevel, Step: stage, Message: input.Message},
	})
	s.sessionLog(sessionLogInput{SessionID: input.SessionID, Level: logLevel, Step: stage, Message: input.Message})
	s.log.Debug("Publish progress", "pluginId", input.PluginID, "siteId", input.SiteID, "sessionId", input.SessionID, "step", input.Step, "stage", stage, "progress", input.Progress, "message", input.Message)
}

// resolveStepLogLevel returns error level for failed steps, info otherwise.
func resolveStepLogLevel(step string) string {
	if step == stagestatus.Failed.String() {
		return loglevel.Error.String()
	}

	return loglevel.Info.String()
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

// ─── Stage Status & Completion ───────────────────────────────────────────────

// broadcastStageStatus explicitly marks a publish stage as success/error
func (s *Service) broadcastStageStatus(input StageStatusInput) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.PublishStageStatusData{
		PluginID: input.PluginID, SiteID: input.SiteID, Stage: input.Stage, Step: input.Stage,
		Status: input.Status, Progress: input.Progress, Total: 100, Message: input.Message, Details: input.Details,
	})

	level := loglevel.Info.String()
	if input.Status == loglevel.Error.String() {
		level = loglevel.Error.String()
	}
	s.wsHub.BroadcastPublishLog(ws.OperationLogInput{
		PluginID: input.PluginID, SiteID: input.SiteID,
		Entry: ws.OperationLogEntry{Level: level, Step: input.Stage, Message: input.Message, Details: input.Details},
	})
}

// broadcastStageComplete sends a stage_complete event for frontend tracking
func (s *Service) broadcastStageComplete(input StageCompleteInput) {
	if s.wsHub == nil {
		return
	}
	ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.PublishStageCompleteData{
		Type: "stage_complete", SessionID: input.SessionID, Stage: input.StageName,
		Status: input.Status, Duration: input.DurationMs, PluginID: input.PluginID, SiteID: input.SiteID, Details: input.Details,
	})
}

// ─── Detailed Logging ────────────────────────────────────────────────────────

// broadcastStageLog sends a detailed log entry with structured context
func (s *Service) broadcastStageLog(input StageLogInput) {
	message := input.Ctx.What
	if input.Ctx.Result != "" {
		message = fmt.Sprintf("%s → %s", input.Ctx.What, input.Ctx.Result)
	}

	detailsJSON, _ := json.Marshal(input.Ctx)
	s.broadcastDetailedLog(DetailedLogInput{
		PluginID: input.PluginID, SiteID: input.SiteID,
		Level: input.Level, Step: input.Stage, Message: message, Details: detailsJSON,
	})
	s.sessionLog(sessionLogInput{SessionID: input.SessionID, Level: input.Level, Step: input.Stage, Message: message, Details: detailsJSON})
}

// broadcastDetailedLog sends a detailed log entry with structured data
func (s *Service) broadcastDetailedLog(input DetailedLogInput) {
	if s.wsHub == nil {
		return
	}
	s.wsHub.BroadcastPublishLog(ws.OperationLogInput{
		PluginID: input.PluginID, SiteID: input.SiteID,
		Entry: ws.OperationLogEntry{Level: input.Level, Step: input.Step, Message: input.Message, Details: input.Details},
	})

	pluginName, siteName, siteURL := s.resolveNames(input.PluginID, input.SiteID, input.Details)
	s.logWithLevel(input.Level, input.Message, logContext{
		PluginName: pluginName, SiteName: siteName, SiteURL: siteURL,
		PluginID: input.PluginID, SiteID: input.SiteID, Step: input.Step,
	})
}

// logWithLevel dispatches a log message at the appropriate level.
func (s *Service) logWithLevel(level, message string, ctx logContext) {
	logFields := buildLogFields(ctx)
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
func buildLogFields(ctx logContext) []any {
	fields := []any{"plugin", ctx.PluginName, "site", ctx.SiteName}
	if ctx.SiteURL != "" {
		fields = append(fields, "siteUrl", ctx.SiteURL)
	}

	return append(fields, "pluginId", ctx.PluginID, "siteId", ctx.SiteID, "step", ctx.Step)
}

// ─── Name Resolution ─────────────────────────────────────────────────────────

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

// ─── Step/Stage Mapping ──────────────────────────────────────────────────────

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

// ─── Session Logging Helpers ─────────────────────────────────────────────────

// sessionLogInput bundles parameters for sessionLog.
type sessionLogInput struct {
	SessionID string
	Level     string
	Step      string
	Message   string
	Details   json.RawMessage
}

func (s *Service) sessionLog(input sessionLogInput) {
	if s.sessionService == nil || input.SessionID == "" {
		return
	}
	s.sessionService.Log(session.LogInput{SessionID: input.SessionID, Level: input.Level, Step: input.Step, Message: input.Message, Details: input.Details})
}

func (s *Service) sessionLogStageStart(sessionID, stageName string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.LogStageStart(sessionID, stageName)
}

// sessionStageEndInput bundles parameters for sessionLogStageEnd.
type sessionStageEndInput struct {
	SessionID  string
	StageName  string
	Status     stagestatus.Variant
	DurationMs int64
}

func (s *Service) sessionLogStageEnd(input sessionStageEndInput) {
	if s.sessionService == nil || input.SessionID == "" {
		return
	}
	s.sessionService.LogStageEnd(session.StageEndInput{SessionID: input.SessionID, StageName: input.StageName, Status: input.Status.String(), DurationMs: input.DurationMs})
}

func (s *Service) endSession(sessionID, status, errorMsg string) {
	if s.sessionService == nil || sessionID == "" {
		return
	}
	s.sessionService.EndSession(sessionID, status, errorMsg)
}
