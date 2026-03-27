import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { DebugRoutesResponse } from "@/lib/api/types";

export function useRemoteDebugRoutes(siteId: number | null) {
  return useQuery<DebugRoutesResponse>({
    queryKey: ["debug-routes", siteId],
    queryFn: async () => {
      const response = await api.getRemoteDebugRoutes(siteId!);
      if (!response.success || !response.data) {
        throw new Error("Failed to fetch debug routes");
      }
      return response.data as DebugRoutesResponse;
    },
    enabled: siteId !== null,
  });
}
