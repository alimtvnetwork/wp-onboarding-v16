// Package site — remote plugins cache status operations.
package site

import (
	"context"
	"database/sql"
	"time"

	"wp-plugin-publish/pkg/apperror"
)

// CacheStatus holds the result of a cache status check.
type CacheStatus struct {
	IsValid   bool
	CachedAt  *time.Time
	ExpiresAt *time.Time
}

// cacheTimestampStrings holds raw timestamp strings from the database.
type cacheTimestampStrings struct {
	CachedAt  string
	ExpiresAt string
}

// GetRemotePluginsCacheStatus returns cache status for a site
func (s *Service) GetRemotePluginsCacheStatus(ctx context.Context, siteId int64) (*CacheStatus, *apperror.AppError) {
	timestamps, queryErr := s.queryCacheTimestamps(ctx, siteId)
	if queryErr != nil {
		return nil, queryErr
	}

	if timestamps.CachedAt == "" {
		result := &CacheStatus{}

		return result, nil
	}

	return parseCacheTimestamps(timestamps), nil
}

// queryCacheTimestamps fetches raw cache timestamps from the database.
func (s *Service) queryCacheTimestamps(ctx context.Context, siteId int64) (*cacheTimestampStrings, *apperror.AppError) {
	query := `SELECT CachedAt, ExpiresAt FROM RemotePluginsCache WHERE SiteId = ?`
	var cachedAtStr, expiresAtStr string
	err := s.db.QueryRowContext(ctx, query, siteId).Scan(&cachedAtStr, &expiresAtStr)
	if err != nil {
	if err == sql.ErrNoRows {
			return &cacheTimestampStrings{}, nil
		}

		return nil, apperror.Wrap(err, apperror.ErrDatabaseQuery, "query cache timestamps")
	}

	result := &cacheTimestampStrings{
		CachedAt:  cachedAtStr,
		ExpiresAt: expiresAtStr,
	}

	return result, nil
}

// parseCacheTimestamps converts timestamp strings to cache status.
func parseCacheTimestamps(timestamps *cacheTimestampStrings) *CacheStatus {
	cachedAtVal := parseTime(timestamps.CachedAt)
	expiresAtVal := parseTime(timestamps.ExpiresAt)
	isExpired :=
		expiresAtVal.IsZero() ||
		expiresAtVal.Before(time.Now())
	isStale := isExpired
	isValid := !isStale

	return &CacheStatus{
		IsValid:   isValid,
		CachedAt:  timeToPtr(cachedAtVal),
		ExpiresAt: timeToPtr(expiresAtVal),
	}
}

// timeToPtr returns a pointer to the time value, or nil if zero.
func timeToPtr(t time.Time) *time.Time {
	if t.IsZero() {
		return nil
	}

	return &t
}
