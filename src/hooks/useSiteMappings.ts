import { api, PluginMapping } from "@/lib/api";
import { useApiQuery } from "@/hooks/useApiQuery";

export function useSiteMappings(siteId: number | null) {
  return useApiQuery<PluginMapping[]>({
    queryKey: ["sites", siteId, "mappings"],
    apiFn: async () => {
      if (!siteId) return { success: true, data: [] as PluginMapping[] };
      return api.getSiteMappings(siteId);
    },
    endpoint: `/sites/${siteId}/mappings`,
    enabled: !!siteId,
  });
}
