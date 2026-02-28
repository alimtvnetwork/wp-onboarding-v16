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
	"wp-plugin-publish/pkg/apperror"
)

// RetryConfig holds retry settings for transient failures
type RetryConfig struct {
	MaxAttempts   int           // Maximum number of attempts (including initial)
	InitialDelay  time.Duration // Delay before first retry
	MaxDelay      time.Duration // Maximum delay between retries
	BackoffFactor float64       // Multiplier for exponential backoff
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
	Attempts   int
	LastError  *apperror.AppError
	TotalDelay time.Duration
	Succeeded  bool
}

// withRetry executes fn with exponential backoff retry for transient errors.
// It returns the result of the last successful call, or the last error if all attempts fail.
func withRetry[T any](ctx context.Context, cfg RetryConfig, operation string, fn func(attempt int) (T, *apperror.AppError)) (T, RetryResult) {
	var zero T
	result := RetryResult{}
	startTime := time.Now()

	for attempt := 1; attempt <= cfg.MaxAttempts; attempt++ {
		result.Attempts = attempt

		val, appErr := fn(attempt)
		if appErr == nil {
			result.Succeeded = true
			result.TotalDelay = time.Since(startTime)

			return val, result
		}

		result.LastError = appErr

		if isPermanentError(appErr) {
			result.TotalDelay = time.Since(startTime)

			return zero, result
		}

		if attempt >= cfg.MaxAttempts {
			break
		}

		if ctx.Err() != nil {
			result.LastError = apperror.Wrap(ctx.Err(), apperror.ErrInternal, "context cancelled during retry")
			break
		}

		delay := calculateBackoff(cfg, attempt)

		select {
		case <-ctx.Done():
			result.LastError = apperror.Wrap(ctx.Err(), apperror.ErrInternal, "context cancelled during retry backoff")
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

// isTransientAppError determines if an AppError wraps a transient cause worth retrying.
func isTransientAppError(appErr *apperror.AppError) bool {
	if appErr == nil {
		return false
	}

	cause := appErr.Unwrap()
	isCauseMissing := cause == nil

	if isCauseMissing {
		return isTransientMessage(appErr.Error())
	}

	netErr, isNetError := cause.(net.Error)

	if isNetError && netErr != nil {
		return true
	}

	apiErr := wordpress.ExtractApiError(cause)
	if apiErr != nil {
		return wordpress.HttpStatusType(apiErr.StatusCode).IsRetryable()
	}

	return isTransientMessage(cause.Error())
}

// isPermanentError returns true when the error is not transient and should not be retried.
func isPermanentError(appErr *apperror.AppError) bool {
	return !isTransientAppError(appErr)
}

// isTransientMessage checks error text for common transient patterns.
func isTransientMessage(msg string) bool {
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
		"eof",
		"broken pipe",
		"no such host",
		"i/o timeout",
		"tls handshake timeout",
	}

	lower := strings.ToLower(msg)
	for _, pattern := range transientPatterns {
		if strings.Contains(lower, pattern) {
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
