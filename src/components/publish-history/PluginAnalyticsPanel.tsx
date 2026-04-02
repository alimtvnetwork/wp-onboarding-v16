// Per-plugin analytics — publish frequency bar chart + success rate table.

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import { Package } from "lucide-react";
import type { PluginBreakdown } from "@/hooks/usePublishAnalytics";

interface Props {
  plugins: PluginBreakdown[];
}

const PLUGIN_COLORS = [
  "hsl(var(--primary))",
  "hsl(var(--chart-2))",
  "hsl(var(--chart-3))",
  "hsl(var(--chart-4))",
  "hsl(var(--chart-5))",
];

function durationLabel(ms: number): string {
  if (ms === 0) return "—";
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

export function PluginAnalyticsPanel({ plugins }: Props) {
  if (plugins.length === 0) {
    return (
      <Card>
        <CardContent className="py-8 text-center text-sm text-muted-foreground">
          No plugin data available
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {/* Frequency bar chart */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium flex items-center gap-2">
            <Package className="h-4 w-4 text-primary" />
            Publish Frequency by Plugin
          </CardTitle>
        </CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={plugins} layout="vertical" margin={{ left: 10, right: 10 }}>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
              <XAxis type="number" tick={{ fontSize: 10 }} className="fill-muted-foreground" allowDecimals={false} />
              <YAxis
                type="category"
                dataKey="pluginName"
                tick={{ fontSize: 10 }}
                className="fill-muted-foreground"
                width={100}
              />
              <Tooltip
                contentStyle={{
                  backgroundColor: "hsl(var(--popover))",
                  border: "1px solid hsl(var(--border))",
                  borderRadius: "8px",
                  fontSize: "12px",
                }}
              />
              <Bar dataKey="total" fill="hsl(var(--primary))" radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      {/* Success rate table */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">Plugin Success Rates</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {plugins.map((plugin) => (
              <div
                key={plugin.pluginId}
                className="flex items-center justify-between rounded-md border border-border p-3"
              >
                <div className="min-w-0">
                  <p className="text-sm font-medium truncate">{plugin.pluginName}</p>
                  <p className="text-xs text-muted-foreground">
                    {plugin.total} publishes · avg {durationLabel(plugin.avgDurationMs)}
                  </p>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <span className="text-xs font-mono text-muted-foreground">
                    {plugin.success}/{plugin.total}
                  </span>
                  <Badge
                    variant="outline"
                    className={`text-[10px] px-1.5 ${
                      plugin.successRate >= 90
                        ? "text-success border-success/30"
                        : plugin.successRate >= 70
                          ? "text-warning border-warning/30"
                          : "text-destructive border-destructive/30"
                    }`}
                  >
                    {plugin.successRate}%
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
