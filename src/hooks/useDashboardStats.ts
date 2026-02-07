import { useQuery } from "@tanstack/react-query";
import { api, requireSuccess, PublishHistoryStats, PublishHistoryEntry } from "@/lib/api";

export interface DashboardStats {
  sites: { total: number; connected: number };
  plugins: { total: number; watching: number; pendingChanges: number };
  errors: { recent: number };
  publish: PublishHistoryStats | null;
  recentPublishes: PublishHistoryEntry[];
}

export function useDashboardStats() {
  return useQuery({
    queryKey: ["dashboard-stats"],
    queryFn: async (): Promise<DashboardStats> => {
      const [sitesRes, pluginsRes, errorsRes, publishStatsRes, recentPublishesRes] =
        await Promise.all([
          api.getSites(),
          api.getPlugins(),
          api.getErrors(10),
          api.getPublishHistoryStats(),
          api.getPublishHistory({ limit: 5 }),
        ]);

      const sites = sitesRes.success ? (sitesRes.data ?? []) : [];
      const plugins = pluginsRes.success ? (pluginsRes.data ?? []) : [];
      const errors = errorsRes.success ? (errorsRes.data ?? []) : [];

      const sitesList = Array.isArray(sites) ? sites : [];
      const pluginsList = Array.isArray(plugins) ? plugins : [];
      const errorsList = Array.isArray(errors) ? errors : [];

      const publishStats =
        publishStatsRes.success && publishStatsRes.data
          ? (publishStatsRes.data as PublishHistoryStats)
          : null;

      const recentPublishes =
        recentPublishesRes.success && recentPublishesRes.data
          ? ((recentPublishesRes.data as any).entries ?? []) as PublishHistoryEntry[]
          : [];

      return {
        sites: {
          total: sitesList.length,
          connected: sitesList.filter((s: any) => s.connectionStatus === "connected").length,
        },
        plugins: {
          total: pluginsList.length,
          watching: pluginsList.filter((p: any) => p.watchEnabled).length,
          pendingChanges: pluginsList.reduce((acc: number, p: any) => acc + (p.modifiedCount || 0), 0),
        },
        errors: { recent: errorsList.length },
        publish: publishStats,
        recentPublishes,
      };
    },
    refetchInterval: 30000,
  });
}
