// Stage duration breakdown — stacked bar chart + duration trend line chart.

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
  BarChart,
  Bar,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from "recharts";
import { Timer, TrendingDown } from "lucide-react";
import type { StageDuration, DurationTrendPoint } from "@/hooks/usePublishAnalytics";

interface Props {
  stages: StageDuration[];
  durationTrend: DurationTrendPoint[];
  p95DurationMs: number;
}

const STAGE_COLORS: Record<string, string> = {
  backup: "hsl(var(--chart-2))",
  upload: "hsl(var(--primary))",
  activation: "hsl(var(--chart-4))",
  cloud_upload: "hsl(var(--chart-3))",
  "remote-backup": "hsl(var(--chart-5))",
};

function durationLabel(ms: number): string {
  if (ms === 0) return "—";
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

export function StageDurationPanel({ stages, durationTrend, p95DurationMs }: Props) {
  const hasData = stages.length > 0;

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {/* Stage breakdown bar chart */}
      <Card>
        <CardHeader className="pb-2">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Timer className="h-4 w-4 text-primary" />
              Duration by Stage
            </CardTitle>
            <Badge variant="outline" className="text-[10px]">
              P95: {durationLabel(p95DurationMs)}
            </Badge>
          </div>
        </CardHeader>
        <CardContent>
          {!hasData ? (
            <div className="text-center py-8 text-sm text-muted-foreground">No stage data</div>
          ) : (
            <>
              <ResponsiveContainer width="100%" height={220}>
                <BarChart data={stages} margin={{ left: -10, right: 10 }}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                  <XAxis dataKey="stage" tick={{ fontSize: 10 }} className="fill-muted-foreground" />
                  <YAxis tick={{ fontSize: 10 }} className="fill-muted-foreground" />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: "hsl(var(--popover))",
                      border: "1px solid hsl(var(--border))",
                      borderRadius: "8px",
                      fontSize: "12px",
                    }}
                    formatter={(value: number, name: string) => [durationLabel(value), name === "avgMs" ? "Average" : "P95"]}
                  />
                  <Legend wrapperStyle={{ fontSize: "11px" }} />
                  <Bar dataKey="avgMs" name="Average" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                  <Bar dataKey="p95Ms" name="P95" fill="hsl(var(--chart-4))" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
              {/* Stage summary */}
              <div className="mt-3 space-y-1.5">
                {stages.map((s) => (
                  <div key={s.stage} className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-2">
                      <div
                        className="w-2 h-2 rounded-full"
                        style={{ backgroundColor: STAGE_COLORS[s.stage] ?? "hsl(var(--muted-foreground))" }}
                      />
                      <span className="capitalize">{s.stage.replace("_", " ")}</span>
                    </div>
                    <span className="font-mono text-muted-foreground">
                      avg {durationLabel(s.avgMs)} · p95 {durationLabel(s.p95Ms)}
                    </span>
                  </div>
                ))}
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {/* Duration trend over time */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium flex items-center gap-2">
            <TrendingDown className="h-4 w-4 text-primary" />
            Deployment Speed Trend
          </CardTitle>
        </CardHeader>
        <CardContent>
          {durationTrend.filter((d) => d.avgMs > 0).length === 0 ? (
            <div className="text-center py-8 text-sm text-muted-foreground">No duration data</div>
          ) : (
            <ResponsiveContainer width="100%" height={260}>
              <LineChart data={durationTrend.filter((d) => d.avgMs > 0)} margin={{ left: -10, right: 10 }}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                <XAxis dataKey="date" tick={{ fontSize: 10 }} interval={4} className="fill-muted-foreground" />
                <YAxis tick={{ fontSize: 10 }} className="fill-muted-foreground" />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "hsl(var(--popover))",
                    border: "1px solid hsl(var(--border))",
                    borderRadius: "8px",
                    fontSize: "12px",
                  }}
                  formatter={(value: number, name: string) => [durationLabel(value), name]}
                />
                <Legend wrapperStyle={{ fontSize: "11px" }} />
                <Line
                  type="monotone"
                  dataKey="avgMs"
                  name="Average"
                  stroke="hsl(var(--primary))"
                  strokeWidth={2}
                  dot={{ r: 2 }}
                />
                <Line
                  type="monotone"
                  dataKey="p95Ms"
                  name="P95"
                  stroke="hsl(var(--chart-4))"
                  strokeWidth={1.5}
                  strokeDasharray="4 2"
                  dot={false}
                />
              </LineChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
