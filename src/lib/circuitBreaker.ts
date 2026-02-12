/**
 * Circuit Breaker Pattern Implementation
 * 
 * Prevents repeated calls to failing functions by:
 * - Tracking failure counts per function/endpoint
 * - Opening circuit after threshold failures
 * - Auto-resetting after cooldown period
 * - Configurable thresholds and cooldowns
 */

import { logger } from './logger';

export interface CircuitBreakerConfig {
  /** Number of failures before circuit opens. Default: 5 */
  failureThreshold: number;
  /** Cooldown period in milliseconds before retry. Default: 60000 (1 minute) */
  cooldownMs: number;
  /** Time window for counting failures in milliseconds. Default: 60000 */
  failureWindowMs: number;
}

interface CircuitState {
  failures: number;
  lastFailureTime: number;
  isOpen: boolean;
  openedAt?: number;
}

export type CircuitStatus = 'closed' | 'open' | 'half-open';

const DEFAULT_CONFIG: CircuitBreakerConfig = {
  failureThreshold: 5,
  cooldownMs: 60000,
  failureWindowMs: 60000,
};

// Global config that can be updated from settings
let globalConfig: CircuitBreakerConfig = { ...DEFAULT_CONFIG };

// Circuit states by function key
const circuits = new Map<string, CircuitState>();

/**
 * Get or create circuit state for a key
 */
function getCircuitState(key: string): CircuitState {
  if (!circuits.has(key)) {
    circuits.set(key, {
      failures: 0,
      lastFailureTime: 0,
      isOpen: false,
    });
  }
  return circuits.get(key)!;
}

/**
 * Check if a failure is within the counting window
 */
function isWithinWindow(timestamp: number): boolean {
  return Date.now() - timestamp < globalConfig.failureWindowMs;
}

/**
 * Check if cooldown period has elapsed
 */
function isCooldownComplete(state: CircuitState): boolean {
  if (!state.openedAt) return true;
  return Date.now() - state.openedAt >= globalConfig.cooldownMs;
}

/**
 * Circuit Breaker class for managing function call protection
 */
class CircuitBreaker {
  /**
   * Check if operation can be executed
   * Returns true if circuit is closed or in half-open state
   */
  canExecute(functionKey: string): boolean {
    const state = getCircuitState(functionKey);

    // Circuit is closed - allow execution
    if (!state.isOpen) {
      return true;
    }

    // Circuit is open - check if cooldown complete (half-open)
    if (isCooldownComplete(state)) {
      logger.debug(`Circuit half-open for ${functionKey}, allowing test execution`, {
        functionKey,
        cooldownMs: globalConfig.cooldownMs,
      });
      return true;
    }

    // Circuit is open and cooldown not complete
    const remainingCooldown = state.openedAt
      ? globalConfig.cooldownMs - (Date.now() - state.openedAt)
      : 0;

    logger.warn(`Circuit open for ${functionKey}, blocking execution`, {
      functionKey,
      failures: state.failures,
      remainingCooldownMs: Math.max(0, remainingCooldown),
    });

    return false;
  }

  /**
   * Record a successful operation - resets the circuit
   */
  recordSuccess(functionKey: string): void {
    const state = getCircuitState(functionKey);
    
    if (state.isOpen) {
      logger.info(`Circuit closed for ${functionKey} after successful operation`, {
        functionKey,
        previousFailures: state.failures,
      });
    }

    // Reset circuit state
    state.failures = 0;
    state.lastFailureTime = 0;
    state.isOpen = false;
    state.openedAt = undefined;
  }

  /**
   * Record a failed operation
   */
  recordFailure(functionKey: string, error?: unknown): void {
    const state = getCircuitState(functionKey);
    const now = Date.now();

    // Reset count if outside failure window
    if (!isWithinWindow(state.lastFailureTime)) {
      state.failures = 0;
    }

    state.failures++;
    state.lastFailureTime = now;

    logger.debug(`Failure recorded for ${functionKey}`, {
      functionKey,
      failures: state.failures,
      threshold: globalConfig.failureThreshold,
      errorMessage: error instanceof Error ? error.message : String(error),
    });

    // Check if threshold reached
    if (state.failures >= globalConfig.failureThreshold && !state.isOpen) {
      state.isOpen = true;
      state.openedAt = now;

      logger.error(`Circuit opened for ${functionKey} after ${state.failures} failures`, error, {
        functionKey,
        failures: state.failures,
        threshold: globalConfig.failureThreshold,
        cooldownMs: globalConfig.cooldownMs,
      });
    }
  }

  /**
   * Manually reset a circuit
   */
  reset(functionKey: string): void {
    const state = getCircuitState(functionKey);
    const wasOpen = state.isOpen;

    state.failures = 0;
    state.lastFailureTime = 0;
    state.isOpen = false;
    state.openedAt = undefined;

    if (wasOpen) {
      logger.info(`Circuit manually reset for ${functionKey}`, { functionKey });
    }
  }

  /**
   * Reset all circuits
   */
  resetAll(): void {
    const openCircuits = Array.from(circuits.entries())
      .filter(([, state]) => state.isOpen)
      .map(([key]) => key);

    circuits.clear();

    if (openCircuits.length > 0) {
      logger.info(`All circuits reset`, { previouslyOpen: openCircuits });
    }
  }

  /**
   * Get status of a specific circuit
   */
  getStatus(functionKey: string): CircuitStatus {
    const state = getCircuitState(functionKey);

    if (!state.isOpen) {
      return 'closed';
    }

    if (isCooldownComplete(state)) {
      return 'half-open';
    }

    return 'open';
  }

  /**
   * Get all circuit statuses for diagnostics
   */
  getAllStatuses(): Record<string, { status: CircuitStatus; failures: number; remainingCooldownMs?: number }> {
    const result: Record<string, { status: CircuitStatus; failures: number; remainingCooldownMs?: number }> = {};

    circuits.forEach((state, key) => {
      const status = this.getStatus(key);
      const remainingCooldownMs =
        state.isOpen && state.openedAt
          ? Math.max(0, globalConfig.cooldownMs - (Date.now() - state.openedAt))
          : undefined;

      result[key] = {
        status,
        failures: state.failures,
        remainingCooldownMs: status === 'open' ? remainingCooldownMs : undefined,
      };
    });

    return result;
  }
}

// Singleton instance
export const circuitBreaker = new CircuitBreaker();

/**
 * Wrapper function that applies circuit breaker pattern
 * 
 * @example
 * const result = await withCircuitBreaker(
 *   'api.getSites',
 *   () => api.getSites()
 * );
 */
export async function withCircuitBreaker<T>(
  functionKey: string,
  operation: () => Promise<T>
): Promise<T> {
  // Check if we can execute
  if (!circuitBreaker.canExecute(functionKey)) {
    const error = new Error(`Circuit breaker open for ${functionKey}`);
    (error as unknown as { code: string }).code = 'E_CIRCUIT_OPEN';
    throw error;
  }

  try {
    const result = await operation();
    circuitBreaker.recordSuccess(functionKey);
    return result;
  } catch (error: unknown) {
    circuitBreaker.recordFailure(functionKey, error);
    throw error;
  }
}

/**
 * Update circuit breaker configuration (call from settings)
 */
export function configureCircuitBreaker(config: Partial<CircuitBreakerConfig>): void {
  globalConfig = { ...globalConfig, ...config };
  logger.debug('Circuit breaker config updated', { config: globalConfig });
}

/**
 * Get current circuit breaker configuration
 */
export function getCircuitBreakerConfig(): CircuitBreakerConfig {
  return { ...globalConfig };
}

/**
 * Reset circuit breaker configuration to defaults
 */
export function resetCircuitBreakerConfig(): void {
  globalConfig = { ...DEFAULT_CONFIG };
}
