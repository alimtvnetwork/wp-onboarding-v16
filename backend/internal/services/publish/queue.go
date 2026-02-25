// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"
	"fmt"
	"sync"
	"time"

	"wp-plugin-publish/internal/enums/queue_status"
	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// QueueConfig holds publish queue settings
type QueueConfig struct {
	MaxConcurrent    int           // Max simultaneous publish operations
	MaxQueueSize     int           // Max pending items in queue
	OperationTimeout time.Duration // Timeout for each publish operation
}

// DefaultQueueConfig returns sensible defaults
func DefaultQueueConfig() QueueConfig {
	return QueueConfig{
		MaxConcurrent:    2,
		MaxQueueSize:     50,
		OperationTimeout: 5 * time.Minute,
	}
}

// QueueItem represents a pending publish operation
type QueueItem struct {
	Id         string
	PluginId   int64
	PluginName string
	SiteId     int64
	SiteName   string
	Options    PublishOptions
	Priority   int // Higher = processed first
	QueuedAt   time.Time
	Status     queuestatus.Variant
}

// QueueStatus provides queue state overview
type QueueStatus struct {
	Active    int
	Queued    int
	Completed int
	Failed    int
	Items     []QueueItem
}

// PublishQueue manages concurrent publish operations with rate limiting
type PublishQueue struct {
	config    QueueConfig
	service   *Service
	wsHub     *ws.Hub

	mu        sync.Mutex
	items     []*QueueItem
	active    map[string]*QueueItem
	completed []*QueueItem
	sem       chan struct{} // Semaphore for concurrency control
	
	cancel    context.CancelFunc
	wg        sync.WaitGroup
}

// NewPublishQueue creates a new publish queue
func NewPublishQueue(service *Service, wsHub *ws.Hub, config QueueConfig) *PublishQueue {
	return &PublishQueue{
		config:    config,
		service:   service,
		wsHub:     wsHub,
		items:     make([]*QueueItem, 0),
		active:    make(map[string]*QueueItem),
		completed: make([]*QueueItem, 0, 100),
		sem:       make(chan struct{}, config.MaxConcurrent),
	}
}

// Enqueue adds a publish operation to the queue
func (q *PublishQueue) Enqueue(item QueueItem) (string, error) {
	q.mu.Lock()
	defer q.mu.Unlock()

	if len(q.items) >= q.config.MaxQueueSize {
		return "", apperror.New(apperror.ErrInternal, "publish queue is full").
			WithValue("current", fmt.Sprintf("%d", len(q.items))).
			WithValue("max", fmt.Sprintf("%d", q.config.MaxQueueSize))
	}

	item.Id = fmt.Sprintf("pq-%d-%d-%d", item.PluginId, item.SiteId, time.Now().UnixMilli())
	item.QueuedAt = time.Now()
	item.Status = queuestatus.Queued

	q.items = append(q.items, &item)
	q.broadcastStatus()
	go q.processNext()

	return item.Id, nil
}

// EnqueueBatch adds multiple publish operations
func (q *PublishQueue) EnqueueBatch(items []QueueItem) ([]string, error) {
	ids := make([]string, 0, len(items))
	for _, item := range items {
		id, err := q.Enqueue(item)
		if err != nil {
			return ids, err
		}
		ids = append(ids, id)
	}
	return ids, nil
}

// Cancel cancels a queued (not yet running) item
func (q *PublishQueue) Cancel(itemId string) bool {
	q.mu.Lock()
	defer q.mu.Unlock()

	for i, item := range q.items {
		if item.Id == itemId && item.Status.IsQueued() {
			item.Status = queuestatus.Cancelled
			q.completed = append(q.completed, item)
			q.items = append(q.items[:i], q.items[i+1:]...)
			q.broadcastStatus()
			return true
		}
	}
	return false
}

// GetStatus returns current queue status
func (q *PublishQueue) GetStatus() QueueStatus {
	q.mu.Lock()
	defer q.mu.Unlock()

	status := QueueStatus{
		Active: len(q.active),
		Queued: len(q.items),
		Items:  make([]QueueItem, 0),
	}
	status.Completed, status.Failed = q.countCompletedLocked()

	for _, item := range q.items {
		status.Items = append(status.Items, *item)
	}
	for _, item := range q.active {
		status.Items = append(status.Items, *item)
	}
	return status
}

// countCompletedLocked counts completed and failed items. Must be called with mu held.
func (q *PublishQueue) countCompletedLocked() (int, int) {
	completed, failed := 0, 0
	for _, item := range q.completed {
		switch {
		case item.Status.IsCompleted():
			completed++
		case item.Status.IsFailed():
			failed++
		}
	}
	return completed, failed
}

// processNext pulls the next queued item and executes it
func (q *PublishQueue) processNext() {
	item := q.dequeueHighestPriority()
	if item == nil {
		return
	}

	q.sem <- struct{}{} // Acquire semaphore

	q.wg.Add(1)
	go q.executeQueueItem(item)
}

// dequeueHighestPriority finds and removes the highest-priority queued item.
func (q *PublishQueue) dequeueHighestPriority() *QueueItem {
	q.mu.Lock()
	defer q.mu.Unlock()

	bestIdx := -1
	for i, item := range q.items {
		if item.Status.IsQueued() {
			if bestIdx == -1 || item.Priority > q.items[bestIdx].Priority {
				bestIdx = i
			}
		}
	}
	if bestIdx == -1 {
		return nil
	}

	item := q.items[bestIdx]
	q.items = append(q.items[:bestIdx], q.items[bestIdx+1:]...)
	item.Status = queuestatus.Running
	q.active[item.Id] = item
	return item
}

// executeQueueItem runs a single publish operation and records the result.
func (q *PublishQueue) executeQueueItem(item *QueueItem) {
	defer q.wg.Done()
	defer func() { <-q.sem }()

	ctx, cancel := context.WithTimeout(context.Background(), q.config.OperationTimeout)
	defer cancel()

	q.broadcastStatus()

	publishResult := q.service.Publish(ctx, item.PluginId, item.SiteId, item.Options)
	q.recordResult(item, publishResult.HasError())

	q.broadcastStatus()
	q.processNext()
}

// recordResult moves an item from active to completed with the appropriate status.
func (q *PublishQueue) recordResult(item *QueueItem, hasError bool) {
	q.mu.Lock()
	defer q.mu.Unlock()

	delete(q.active, item.Id)
	if hasError {
		item.Status = queuestatus.Failed
	} else {
		item.Status = queuestatus.Completed
	}
	q.completed = append(q.completed, item)
	q.trimCompletedLocked()
}

// trimCompletedLocked keeps the completed list at most 100 entries. Must be called with mu held.
func (q *PublishQueue) trimCompletedLocked() {
	if len(q.completed) > 100 {
		q.completed = q.completed[len(q.completed)-100:]
	}
}

// broadcastStatus sends queue status via WebSocket
func (q *PublishQueue) broadcastStatus() {
	if q.wsHub == nil {
		return
	}
	q.mu.Lock()
	completed, failed := q.countCompletedLocked()
	status := QueueStatus{
		Active: len(q.active),
		Queued: len(q.items),
		Completed: completed,
		Failed:    failed,
	}
	q.mu.Unlock()

	ws.Broadcast(q.wsHub, ws.EventPublishProgress, ws.QueueStatusData{
		Type:      "queue_status",
		Active:    status.Active,
		Queued:    status.Queued,
		Completed: status.Completed,
		Failed:    status.Failed,
	})
}

// Shutdown gracefully stops the queue
func (q *PublishQueue) Shutdown() {
	if q.cancel != nil {
		q.cancel()
	}
	q.wg.Wait()
}
