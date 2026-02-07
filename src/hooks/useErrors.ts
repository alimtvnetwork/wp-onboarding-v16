import { useQuery } from "@tanstack/react-query";
import { api, ErrorLog, requireSuccess } from "@/lib/api";
import { requireSuccessWithEnvelope, withPaginationParams, PaginatedResult } from "@/lib/apiHelpers";

export function useErrors(limit?: number) {
  return useQuery({
    queryKey: ["errors", limit],
    queryFn: async () => {
      const response = await api.getErrors(limit);
      return requireSuccess(response, {
        endpoint: `/errors${limit ? `?limit=${limit}` : ""}`,
        method: "GET",
      });
    },
  });
}

export function useErrorsPaginated(page: number = 1, perPage: number = 25) {
  return useQuery({
    queryKey: ["errors", "paginated", page, perPage],
    queryFn: async () => {
      const endpoint = withPaginationParams("/errors", { page, perPage });
      const response = await api.getErrors();
      return requireSuccessWithEnvelope<ErrorLog[]>(response, { endpoint, method: "GET" });
    },
  });
}
