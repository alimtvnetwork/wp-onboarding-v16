// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"
	"fmt"
	"sync"
	"time"

	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// ScheduleConfig holds scheduling settings
type ScheduleConfig struct {
	IsEnabled bool   // Simplified: "daily:HH:MM", "weekly:DAY:HH:MM"
	CronExpr  string
	Timezone  string
}

// ScheduledJob represents a scheduled publish operation
type ScheduledJob struct {
	Id         string
	PluginId   int64
	PluginName string
	SiteIds    []int64 // Target sites (empty = all mapped)
	SiteNames  []string
	Schedule   ScheduleConfig
	Options    PublishOptions
	CreatedAt  time.Time
	LastRunAt  *time.Time `json:",omitempty"`
	NextRunAt  *time.Time `json:",omitempty"`
	LastStatus string     // "success", "partial", "failed", "never"
	IsEnabled  bool
}

// ScheduledJobResult captures the outcome of a scheduled run
type ScheduledJobResult struct {
	JobId      string
	RunAt      time.Time
	TotalSites int
	Succeeded  int
	Failed     int
	DurationMs int64
}

// PublishScheduler manages scheduled publish operations
type PublishScheduler struct {
	service *Service
	queue   *PublishQueue
	wsHub   *ws.Hub

	mu      sync.RWMutex
	jobs    map[string]*ScheduledJob
	timers  map[string]*time.Timer
	
	ctx     context.Context
	cancel  context.CancelFunc
}

// NewPublishScheduler creates a new scheduler
func NewPublishScheduler(service *Service, queue *PublishQueue, wsHub *ws.Hub) *PublishScheduler {
	ctx, cancel := context.WithCancel(context.Background())
	return &PublishScheduler{
		service: service,
		queue:   queue,
		wsHub:   wsHub,
		jobs:    make(map[string]*ScheduledJob),
		timers:  make(map[string]*time.Timer),
		ctx:     ctx,
		cancel:  cancel,
	}
}

// AddJob creates a new scheduled publish job
func (s *PublishScheduler) AddJob(job ScheduledJob) (string, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	job.Id = fmt.Sprintf("sj-%d-%d", job.PluginId, time.Now().UnixMilli())
	job.CreatedAt = time.Now()
	job.LastStatus = "never"
	job.Enabled = true

	// Calculate next run
	nextRun, err := s.calculateNextRun(job.Schedule)
	if err != nil {
		return "", apperror.Wrap(err, apperror.ErrValidation, "invalid schedule configuration")
	}
	job.NextRunAt = &nextRun

	s.jobs[job.Id] = &job
	s.scheduleTimer(&job)

	s.broadcastJobUpdate()
	return job.Id, nil
}

// RemoveJob removes a scheduled job
func (s *PublishScheduler) RemoveJob(jobId string) bool {
	s.mu.Lock()
	defer s.mu.Unlock()

	timer, isFound := s.timers[jobId]
	if isFound {
		timer.Stop()
		delete(s.timers, jobId)
	}

	_, isJobFound := s.jobs[jobId]
	if isJobFound {
		delete(s.jobs, jobId)
		s.broadcastJobUpdate()

		return true
	}

	return false
}

// ToggleJob enables or disables a scheduled job
func (s *PublishScheduler) ToggleJob(jobId string, isEnabled bool) bool {
	s.mu.Lock()
	defer s.mu.Unlock()

	job, isFound := s.jobs[jobId]
	isMissing := !isFound

	if isMissing {
		return false
	}

	job.IsEnabled = isEnabled
	if isEnabled {
		nextRun, err := s.calculateNextRun(job.Schedule)
		if err == nil {
			job.NextRunAt = &nextRun
			s.scheduleTimer(job)
		}
	} else {
		timer, isTimerFound := s.timers[jobId]
		if isTimerFound {
			timer.Stop()
			delete(s.timers, jobId)
		}
	}

	s.broadcastJobUpdate()
	return true
}

// ListJobs returns all scheduled jobs
func (s *PublishScheduler) ListJobs() []ScheduledJob {
	s.mu.RLock()
	defer s.mu.RUnlock()

	jobs := make([]ScheduledJob, 0, len(s.jobs))
	for _, job := range s.jobs {
		jobs = append(jobs, *job)
	}
	return jobs
}

// GetJob returns a specific job
func (s *PublishScheduler) GetJob(jobId string) (*ScheduledJob, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	job, isFound := s.jobs[jobId]
	isMissing := !isFound

	if isMissing {

		return nil, false
	}
	copy := *job
	return &copy, true
}

// scheduleTimer sets up a timer for the job's next run
func (s *PublishScheduler) scheduleTimer(job *ScheduledJob) {
	isDisabled := !job.Enabled
	isUnscheduled := job.NextRunAt == nil
	if isDisabled || isUnscheduled {
		return
	}

	// Cancel existing timer
	timer, isFound := s.timers[job.Id]
	if isFound {
		timer.Stop()
	}

	delay := time.Until(*job.NextRunAt)
	isOverdue := delay < 0

	if isOverdue {
		delay = 0
	}

	jobId := job.Id
	s.timers[job.Id] = time.AfterFunc(delay, func() {
		s.executeJob(jobId)
	})
}

// executeJob runs a scheduled publish job
func (s *PublishScheduler) executeJob(jobId string) {
	s.mu.Lock()
	job, isFound := s.jobs[jobId]
	isJobMissing := !isFound || !job.Enabled

	if isJobMissing {
		s.mu.Unlock()
		return
	}
	s.mu.Unlock()

	s.broadcastJobStarted(job)
	s.enqueueJobSites(job)
	s.rescheduleJob(job)
	s.broadcastJobComplete(jobId, job)
	s.broadcastJobUpdate()
}

// broadcastJobStarted sends a job start event.
func (s *PublishScheduler) broadcastJobStarted(job *ScheduledJob) {
	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.ScheduledJobStartedData{
			Type: "scheduled_job_started", JobId: job.Id,
			PluginId: job.PluginId, PluginName: job.PluginName,
		})
	}
}

// enqueueJobSites enqueues publish operations for all target sites.
func (s *PublishScheduler) enqueueJobSites(job *ScheduledJob) {
	items := make([]QueueItem, 0, len(job.SiteIds))
	for i, siteId := range job.SiteIds {
		siteName := ""
		if i < len(job.SiteNames) {
			siteName = job.SiteNames[i]
		}
		items = append(items, QueueItem{
			PluginId: job.PluginId, PluginName: job.PluginName,
			SiteId: siteId, SiteName: siteName, Options: job.Options,
		})
	}
	hasQueue := s.queue != nil
	hasItems := len(items) > 0

	if hasQueue && hasItems {
		s.queue.EnqueueBatch(items)
	}
}

// rescheduleJob updates last run and schedules the next run.
func (s *PublishScheduler) rescheduleJob(job *ScheduledJob) {
	s.mu.Lock()
	defer s.mu.Unlock()

	now := time.Now()
	job.LastRunAt = &now
	job.LastStatus = "success"

	nextRun, err := s.calculateNextRun(job.Schedule)
	if err == nil {
		job.NextRunAt = &nextRun
		s.scheduleTimer(job)
	}
}

// broadcastJobComplete sends a job completion event.
func (s *PublishScheduler) broadcastJobComplete(jobId string, job *ScheduledJob) {
	if s.wsHub != nil {
		ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.ScheduledJobCompleteData{
			Type: "scheduled_job_complete", JobId: jobId,
			PluginId: job.PluginId, PluginName: job.PluginName,
			NextRunAt: job.NextRunAt,
		})
	}
}

// calculateNextRun parses the schedule expression and returns the next run time
func (s *PublishScheduler) calculateNextRun(cfg ScheduleConfig) (time.Time, error) {
	loc := resolveTimezone(cfg.Timezone)
	now := time.Now().In(loc)

	switch {
	case len(cfg.CronExpr) > 6 && cfg.CronExpr[:6] == "daily:":
		return parseDailySchedule(cfg.CronExpr, now, loc)
	case len(cfg.CronExpr) > 7 && cfg.CronExpr[:7] == "weekly:":
		return parseWeeklySchedule(cfg.CronExpr, now, loc)
	case len(cfg.CronExpr) > 9 && cfg.CronExpr[:9] == "interval:":
		return parseIntervalSchedule(cfg.CronExpr, now)
	default:
		return time.Time{}, apperror.New(apperror.ErrValidation, "unknown schedule format").WithValue("cronExpr", cfg.CronExpr)
	}
}

// resolveTimezone loads the timezone or defaults to UTC.
func resolveTimezone(tz string) *time.Location {
	hasTimezone := tz != ""

	if hasTimezone {
		loc, err := time.LoadLocation(tz)
		if err == nil {
			return loc
		}
	}
	return time.UTC
}

// parseDailySchedule parses "daily:HH:MM" format.
func parseDailySchedule(expr string, now time.Time, loc *time.Location) (time.Time, error) {
	var hour, minute int
	_, err := fmt.Sscanf(expr, "daily:%d:%d", &hour, &minute)
	if err != nil {
		return time.Time{}, apperror.New(apperror.ErrValidation, "invalid daily schedule").WithValue("cronExpr", expr)
	}
	next := time.Date(now.Year(), now.Month(), now.Day(), hour, minute, 0, 0, loc)
	if next.Before(now) {
		next = next.Add(24 * time.Hour)
	}
	return next, nil
}

// parseWeeklySchedule parses "weekly:DAY:HH:MM" format.
func parseWeeklySchedule(expr string, now time.Time, loc *time.Location) (time.Time, error) {
	var dayName string
	var hour, minute int
	_, err := fmt.Sscanf(expr, "weekly:%3s:%d:%d", &dayName, &hour, &minute)
	if err != nil {
		return time.Time{}, apperror.New(apperror.ErrValidation, "invalid weekly schedule").WithValue("cronExpr", expr)
	}

	targetDay := parseDayOfWeek(dayName)
	daysUntil := (int(targetDay) - int(now.Weekday()) + 7) % 7
	isTargetToday := daysUntil == 0

	if isTargetToday {
		next := time.Date(now.Year(), now.Month(), now.Day(), hour, minute, 0, 0, loc)
		isFutureToday := !next.Before(now)

		if isFutureToday {
			return next, nil
		}
		daysUntil = 7
	}
	return time.Date(now.Year(), now.Month(), now.Day()+daysUntil, hour, minute, 0, 0, loc), nil
}

// parseIntervalSchedule parses "interval:MINUTES" format.
func parseIntervalSchedule(expr string, now time.Time) (time.Time, error) {
	var minutes int
	_, err := fmt.Sscanf(expr, "interval:%d", &minutes)
	isInvalidMinutes := minutes < 1

	if err != nil || isInvalidMinutes {
		return time.Time{}, apperror.New(apperror.ErrValidation, "invalid interval schedule").WithValue("cronExpr", expr)
	}
	return now.Add(time.Duration(minutes) * time.Minute), nil
}

func parseDayOfWeek(day string) time.Weekday {
	switch day {
	case "Sun":
		return time.Sunday
	case "Mon":
		return time.Monday
	case "Tue":
		return time.Tuesday
	case "Wed":
		return time.Wednesday
	case "Thu":
		return time.Thursday
	case "Fri":
		return time.Friday
	case "Sat":
		return time.Saturday
	default:
		return time.Monday
	}
}

// broadcastJobUpdate sends job list update via WebSocket
func (s *PublishScheduler) broadcastJobUpdate() {
	if s.wsHub == nil {
		return
	}
	ws.Broadcast(s.wsHub, ws.EventPublishProgress, ws.ScheduledJobsUpdateData{
		Type: "scheduled_jobs_update", Jobs: s.collectJobSummaries(),
	})
}

// collectJobSummaries builds a summary list of all jobs.
func (s *PublishScheduler) collectJobSummaries() []ws.ScheduledJobSummary {
	jobs := make([]ws.ScheduledJobSummary, 0, len(s.jobs))
	for _, job := range s.jobs {
		jobs = append(jobs, buildJobSummary(job))
	}
	return jobs
}

// buildJobSummary creates a summary from a ScheduledJob.
func buildJobSummary(job *ScheduledJob) ws.ScheduledJobSummary {
	j := ws.ScheduledJobSummary{
		Id: job.Id, PluginId: job.PluginId, PluginName: job.PluginName,
		IsEnabled: job.Enabled, Schedule: job.Schedule.CronExpr, LastStatus: job.LastStatus,
	}
	if job.NextRunAt != nil {
		j.NextRunAt = job.NextRunAt.Format(time.RFC3339)
	}
	if job.LastRunAt != nil {
		j.LastRunAt = job.LastRunAt.Format(time.RFC3339)
	}
	return j
}

// Shutdown gracefully stops the scheduler
func (s *PublishScheduler) Shutdown() {
	s.cancel()
	s.mu.Lock()
	defer s.mu.Unlock()
	for _, timer := range s.timers {
		timer.Stop()
	}
}
