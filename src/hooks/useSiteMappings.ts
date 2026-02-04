import { useQuery } from "@tanstack/react-query";
import { api, requireSuccess, PluginMapping } from "@/lib/api";

export function useSiteMappings(siteId: number | null) {
  return useQuery({
    queryKey: ["sites", siteId, "mappings"],
    queryFn: async () => {
      if (!siteId) return [];
      const response = await api.getSiteMappings(siteId);
      return requireSuccess(response, { endpoint: `/sites/${siteId}/mappings`, method: "GET" });
    },
    enabled: !!siteId,
  });
}
