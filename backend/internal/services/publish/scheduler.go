// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"
	"fmt"
	"sync"
	"time"

	"wp-plugin-publish/internal/ws"
)

// ScheduleConfig holds scheduling settings
type ScheduleConfig struct {
	Enabled   bool   `json:"enabled"`
	CronExpr  string `json:"cronExpr"`  // Simplified: "daily:HH:MM", "weekly:DAY:HH:MM"
	Timezone  string `json:"timezone"`
}

// ScheduledJob represents a scheduled publish operation
type ScheduledJob struct {
	ID          string          `json:"id"`
	PluginID    int64           `json:"pluginId"`
	PluginName  string          `json:"pluginName"`
	SiteIDs     []int64         `json:"siteIds"`    // Target sites (empty = all mapped)
	SiteNames   []string        `json:"siteNames"`
	Schedule    ScheduleConfig  `json:"schedule"`
	Options     PublishOptions  `json:"options"`
	CreatedAt   time.Time       `json:"createdAt"`
	LastRunAt   *time.Time      `json:"lastRunAt,omitempty"`
	NextRunAt   *time.Time      `json:"nextRunAt,omitempty"`
	LastStatus  string          `json:"lastStatus"` // "success", "partial", "failed", "never"
	Enabled     bool            `json:"enabled"`
}

// ScheduledJobResult captures the outcome of a scheduled run
type ScheduledJobResult struct {
	JobID       string    `json:"jobId"`
	RunAt       time.Time `json:"runAt"`
	TotalSites  int       `json:"totalSites"`
	Succeeded   int       `json:"succeeded"`
	Failed      int       `json:"failed"`
	Duration    int64     `json:"durationMs"`
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

	job.ID = fmt.Sprintf("sj-%d-%d", job.PluginID, time.Now().UnixMilli())
	job.CreatedAt = time.Now()
	job.LastStatus = "never"
	job.Enabled = true

	// Calculate next run
	nextRun, err := s.calculateNextRun(job.Schedule)
	if err != nil {
		return "", fmt.Errorf("invalid schedule: %w", err)
	}
	job.NextRunAt = &nextRun

	s.jobs[job.ID] = &job
	s.scheduleTimer(&job)

	s.broadcastJobUpdate()
	return job.ID, nil
}

// RemoveJob removes a scheduled job
func (s *PublishScheduler) RemoveJob(jobID string) bool {
	s.mu.Lock()
	defer s.mu.Unlock()

	if timer, ok := s.timers[jobID]; ok {
		timer.Stop()
		delete(s.timers, jobID)
	}
	if _, ok := s.jobs[jobID]; ok {
		delete(s.jobs, jobID)
		s.broadcastJobUpdate()
		return true
	}
	return false
}

// ToggleJob enables or disables a scheduled job
func (s *PublishScheduler) ToggleJob(jobID string, enabled bool) bool {
	s.mu.Lock()
	defer s.mu.Unlock()

	job, ok := s.jobs[jobID]
	if !ok {
		return false
	}

	job.Enabled = enabled
	if enabled {
		nextRun, err := s.calculateNextRun(job.Schedule)
		if err == nil {
			job.NextRunAt = &nextRun
			s.scheduleTimer(job)
		}
	} else {
		if timer, ok := s.timers[jobID]; ok {
			timer.Stop()
			delete(s.timers, jobID)
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
func (s *PublishScheduler) GetJob(jobID string) (*ScheduledJob, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	job, ok := s.jobs[jobID]
	if !ok {
		return nil, false
	}
	copy := *job
	return &copy, true
}

// scheduleTimer sets up a timer for the job's next run
func (s *PublishScheduler) scheduleTimer(job *ScheduledJob) {
	if !job.Enabled || job.NextRunAt == nil {
		return
	}

	// Cancel existing timer
	if timer, ok := s.timers[job.ID]; ok {
		timer.Stop()
	}

	delay := time.Until(*job.NextRunAt)
	if delay < 0 {
		delay = 0
	}

	jobID := job.ID
	s.timers[job.ID] = time.AfterFunc(delay, func() {
		s.executeJob(jobID)
	})
}

// executeJob runs a scheduled publish job
func (s *PublishScheduler) executeJob(jobID string) {
	s.mu.Lock()
	job, ok := s.jobs[jobID]
	if !ok || !job.Enabled {
		s.mu.Unlock()
		return
	}
	s.mu.Unlock()

	startTime := time.Now()

	// Broadcast job starting
	if s.wsHub != nil {
		s.wsHub.Broadcast(ws.EventPublishProgress, map[string]any{
			"type":       "scheduled_job_started",
			"jobId":      jobID,
			"pluginId":   job.PluginID,
			"pluginName": job.PluginName,
		})
	}

	// Enqueue publish operations for all target sites
	items := make([]QueueItem, 0)
	for i, siteID := range job.SiteIDs {
		siteName := ""
		if i < len(job.SiteNames) {
			siteName = job.SiteNames[i]
		}
		items = append(items, QueueItem{
			PluginID:   job.PluginID,
			PluginName: job.PluginName,
			SiteID:     siteID,
			SiteName:   siteName,
			Options:    job.Options,
			Priority:   0, // Normal priority for scheduled jobs
		})
	}

	if s.queue != nil && len(items) > 0 {
		s.queue.EnqueueBatch(items)
	}

	// Update job state
	s.mu.Lock()
	now := time.Now()
	job.LastRunAt = &now
	job.LastStatus = "success" // Will be updated based on results

	// Schedule next run
	nextRun, err := s.calculateNextRun(job.Schedule)
	if err == nil {
		job.NextRunAt = &nextRun
		s.scheduleTimer(job)
	}
	s.mu.Unlock()

	duration := time.Since(startTime).Milliseconds()

	// Broadcast job complete
	if s.wsHub != nil {
		s.wsHub.Broadcast(ws.EventPublishProgress, map[string]any{
			"type":       "scheduled_job_complete",
			"jobId":      jobID,
			"pluginId":   job.PluginID,
			"pluginName": job.PluginName,
			"durationMs": duration,
			"nextRunAt":  job.NextRunAt,
		})
	}

	s.broadcastJobUpdate()
}

// calculateNextRun parses the schedule expression and returns the next run time
func (s *PublishScheduler) calculateNextRun(cfg ScheduleConfig) (time.Time, error) {
	loc := time.UTC
	if cfg.Timezone != "" {
		var err error
		loc, err = time.LoadLocation(cfg.Timezone)
		if err != nil {
			loc = time.UTC
		}
	}

	now := time.Now().In(loc)

	// Parse simplified cron expression
	// "daily:HH:MM" - Run daily at specified time
	// "weekly:DAY:HH:MM" - Run weekly on specified day
	// "interval:MINUTES" - Run every N minutes
	var hour, minute int

	switch {
	case len(cfg.CronExpr) > 6 && cfg.CronExpr[:6] == "daily:":
		_, err := fmt.Sscanf(cfg.CronExpr, "daily:%d:%d", &hour, &minute)
		if err != nil {
			return time.Time{}, fmt.Errorf("invalid daily schedule: %s", cfg.CronExpr)
		}
		next := time.Date(now.Year(), now.Month(), now.Day(), hour, minute, 0, 0, loc)
		if next.Before(now) {
			next = next.Add(24 * time.Hour)
		}
		return next, nil

	case len(cfg.CronExpr) > 7 && cfg.CronExpr[:7] == "weekly:":
		var dayName string
		_, err := fmt.Sscanf(cfg.CronExpr, "weekly:%3s:%d:%d", &dayName, &hour, &minute)
		if err != nil {
			return time.Time{}, fmt.Errorf("invalid weekly schedule: %s", cfg.CronExpr)
		}
		targetDay := parseDayOfWeek(dayName)
		daysUntil := (int(targetDay) - int(now.Weekday()) + 7) % 7
		if daysUntil == 0 {
			next := time.Date(now.Year(), now.Month(), now.Day(), hour, minute, 0, 0, loc)
			if next.Before(now) {
				daysUntil = 7
			} else {
				return next, nil
			}
		}
		next := time.Date(now.Year(), now.Month(), now.Day()+daysUntil, hour, minute, 0, 0, loc)
		return next, nil

	case len(cfg.CronExpr) > 9 && cfg.CronExpr[:9] == "interval:":
		var minutes int
		_, err := fmt.Sscanf(cfg.CronExpr, "interval:%d", &minutes)
		if err != nil || minutes < 1 {
			return time.Time{}, fmt.Errorf("invalid interval schedule: %s", cfg.CronExpr)
		}
		return now.Add(time.Duration(minutes) * time.Minute), nil

	default:
		return time.Time{}, fmt.Errorf("unknown schedule format: %s", cfg.CronExpr)
	}
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
	jobs := make([]map[string]any, 0)
	for _, job := range s.jobs {
		j := map[string]any{
			"id":         job.ID,
			"pluginId":   job.PluginID,
			"pluginName": job.PluginName,
			"enabled":    job.Enabled,
			"schedule":   job.Schedule.CronExpr,
			"lastStatus": job.LastStatus,
		}
		if job.NextRunAt != nil {
			j["nextRunAt"] = job.NextRunAt.Format(time.RFC3339)
		}
		if job.LastRunAt != nil {
			j["lastRunAt"] = job.LastRunAt.Format(time.RFC3339)
		}
		jobs = append(jobs, j)
	}
	s.wsHub.Broadcast(ws.EventPublishProgress, map[string]any{
		"type": "scheduled_jobs_update",
		"jobs": jobs,
	})
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
