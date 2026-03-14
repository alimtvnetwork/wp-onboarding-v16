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
	job.IsEnabled = true

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

// Shutdown gracefully stops the scheduler
func (s *PublishScheduler) Shutdown() {
	s.cancel()
	s.mu.Lock()
	defer s.mu.Unlock()
	for _, timer := range s.timers {
		timer.Stop()
	}
}
