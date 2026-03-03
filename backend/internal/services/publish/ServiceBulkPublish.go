// Package publish — bulk publish service for multi-plugin operations
package publish

import (
	"context"
	"fmt"
	"time"

	"wp-plugin-publish/internal/ws"
	"wp-plugin-publish/pkg/apperror"
)

// BulkPublishInput holds the request parameters for a bulk publish operation.
type BulkPublishInput struct {
	PluginIds []int64
	SiteIds   []int64
	Options   PublishOptions
}

// BulkPublishItemResult captures the outcome of a single plugin-site publish.
type BulkPublishItemResult struct {
	PluginId     int64  `json:"pluginId"`
	PluginName   string `json:"pluginName"`
	SiteId       int64  `json:"siteId"`
	SiteName     string `json:"siteName"`
	IsSuccess    bool   `json:"isSuccess"`
	ErrorMessage string `json:"errorMessage,omitempty"`
	BackupId     *int64 `json:"backupId,omitempty"`
	DurationMs   int64  `json:"durationMs"`
}

// BulkPublishResult captures the overall outcome of a bulk publish.
type BulkPublishResult struct {
	TotalOperations int                     `json:"totalOperations"`
	Succeeded       int                     `json:"succeeded"`
	Failed          int                     `json:"failed"`
	DurationMs      int64                   `json:"durationMs"`
	Items           []BulkPublishItemResult `json:"items"`
}

// BulkPublish publishes multiple plugins to multiple sites sequentially.
func (s *Service) BulkPublish(ctx context.Context, input BulkPublishInput) apperror.Result[BulkPublishResult] {
	pairs := buildPublishPairs(input)
	result := initBulkResult(len(pairs))
	startTime := time.Now()

	s.broadcastBulkStarted(input, len(pairs))

	for i, pair := range pairs {
		itemResult := s.executeBulkItem(ctx, pair, input.Options, i, len(pairs))
		result.Items = append(result.Items, itemResult)
		applyItemCounts(&result, itemResult)
	}

	result.DurationMs = time.Since(startTime).Milliseconds()
	s.broadcastBulkComplete(result)

	return apperror.Ok(result)
}

// publishPair represents a single plugin-site combination to publish.
type publishPair struct {
	PluginId int64
	SiteId   int64
}

// buildPublishPairs creates the cartesian product of plugin IDs × site IDs.
func buildPublishPairs(input BulkPublishInput) []publishPair {
	pairs := make([]publishPair, 0, len(input.PluginIds)*len(input.SiteIds))

	for _, pluginId := range input.PluginIds {
		for _, siteId := range input.SiteIds {
			pairs = append(pairs, publishPair{PluginId: pluginId, SiteId: siteId})
		}
	}

	return pairs
}

// initBulkResult initializes an empty BulkPublishResult.
func initBulkResult(capacity int) BulkPublishResult {
	return BulkPublishResult{
		Items: make([]BulkPublishItemResult, 0, capacity),
	}
}

// applyItemCounts updates the running success/fail counters.
func applyItemCounts(result *BulkPublishResult, item BulkPublishItemResult) {
	result.TotalOperations++

	if item.IsSuccess {
		result.Succeeded++
	} else {
		result.Failed++
	}
}

// executeBulkItem publishes a single plugin-site pair and captures the result.
func (s *Service) executeBulkItem(
	ctx context.Context,
	pair publishPair,
	opts PublishOptions,
	index int,
	total int,
) BulkPublishItemResult {
	startTime := time.Now()

	s.broadcastBulkItemStarted(pair, index, total)

	publishResult := s.Publish(ctx, pair.PluginId, pair.SiteId, opts)
	durationMs := time.Since(startTime).Milliseconds()

	if publishResult.HasError() {
		return buildFailedItemResult(pair, publishResult.AppError(), durationMs)
	}

	return buildSuccessItemResult(pair, publishResult.Value(), durationMs)
}

// buildFailedItemResult creates a BulkPublishItemResult for a failed publish.
func buildFailedItemResult(pair publishPair, appErr *apperror.AppError, durationMs int64) BulkPublishItemResult {
	return BulkPublishItemResult{
		PluginId:     pair.PluginId,
		SiteId:       pair.SiteId,
		IsSuccess:    false,
		ErrorMessage: appErr.Error(),
		DurationMs:   durationMs,
	}
}

// buildSuccessItemResult creates a BulkPublishItemResult from a successful publish.
func buildSuccessItemResult(pair publishPair, result PublishResult, durationMs int64) BulkPublishItemResult {
	return BulkPublishItemResult{
		PluginId:   pair.PluginId,
		SiteId:     pair.SiteId,
		IsSuccess:  result.IsSuccess,
		BackupId:   result.BackupId,
		DurationMs: durationMs,
	}
}

// ─── Bulk Broadcast Helpers ─────────────────────────────────────────────────

// broadcastBulkStarted sends the bulk publish started event.
func (s *Service) broadcastBulkStarted(input BulkPublishInput, totalPairs int) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventBulkPublishStarted, ws.BulkPublishStartedData{
		Type:            "bulk_publish_started",
		TotalOperations: totalPairs,
		PluginCount:     len(input.PluginIds),
		SiteCount:       len(input.SiteIds),
	})

	s.log.Info("Bulk publish started",
		"plugins", len(input.PluginIds),
		"sites", len(input.SiteIds),
		"totalPairs", totalPairs,
	)
}

// broadcastBulkItemStarted sends a per-item progress update.
func (s *Service) broadcastBulkItemStarted(pair publishPair, index, total int) {
	if s.wsHub == nil {
		return
	}

	pct := (index * 100) / total

	ws.Broadcast(s.wsHub, ws.EventBulkPublishProgress, ws.BulkPublishProgressData{
		Type:     "bulk_publish_item_started",
		PluginId: pair.PluginId,
		SiteId:   pair.SiteId,
		Current:  index + 1,
		Total:    total,
		Progress: pct,
		Message:  fmt.Sprintf("Publishing plugin %d/%d", index+1, total),
	})
}

// broadcastBulkComplete sends the bulk publish completed event.
func (s *Service) broadcastBulkComplete(result BulkPublishResult) {
	if s.wsHub == nil {
		return
	}

	ws.Broadcast(s.wsHub, ws.EventBulkPublishComplete, ws.BulkPublishCompleteData{
		Type:       "bulk_publish_complete",
		Succeeded:  result.Succeeded,
		Failed:     result.Failed,
		Total:      result.TotalOperations,
		DurationMs: result.DurationMs,
	})

	isSomeFailed := result.Failed > 0
	msg := fmt.Sprintf("Bulk publish complete: %d/%d succeeded in %dms", result.Succeeded, result.TotalOperations, result.DurationMs)

	if isSomeFailed {
		s.log.Warn(msg, "succeeded", result.Succeeded, "failed", result.Failed)
	} else {
		s.log.Info(msg, "succeeded", result.Succeeded, "total", result.TotalOperations)
	}
}
