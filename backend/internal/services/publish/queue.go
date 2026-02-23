// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"
	"fmt"
	"sync"
	"time"

	queuestatus "wp-plugin-publish/internal/enums/queue_status"
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
	Active    int          `json:"active"`
	Queued    int          `json:"queued"`
	Completed int          `json:"completed"`
	Failed    int          `json:"failed"`
	Items     []QueueItem  `json:"items"`
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

	// Generate ID
	item.Id = fmt.Sprintf("pq-%d-%d-%d", item.PluginId, item.SiteId, time.Now().UnixMilli())
	item.QueuedAt = time.Now()
	item.Status = queuestatus.Queued

	q.items = append(q.items, &item)

	// Broadcast queue update
	q.broadcastStatus()

	// Try to process immediately
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
		Active:    len(q.active),
		Queued:    len(q.items),
		Items:     make([]QueueItem, 0),
	}

	// Count completed/failed
	for _, item := range q.completed {
		switch {
		case item.Status.IsCompleted():
			status.Completed++
		case item.Status.IsFailed():
			status.Failed++
		}
	}

	// Include all items for visibility
	for _, item := range q.items {
		status.Items = append(status.Items, *item)
	}
	for _, item := range q.active {
		status.Items = append(status.Items, *item)
	}

	return status
}

// processNext pulls the next queued item and executes it
func (q *PublishQueue) processNext() {
	q.mu.Lock()
	if len(q.items) == 0 {
		q.mu.Unlock()
		return
	}

	// Find highest priority queued item
	bestIdx := -1
	for i, item := range q.items {
		if item.Status.IsQueued() {
			if bestIdx == -1 || item.Priority > q.items[bestIdx].Priority {
				bestIdx = i
			}
		}
	}

	if bestIdx == -1 {
		q.mu.Unlock()
		return
	}

	item := q.items[bestIdx]
	q.items = append(q.items[:bestIdx], q.items[bestIdx+1:]...)
	item.Status = queuestatus.Running
	q.active[item.Id] = item
	q.mu.Unlock()

	// Acquire semaphore (blocks if at max concurrency)
	q.sem <- struct{}{}
	
	q.wg.Add(1)
	go func() {
		defer q.wg.Done()
		defer func() { <-q.sem }() // Release semaphore

		ctx, cancel := context.WithTimeout(context.Background(), q.config.OperationTimeout)
		defer cancel()

		q.broadcastStatus()

		// Execute the publish
		publishResult := q.service.Publish(ctx, item.PluginId, item.SiteId, item.Options)

		q.mu.Lock()
		delete(q.active, item.Id)
		if publishResult.HasError() || (publishResult.IsSafe() && !publishResult.Value().Success) {
			item.Status = queuestatus.Failed
		} else {
			item.Status = queuestatus.Completed
		}
		q.completed = append(q.completed, item)
		
		// Trim completed list
		if len(q.completed) > 100 {
			q.completed = q.completed[len(q.completed)-100:]
		}
		q.mu.Unlock()

		q.broadcastStatus()

		// Process next in queue
		q.processNext()
	}()
}

// broadcastStatus sends queue status via WebSocket
func (q *PublishQueue) broadcastStatus() {
	if q.wsHub == nil {
		return
	}
	status := QueueStatus{
		Active: len(q.active),
		Queued: len(q.items),
	}
	for _, item := range q.completed {
		switch {
		case item.Status.IsCompleted():
			status.Completed++
		case item.Status.IsFailed():
			status.Failed++
		}
	}
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
