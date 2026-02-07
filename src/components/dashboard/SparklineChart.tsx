import { Area, AreaChart, ResponsiveContainer } from "recharts";

export interface SparklinePoint {
  value: number;
}

interface SparklineChartProps {
  data: SparklinePoint[];
  color?: string;
  height?: number;
}

/**
 * Tiny area chart for embedding inside stat cards.
 * No axes, no labels — just the trend shape.
 */
export function SparklineChart({ data, color = "hsl(var(--primary))", height = 32 }: SparklineChartProps) {
  if (!data || data.length < 2) return null;

  return (
    <ResponsiveContainer width="100%" height={height}>
      <AreaChart data={data} margin={{ top: 0, right: 0, bottom: 0, left: 0 }}>
        <defs>
          <linearGradient id={`spark-${color.replace(/[^a-z0-9]/gi, "")}`} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor={color} stopOpacity={0.3} />
            <stop offset="100%" stopColor={color} stopOpacity={0.05} />
          </linearGradient>
        </defs>
        <Area
          type="monotone"
          dataKey="value"
          stroke={color}
          strokeWidth={1.5}
          fill={`url(#spark-${color.replace(/[^a-z0-9]/gi, "")})`}
          isAnimationActive={false}
        />
      </AreaChart>
    </ResponsiveContainer>
  );
}
