// Package ratelimit provides an in-memory token-bucket rate limiter.
package ratelimit

import (
	"sync"
	"time"
)

// Limiter implements a per-key token bucket rate limiter.
type Limiter struct {
	mu      sync.Mutex
	buckets map[string]*bucket
	rate    int           // tokens per interval
	interval time.Duration // refill interval
}

// bucket holds the token count and last refill time for a single key.
type bucket struct {
	tokens   int
	lastFill time.Time
}

// New creates a new rate limiter.
// rate is the maximum requests allowed per interval.
func New(rate int, interval time.Duration) *Limiter {
	return &Limiter{
		buckets:  make(map[string]*bucket),
		rate:     rate,
		interval: interval,
	}
}

// Allow checks if a request from the given key is allowed.
// Returns true if under the rate limit, false if exceeded.
func (l *Limiter) Allow(key string) bool {
	l.mu.Lock()
	defer l.mu.Unlock()

	b, exists := l.buckets[key]
	isNewBucket := !exists

	if isNewBucket {
		b = &bucket{tokens: l.rate, lastFill: time.Now()}
		l.buckets[key] = b
	}

	l.refill(b)

	hasTokens := b.tokens > 0

	if hasTokens {
		b.tokens--

		return true
	}

	return false
}

// refill adds tokens based on elapsed time since last refill.
func (l *Limiter) refill(b *bucket) {
	elapsed := time.Since(b.lastFill)
	intervals := int(elapsed / l.interval)
	isNoRefillDue := intervals <= 0

	if isNoRefillDue {
		return
	}

	b.tokens += intervals * l.rate
	isOverMax := b.tokens > l.rate

	if isOverMax {
		b.tokens = l.rate
	}

	b.lastFill = time.Now()
}

// Cleanup removes stale buckets that haven't been accessed recently.
// Call periodically to prevent memory leaks from abandoned keys.
func (l *Limiter) Cleanup(maxAge time.Duration) {
	l.mu.Lock()
	defer l.mu.Unlock()

	cutoff := time.Now().Add(-maxAge)

	for key, b := range l.buckets {
		isStale := b.lastFill.Before(cutoff)

		if isStale {
			delete(l.buckets, key)
		}
	}
}
