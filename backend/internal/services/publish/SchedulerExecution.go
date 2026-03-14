package publish

import (
	"time"

	"wp-plugin-publish/internal/ws"
)

// scheduleTimer sets up a timer for the job's next run
func (s *PublishScheduler) scheduleTimer(job *ScheduledJob) {
	isEnabled := job.IsEnabled
	isScheduled := job.NextRunAt != nil
	canRun := isEnabled && isScheduled

	if !canRun {
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
	isJobMissing := !isFound || !job.IsEnabled

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
		IsEnabled: job.IsEnabled, Schedule: job.Schedule.CronExpr, LastStatus: job.LastStatus,
	}
	if job.NextRunAt != nil {
		j.NextRunAt = job.NextRunAt.Format(time.RFC3339)
	}
	if job.LastRunAt != nil {
		j.LastRunAt = job.LastRunAt.Format(time.RFC3339)
	}
	return j
}
