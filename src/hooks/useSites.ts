import { api, Site } from "@/lib/api";
import { useApiQuery, useApiQueryPaginated } from "@/hooks/useApiQuery";

export function useSites() {
  return useApiQuery<Site[]>({
    queryKey: ["sites"],
    apiFn: () => api.getSites(),
    endpoint: "/sites",
  });
}

export function useSitesPaginated(page: number = 1, perPage: number = 25) {
  return useApiQueryPaginated<Site[]>({
    queryKey: ["sites", "paginated", page, perPage],
    apiFn: () => api.getSites(),
    endpoint: "/sites",
    page,
    perPage,
  });
}

export function useSite(id: number) {
  return useApiQuery<Site>({
    queryKey: ["sites", id],
    apiFn: () => api.getSite(id),
    endpoint: `/sites/${id}`,
    enabled: !!id,
  });
}
