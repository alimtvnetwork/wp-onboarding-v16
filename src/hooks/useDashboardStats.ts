import { useQuery } from "@tanstack/react-query";
import { api, requireSuccess, PublishHistoryStats, PublishHistoryEntry, ErrorLog, Site, Plugin } from "@/lib/api";
import type { SparklinePoint } from "@/components/dashboard/SparklineChart";
import { ConnectionStatus, POLL_INTERVAL_DASHBOARD_MS, RECENT_ERRORS_LIMIT, RECENT_PUBLISHES_LIMIT, DASHBOARD_TREND_LIMIT } from "@/lib/constants";

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
          api.getErrors(RECENT_ERRORS_LIMIT),
          api.getPublishHistoryStats(),
          api.getPublishHistory({ limit: RECENT_PUBLISHES_LIMIT }),
          // Fetch enough entries for 7-day trend
          api.getPublishHistory({ limit: DASHBOARD_TREND_LIMIT }),
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
          ? (recentPublishesRes.data.entries ?? [])
          : [];

      // Build trend sparklines
      const allPublishes =
        trendPublishesRes.success && trendPublishesRes.data
          ? (trendPublishesRes.data.entries ?? [])
          : [];

      const trends: DashboardTrends = {
        publishes: buildDailyBuckets(allPublishes),
        errors: buildDailyBuckets(
          errorsList.map((e: ErrorLog) => ({ createdAt: e.createdAt }))
        ),
      };

      return {
        sites: {
          total: sitesList.length,
          connected: sitesList.filter((s: Site) => s.connectionStatus === ConnectionStatus.Connected).length,
        },
        plugins: {
          total: pluginsList.length,
          watching: pluginsList.filter((p: Plugin) => p.watchEnabled).length,
          pendingChanges: pluginsList.reduce((acc: number, p: Plugin) => acc + (p.modifiedCount || 0), 0),
        },
        errors: { recent: errorsList.length },
        publish: publishStats,
        recentPublishes,
        trends,
      };
    },
    refetchInterval: POLL_INTERVAL_DASHBOARD_MS,
  });
}
