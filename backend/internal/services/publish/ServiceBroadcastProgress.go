package publish

import (
	"encoding/json"
	"strings"
	"time"

	loglevel "wp-plugin-publish/internal/enums/logleveltype"
	publishstep "wp-plugin-publish/internal/enums/publishsteptype"
	stagestatus "wp-plugin-publish/internal/enums/stagestatustype"
	enumstatus "wp-plugin-publish/internal/enums/statustype"
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

	data := buildProgressData(input)

	if input.SessionId != "" {
		s.broadcastSessionProgress(input, data)
	} else {
		s.broadcastNonSessionProgress(input, data)
	}
}

// buildProgressData constructs the WebSocket progress event data.
func buildProgressData(input ProgressInput) ws.PublishStageProgressData {
	return ws.PublishStageProgressData{
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
		Stage:    mapStepToStage(input.Step),
		Step:     input.Step.Value(),
		Status:   mapStepToStatus(input.Step),
		Progress: input.Progress,
		Total:    100,
		Message:  input.Message,
	}
}

// broadcastSessionProgress sends progress with session context.
func (s *Service) broadcastSessionProgress(input ProgressInput, data ws.PublishStageProgressData) {
	eventType := resolveProgressEvent(input.Step)
	ws.BroadcastWithSession(s.wsHub, eventType, data, input.SessionId)
	s.emitSessionProgressLog(input, mapStepToStage(input.Step))
}

// broadcastNonSessionProgress sends progress without session context.
func (s *Service) broadcastNonSessionProgress(input ProgressInput, data ws.PublishStageProgressData) {
	eventType := resolveProgressEvent(input.Step)
	ws.Broadcast(s.wsHub, eventType, data)
	s.emitProgressLog(input, mapStepToStage(input.Step))
}

// emitProgressLog logs a non-session progress event.
func (s *Service) emitProgressLog(input ProgressInput, stage string) {
	logLevel := resolveStepLogLevel(input.Step)

	logEntry := buildProgressLogEntry(input, stage, logLevel)
	s.wsHub.BroadcastPublishLog(logEntry)

	s.logProgressDebug(input, stage)
}

// buildProgressLogEntry constructs a log entry for progress events.
func buildProgressLogEntry(input ProgressInput, stage string, logLevel loglevel.Variant) ws.OperationLogInput {
	return ws.OperationLogInput{
		PluginID: input.PluginId,
		SiteID:   input.SiteId,
		Entry: ws.OperationLogEntry{
			Level:   logLevel.Lower(),
			Step:    stage,
			Message: input.Message,
		},
	}
}

// logProgressDebug writes a debug log for progress events.
func (s *Service) logProgressDebug(input ProgressInput, stage string) {
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

	logEntry := buildSessionProgressLogEntry(input, stage, logLevel)
	s.wsHub.BroadcastPublishLogWithSession(logEntry)

	s.emitSessionLogAndDebug(input, stage, logLevel)
}

// buildSessionProgressLogEntry constructs a session-scoped log entry.
func buildSessionProgressLogEntry(input ProgressInput, stage string, logLevel loglevel.Variant) ws.OperationLogInput {
	return ws.OperationLogInput{
		PluginID:  input.PluginId,
		SiteID:    input.SiteId,
		SessionID: input.SessionId,
		Entry: ws.OperationLogEntry{
			Level:   logLevel.Lower(),
			Step:    stage,
			Message: input.Message,
		},
	}
}

// emitSessionLogAndDebug writes session log and debug output for progress.
func (s *Service) emitSessionLogAndDebug(input ProgressInput, stage string, logLevel loglevel.Variant) {
	sessionLog := sessionLogInput{
		SessionId: input.SessionId,
		Level:     logLevel,
		Step:      input.Step,
		Message:   input.Message,
	}
	s.sessionLog(sessionLog)

	s.logSessionProgressDebug(input, stage)
}

// logSessionProgressDebug writes a debug log for session progress events.
func (s *Service) logSessionProgressDebug(input ProgressInput, stage string) {
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
