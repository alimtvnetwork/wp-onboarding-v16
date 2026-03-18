// Hook to fetch and aggregate publish history data for analytics charts (30-day window).

import { useQuery } from "@tanstack/react-query";
import { api, PublishHistoryEntry } from "@/lib/api";
import { subDays, startOfDay, format, getDay, getHours, parseISO } from "date-fns";

const ANALYTICS_LIMIT = 500;

export interface DailyPublishPoint {
  date: string;          // "Mar 01"
  success: number;
  failed: number;
  partial: number;
  total: number;
}

export interface SuccessRatePoint {
  date: string;
  rate: number;          // 0–100
  total: number;
}

export interface HeatmapCell {
  day: number;           // 0 (Sun) – 6 (Sat)
  hour: number;          // 0–23
  avgDurationMs: number;
  count: number;
}

export interface SiteBreakdown {
  siteName: string;
  siteId: number;
  total: number;
  success: number;
  failed: number;
  partial: number;
  successRate: number;
}

export interface PublishAnalyticsData {
  daily: DailyPublishPoint[];
  successRate: SuccessRatePoint[];
  heatmap: HeatmapCell[];
  sites: SiteBreakdown[];
  summary: {
    total: number;
    success: number;
    failed: number;
    avgDurationMs: number;
    peakDay: string;
  };
}

function buildAnalytics(entries: PublishHistoryEntry[], days: number, startFrom?: Date): PublishAnalyticsData {
  const now = new Date();
  const anchor = startFrom ?? subDays(now, days);

  // Filter to window
  const recent = startFrom
    ? entries
    : entries.filter((e) => new Date(e.createdAt) >= anchor);

  // ── Daily publishes ──────────────────────────
  const dailyMap = new Map<string, DailyPublishPoint>();
  const baseDate = startFrom ?? subDays(now, days - 1);
  for (let i = 0; i < days; i++) {
    const d = new Date(baseDate.getTime() + i * 24 * 60 * 60 * 1000);
    const key = format(startOfDay(d), "MMM dd");
    dailyMap.set(key, { date: key, success: 0, failed: 0, partial: 0, total: 0 });
  }

  for (const e of recent) {
    const key = format(startOfDay(parseISO(e.createdAt)), "MMM dd");
    const bucket = dailyMap.get(key);
    if (!bucket) continue;
    bucket.total++;
    if (e.status === "Success") bucket.success++;
    else if (e.status === "Failed") bucket.failed++;
    else bucket.partial++;
  }
  const daily = Array.from(dailyMap.values());

  // ── Success rate trend ───────────────────────
  const successRate: SuccessRatePoint[] = daily.map((d) => ({
    date: d.date,
    rate: d.total > 0 ? Math.round((d.success / d.total) * 100) : 0,
    total: d.total,
  }));

  // ── Duration heatmap ─────────────────────────
  const heatBuckets = new Map<string, { totalMs: number; count: number }>();
  for (const e of recent) {
    const dt = parseISO(e.createdAt);
    const key = `${getDay(dt)}-${getHours(dt)}`;
    const b = heatBuckets.get(key) ?? { totalMs: 0, count: 0 };
    b.totalMs += e.durationMs;
    b.count++;
    heatBuckets.set(key, b);
  }
  const heatmap: HeatmapCell[] = [];
  for (let day = 0; day < 7; day++) {
    for (let hour = 0; hour < 24; hour++) {
      const b = heatBuckets.get(`${day}-${hour}`);
      heatmap.push({
        day,
        hour,
        avgDurationMs: b ? Math.round(b.totalMs / b.count) : 0,
        count: b?.count ?? 0,
      });
    }
  }

  // ── Per-site breakdown ───────────────────────
  const siteMap = new Map<number, SiteBreakdown>();
  for (const e of recent) {
    const s = siteMap.get(e.siteId) ?? {
      siteName: e.siteName,
      siteId: e.siteId,
      total: 0,
      success: 0,
      failed: 0,
      partial: 0,
      successRate: 0,
    };
    s.total++;
    if (e.status === "Success") s.success++;
    else if (e.status === "Failed") s.failed++;
    else s.partial++;
    siteMap.set(e.siteId, s);
  }
  const sites = Array.from(siteMap.values())
    .map((s) => ({ ...s, successRate: s.total > 0 ? Math.round((s.success / s.total) * 100) : 0 }))
    .sort((a, b) => b.total - a.total);

  // ── Summary ──────────────────────────────────
  const totalDurationMs = recent.reduce((acc, e) => acc + e.durationMs, 0);
  const peakBucket = daily.reduce((max, d) => (d.total > max.total ? d : max), daily[0]);

  return {
    daily,
    successRate,
    heatmap,
    sites,
    summary: {
      total: recent.length,
      success: recent.filter((e) => e.status === "Success").length,
      failed: recent.filter((e) => e.status === "Failed").length,
      avgDurationMs: recent.length > 0 ? Math.round(totalDurationMs / recent.length) : 0,
      peakDay: peakBucket?.date ?? "—",
    },
  };
}

export function usePublishAnalytics(days: number = 30, customRange?: { from: Date; to: Date }) {
  const rangeKey = customRange
    ? `${customRange.from.toISOString()}-${customRange.to.toISOString()}`
    : String(days);

  return useQuery({
    queryKey: ["publish-analytics", rangeKey],
    queryFn: async () => {
      const res = await api.getPublishHistory({ limit: ANALYTICS_LIMIT });
      const entries = res.data?.entries ?? [];
      if (customRange) {
        const diffDays = Math.ceil(
          (customRange.to.getTime() - customRange.from.getTime()) / (1000 * 60 * 60 * 24)
        ) + 1;
        return buildAnalytics(
          entries.filter((e) => {
            const d = new Date(e.createdAt);
            return d >= customRange.from && d <= customRange.to;
          }),
          diffDays,
          customRange.from
        );
      }
      return buildAnalytics(entries, days);
    },
    staleTime: 60_000,
  });
}
