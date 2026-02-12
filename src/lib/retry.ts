/**
 * Retry Logic with Exponential Backoff
 * 
 * Provides configurable retry wrapper for async operations with:
 * - Exponential backoff with jitter
 * - Configurable max attempts and delays
 * - Integration with logger for visibility
 * - Per-operation custom retry conditions
 */

import { logger } from './logger';

export interface RetryConfig {
  /** Maximum number of attempts (including first try). Default: 3 */
  maxAttempts: number;
  /** Initial delay in milliseconds. Default: 1000 */
  initialDelayMs: number;
  /** Maximum delay in milliseconds. Default: 30000 */
  maxDelayMs: number;
  /** Backoff multiplier. Default: 2 (exponential) */
  backoffMultiplier: number;
  /** Jitter factor (0-1) to randomize delays. Default: 0.1 */
  jitterFactor: number;
  /** Custom function to determine if error should be retried */
  shouldRetry?: (error: unknown, attempt: number) => boolean;
  /** Callback on each retry attempt */
  onRetry?: (error: unknown, attempt: number, delayMs: number) => void;
}

export interface RetryContext {
  /** Name of the function being retried (for logging) */
  functionName: string;
  /** Component name (for logging) */
  component?: string;
  /** Additional context for logging */
  context?: RetryLogContext;
}

/** Structured retry log context — replaces Record<string, unknown> per GE-1 */
export interface RetryLogContext {
  [key: string]: unknown;
}

const DEFAULT_CONFIG: RetryConfig = {
  maxAttempts: 3,
  initialDelayMs: 1000,
  maxDelayMs: 30000,
  backoffMultiplier: 2,
  jitterFactor: 0.1,
};

// Global retry config that can be updated from settings
let globalConfig: Partial<RetryConfig> = {};

/**
 * Calculate delay with exponential backoff and jitter
 */
function calculateDelay(attempt: number, config: RetryConfig): number {
  // Exponential backoff: initialDelay * (multiplier ^ attempt)
  const exponentialDelay = config.initialDelayMs * Math.pow(config.backoffMultiplier, attempt);
  
  // Cap at max delay
  const cappedDelay = Math.min(exponentialDelay, config.maxDelayMs);
  
  // Add jitter to prevent thundering herd
  const jitter = cappedDelay * config.jitterFactor * (Math.random() * 2 - 1);
  
  return Math.max(0, Math.round(cappedDelay + jitter));
}

/**
 * Default retry condition - retry on network errors, 5xx errors, timeout
 */
function defaultShouldRetry(error: unknown): boolean {
  // Always retry network errors
  if (error instanceof TypeError && error.message.includes('fetch')) {
    return true;
  }

  // Check for specific error codes
  if (error && typeof error === 'object' && 'code' in error) {
    const code = (error as { code: string }).code;
    // Retry on network/timeout errors
    if (['E9003', 'E9004', 'ECONNRESET', 'ETIMEDOUT'].includes(code)) {
      return true;
    }
  }

  // Check HTTP status for server errors (5xx)
  if (error && typeof error === 'object' && 'status' in error) {
    const status = (error as { status: number }).status;
    if (status >= 500 && status < 600) {
      return true;
    }
  }

  return false;
}

/**
 * Sleep for specified duration
 */
function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Execute an async operation with retry logic
 * 
 * @example
 * const result = await withRetry(
 *   () => api.getSites(),
 *   { maxAttempts: 3 },
 *   { functionName: 'getSites', component: 'SitesPage' }
 * );
 */
export async function withRetry<T>(
  operation: () => Promise<T>,
  configOverride: Partial<RetryConfig> = {},
  context: RetryContext
): Promise<T> {
  const config: RetryConfig = {
    ...DEFAULT_CONFIG,
    ...globalConfig,
    ...configOverride,
  };

  let lastError: unknown;

  for (let attempt = 0; attempt < config.maxAttempts; attempt++) {
    try {
      // Log attempt if not first try
      if (attempt > 0) {
        logger.debug(`Retry attempt ${attempt + 1}/${config.maxAttempts}`, {
          functionName: context.functionName,
          component: context.component,
          ...context.context,
        });
      }

      const result = await operation();
      
      // Log success after retry
      if (attempt > 0) {
        logger.info(`Retry succeeded after ${attempt + 1} attempts`, {
          functionName: context.functionName,
          component: context.component,
        });
      }

      return result;
    } catch (error) {
      lastError = error;

      // Check if we should retry
      const shouldRetry = config.shouldRetry
        ? config.shouldRetry(error, attempt)
        : defaultShouldRetry(error);

      // If this was the last attempt or we shouldn't retry, throw
      if (attempt >= config.maxAttempts - 1 || !shouldRetry) {
        logger.error(
          `Operation failed after ${attempt + 1} attempt(s): ${context.functionName}`,
          error,
          {
            functionName: context.functionName,
            component: context.component,
            totalAttempts: attempt + 1,
            ...context.context,
          }
        );
        throw error;
      }

      // Calculate delay for next attempt
      const delayMs = calculateDelay(attempt, config);

      // Log retry warning
      logger.warn(`Retrying ${context.functionName} in ${delayMs}ms (attempt ${attempt + 1}/${config.maxAttempts})`, {
        functionName: context.functionName,
        component: context.component,
        attempt: attempt + 1,
        delayMs,
        errorMessage: error instanceof Error ? error.message : String(error),
      });

      // Call onRetry callback if provided
      if (config.onRetry) {
        config.onRetry(error, attempt + 1, delayMs);
      }

      // Wait before retrying
      await sleep(delayMs);
    }
  }

  // This should never be reached, but TypeScript needs it
  throw lastError;
}

/**
 * Create a retryable version of an async function
 * 
 * @example
 * const retryableGetSites = createRetryable(
 *   api.getSites,
 *   { maxAttempts: 3 },
 *   { functionName: 'getSites', component: 'API' }
 * );
 */
export function createRetryable<TArgs extends unknown[], TResult>(
  fn: (...args: TArgs) => Promise<TResult>,
  configOverride: Partial<RetryConfig> = {},
  context: RetryContext
): (...args: TArgs) => Promise<TResult> {
  return (...args: TArgs) =>
    withRetry(() => fn(...args), configOverride, context);
}

/**
 * Update global retry configuration (call from settings)
 */
export function configureRetry(config: Partial<RetryConfig>): void {
  globalConfig = { ...globalConfig, ...config };
  logger.debug('Retry config updated', { config: globalConfig });
}

/**
 * Get current retry configuration
 */
export function getRetryConfig(): Partial<RetryConfig> {
  return { ...DEFAULT_CONFIG, ...globalConfig };
}

/**
 * Reset retry configuration to defaults
 */
export function resetRetryConfig(): void {
  globalConfig = {};
}
