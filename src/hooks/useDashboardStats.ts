import { useQuery } from "@tanstack/react-query";
import { api, requireSuccess, PublishHistoryStats, PublishHistoryEntry, ErrorLog } from "@/lib/api";
import type { SparklinePoint } from "@/components/dashboard/SparklineChart";

export interface DashboardTrends {
  publishes: SparklinePoint[];
  errors: SparklinePoint[];
}

export interface DashboardStats {
  sites: { total: number; connected: number };
  plugins: { total: number; watching: number; pendingChanges: number };
  errors: { recent: number };
  publish: PublishHistoryStats | null;
  recentPublishes: PublishHistoryEntry[];
  trends: DashboardTrends;
}

/** Aggregate timestamped items into 7-day buckets (today → 6 days ago) */
function buildDailyBuckets(items: { createdAt: string }[], days = 7): SparklinePoint[] {
  const now = new Date();
  const buckets = Array.from({ length: days }, () => 0);

  for (const item of items) {
    const d = new Date(item.createdAt);
    const diffDays = Math.floor((now.getTime() - d.getTime()) / 86_400_000);
    if (diffDays >= 0 && diffDays < days) {
      buckets[days - 1 - diffDays]++;
    }
  }

  return buckets.map((value) => ({ value }));
}

export function useDashboardStats() {
  return useQuery({
    queryKey: ["dashboard-stats"],
    queryFn: async (): Promise<DashboardStats> => {
      const [sitesRes, pluginsRes, errorsRes, publishStatsRes, recentPublishesRes, trendPublishesRes] =
        await Promise.all([
          api.getSites(),
          api.getPlugins(),
          api.getErrors(10),
          api.getPublishHistoryStats(),
          api.getPublishHistory({ limit: 5 }),
          // Fetch enough entries for 7-day trend
          api.getPublishHistory({ limit: 200 }),
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

      // Build trend sparklines
      const allPublishes =
        trendPublishesRes.success && trendPublishesRes.data
          ? ((trendPublishesRes.data as any).entries ?? []) as PublishHistoryEntry[]
          : [];

      const trends: DashboardTrends = {
        publishes: buildDailyBuckets(allPublishes),
        errors: buildDailyBuckets(
          errorsList.map((e: any) => ({ createdAt: e.createdAt || e.timestamp || new Date().toISOString() }))
        ),
      };

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
        trends,
      };
    },
    refetchInterval: 30000,
  });
}
