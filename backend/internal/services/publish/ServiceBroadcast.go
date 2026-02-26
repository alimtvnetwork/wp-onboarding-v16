package publish

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	loglevel "wp-plugin-publish/internal/enums/log_level"
	publishstep "wp-plugin-publish/internal/enums/publish_step"
	stagestatus "wp-plugin-publish/internal/enums/stage_status"
	enumstatus "wp-plugin-publish/internal/enums/status"
	"wp-plugin-publish/internal/services/session"
	"wp-plugin-publish/internal/ws"
)

// ─── Input Structs ───────────────────────────────────────────────────────────

// ProgressInput bundles parameters for broadcastProgress.
type ProgressInput struct {
	PluginId  int64
	SiteId    int64
	SessionId string
	Step      publishstep.Variant
	Progress  int
	Message   string
}

// StageStatusInput bundles parameters for broadcastStageStatus.
type StageStatusInput struct {
	PluginId int64
	SiteId   int64
	Stage    publishstep.Variant
	Status   string
	Progress int
	Message  string
	Details  json.RawMessage
}

// StageLogInput bundles parameters for broadcastStageLog.
type StageLogInput struct {
	PluginId  int64
	SiteId    int64
	SessionId string
	Level     loglevel.Variant
	Stage     publishstep.Variant
	Ctx       StageContext
}

// DetailedLogInput bundles parameters for broadcastDetailedLog.
type DetailedLogInput struct {
	PluginId int64
	SiteId   int64
	Level    loglevel.Variant
	Step     publishstep.Variant
	Message  string
	Details  json.RawMessage
}

// StageCompleteInput bundles parameters for broadcastStageComplete.
type StageCompleteInput struct {
	PluginId   int64
	SiteId     int64
	SessionId  string
	StageName  publishstep.Variant
	Status     string
	DurationMs int64
	Details    json.RawMessage
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

// ─── Stage Execution ─────────────────────────────────────────────────────────

// runStage executes a stage and captures timing/result
func (s *Service) runStage(name string, fn func() error) Stage {
	start := time.Now()
	err := fn()

	return buildStage(name, start, err)
}

// runStageWithSession executes a stage with session logging and captures timing/result
func (s *Service) runStageWithSession(sessionId, name string, fn func() error) Stage {
	start := time.Now()
	s.sessionLogStageStart(sessionId, strings.ToUpper(name))

	err := fn()
	stage := buildStage(name, start, err)

	stageEnd := sessionStageEndInput{
		SessionId:  sessionId,
		StageName:  strings.ToUpper(name),
		Status:     stage.Status,
		DurationMs: stage.Duration,
	}
	s.sessionLogStageEnd(stageEnd)

	return stage
}

// buildStage creates a Stage from a name, start time, and error.
func buildStage(name string, start time.Time, err error) Stage {
	stage := Stage{
		Name:     name,
		Duration: time.Since(start).Milliseconds(),
	}
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
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
		Stage:    stage,
		Step:     input.Step.Value(),
		Status:   status,
		Progress: input.Progress,
		Total:    100,
		Message:  input.Message,
	}

	if input.SessionId != "" {
		ws.BroadcastWithSession(s.wsHub, eventType, data, input.SessionId)
		s.emitSessionProgressLog(input, stage)
	} else {
		ws.Broadcast(s.wsHub, eventType, data)
		s.emitProgressLog(input, stage)
	}
}

// emitProgressLog logs a non-session progress event.
func (s *Service) emitProgressLog(input ProgressInput, stage string) {
	logLevel := resolveStepLogLevel(input.Step)

	logEntry := ws.OperationLogInput{
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
		Entry: ws.OperationLogEntry{
			Level:   logLevel.Lower(),
			Step:    stage,
			Message: input.Message,
		},
	}
	s.wsHub.BroadcastPublishLog(logEntry)

	s.log.Debug("Publish progress",
		"pluginId", input.PluginId,
		"siteId", input.SiteId,
		"step", input.Step.Value(),
		"stage", stage,
		"progress", input.Progress,
		"message", input.Message,
	)
}

// emitSessionProgressLog logs a session-scoped progress event.
func (s *Service) emitSessionProgressLog(input ProgressInput, stage string) {
	logLevel := resolveStepLogLevel(input.Step)

	logEntry := ws.OperationLogInput{
		PluginID:  input.PluginId,
		SiteID:    input.SiteId,
		SessionID: input.SessionId,
		Entry: ws.OperationLogEntry{
			Level:   logLevel.Lower(),
			Step:    stage,
			Message: input.Message,
		},
	}
	s.wsHub.BroadcastPublishLogWithSession(logEntry)

	sessionLog := sessionLogInput{
		SessionId: input.SessionId,
		Level:     logLevel,
		Step:      input.Step,
		Message:   input.Message,
	}
	s.sessionLog(sessionLog)

	s.log.Debug("Publish progress",
		"pluginId", input.PluginId,
		"siteId", input.SiteId,
		"sessionId", input.SessionId,
		"step", input.Step.Value(),
		"stage", stage,
		"progress", input.Progress,
		"message", input.Message,
	)
}

// resolveStepLogLevel returns error level for failed steps, info otherwise.
func resolveStepLogLevel(step publishstep.Variant) loglevel.Variant {
	if step == publishstep.Failed {
		return loglevel.Error
	}

	return loglevel.Info
}

// resolveProgressEvent maps step to ws event type.
func resolveProgressEvent(step publishstep.Variant) string {
	switch step {
	case publishstep.Started:
		return ws.EventPublishStarted
	case publishstep.Completed, publishstep.Failed:
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

	stageData := ws.PublishStageStatusData{
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
	ws.Broadcast(s.wsHub, ws.EventPublishProgress, stageData)

	level := loglevel.Info
	if input.Status == loglevel.Error.Lower() {
		level = loglevel.Error
	}

	logEntry := ws.OperationLogInput{
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
		Entry: ws.OperationLogEntry{
			Level:   level.Lower(),
			Step:    input.Stage.Value(),
			Message: input.Message,
			Details: input.Details,
		},
	}
	s.wsHub.BroadcastPublishLog(logEntry)
}

// broadcastStageComplete sends a stage_complete event for frontend tracking
func (s *Service) broadcastStageComplete(input StageCompleteInput) {
	if s.wsHub == nil {
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
	message := input.Ctx.What
	if input.Ctx.Result != "" {
		message = fmt.Sprintf("%s → %s", input.Ctx.What, input.Ctx.Result)
	}

	detailsJSON, _ := json.Marshal(input.Ctx)

	detailedLog := DetailedLogInput{
		PluginId: input.PluginId,
		SiteId:   input.SiteId,
		Level:    input.Level,
		Step:     input.Stage,
		Message:  message,
		Details:  detailsJSON,
	}
	s.broadcastDetailedLog(detailedLog)

	sessionLog := sessionLogInput{
		SessionId: input.SessionId,
		Level:     input.Level,
		Step:      input.Stage,
		Message:   message,
		Details:   detailsJSON,
	}
	s.sessionLog(sessionLog)
}

// broadcastDetailedLog sends a detailed log entry with structured data
func (s *Service) broadcastDetailedLog(input DetailedLogInput) {
	if s.wsHub == nil {
		return
	}

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
	if ctx.SiteUrl != "" {
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
	if len(details) > 0 {
		_ = json.Unmarshal(details, &parsed)
	}

	return &resolvedNames{PluginName: parsed.PluginName, SiteName: parsed.SiteName, SiteURL: parsed.SiteURL}
}

// resolvePluginName fetches plugin name from DB if not provided.
func (s *Service) resolvePluginName(pluginId int64, name string) string {
	if name == "" && pluginId > 0 {
		pResult := s.pluginService.GetByID(context.Background(), pluginId)
		if pResult.IsSafe() {
			return pResult.Value().Name
		}
	}
	if name == "" {
		return fmt.Sprintf("plugin#%d", pluginId)
	}

	return name
}

// resolveSiteNames fetches site name/URL from DB if not provided.
func (s *Service) resolveSiteNames(siteId int64, name, url string) (string, string) {
	if (name == "" || url == "") && siteId > 0 {
		if siteInfo, _, err := s.getSiteCredentials(context.Background(), siteId); err == nil {
			if name == "" {
				name = siteInfo.Name
			}
			if url == "" {
				url = siteInfo.URL
			}
		}
	}
	if name == "" {
		name = fmt.Sprintf("site#%d", siteId)
	}

	return name, url
}

// ─── Step/Stage Mapping ──────────────────────────────────────────────────────

// mapStepToStage maps step names to stage names for frontend compatibility
func mapStepToStage(step publishstep.Variant) string {
	switch step {
	case publishstep.Started:
		return publishstep.Backup.Value()
	case publishstep.Packaging:
		return publishstep.Package.Value()
	case publishstep.Uploading:
		return publishstep.Upload.Value()
	case publishstep.Activating:
		return publishstep.Activate.Value()
	case publishstep.Cleanup:
		return publishstep.Cleanup.Value()
	default:
		return step.Value()
	}
}

// mapStepToStatus maps step names to status strings
func mapStepToStatus(step publishstep.Variant) string {
	switch step {
	case publishstep.Completed:
		return enumstatus.Success.String()
	case publishstep.Failed:
		return loglevel.Error.Lower()
	default:
		return stagestatus.Running.String()
	}
}

// ─── Session Logging Helpers ─────────────────────────────────────────────────

// sessionLogInput bundles parameters for sessionLog.
type sessionLogInput struct {
	SessionId string
	Level     loglevel.Variant
	Step      publishstep.Variant
	Message   string
	Details   json.RawMessage
}

func (s *Service) sessionLog(input sessionLogInput) {
	if s.sessionService == nil || input.SessionId == "" {
		return
	}

	logInput := session.LogInput{
		SessionID: input.SessionId,
		Level:     input.Level.Lower(),
		Step:      input.Step.Value(),
		Message:   input.Message,
		Details:   input.Details,
	}
	s.sessionService.Log(logInput)
}

func (s *Service) sessionLogStageStart(sessionId, stageName string) {
	if s.sessionService == nil || sessionId == "" {
		return
	}
	s.sessionService.LogStageStart(sessionId, stageName)
}

// sessionStageEndInput bundles parameters for sessionLogStageEnd.
type sessionStageEndInput struct {
	SessionId  string
	StageName  string
	Status     stagestatus.Variant
	DurationMs int64
}

func (s *Service) sessionLogStageEnd(input sessionStageEndInput) {
	if s.sessionService == nil || input.SessionId == "" {
		return
	}

	stageEnd := session.StageEndInput{
		SessionID:  input.SessionId,
		StageName:  input.StageName,
		Status:     input.Status.String(),
		DurationMs: input.DurationMs,
	}
	s.sessionService.LogStageEnd(stageEnd)
}

func (s *Service) endSession(sessionId, status, errorMsg string) {
	if s.sessionService == nil || sessionId == "" {
		return
	}
	s.sessionService.EndSession(sessionId, status, errorMsg)
}
