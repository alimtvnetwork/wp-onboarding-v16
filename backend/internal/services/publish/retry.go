// Package publish provides plugin publishing to WordPress sites
package publish

import (
	"context"
	"fmt"
	"math"
	"net"
	"strings"
	"time"

	"wp-plugin-publish/internal/wordpress"
)

// RetryConfig holds retry settings for transient failures
type RetryConfig struct {
	MaxAttempts     int           // Maximum number of attempts (including initial)
	InitialDelay    time.Duration // Delay before first retry
	MaxDelay        time.Duration // Maximum delay between retries
	BackoffFactor   float64       // Multiplier for exponential backoff
}

// DefaultRetryConfig returns sensible defaults for publish retries
func DefaultRetryConfig() RetryConfig {
	return RetryConfig{
		MaxAttempts:   3,
		InitialDelay:  2 * time.Second,
		MaxDelay:      30 * time.Second,
		BackoffFactor: 2.0,
	}
}

// RetryResult captures the outcome of a retried operation
type RetryResult struct {
	Attempts    int
	LastError   error
	TotalDelay  time.Duration
	Succeeded   bool
}

// withRetry executes fn with exponential backoff retry for transient errors.
// It returns the result of the last successful call, or the last error if all attempts fail.
func withRetry[T any](ctx context.Context, cfg RetryConfig, operation string, fn func(attempt int) (T, error)) (T, RetryResult) {
	var zero T
	result := RetryResult{}
	startTime := time.Now()

	for attempt := 1; attempt <= cfg.MaxAttempts; attempt++ {
		result.Attempts = attempt

		val, err := fn(attempt)
		if err == nil {
			result.Succeeded = true
			result.TotalDelay = time.Since(startTime)
			return val, result
		}

		result.LastError = err

		// Don't retry non-transient errors
		if !isTransientError(err) {
			result.TotalDelay = time.Since(startTime)
			return zero, result
		}

		// Don't retry if this was the last attempt
		if attempt >= cfg.MaxAttempts {
			break
		}

		// Check context cancellation
		if ctx.Err() != nil {
			result.LastError = ctx.Err()
			break
		}

		// Calculate backoff delay
		delay := calculateBackoff(cfg, attempt)

		// Wait with context cancellation support
		select {
		case <-ctx.Done():
			result.LastError = ctx.Err()
			result.TotalDelay = time.Since(startTime)
			return zero, result
		case <-time.After(delay):
			// Continue to next attempt
		}
	}

	result.TotalDelay = time.Since(startTime)
	return zero, result
}

// calculateBackoff returns the delay for the given attempt using exponential backoff
func calculateBackoff(cfg RetryConfig, attempt int) time.Duration {
	delay := float64(cfg.InitialDelay) * math.Pow(cfg.BackoffFactor, float64(attempt-1))
	if delay > float64(cfg.MaxDelay) {
		delay = float64(cfg.MaxDelay)
	}
	return time.Duration(delay)
}

// isTransientError determines if an error is likely transient and worth retrying
func isTransientError(err error) bool {
	if err == nil {
		return false
	}

	errMsg := err.Error()

	// Network errors
	if _, ok := err.(net.Error); ok {
		return true
	}

	// WordPress API errors with retryable status codes
	if apiErr, ok := err.(*wordpress.APIError); ok {
		switch apiErr.StatusCode {
		case 408, 429, 500, 502, 503, 504:
			return true
		}
		return false
	}

	// Common transient error patterns
	transientPatterns := []string{
		"connection refused",
		"connection reset",
		"connection timed out",
		"timeout",
		"temporary failure",
		"too many requests",
		"service unavailable",
		"bad gateway",
		"gateway timeout",
		"EOF",
		"broken pipe",
		"no such host",
		"i/o timeout",
		"TLS handshake timeout",
	}

	lower := strings.ToLower(errMsg)
	for _, pattern := range transientPatterns {
		if strings.Contains(lower, strings.ToLower(pattern)) {
			return true
		}
	}

	return false
}

// retryDescription returns a human-readable description of the retry outcome
func retryDescription(result RetryResult) string {
	if result.Succeeded {
		if result.Attempts > 1 {
			return fmt.Sprintf("Succeeded after %d attempts (%s total delay)", result.Attempts, result.TotalDelay.Round(time.Millisecond))
		}
		return "Succeeded on first attempt"
	}
	return fmt.Sprintf("Failed after %d attempts (%s total delay): %v", result.Attempts, result.TotalDelay.Round(time.Millisecond), result.LastError)
}
