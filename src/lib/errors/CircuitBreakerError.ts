/**
 * Typed error for circuit breaker open state.
 * Replaces unsafe `as unknown as` double casts.
 */
export class CircuitBreakerError extends Error {
  readonly code = 'E_CIRCUIT_OPEN' as const;

  constructor(functionKey: string) {
    super(`Circuit breaker open for ${functionKey}`);
    this.name = 'CircuitBreakerError';
  }
}
