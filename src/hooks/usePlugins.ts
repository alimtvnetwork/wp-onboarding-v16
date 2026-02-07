import { useQuery } from "@tanstack/react-query";
import { api, Plugin, requireSuccess } from "@/lib/api";
import { requireSuccessWithEnvelope, withPaginationParams, PaginatedResult } from "@/lib/apiHelpers";

export function usePlugins() {
  return useQuery({
    queryKey: ["plugins"],
    queryFn: async () => {
      const response = await api.getPlugins();
      return requireSuccess(response, { endpoint: "/plugins", method: "GET" });
    },
  });
}

export function usePluginsPaginated(page: number = 1, perPage: number = 25) {
  return useQuery({
    queryKey: ["plugins", "paginated", page, perPage],
    queryFn: async () => {
      const endpoint = withPaginationParams("/plugins", { page, perPage });
      const response = await api.getPlugins();
      return requireSuccessWithEnvelope<Plugin[]>(response, { endpoint, method: "GET" });
    },
  });
}

export function usePlugin(id: number) {
  return useQuery({
    queryKey: ["plugins", id],
    queryFn: async () => {
      const response = await api.getPlugin(id);
      return requireSuccess(response, { endpoint: `/plugins/${id}`, method: "GET" });
    },
    enabled: !!id,
  });
}
