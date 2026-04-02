// Publish Analytics Tab — charts, plugin analytics, stage durations, and failure analysis.

import { useState } from "react";
import { format } from "date-fns";
import { usePublishAnalytics } from "@/hooks/usePublishAnalytics";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  BarChart,
  Bar,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
  PieChart,
  Pie,
  Cell,
} from "recharts";
import { Activity, TrendingUp, Clock, Globe, Download, FileText, CalendarIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Calendar } from "@/components/ui/calendar";
import { cn } from "@/lib/utils";
import { exportAnalyticsCsv, exportAnalyticsPdf } from "@/lib/analyticsExport";
import { PluginAnalyticsPanel } from "./PluginAnalyticsPanel";
import { StageDurationPanel } from "./StageDurationPanel";
import { FailureAnalysisPanel } from "./FailureAnalysisPanel";

const RANGE_OPTIONS = [
  { value: "7", label: "Last 7 days" },
  { value: "30", label: "Last 30 days" },
  { value: "90", label: "Last 90 days" },
  { value: "custom", label: "Custom range" },
] as const;

const DAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

function durationLabel(ms: number): string {
  if (ms === 0) return "—";
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

function heatColor(avgMs: number, maxMs: number): string {
  if (avgMs === 0) return "hsl(var(--muted))";
  const ratio = Math.min(avgMs / (maxMs || 1), 1);
  // Green → Yellow → Red
  if (ratio < 0.5) {
    const g = Math.round(140 + ratio * 2 * (50 - 140)); // not used, hsl approach:
    return `hsl(${Math.round(120 - ratio * 2 * 120)}, 70%, 45%)`;
  }
  return `hsl(${Math.round(120 - ratio * 120)}, 70%, 45%)`;
}

const SITE_COLORS = [
  "hsl(var(--primary))",
  "hsl(var(--chart-2))",
  "hsl(var(--chart-3))",
  "hsl(var(--chart-4))",
  "hsl(var(--chart-5))",
  "hsl(210, 60%, 55%)",
  "hsl(280, 50%, 55%)",
  "hsl(30, 70%, 50%)",
];

export function PublishAnalyticsTab() {
  const [rangeType, setRangeType] = useState<string>("30");
  const [customFrom, setCustomFrom] = useState<Date | undefined>();
  const [customTo, setCustomTo] = useState<Date | undefined>();

  const days = rangeType === "custom" ? 30 : Number(rangeType);
  const customRange =
    rangeType === "custom" && customFrom && customTo
      ? { from: customFrom, to: customTo }
      : undefined;

  const { data, isLoading } = usePublishAnalytics(days, customRange);

  const rangeLabel =
    rangeType === "custom" && customFrom && customTo
      ? `${format(customFrom, "MMM dd")} – ${format(customTo, "MMM dd")}`
      : `${days}d`;

  if (isLoading) {
    return <div className="text-center py-12 text-muted-foreground">Loading analytics…</div>;
  }

  if (!data || data.summary.total === 0) {
    return (
      <div className="space-y-4">
        <RangeControls
          rangeType={rangeType}
          setRangeType={setRangeType}
          customFrom={customFrom}
          customTo={customTo}
          setCustomFrom={setCustomFrom}
          setCustomTo={setCustomTo}
        />
        <div className="text-center py-12 text-muted-foreground">
          No publish data in the selected range.
        </div>
      </div>
    );
  }

  const maxHeatMs = Math.max(...data.heatmap.map((h) => h.avgDurationMs));

  return (
    <div className="space-y-6">
      {/* Header with range picker and export */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <RangeControls
          rangeType={rangeType}
          setRangeType={setRangeType}
          customFrom={customFrom}
          customTo={customTo}
          setCustomFrom={setCustomFrom}
          setCustomTo={setCustomTo}
        />
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm">
              <Download className="h-3.5 w-3.5 mr-1.5" />
              Export
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => exportAnalyticsCsv(data)}>
              <Download className="h-3.5 w-3.5 mr-2" />
              Download CSV
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => exportAnalyticsPdf(data)}>
              <FileText className="h-3.5 w-3.5 mr-2" />
              Export as PDF
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      {/* Summary stats */}
      <div className="grid grid-cols-2 md:grid-cols-6 gap-4">
        <SummaryCard label={`Total (${rangeLabel})`} value={data.summary.total} />
        <SummaryCard
          label="Success Rate"
          value={`${data.summary.total > 0 ? Math.round((data.summary.success / data.summary.total) * 100) : 0}%`}
        />
        <SummaryCard label="Successes" value={data.summary.success} />
        <SummaryCard label="Failures" value={data.summary.failed} />
        <SummaryCard label="Avg Duration" value={durationLabel(data.summary.avgDurationMs)} />
        <SummaryCard label="P95 Duration" value={durationLabel(data.summary.p95DurationMs)} />
      </div>

      {/* Row 1: Daily publishes + Success rate trend */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Daily publishes stacked bar */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Activity className="h-4 w-4 text-primary" />
              Publishes Over Time
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={data.daily} margin={{ top: 4, right: 4, bottom: 0, left: -20 }}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                <XAxis
                  dataKey="date"
                  tick={{ fontSize: 10 }}
                  interval={4}
                  className="fill-muted-foreground"
                />
                <YAxis tick={{ fontSize: 10 }} className="fill-muted-foreground" allowDecimals={false} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "hsl(var(--popover))",
                    border: "1px solid hsl(var(--border))",
                    borderRadius: "8px",
                    fontSize: "12px",
                  }}
                />
                <Legend wrapperStyle={{ fontSize: "11px" }} />
                <Bar dataKey="success" stackId="a" fill="hsl(var(--chart-2))" name="Success" radius={[0, 0, 0, 0]} />
                <Bar dataKey="partial" stackId="a" fill="hsl(var(--chart-4))" name="Partial" />
                <Bar dataKey="failed" stackId="a" fill="hsl(var(--destructive))" name="Failed" radius={[2, 2, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Success rate area chart */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <TrendingUp className="h-4 w-4 text-primary" />
              Success Rate Trend
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={260}>
              <AreaChart data={data.successRate.filter((d) => d.total > 0)} margin={{ top: 4, right: 4, bottom: 0, left: -20 }}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                <XAxis dataKey="date" tick={{ fontSize: 10 }} interval={4} className="fill-muted-foreground" />
                <YAxis tick={{ fontSize: 10 }} domain={[0, 100]} className="fill-muted-foreground" />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "hsl(var(--popover))",
                    border: "1px solid hsl(var(--border))",
                    borderRadius: "8px",
                    fontSize: "12px",
                  }}
                  formatter={(value: number) => [`${value}%`, "Success Rate"]}
                />
                {/* Threshold line at 90% */}
                <defs>
                  <linearGradient id="successGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="hsl(var(--chart-2))" stopOpacity={0.3} />
                    <stop offset="100%" stopColor="hsl(var(--chart-2))" stopOpacity={0.05} />
                  </linearGradient>
                </defs>
                <Area
                  type="monotone"
                  dataKey="rate"
                  stroke="hsl(var(--chart-2))"
                  fill="url(#successGrad)"
                  strokeWidth={2}
                  dot={{ r: 2, fill: "hsl(var(--chart-2))" }}
                />
              </AreaChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      {/* Row 2: Duration heatmap + Per-site breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Duration heatmap */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Clock className="h-4 w-4 text-primary" />
              Duration by Day × Hour
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <div className="min-w-[420px]">
                {/* Hour labels */}
                <div className="flex mb-1 ml-10">
                  {[0, 3, 6, 9, 12, 15, 18, 21].map((h) => (
                    <span
                      key={h}
                      className="text-[9px] text-muted-foreground"
                      style={{ width: `${(3 / 24) * 100}%` }}
                    >
                      {h}:00
                    </span>
                  ))}
                </div>
                {/* Grid */}
                {DAY_LABELS.map((dayLabel, dayIdx) => (
                  <div key={dayIdx} className="flex items-center gap-1 mb-0.5">
                    <span className="text-[10px] text-muted-foreground w-8 text-right shrink-0">
                      {dayLabel}
                    </span>
                    <div className="flex flex-1 gap-[1px]">
                      {Array.from({ length: 24 }, (_, hour) => {
                        const cell = data.heatmap.find(
                          (c) => c.day === dayIdx && c.hour === hour
                        );
                        const avgMs = cell?.avgDurationMs ?? 0;
                        const count = cell?.count ?? 0;
                        return (
                          <div
                            key={hour}
                            className="flex-1 aspect-square rounded-[2px] cursor-default transition-colors"
                            style={{ backgroundColor: heatColor(avgMs, maxHeatMs) }}
                            title={`${dayLabel} ${hour}:00 — ${count} publish${count !== 1 ? "es" : ""}, avg ${durationLabel(avgMs)}`}
                          />
                        );
                      })}
                    </div>
                  </div>
                ))}
                {/* Legend */}
                <div className="flex items-center justify-end gap-2 mt-2">
                  <span className="text-[9px] text-muted-foreground">Fast</span>
                  <div className="flex gap-[1px]">
                    {[0, 0.25, 0.5, 0.75, 1].map((r) => (
                      <div
                        key={r}
                        className="w-3 h-3 rounded-[2px]"
                        style={{ backgroundColor: r === 0 ? "hsl(var(--muted))" : `hsl(${Math.round(120 - r * 120)}, 70%, 45%)` }}
                      />
                    ))}
                  </div>
                  <span className="text-[9px] text-muted-foreground">Slow</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Per-site breakdown */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Globe className="h-4 w-4 text-primary" />
              Per-Site Breakdown
            </CardTitle>
          </CardHeader>
          <CardContent>
            {data.sites.length === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-8">No data</p>
            ) : (
              <div className="flex gap-6">
                {/* Pie chart */}
                <div className="w-36 h-36 shrink-0">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={data.sites}
                        dataKey="total"
                        nameKey="siteName"
                        cx="50%"
                        cy="50%"
                        innerRadius={30}
                        outerRadius={55}
                        paddingAngle={2}
                      >
                        {data.sites.map((_, i) => (
                          <Cell key={i} fill={SITE_COLORS[i % SITE_COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip
                        contentStyle={{
                          backgroundColor: "hsl(var(--popover))",
                          border: "1px solid hsl(var(--border))",
                          borderRadius: "8px",
                          fontSize: "12px",
                        }}
                      />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
                {/* Legend/table */}
                <div className="flex-1 space-y-2 overflow-y-auto max-h-[200px]">
                  {data.sites.map((site, i) => (
                    <div key={site.siteId} className="flex items-center justify-between text-sm">
                      <div className="flex items-center gap-2 min-w-0">
                        <div
                          className="w-2.5 h-2.5 rounded-full shrink-0"
                          style={{ backgroundColor: SITE_COLORS[i % SITE_COLORS.length] }}
                        />
                        <span className="truncate">{site.siteName}</span>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <span className="font-mono text-xs text-muted-foreground">{site.total}</span>
                        <Badge
                          variant="outline"
                          className={`text-[10px] px-1.5 ${
                            site.successRate >= 90
                              ? "text-success border-success/30"
                              : site.successRate >= 70
                                ? "text-warning border-warning/30"
                                : "text-destructive border-destructive/30"
                          }`}
                        >
                          {site.successRate}%
                        </Badge>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Row 3: Plugin analytics */}
      <PluginAnalyticsPanel plugins={data.plugins} />

      {/* Row 4: Stage duration breakdown + deployment speed trend */}
      <StageDurationPanel
        stages={data.stages}
        durationTrend={data.durationTrend}
        p95DurationMs={data.summary.p95DurationMs}
      />

      {/* Row 5: Failure analysis */}
      <FailureAnalysisPanel failures={data.failures} totalFailed={data.summary.failed} />
    </div>
  );
}

function RangeControls({
  rangeType,
  setRangeType,
  customFrom,
  customTo,
  setCustomFrom,
  setCustomTo,
}: {
  rangeType: string;
  setRangeType: (v: string) => void;
  customFrom: Date | undefined;
  customTo: Date | undefined;
  setCustomFrom: (d: Date | undefined) => void;
  setCustomTo: (d: Date | undefined) => void;
}) {
  return (
    <div className="flex items-center gap-3 flex-wrap">
      <h2 className="text-sm font-medium text-muted-foreground">Publish analytics</h2>
      <Select value={rangeType} onValueChange={setRangeType}>
        <SelectTrigger className="h-8 w-[150px] text-xs">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {RANGE_OPTIONS.map((opt) => (
            <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
          ))}
        </SelectContent>
      </Select>
      {rangeType === "custom" && (
        <div className="flex items-center gap-2">
          <DatePickerButton
            label="From"
            date={customFrom}
            onSelect={setCustomFrom}
            maxDate={customTo ?? new Date()}
          />
          <span className="text-xs text-muted-foreground">→</span>
          <DatePickerButton
            label="To"
            date={customTo}
            onSelect={setCustomTo}
            minDate={customFrom}
            maxDate={new Date()}
          />
        </div>
      )}
    </div>
  );
}

function DatePickerButton({
  label,
  date,
  onSelect,
  minDate,
  maxDate,
}: {
  label: string;
  date: Date | undefined;
  onSelect: (d: Date | undefined) => void;
  minDate?: Date;
  maxDate?: Date;
}) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className={cn(
            "h-8 w-[130px] justify-start text-xs font-normal",
            !date && "text-muted-foreground"
          )}
        >
          <CalendarIcon className="h-3.5 w-3.5 mr-1.5" />
          {date ? format(date, "MMM dd, yyyy") : label}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={date}
          onSelect={onSelect}
          disabled={(d) =>
            (maxDate ? d > maxDate : false) || (minDate ? d < minDate : false)
          }
          initialFocus
          className={cn("p-3 pointer-events-auto")}
        />
      </PopoverContent>
    </Popover>
  );
}

function SummaryCard({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <CardHeader className="pb-1">
        <CardTitle className="text-xs font-medium text-muted-foreground">{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="text-xl font-bold">{value}</div>
      </CardContent>
    </Card>
  );
}
