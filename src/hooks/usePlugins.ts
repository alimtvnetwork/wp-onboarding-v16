import { api, Plugin } from "@/lib/api";
import { useApiQuery, useApiQueryPaginated } from "@/hooks/useApiQuery";

export function usePlugins() {
  return useApiQuery<Plugin[]>({
    queryKey: ["plugins"],
    apiFn: () => api.getPlugins(),
    endpoint: "/plugins",
  });
}

export function usePluginsPaginated(page: number = 1, perPage: number = 25) {
  return useApiQueryPaginated<Plugin[]>({
    queryKey: ["plugins", "paginated", page, perPage],
    apiFn: () => api.getPlugins(),
    endpoint: "/plugins",
    page,
    perPage,
  });
}

export function usePlugin(id: number) {
  return useApiQuery<Plugin>({
    queryKey: ["plugins", id],
    apiFn: () => api.getPlugin(id),
    endpoint: `/plugins/${id}`,
    enabled: !!id,
  });
}
