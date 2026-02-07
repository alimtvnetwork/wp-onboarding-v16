import { ApiResponse, ApiError, ApiCallMeta, ApiClientError, EnvelopeMeta } from "@/lib/api";

export interface PaginatedResult<T> {
  data: T;
  envelope?: EnvelopeMeta;
}

/**
 * Like requireSuccess but also returns envelope metadata (pagination, diagnostics).
 * Use this in hooks that need to expose pagination controls.
 */
export function requireSuccessWithEnvelope<T>(
  response: ApiResponse<T>,
  meta: ApiCallMeta
): PaginatedResult<T> {
  if (response.success) {
    return {
      data: response.data as T,
      envelope: response.envelope,
    };
  }
  const apiError: ApiError =
    response.error ||
    ({
      code: "E9999",
      message: "Unknown API error",
      timestamp: new Date().toISOString(),
    } as ApiError);
  throw new ApiClientError(apiError, meta);
}

/** Build query string with pagination params */
export function withPaginationParams(
  base: string,
  params?: { page?: number; perPage?: number }
): string {
  if (!params?.page && !params?.perPage) return base;
  const sep = base.includes("?") ? "&" : "?";
  const parts: string[] = [];
  if (params.page) parts.push(`page=${params.page}`);
  if (params.perPage) parts.push(`perPage=${params.perPage}`);
  return `${base}${sep}${parts.join("&")}`;
}
