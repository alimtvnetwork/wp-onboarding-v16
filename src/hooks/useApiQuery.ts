import { useQuery, UseQueryOptions } from "@tanstack/react-query";
import { ApiResponse, ApiCallMeta } from "@/lib/api";
import { requireSuccess } from "@/lib/api";
import {
  requireSuccessWithEnvelope,
  withPaginationParams,
  PaginatedResult,
} from "@/lib/apiHelpers";

// ---------------------------------------------------------------------------
// useApiQuery — factory for the repeated useQuery + requireSuccess pattern
// ---------------------------------------------------------------------------

interface UseApiQueryOptions<T> {
  /** React Query cache key */
  queryKey: unknown[];
  /** API method that returns an ApiResponse<T> */
  apiFn: () => Promise<ApiResponse<T>>;
  /** Endpoint string for error diagnostics (e.g. "/plugins") */
  endpoint: string;
  /** HTTP method for diagnostics (default: "GET") */
  method?: ApiCallMeta["method"];
  /** Standard React Query `enabled` flag */
  enabled?: boolean;
  /** Additional useQuery options (refetchInterval, staleTime, etc.) */
  queryOptions?: Omit<
    UseQueryOptions<T, Error, T, unknown[]>,
    "queryKey" | "queryFn" | "enabled"
  >;
}

/**
 * Thin wrapper around `useQuery` that applies `requireSuccess` automatically.
 *
 * Before:
 * ```ts
 * useQuery({
 *   queryKey: ["plugins"],
 *   queryFn: async () => {
 *     const response = await api.getPlugins();
 *     return requireSuccess(response, { endpoint: "/plugins", method: "GET" });
 *   },
 * });
 * ```
 *
 * After:
 * ```ts
 * useApiQuery({
 *   queryKey: ["plugins"],
 *   apiFn: () => api.getPlugins(),
 *   endpoint: "/plugins",
 * });
 * ```
 */
export function useApiQuery<T>({
  queryKey,
  apiFn,
  endpoint,
  method = "GET",
  enabled,
  queryOptions,
}: UseApiQueryOptions<T>) {
  return useQuery<T, Error, T, unknown[]>({
    queryKey,
    queryFn: async () => {
      const response = await apiFn();
      return requireSuccess(response, { endpoint, method });
    },
    enabled,
    ...queryOptions,
  });
}

// ---------------------------------------------------------------------------
// useApiQueryPaginated — same factory with envelope pagination support
// ---------------------------------------------------------------------------

interface UseApiQueryPaginatedOptions<T> {
  queryKey: unknown[];
  apiFn: () => Promise<ApiResponse<T>>;
  /** Base endpoint without pagination params (e.g. "/plugins") */
  endpoint: string;
  page: number;
  perPage: number;
  method?: ApiCallMeta["method"];
  enabled?: boolean;
  queryOptions?: Omit<
    UseQueryOptions<PaginatedResult<T>, Error, PaginatedResult<T>, unknown[]>,
    "queryKey" | "queryFn" | "enabled"
  >;
}

/**
 * Like `useApiQuery` but extracts envelope pagination metadata via
 * `requireSuccessWithEnvelope`.
 */
export function useApiQueryPaginated<T>({
  queryKey,
  apiFn,
  endpoint: baseEndpoint,
  page,
  perPage,
  method = "GET",
  enabled,
  queryOptions,
}: UseApiQueryPaginatedOptions<T>) {
  return useQuery<PaginatedResult<T>, Error, PaginatedResult<T>, unknown[]>({
    queryKey,
    queryFn: async () => {
      const endpoint = withPaginationParams(baseEndpoint, { page, perPage });
      const response = await apiFn();
      return requireSuccessWithEnvelope<T>(response, { endpoint, method });
    },
    enabled,
    ...queryOptions,
  });
}
