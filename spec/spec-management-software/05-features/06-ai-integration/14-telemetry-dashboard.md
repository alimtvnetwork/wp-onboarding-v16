# AI Telemetry Dashboard

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  

---

## Overview

The AI Telemetry Dashboard provides real-time monitoring of AI execution success rates, failure patterns, escalation metrics, and system health. It enables operators to identify issues proactively and optimize the Resilient Execution System.

**Cross-References:**
- [Resilient Execution System](./12-resilient-execution-system.md) — Data source
- [Escalation Notifications](./13-escalation-notifications.md) — Escalation metrics
- [LLM Live Logging](./06-llm-live-logging.md) — Streaming data

---

## 14.1 Dashboard Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          AI TELEMETRY DASHBOARD                                      │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────┐     │
│  │                           Header / Filters                                    │     │
│  │  [Time Range ▼] [Project ▼] [Model Category ▼] [Priority ▼] [🔄 Auto-refresh] │     │
│  └─────────────────────────────────────────────────────────────────────────────┘     │
│                                                                                       │
│  ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐ │
│  │  SUCCESS RATE    │ │  AVG ATTEMPTS    │ │  ESCALATION RATE │ │  ACTIVE TASKS    │ │
│  │     98.2%        │ │      1.3         │ │      1.8%        │ │       12         │ │
│  │   ↑ 0.5% vs 24h  │ │   ↓ 0.2 vs 24h   │ │   ↓ 0.3% vs 24h  │ │   3 high priority│ │
│  └──────────────────┘ └──────────────────┘ └──────────────────┘ └──────────────────┘ │
│                                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────┐     │
│  │                      Success Rate Over Time                                   │     │
│  │  100% ─┬─────────────────────────────────────────────────────────────────    │     │
│  │   95% ─┼────────██████████████████████████████████████████████████████────   │     │
│  │   90% ─┼────────────────────────────────────────────────────────────────────  │     │
│  │   85% ─┼────────────────────────────────────────────────────────────────────  │     │
│  │        └─────┬─────┬─────┬─────┬─────┬─────┬─────┬─────┬─────┬─────┬─────    │     │
│  │            00:00  04:00  08:00  12:00  16:00  20:00                           │     │
│  └─────────────────────────────────────────────────────────────────────────────┘     │
│                                                                                       │
│  ┌────────────────────────────────────┐ ┌────────────────────────────────────┐       │
│  │     Failure Distribution           │ │     Recovery Path Analysis         │       │
│  │  ┌─────────────────────────────┐   │ │  ┌─────────────────────────────┐   │       │
│  │  │ Hallucination    ████░░ 25% │   │ │  │ Retry Success      ████ 60% │   │       │
│  │  │ Incomplete       ███░░░ 20% │   │ │  │ Self-Correction    ██░░ 25% │   │       │
│  │  │ Timeout          ██░░░░ 15% │   │ │  │ Consensus          █░░░ 10% │   │       │
│  │  │ Ambiguous        ██░░░░ 15% │   │ │  │ Escalation         ░░░░  5% │   │       │
│  │  │ Validation       █░░░░░ 10% │   │ │  └─────────────────────────────┘   │       │
│  │  │ Other            █░░░░░ 15% │   │ │                                     │       │
│  │  └─────────────────────────────┘   │ │                                     │       │
│  └────────────────────────────────────┘ └────────────────────────────────────┘       │
│                                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────┐     │
│  │                      Recent Failures & Escalations                            │     │
│  │  ┌─────────────────────────────────────────────────────────────────────────┐ │     │
│  │  │ 🔴 CRITICAL │ 2m ago  │ Destructive action approval │ Pending      [→] │ │     │
│  │  │ 🟠 HIGH     │ 5m ago  │ Repeated failure (3x)       │ Resolved     [→] │ │     │
│  │  │ 🟡 MEDIUM   │ 12m ago │ Low confidence output       │ Auto-resolved[→] │ │     │
│  │  │ 🔵 LOW      │ 18m ago │ Ambiguous instruction       │ Resolved     [→] │ │     │
│  │  └─────────────────────────────────────────────────────────────────────────┘ │     │
│  └─────────────────────────────────────────────────────────────────────────────┘     │
│                                                                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 14.2 Data Models

### Dashboard Metrics

```typescript
interface DashboardMetrics {
  readonly timeRange: TimeRange;
  readonly summary: SummaryMetrics;
  readonly successTrend: readonly DataPoint[];
  readonly failureDistribution: readonly FailureCategory[];
  readonly recoveryPaths: readonly RecoveryPath[];
  readonly modelPerformance: readonly ModelMetrics[];
  readonly recentEvents: readonly TelemetryEvent[];
}

interface SummaryMetrics {
  readonly successRate: number;
  readonly successRateChange: number;  // vs previous period
  readonly averageAttempts: number;
  readonly averageAttemptsChange: number;
  readonly escalationRate: number;
  readonly escalationRateChange: number;
  readonly activeTasks: number;
  readonly highPriorityTasks: number;
  readonly totalTasks: number;
  readonly totalFailures: number;
}

interface DataPoint {
  readonly timestamp: string;
  readonly value: number;
  readonly label?: string;
}

interface FailureCategory {
  readonly category: string;
  readonly count: number;
  readonly percentage: number;
  readonly trend: 'up' | 'down' | 'stable';
}

interface RecoveryPath {
  readonly path: string;
  readonly count: number;
  readonly percentage: number;
  readonly avgDuration: number;
}

interface ModelMetrics {
  readonly modelId: string;
  readonly modelName: string;
  readonly category: 'thinking' | 'writing' | 'coding' | 'voice';
  readonly successRate: number;
  readonly avgLatency: number;
  readonly totalTasks: number;
  readonly tokenUsage: number;
}

interface TelemetryEvent {
  readonly id: string;
  readonly type: 'failure' | 'escalation' | 'recovery' | 'success';
  readonly priority: 'low' | 'medium' | 'high' | 'critical';
  readonly title: string;
  readonly description: string;
  readonly taskId: string;
  readonly timestamp: string;
  readonly status: 'pending' | 'resolved' | 'auto_resolved' | 'expired';
  readonly metadata: Record<string, unknown>;
}
```

### Time Range Options

```typescript
type TimeRange = 
  | { type: 'preset'; value: '1h' | '6h' | '24h' | '7d' | '30d' }
  | { type: 'custom'; start: string; end: string };

const TIME_RANGE_OPTIONS = [
  { label: 'Last hour', value: '1h' },
  { label: 'Last 6 hours', value: '6h' },
  { label: 'Last 24 hours', value: '24h' },
  { label: 'Last 7 days', value: '7d' },
  { label: 'Last 30 days', value: '30d' },
  { label: 'Custom', value: 'custom' },
] as const;
```

---

## 14.3 Dashboard Components

### Main Dashboard Page

```typescript
// src/pages/TelemetryDashboard.tsx
import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { SummaryCards } from '@/components/telemetry/SummaryCards';
import { SuccessRateChart } from '@/components/telemetry/SuccessRateChart';
import { FailureDistributionChart } from '@/components/telemetry/FailureDistributionChart';
import { RecoveryPathChart } from '@/components/telemetry/RecoveryPathChart';
import { ModelPerformanceTable } from '@/components/telemetry/ModelPerformanceTable';
import { RecentEventsTable } from '@/components/telemetry/RecentEventsTable';
import { fetchDashboardMetrics } from '@/api/telemetry';

export function TelemetryDashboard(): JSX.Element {
  const [timeRange, setTimeRange] = useState<string>('24h');
  const [projectFilter, setProjectFilter] = useState<string>('all');
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [refreshInterval, setRefreshInterval] = useState(30000);

  const { data: metrics, isLoading, refetch } = useQuery({
    queryKey: ['telemetry', timeRange, projectFilter],
    queryFn: () => fetchDashboardMetrics({ timeRange, projectId: projectFilter }),
    refetchInterval: autoRefresh ? refreshInterval : false,
  });

  return (
    <div className="flex flex-col gap-6 p-6">
      {/* Header & Filters */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">AI Telemetry Dashboard</h1>
          <p className="text-muted-foreground">Monitor execution success rates and system health</p>
        </div>
        
        <div className="flex items-center gap-4">
          <Select value={timeRange} onValueChange={setTimeRange}>
            <SelectTrigger className="w-40">
              <SelectValue placeholder="Time range" />
            </SelectTrigger>
            <SelectContent>
              {TIME_RANGE_OPTIONS.map(opt => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          
          <div className="flex items-center gap-2">
            <Switch
              checked={autoRefresh}
              onCheckedChange={setAutoRefresh}
              id="auto-refresh"
            />
            <label htmlFor="auto-refresh" className="text-sm">
              Auto-refresh
            </label>
          </div>
          
          <Button variant="outline" size="icon" onClick={() => refetch()}>
            <RefreshCw className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Summary Cards */}
      <SummaryCards metrics={metrics?.summary} isLoading={isLoading} />

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <SuccessRateChart 
          data={metrics?.successTrend} 
          isLoading={isLoading}
          target={98}
        />
        <div className="grid grid-cols-2 gap-6">
          <FailureDistributionChart 
            data={metrics?.failureDistribution} 
            isLoading={isLoading} 
          />
          <RecoveryPathChart 
            data={metrics?.recoveryPaths} 
            isLoading={isLoading} 
          />
        </div>
      </div>

      {/* Model Performance */}
      <ModelPerformanceTable 
        data={metrics?.modelPerformance} 
        isLoading={isLoading} 
      />

      {/* Recent Events */}
      <RecentEventsTable 
        data={metrics?.recentEvents} 
        isLoading={isLoading}
        onEventClick={(event) => {/* Navigate to detail */}}
      />
    </div>
  );
}
```

### Summary Cards Component

```typescript
// src/components/telemetry/SummaryCards.tsx
import { TrendingUp, TrendingDown, Minus, RotateCcw, AlertTriangle, Activity, CheckCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface SummaryCardsProps {
  readonly metrics?: SummaryMetrics;
  readonly isLoading: boolean;
}

export function SummaryCards({ metrics, isLoading }: SummaryCardsProps): JSX.Element {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {[...Array(4)].map((_, i) => (
          <Card key={i}>
            <CardHeader className="pb-2">
              <Skeleton className="h-4 w-24" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-8 w-16" />
              <Skeleton className="h-3 w-20 mt-2" />
            </CardContent>
          </Card>
        ))}
      </div>
    );
  }

  const cards = [
    {
      title: 'Success Rate',
      value: `${metrics?.successRate.toFixed(1)}%`,
      change: metrics?.successRateChange,
      icon: CheckCircle,
      target: 98,
      isAboveTarget: (metrics?.successRate ?? 0) >= 98,
    },
    {
      title: 'Avg Attempts',
      value: metrics?.averageAttempts.toFixed(2),
      change: metrics?.averageAttemptsChange,
      invertChange: true, // Lower is better
      icon: RotateCcw,
      target: 1.5,
      isAboveTarget: (metrics?.averageAttempts ?? 0) <= 1.5,
    },
    {
      title: 'Escalation Rate',
      value: `${metrics?.escalationRate.toFixed(1)}%`,
      change: metrics?.escalationRateChange,
      invertChange: true, // Lower is better
      icon: AlertTriangle,
      target: 2,
      isAboveTarget: (metrics?.escalationRate ?? 0) <= 2,
    },
    {
      title: 'Active Tasks',
      value: metrics?.activeTasks.toString(),
      subtitle: `${metrics?.highPriorityTasks} high priority`,
      icon: Activity,
    },
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      {cards.map((card) => (
        <MetricCard key={card.title} {...card} />
      ))}
    </div>
  );
}

interface MetricCardProps {
  readonly title: string;
  readonly value: string;
  readonly change?: number;
  readonly invertChange?: boolean;
  readonly subtitle?: string;
  readonly icon: React.ComponentType<{ className?: string }>;
  readonly target?: number;
  readonly isAboveTarget?: boolean;
}

function MetricCard({ 
  title, 
  value, 
  change, 
  invertChange, 
  subtitle,
  icon: Icon,
  isAboveTarget 
}: MetricCardProps): JSX.Element {
  const getTrendIcon = () => {
    if (change === undefined || change === 0) return <Minus className="h-3 w-3" />;
    const isPositive = invertChange ? change < 0 : change > 0;
    return isPositive 
      ? <TrendingUp className="h-3 w-3 text-green-500" />
      : <TrendingDown className="h-3 w-3 text-red-500" />;
  };

  const getChangeColor = () => {
    if (change === undefined || change === 0) return 'text-muted-foreground';
    const isPositive = invertChange ? change < 0 : change > 0;
    return isPositive ? 'text-green-500' : 'text-red-500';
  };

  return (
    <Card className={cn(
      isAboveTarget !== undefined && !isAboveTarget && "border-orange-500/50"
    )}>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">
          {title}
        </CardTitle>
        <Icon className={cn(
          "h-4 w-4",
          isAboveTarget ? "text-green-500" : "text-muted-foreground"
        )} />
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        {change !== undefined && (
          <div className={cn("flex items-center gap-1 text-xs mt-1", getChangeColor())}>
            {getTrendIcon()}
            <span>{Math.abs(change).toFixed(1)}% vs 24h</span>
          </div>
        )}
        {subtitle && (
          <p className="text-xs text-muted-foreground mt-1">{subtitle}</p>
        )}
      </CardContent>
    </Card>
  );
}
```

### Success Rate Chart

```typescript
// src/components/telemetry/SuccessRateChart.tsx
import { useMemo } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, ReferenceLine } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

interface SuccessRateChartProps {
  readonly data?: readonly DataPoint[];
  readonly isLoading: boolean;
  readonly target: number;
}

export function SuccessRateChart({ data, isLoading, target }: SuccessRateChartProps): JSX.Element {
  const chartData = useMemo(() => {
    return data?.map(point => ({
      ...point,
      timestamp: new Date(point.timestamp).toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
      }),
    }));
  }, [data]);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Success Rate Over Time</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[300px] w-full" />
        </CardContent>
      </Card>
    );
  }

  const minValue = Math.min(...(data?.map(d => d.value) ?? [0]));
  const yAxisMin = Math.max(0, Math.floor(minValue / 5) * 5 - 5);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Success Rate Over Time</CardTitle>
        <CardDescription>
          Target: {target}% | Current trend shown with {target}% threshold line
        </CardDescription>
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={300}>
          <LineChart data={chartData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
            <XAxis 
              dataKey="timestamp" 
              className="text-xs fill-muted-foreground"
              tickLine={false}
            />
            <YAxis 
              domain={[yAxisMin, 100]}
              className="text-xs fill-muted-foreground"
              tickLine={false}
              tickFormatter={(value) => `${value}%`}
            />
            <Tooltip 
              contentStyle={{ 
                backgroundColor: 'hsl(var(--card))', 
                border: '1px solid hsl(var(--border))',
                borderRadius: '8px',
              }}
              formatter={(value: number) => [`${value.toFixed(2)}%`, 'Success Rate']}
            />
            <ReferenceLine 
              y={target} 
              stroke="hsl(var(--primary))" 
              strokeDasharray="5 5"
              label={{ 
                value: `Target: ${target}%`, 
                position: 'right',
                fill: 'hsl(var(--primary))',
                fontSize: 12,
              }}
            />
            <Line 
              type="monotone" 
              dataKey="value" 
              stroke="hsl(var(--chart-1))"
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4, fill: 'hsl(var(--chart-1))' }}
            />
          </LineChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}
```

### Failure Distribution Chart

```typescript
// src/components/telemetry/FailureDistributionChart.tsx
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

interface FailureDistributionChartProps {
  readonly data?: readonly FailureCategory[];
  readonly isLoading: boolean;
}

const FAILURE_COLORS: Record<string, string> = {
  hallucination: 'hsl(var(--destructive))',
  incomplete: 'hsl(var(--chart-2))',
  timeout: 'hsl(var(--chart-3))',
  ambiguous: 'hsl(var(--chart-4))',
  validation: 'hsl(var(--chart-5))',
  other: 'hsl(var(--muted-foreground))',
};

export function FailureDistributionChart({ data, isLoading }: FailureDistributionChartProps): JSX.Element {
  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Failure Distribution</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[200px] w-full" />
        </CardContent>
      </Card>
    );
  }

  const chartData = data?.map(item => ({
    name: formatCategoryName(item.category),
    value: item.count,
    percentage: item.percentage,
    color: FAILURE_COLORS[item.category] ?? FAILURE_COLORS.other,
  }));

  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Failure Types</CardTitle>
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={180}>
          <PieChart>
            <Pie
              data={chartData}
              cx="50%"
              cy="50%"
              innerRadius={40}
              outerRadius={70}
              paddingAngle={2}
              dataKey="value"
            >
              {chartData?.map((entry, index) => (
                <Cell key={index} fill={entry.color} />
              ))}
            </Pie>
            <Tooltip 
              formatter={(value: number, name: string, props: any) => [
                `${props.payload.percentage.toFixed(1)}% (${value})`,
                name
              ]}
            />
          </PieChart>
        </ResponsiveContainer>
        
        {/* Legend */}
        <div className="flex flex-wrap justify-center gap-x-4 gap-y-1 mt-2">
          {chartData?.slice(0, 4).map((item) => (
            <div key={item.name} className="flex items-center gap-1.5 text-xs">
              <div 
                className="w-2 h-2 rounded-full" 
                style={{ backgroundColor: item.color }} 
              />
              <span className="text-muted-foreground">{item.name}</span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

function formatCategoryName(category: string): string {
  return category
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}
```

### Recovery Path Chart

```typescript
// src/components/telemetry/RecoveryPathChart.tsx
import { BarChart, Bar, XAxis, YAxis, ResponsiveContainer, Tooltip, Cell } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

interface RecoveryPathChartProps {
  readonly data?: readonly RecoveryPath[];
  readonly isLoading: boolean;
}

const PATH_COLORS = [
  'hsl(var(--chart-1))',
  'hsl(var(--chart-2))',
  'hsl(var(--chart-3))',
  'hsl(var(--chart-4))',
];

export function RecoveryPathChart({ data, isLoading }: RecoveryPathChartProps): JSX.Element {
  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Recovery Paths</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[200px] w-full" />
        </CardContent>
      </Card>
    );
  }

  const chartData = data?.map(item => ({
    name: formatPathName(item.path),
    value: item.percentage,
    count: item.count,
    duration: item.avgDuration,
  }));

  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Recovery Paths</CardTitle>
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={180}>
          <BarChart 
            data={chartData} 
            layout="vertical"
            margin={{ top: 0, right: 0, left: 0, bottom: 0 }}
          >
            <XAxis 
              type="number" 
              domain={[0, 100]}
              tickFormatter={(v) => `${v}%`}
              className="text-xs fill-muted-foreground"
            />
            <YAxis 
              type="category" 
              dataKey="name"
              width={80}
              className="text-xs fill-muted-foreground"
              tickLine={false}
            />
            <Tooltip 
              formatter={(value: number, name: string, props: any) => [
                `${value.toFixed(1)}% (${props.payload.count} tasks)`,
                'Usage'
              ]}
            />
            <Bar dataKey="value" radius={[0, 4, 4, 0]}>
              {chartData?.map((_, index) => (
                <Cell key={index} fill={PATH_COLORS[index % PATH_COLORS.length]} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}

function formatPathName(path: string): string {
  const names: Record<string, string> = {
    retry_success: 'Retry',
    self_correction: 'Self-Fix',
    consensus: 'Consensus',
    escalation: 'Escalate',
  };
  return names[path] ?? path;
}
```

### Model Performance Table

```typescript
// src/components/telemetry/ModelPerformanceTable.tsx
import { ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface ModelPerformanceTableProps {
  readonly data?: readonly ModelMetrics[];
  readonly isLoading: boolean;
}

type SortField = 'successRate' | 'avgLatency' | 'totalTasks' | 'tokenUsage';
type SortDirection = 'asc' | 'desc';

export function ModelPerformanceTable({ data, isLoading }: ModelPerformanceTableProps): JSX.Element {
  const [sortField, setSortField] = useState<SortField>('totalTasks');
  const [sortDirection, setSortDirection] = useState<SortDirection>('desc');

  const sortedData = [...(data ?? [])].sort((a, b) => {
    const aVal = a[sortField];
    const bVal = b[sortField];
    return sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
  });

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDirection(d => d === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortDirection('desc');
    }
  };

  const SortIcon = ({ field }: { field: SortField }) => {
    if (sortField !== field) return <ArrowUpDown className="h-3 w-3 opacity-50" />;
    return sortDirection === 'asc' 
      ? <ArrowUp className="h-3 w-3" />
      : <ArrowDown className="h-3 w-3" />;
  };

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Model Performance</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[200px] w-full" />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Model Performance</CardTitle>
        <CardDescription>Performance metrics by AI model</CardDescription>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Model</TableHead>
              <TableHead>Category</TableHead>
              <TableHead>
                <Button 
                  variant="ghost" 
                  size="sm" 
                  className="h-auto p-0 font-medium"
                  onClick={() => handleSort('successRate')}
                >
                  Success Rate <SortIcon field="successRate" />
                </Button>
              </TableHead>
              <TableHead>
                <Button 
                  variant="ghost" 
                  size="sm" 
                  className="h-auto p-0 font-medium"
                  onClick={() => handleSort('avgLatency')}
                >
                  Avg Latency <SortIcon field="avgLatency" />
                </Button>
              </TableHead>
              <TableHead>
                <Button 
                  variant="ghost" 
                  size="sm" 
                  className="h-auto p-0 font-medium"
                  onClick={() => handleSort('totalTasks')}
                >
                  Tasks <SortIcon field="totalTasks" />
                </Button>
              </TableHead>
              <TableHead>
                <Button 
                  variant="ghost" 
                  size="sm" 
                  className="h-auto p-0 font-medium"
                  onClick={() => handleSort('tokenUsage')}
                >
                  Tokens <SortIcon field="tokenUsage" />
                </Button>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {sortedData.map((model) => (
              <TableRow key={model.modelId}>
                <TableCell className="font-medium">{model.modelName}</TableCell>
                <TableCell>
                  <Badge variant="outline" className="capitalize">
                    {model.category}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Progress 
                      value={model.successRate} 
                      className={cn(
                        "w-16 h-2",
                        model.successRate >= 98 && "[&>div]:bg-green-500",
                        model.successRate >= 90 && model.successRate < 98 && "[&>div]:bg-yellow-500",
                        model.successRate < 90 && "[&>div]:bg-red-500"
                      )}
                    />
                    <span className="text-sm">{model.successRate.toFixed(1)}%</span>
                  </div>
                </TableCell>
                <TableCell>{formatLatency(model.avgLatency)}</TableCell>
                <TableCell>{model.totalTasks.toLocaleString()}</TableCell>
                <TableCell>{formatTokens(model.tokenUsage)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}

function formatLatency(ms: number): string {
  if (ms < 1000) return `${ms}ms`;
  return `${(ms / 1000).toFixed(1)}s`;
}

function formatTokens(tokens: number): string {
  if (tokens < 1000) return tokens.toString();
  if (tokens < 1000000) return `${(tokens / 1000).toFixed(1)}K`;
  return `${(tokens / 1000000).toFixed(1)}M`;
}
```

### Recent Events Table

```typescript
// src/components/telemetry/RecentEventsTable.tsx
import { AlertCircle, AlertTriangle, Info, CheckCircle, ExternalLink } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { formatDistanceToNow } from 'date-fns';

interface RecentEventsTableProps {
  readonly data?: readonly TelemetryEvent[];
  readonly isLoading: boolean;
  readonly onEventClick: (event: TelemetryEvent) => void;
}

const priorityConfig = {
  critical: { icon: AlertCircle, color: 'text-destructive', badge: 'destructive' },
  high: { icon: AlertTriangle, color: 'text-orange-500', badge: 'warning' },
  medium: { icon: Info, color: 'text-blue-500', badge: 'secondary' },
  low: { icon: CheckCircle, color: 'text-muted-foreground', badge: 'outline' },
} as const;

const statusConfig = {
  pending: { label: 'Pending', variant: 'destructive' },
  resolved: { label: 'Resolved', variant: 'default' },
  auto_resolved: { label: 'Auto-resolved', variant: 'secondary' },
  expired: { label: 'Expired', variant: 'outline' },
} as const;

export function RecentEventsTable({ data, isLoading, onEventClick }: RecentEventsTableProps): JSX.Element {
  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Recent Events</CardTitle>
        </CardHeader>
        <CardContent>
          <Skeleton className="h-[300px] w-full" />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Recent Failures & Escalations</CardTitle>
        <CardDescription>Latest system events requiring attention</CardDescription>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-24">Priority</TableHead>
              <TableHead className="w-24">Time</TableHead>
              <TableHead>Event</TableHead>
              <TableHead className="w-28">Status</TableHead>
              <TableHead className="w-12"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                  No recent events
                </TableCell>
              </TableRow>
            ) : (
              data?.map((event) => {
                const priority = priorityConfig[event.priority];
                const status = statusConfig[event.status];
                const PriorityIcon = priority.icon;
                
                return (
                  <TableRow 
                    key={event.id}
                    className={cn(
                      "cursor-pointer hover:bg-muted/50",
                      event.status === 'pending' && "bg-destructive/5"
                    )}
                    onClick={() => onEventClick(event)}
                  >
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <PriorityIcon className={cn("h-4 w-4", priority.color)} />
                        <span className="text-xs uppercase font-medium">
                          {event.priority}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell className="text-muted-foreground text-sm">
                      {formatDistanceToNow(new Date(event.timestamp), { addSuffix: true })}
                    </TableCell>
                    <TableCell>
                      <div>
                        <p className="font-medium">{event.title}</p>
                        <p className="text-sm text-muted-foreground truncate max-w-md">
                          {event.description}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={status.variant as any}>
                        {status.label}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Button variant="ghost" size="icon" className="h-8 w-8">
                        <ExternalLink className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  );
}
```

---

## 14.4 Real-Time Updates

### WebSocket Hook

```typescript
// src/hooks/useTelemetryStream.ts
import { useEffect, useCallback, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';

interface TelemetryUpdate {
  type: 'metric_update' | 'new_event' | 'status_change';
  data: unknown;
}

export function useTelemetryStream(enabled: boolean = true): void {
  const queryClient = useQueryClient();
  const wsRef = useRef<WebSocket | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout>();

  const connect = useCallback(() => {
    if (!enabled) return;

    const ws = new WebSocket(`${import.meta.env.VITE_WS_URL}/telemetry`);
    wsRef.current = ws;

    ws.onopen = () => {
      console.log('Telemetry stream connected');
    };

    ws.onmessage = (event) => {
      try {
        const update: TelemetryUpdate = JSON.parse(event.data);
        handleUpdate(update, queryClient);
      } catch (e) {
        console.error('Failed to parse telemetry update:', e);
      }
    };

    ws.onclose = () => {
      console.log('Telemetry stream disconnected, reconnecting...');
      reconnectTimeoutRef.current = setTimeout(connect, 3000);
    };

    ws.onerror = (error) => {
      console.error('Telemetry stream error:', error);
      ws.close();
    };
  }, [enabled, queryClient]);

  useEffect(() => {
    connect();
    return () => {
      wsRef.current?.close();
      if (reconnectTimeoutRef.current) {
        clearTimeout(reconnectTimeoutRef.current);
      }
    };
  }, [connect]);
}

function handleUpdate(update: TelemetryUpdate, queryClient: any): void {
  switch (update.type) {
    case 'metric_update':
      // Invalidate metrics query to refresh
      queryClient.invalidateQueries({ queryKey: ['telemetry'] });
      break;
      
    case 'new_event':
      // Add to recent events cache
      queryClient.setQueryData(['telemetry'], (old: any) => {
        if (!old) return old;
        return {
          ...old,
          recentEvents: [update.data, ...old.recentEvents].slice(0, 20),
        };
      });
      break;
      
    case 'status_change':
      // Update specific event status
      queryClient.setQueryData(['telemetry'], (old: any) => {
        if (!old) return old;
        return {
          ...old,
          recentEvents: old.recentEvents.map((e: TelemetryEvent) =>
            e.id === (update.data as any).id ? { ...e, ...(update.data as any) } : e
          ),
        };
      });
      break;
  }
}
```

---

## 14.5 API Endpoints

### Backend Routes

```go
// routes/telemetry.go
func RegisterTelemetryRoutes(r *mux.Router, service *TelemetryService) {
    r.HandleFunc("/api/v1/telemetry/dashboard", service.GetDashboardMetrics).Methods("GET")
    r.HandleFunc("/api/v1/telemetry/success-rate", service.GetSuccessRateTrend).Methods("GET")
    r.HandleFunc("/api/v1/telemetry/failures", service.GetFailureDistribution).Methods("GET")
    r.HandleFunc("/api/v1/telemetry/models", service.GetModelPerformance).Methods("GET")
    r.HandleFunc("/api/v1/telemetry/events", service.GetRecentEvents).Methods("GET")
    r.HandleFunc("/api/v1/telemetry/events/{id}", service.GetEventDetails).Methods("GET")
    r.HandleFunc("/api/v1/telemetry/export", service.ExportMetrics).Methods("GET")
    
    // WebSocket for real-time updates
    r.HandleFunc("/ws/telemetry", service.HandleWebSocket)
}
```

### Dashboard Metrics Query

```go
func (s *TelemetryService) GetDashboardMetrics(w http.ResponseWriter, r *http.Request) {
    ctx := r.Context()
    
    // Parse query parameters
    timeRange := r.URL.Query().Get("timeRange")
    projectId := r.URL.Query().Get("projectId")
    
    start, end := parseTimeRange(timeRange)
    
    // Fetch all metrics in parallel
    var wg sync.WaitGroup
    var summary SummaryMetrics
    var successTrend []DataPoint
    var failures []FailureCategory
    var recovery []RecoveryPath
    var models []ModelMetrics
    var events []TelemetryEvent
    
    wg.Add(6)
    
    go func() {
        defer wg.Done()
        summary = s.repo.GetSummaryMetrics(ctx, start, end, projectId)
    }()
    
    go func() {
        defer wg.Done()
        successTrend = s.repo.GetSuccessRateTrend(ctx, start, end, projectId)
    }()
    
    go func() {
        defer wg.Done()
        failures = s.repo.GetFailureDistribution(ctx, start, end, projectId)
    }()
    
    go func() {
        defer wg.Done()
        recovery = s.repo.GetRecoveryPaths(ctx, start, end, projectId)
    }()
    
    go func() {
        defer wg.Done()
        models = s.repo.GetModelPerformance(ctx, start, end, projectId)
    }()
    
    go func() {
        defer wg.Done()
        events = s.repo.GetRecentEvents(ctx, start, end, projectId, 20)
    }()
    
    wg.Wait()
    
    response := DashboardMetrics{
        TimeRange:            timeRange,
        Summary:              summary,
        SuccessTrend:         successTrend,
        FailureDistribution:  failures,
        RecoveryPaths:        recovery,
        ModelPerformance:     models,
        RecentEvents:         events,
    }
    
    json.NewEncoder(w).Encode(response)
}
```

---

## 14.6 Alerting Rules

### Threshold Configuration

```yaml
# alerts/telemetry_rules.yaml
alerts:
  - name: success_rate_critical
    condition: success_rate < 95
    severity: critical
    message: "Success rate dropped below 95% (current: {{.value}}%)"
    channels: [in_app, email, slack]
    cooldown: 15m
    
  - name: success_rate_warning
    condition: success_rate < 98
    severity: warning
    message: "Success rate below target of 98% (current: {{.value}}%)"
    channels: [in_app]
    cooldown: 30m
    
  - name: escalation_spike
    condition: escalation_rate > 5 AND escalation_rate_change > 100
    severity: high
    message: "Escalation rate spiked to {{.value}}% (2x increase)"
    channels: [in_app, email]
    cooldown: 1h
    
  - name: model_degradation
    condition: model_success_rate < 90
    severity: high
    message: "Model {{.model}} success rate dropped to {{.value}}%"
    channels: [in_app, email]
    cooldown: 30m
    
  - name: pending_escalations
    condition: pending_escalation_count > 10
    severity: warning
    message: "{{.value}} escalations pending resolution"
    channels: [in_app]
    cooldown: 1h
```

---

## 14.7 Acceptance Criteria

### Dashboard Display
- [ ] All summary cards display correct metrics with trends
- [ ] Success rate chart shows target line at 98%
- [ ] Failure distribution updates in real-time
- [ ] Model performance table is sortable

### Real-Time Updates
- [ ] WebSocket connection established on page load
- [ ] New events appear without page refresh
- [ ] Status changes update immediately
- [ ] Reconnection on connection loss

### Filtering & Time Ranges
- [ ] All preset time ranges work correctly
- [ ] Custom date range picker functions
- [ ] Project filter narrows results
- [ ] Filters persist across page refresh

### Performance
- [ ] Dashboard loads in < 2 seconds
- [ ] Charts render smoothly with 1000+ data points
- [ ] Real-time updates don't cause lag

---

## 14.8 Related Specifications

- [Resilient Execution System](./12-resilient-execution-system.md) — Data source
- [Escalation Notifications](./13-escalation-notifications.md) — Event integration
- [LLM Live Logging](./06-llm-live-logging.md) — Stream handling patterns
