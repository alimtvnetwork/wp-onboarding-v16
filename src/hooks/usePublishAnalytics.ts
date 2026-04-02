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

export interface PluginBreakdown {
  pluginName: string;
  pluginId: number;
  total: number;
  success: number;
  failed: number;
  successRate: number;
  avgDurationMs: number;
}

export interface StageDuration {
  stage: string;
  avgMs: number;
  p95Ms: number;
  count: number;
}

export interface FailureCategory {
  category: string;
  count: number;
  percentage: number;
  examples: string[];
}

export interface DurationTrendPoint {
  date: string;
  avgMs: number;
  p95Ms: number;
}

export interface PublishAnalyticsData {
  daily: DailyPublishPoint[];
  successRate: SuccessRatePoint[];
  heatmap: HeatmapCell[];
  sites: SiteBreakdown[];
  plugins: PluginBreakdown[];
  stages: StageDuration[];
  failures: FailureCategory[];
  durationTrend: DurationTrendPoint[];
  summary: {
    total: number;
    success: number;
    failed: number;
    avgDurationMs: number;
    p95DurationMs: number;
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

  // ── Per-plugin breakdown ─────────────────────
  const pluginMap = new Map<number, PluginBreakdown>();
  for (const e of recent) {
    const p = pluginMap.get(e.pluginId) ?? {
      pluginName: e.pluginName,
      pluginId: e.pluginId,
      total: 0,
      success: 0,
      failed: 0,
      successRate: 0,
      avgDurationMs: 0,
    };
    p.total++;
    if (e.status === "Success") p.success++;
    else if (e.status === "Failed") p.failed++;
    (p as any)._totalMs = ((p as any)._totalMs ?? 0) + e.durationMs;
    pluginMap.set(e.pluginId, p);
  }
  const plugins = Array.from(pluginMap.values())
    .map((p) => ({
      ...p,
      avgDurationMs: p.total > 0 ? Math.round((p as any)._totalMs / p.total) : 0,
      successRate: p.total > 0 ? Math.round((p.success / p.total) * 100) : 0,
    }))
    .sort((a, b) => b.total - a.total);

  // ── Stage duration breakdown ─────────────────
  // Derive from mode field: backup, upload, activation, cloud_upload
  const STAGE_NAMES = ["backup", "upload", "activation", "cloud_upload", "remote-backup"];
  const stageAccum = new Map<string, number[]>();
  for (const e of recent) {
    // Use mode as an approximation of the primary stage
    const stage = e.mode || "upload";
    if (!stageAccum.has(stage)) stageAccum.set(stage, []);
    stageAccum.get(stage)!.push(e.durationMs);
  }
  // If no per-stage data, distribute total duration across synthetic stages
  if (stageAccum.size <= 1 && recent.length > 0) {
    stageAccum.clear();
    for (const stage of ["backup", "upload", "activation"]) {
      const durations = recent.map((e) => Math.round(e.durationMs * (stage === "upload" ? 0.6 : stage === "backup" ? 0.25 : 0.15)));
      stageAccum.set(stage, durations);
    }
  }
  const stages: StageDuration[] = Array.from(stageAccum.entries()).map(([stage, durations]) => {
    const sorted = [...durations].sort((a, b) => a - b);
    const p95Idx = Math.floor(sorted.length * 0.95);
    return {
      stage,
      avgMs: Math.round(durations.reduce((a, b) => a + b, 0) / durations.length),
      p95Ms: sorted[p95Idx] ?? sorted[sorted.length - 1] ?? 0,
      count: durations.length,
    };
  });

  // ── Duration trend ───────────────────────────
  const durationTrend: DurationTrendPoint[] = daily.map((d) => {
    const dayEntries = recent.filter(
      (e) => format(startOfDay(parseISO(e.createdAt)), "MMM dd") === d.date
    );
    const sorted = dayEntries.map((e) => e.durationMs).sort((a, b) => a - b);
    const avg = sorted.length > 0 ? Math.round(sorted.reduce((a, b) => a + b, 0) / sorted.length) : 0;
    const p95Idx = Math.floor(sorted.length * 0.95);
    return {
      date: d.date,
      avgMs: avg,
      p95Ms: sorted[p95Idx] ?? sorted[sorted.length - 1] ?? 0,
    };
  });

  // ── Failure analysis ─────────────────────────
  const failedEntries = recent.filter((e) => e.status === "Failed");
  const categoryMap = new Map<string, { count: number; examples: string[] }>();
  for (const e of failedEntries) {
    const msg = e.errorMessage || "Unknown error";
    const category = categorizeError(msg);
    const c = categoryMap.get(category) ?? { count: 0, examples: [] };
    c.count++;
    if (c.examples.length < 3) c.examples.push(msg);
    categoryMap.set(category, c);
  }
  const failures: FailureCategory[] = Array.from(categoryMap.entries())
    .map(([category, { count, examples }]) => ({
      category,
      count,
      percentage: failedEntries.length > 0 ? Math.round((count / failedEntries.length) * 100) : 0,
      examples,
    }))
    .sort((a, b) => b.count - a.count);

  // ── Summary ──────────────────────────────────
  const allDurations = recent.map((e) => e.durationMs).sort((a, b) => a - b);
  const totalDurationMs = allDurations.reduce((acc, d) => acc + d, 0);
  const p95Idx = Math.floor(allDurations.length * 0.95);
  const peakBucket = daily.reduce((max, d) => (d.total > max.total ? d : max), daily[0]);

  return {
    daily,
    successRate,
    heatmap,
    sites,
    plugins,
    stages,
    failures,
    durationTrend,
    summary: {
      total: recent.length,
      success: recent.filter((e) => e.status === "Success").length,
      failed: recent.filter((e) => e.status === "Failed").length,
      avgDurationMs: recent.length > 0 ? Math.round(totalDurationMs / recent.length) : 0,
      p95DurationMs: allDurations[p95Idx] ?? allDurations[allDurations.length - 1] ?? 0,
      peakDay: peakBucket?.date ?? "—",
    },
  };
}

function categorizeError(msg: string): string {
  const lower = msg.toLowerCase();
  if (lower.includes("timeout") || lower.includes("timed out")) return "Timeout";
  if (lower.includes("network") || lower.includes("connection") || lower.includes("refused") || lower.includes("dns")) return "Network";
  if (lower.includes("activation") || lower.includes("activate")) return "Activation";
  if (lower.includes("permission") || lower.includes("forbidden") || lower.includes("401") || lower.includes("403")) return "Permission";
  if (lower.includes("disk") || lower.includes("space") || lower.includes("storage")) return "Storage";
  if (lower.includes("upload") || lower.includes("ftp") || lower.includes("sftp")) return "Upload";
  if (lower.includes("backup") || lower.includes("snapshot")) return "Backup";
  return "Other";
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

