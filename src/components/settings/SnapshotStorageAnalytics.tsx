import { useMemo } from "react";
import { SnapshotRecord } from "@/lib/api/types";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Area, AreaChart, CartesianGrid } from "recharts";
import { format, parseISO, startOfDay } from "date-fns";
import { cn } from "@/lib/utils";
import { HardDrive, TrendingUp, Database, Layers } from "lucide-react";

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

interface Props {
  snapshots: SnapshotRecord[];
}

export function SnapshotStorageAnalytics({ snapshots }: Props) {
  const { dailyData, stats } = useMemo(() => {
    const sorted = [...(snapshots ?? [])].sort(
      (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    );

    // Group by day
    const byDay = new Map<string, { size: number; count: number; rows: number }>();
    let cumSize = 0;
    sorted.forEach((s) => {
      const day = format(startOfDay(parseISO(s.created_at)), "yyyy-MM-dd");
      const existing = byDay.get(day) || { size: 0, count: 0, rows: 0 };
      existing.size += s.file_size ?? 0;
      existing.count += 1;
      existing.rows += s.total_rows ?? 0;
      byDay.set(day, existing);
    });

    const dailyData = Array.from(byDay.entries()).map(([day, d]) => {
      cumSize += d.size;
      return {
        date: day,
        label: format(parseISO(day), "MMM d"),
        size: d.size,
        cumSize,
        count: d.count,
        rows: d.rows,
      };
    });

    const totalSize = sorted.reduce((sum, s) => sum + (s.file_size ?? 0), 0);
    const totalRows = sorted.reduce((sum, s) => sum + (s.total_rows ?? 0), 0);
    const avgSize = sorted.length > 0 ? totalSize / sorted.length : 0;

    return {
      dailyData,
      stats: { totalSize, totalRows, totalCount: sorted.length, avgSize },
    };
  }, [snapshots]);

  if (!snapshots?.length) {
    return (
      <div className="space-y-3">
        <div className="flex items-center gap-2 text-sm font-medium">
          <TrendingUp className="h-4 w-4" />
          Storage Analytics
        </div>
        <div className="text-center py-6 text-muted-foreground text-xs border rounded-md border-dashed">
          No snapshot data available for analytics.
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 text-sm font-medium">
        <TrendingUp className="h-4 w-4" />
        Storage Analytics
      </div>

      {/* Summary cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
        {[
          { icon: HardDrive, label: "Total Size", value: formatBytes(stats.totalSize) },
          { icon: Database, label: "Total Rows", value: stats.totalRows.toLocaleString() },
          { icon: Layers, label: "Snapshots", value: String(stats.totalCount) },
          { icon: HardDrive, label: "Avg Size", value: formatBytes(stats.avgSize) },
        ].map((card) => (
          <div key={card.label} className="rounded-lg border p-2.5 space-y-1">
            <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground uppercase tracking-wider">
              <card.icon className="h-3 w-3" />
              {card.label}
            </div>
            <p className="text-sm font-mono font-semibold">{card.value}</p>
          </div>
        ))}
      </div>

      {/* Cumulative storage trend */}
      {dailyData.length > 1 && (
        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">Cumulative Disk Usage</p>
          <div className="h-40 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={dailyData} margin={{ top: 4, right: 4, bottom: 0, left: 0 }}>
                <defs>
                  <linearGradient id="storageGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted/30" />
                <XAxis
                  dataKey="label"
                  tick={{ fontSize: 10 }}
                  className="fill-muted-foreground"
                  tickLine={false}
                  axisLine={false}
                />
                <YAxis
                  tick={{ fontSize: 10 }}
                  className="fill-muted-foreground"
                  tickFormatter={(v) => formatBytes(v)}
                  tickLine={false}
                  axisLine={false}
                  width={55}
                />
                <Tooltip
                  contentStyle={{
                    fontSize: 11,
                    borderRadius: 8,
                    border: "1px solid hsl(var(--border))",
                    background: "hsl(var(--popover))",
                    color: "hsl(var(--popover-foreground))",
                  }}
                  formatter={(value: number) => [formatBytes(value), "Total"]}
                  labelFormatter={(l) => `Date: ${l}`}
                />
                <Area
                  type="monotone"
                  dataKey="cumSize"
                  stroke="hsl(var(--primary))"
                  fill="url(#storageGrad)"
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>
      )}

      {/* Daily snapshot sizes */}
      {dailyData.length > 1 && (
        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">Daily Snapshot Size</p>
          <div className="h-32 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={dailyData} margin={{ top: 4, right: 4, bottom: 0, left: 0 }}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted/30" />
                <XAxis
                  dataKey="label"
                  tick={{ fontSize: 10 }}
                  className="fill-muted-foreground"
                  tickLine={false}
                  axisLine={false}
                />
                <YAxis
                  tick={{ fontSize: 10 }}
                  className="fill-muted-foreground"
                  tickFormatter={(v) => formatBytes(v)}
                  tickLine={false}
                  axisLine={false}
                  width={55}
                />
                <Tooltip
                  contentStyle={{
                    fontSize: 11,
                    borderRadius: 8,
                    border: "1px solid hsl(var(--border))",
                    background: "hsl(var(--popover))",
                    color: "hsl(var(--popover-foreground))",
                  }}
                  formatter={(value: number) => [formatBytes(value), "Size"]}
                  labelFormatter={(l) => `Date: ${l}`}
                />
                <Bar dataKey="size" fill="hsl(var(--primary))" radius={[3, 3, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      )}
    </div>
  );
}
