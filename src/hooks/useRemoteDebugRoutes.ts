import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { DebugRoutesResponse } from "@/lib/api/types";
import type { ApiError } from "@/lib/api/types";

export function useRemoteDebugRoutes(siteId: number | null) {
  return useQuery<DebugRoutesResponse, ApiError | Error>({
    queryKey: ["debug-routes", siteId],
    queryFn: async () => {
      const response = await api.getRemoteDebugRoutes(siteId!);
      if (!response.success || !response.data) {
        // Throw with the structured API error when available so
        // the inline error UI can display the real reason.
        const apiErr = response.error;
        if (apiErr) {
          const err = new Error(apiErr.message || "Failed to fetch debug routes");
          // Attach the API error for richer inline diagnostics
          (err as any).apiError = apiErr;
          throw err;
        }
        throw new Error("Failed to fetch debug routes");
      }
      return response.data as DebugRoutesResponse;
    },
    enabled: siteId !== null,
    retry: false,
    meta: { suppressGlobalError: true },
  });
}
