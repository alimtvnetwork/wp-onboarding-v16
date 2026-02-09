import { api, ErrorLog } from "@/lib/api";
import { useApiQuery, useApiQueryPaginated } from "@/hooks/useApiQuery";

export function useErrors(limit?: number) {
  return useApiQuery<ErrorLog[]>({
    queryKey: ["errors", limit],
    apiFn: () => api.getErrors(limit),
    endpoint: `/errors${limit ? `?limit=${limit}` : ""}`,
  });
}

export function useErrorsPaginated(page: number = 1, perPage: number = 25) {
  return useApiQueryPaginated<ErrorLog[]>({
    queryKey: ["errors", "paginated", page, perPage],
    apiFn: () => api.getErrors(),
    endpoint: "/errors",
    page,
    perPage,
  });
}
