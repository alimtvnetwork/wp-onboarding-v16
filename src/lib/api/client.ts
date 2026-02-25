// HTTP client — fetchRequest, request (with circuit breaker), ApiClientError, helpers.

import { resolveApiBase, resolveApiOrigin, resolveApiUrl, toAbsoluteUrl } from "@/lib/endpoints";
import { logger } from "@/lib/logger";
import { withCircuitBreaker } from "@/lib/circuitBreaker";
import { CircuitBreakerError } from "@/lib/errors/CircuitBreakerError";
import { logApiCall } from "@/hooks/useExecutionLogger";
import type { ApiResponse, ApiError, ApiMethod, ApiCallMeta, ErrorDiagnosticContext } from './types';
import { isEnvelope, parseEnvelope, looksLikeJson } from './envelope';
import { transformKeys } from './keyTransform';

// Re-export looksLikeJson so methods.ts can use it
export { looksLikeJson };

// ---------------------------------------------------------------------------
// ApiClientError
// ---------------------------------------------------------------------------

export class ApiClientError extends Error {
  readonly apiError: ApiError;
  readonly meta: Required<Pick<ApiCallMeta, "endpoint">> & {
    method?: ApiMethod;
    requestBody?: unknown;
    apiOrigin?: string;
    apiBase: string;
    requestUrl: string;
  };

  constructor(apiError: ApiError, meta: ApiCallMeta) {
    super(apiError.message);
    this.name = "ApiClientError";
    this.apiError = apiError;
    const apiBase = resolveApiBase();
    const requestUrl = toAbsoluteUrl(resolveApiUrl(meta.endpoint));
    this.meta = {
      endpoint: meta.endpoint,
      method: meta.method,
      requestBody: meta.requestBody,
      apiOrigin: resolveApiOrigin(),
      apiBase,
      requestUrl,
    };
  }
}

export function isApiClientError(err: unknown): err is ApiClientError {
  return err instanceof ApiClientError;
}

export function requireSuccess<T>(response: ApiResponse<T>, meta: ApiCallMeta): T {
  if (response.success) return response.data as T;
  const apiError: ApiError =
    response.error ||
    ({
      code: "E9999",
      message: "Unknown API error",
      timestamp: new Date().toISOString(),
    } as ApiError);
  throw new ApiClientError(apiError, meta);
}

// ---------------------------------------------------------------------------
// Core fetch (no circuit breaker)
// ---------------------------------------------------------------------------

async function fetchRequest<T>(
  endpoint: string,
  options?: RequestInit
): Promise<ApiResponse<T>> {
  const method = (options?.method || 'GET') as ApiMethod;
  const functionName = `api.${method.toLowerCase()}.${endpoint}`;
  
  logger.trace(functionName, 'enter', { endpoint, method });
  logApiCall(method, toAbsoluteUrl(resolveApiUrl(endpoint)));
  const startTime = Date.now();
  // Hoist env/diagnostic values once for all error paths
  const envViteApiUrl = (import.meta.env.VITE_API_URL as string | undefined) || "(not set)";
  const envViteWsUrl = (import.meta.env.VITE_WS_URL as string | undefined) || "(not set)";
  const uiOrigin = typeof window !== "undefined" ? window.location.origin : "N/A";

  try {
    const apiBase = resolveApiBase();
    const apiOrigin = resolveApiOrigin();
    const url = resolveApiUrl(endpoint);
    const requestUrl = toAbsoluteUrl(url);

    /** Builds the shared diagnostic context object for error responses. */
    const buildDiagnosticContext = (extras?: ErrorDiagnosticContext) => ({
      requestUrl,
      apiBase,
      apiBaseAbsolute: toAbsoluteUrl(apiBase),
      "VITE_API_URL (raw)": envViteApiUrl,
      "VITE_WS_URL (raw)": envViteWsUrl,
      resolvedApiOrigin: apiOrigin || null,
      uiOrigin,
      ...extras,
    });

    const headers = new Headers(options?.headers);
    headers.set("Accept", "application/json");
    // Only set Content-Type when we actually send a body.
    if (options?.body != null && !headers.has("Content-Type")) {
      headers.set("Content-Type", "application/json");
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    // Read as text first so we can gracefully handle HTML even when the server lies about Content-Type.
    const raw = await response.text();
    const contentType = response.headers.get("content-type") || "";
    const preview = raw.slice(0, 800);

    // JSON happy-path
    if (looksLikeJson(raw)) {
      const parsed = JSON.parse(raw);
      // Auto-detect PascalCase universal envelope and convert transparently
      if (isEnvelope(parsed)) {
        return parseEnvelope<T>(parsed);
      }
      // Non-envelope JSON: transform PascalCase keys → camelCase
      return transformKeys<ApiResponse<T>>(parsed);
    }

    // HTML / SPA fallback detection
    const rawTrim = raw.trim();
    const looksLikeHtml = rawTrim.startsWith("<!") || rawTrim.startsWith("<html") || /<html[\s>]/i.test(raw);

    if (looksLikeHtml || !contentType.includes("application/json")) {
      return {
        success: false,
        error: {
          code: "E9005",
          message: "API returned HTML instead of JSON",
          details:
            "This usually means the UI is not talking to the Go backend (wrong base URL/port, or preview environment).\n" +
            `Requested URL: ${requestUrl}\n` +
            `Configured API base: ${apiBase}\n` +
            `API Base (absolute): ${toAbsoluteUrl(apiBase)}\n` +
            `VITE_API_URL (raw): ${envViteApiUrl}\n` +
            "Fix: set VITE_API_URL to your backend origin (e.g. http://localhost:8080) and reload.\n" +
            `HTTP ${response.status} (${contentType || "no content-type"})`,
          context: buildDiagnosticContext({
            responseStatus: response.status,
            contentType: contentType || null,
            responsePreview: preview,
          }),
          timestamp: new Date().toISOString(),
        },
      };
    }

    // Unexpected non-JSON (but not HTML)
    return {
      success: false,
      error: {
        code: "E9006",
        message: "Unexpected API response format",
        details:
          `Expected JSON but got: ${contentType || "unknown"}\n` +
          `Requested URL: ${requestUrl}\n` +
          `Preview: ${preview}`,
        context: buildDiagnosticContext({
          responseStatus: response.status,
          contentType: contentType || null,
          responsePreview: preview,
        }),
        timestamp: new Date().toISOString(),
      },
    };
  } catch (error: unknown) {
    const duration = Date.now() - startTime;
    logger.error(`API request failed: ${endpoint}`, error, { endpoint, method, duration });
    
    return {
      success: false,
      error: {
        code: "E9003",
        message: "Network error",
        details: error instanceof Error ? error.message : "Unknown error",
        context: {
          apiBase: resolveApiBase(),
          apiBaseAbsolute: toAbsoluteUrl(resolveApiBase()),
          "VITE_API_URL (raw)": envViteApiUrl,
          "VITE_WS_URL (raw)": envViteWsUrl,
          resolvedApiOrigin: resolveApiOrigin() || null,
          uiOrigin,
        },
        timestamp: new Date().toISOString(),
      },
    };
  } finally {
    const duration = Date.now() - startTime;
    logger.trace(functionName, 'exit', { endpoint, method, duration });
  }
}

// ---------------------------------------------------------------------------
// Public request (with circuit breaker)
// ---------------------------------------------------------------------------

export async function request<T>(
  endpoint: string,
  options?: RequestInit
): Promise<ApiResponse<T>> {
  const circuitKey = `api:${endpoint}`;
  
  try {
    return await withCircuitBreaker(circuitKey, () => fetchRequest<T>(endpoint, options));
  } catch (error: unknown) {
    // If circuit breaker blocked the call, return a user-friendly error
    if (error instanceof CircuitBreakerError) {
      logger.warn(`Circuit breaker open for ${endpoint}, request blocked`);
      return {
        success: false,
        error: {
          code: "E_CIRCUIT_OPEN",
          message: "Too many recent failures for this operation",
          details: `The circuit breaker has blocked requests to ${endpoint} due to repeated failures. Please wait a moment and try again.`,
          timestamp: new Date().toISOString(),
        },
      };
    }
    throw error;
  }
}

export function getApiDiagnostics() {
  const apiBase = resolveApiBase();
  const apiOrigin = resolveApiOrigin();
  return {
    apiOrigin: apiOrigin || null,
    apiBase,
    apiBaseAbsolute: toAbsoluteUrl(apiBase),
  };
}
