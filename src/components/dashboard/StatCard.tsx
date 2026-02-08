import { Link } from "react-router-dom";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { SparklineChart, type SparklinePoint } from "./SparklineChart";
import type { LucideIcon } from "lucide-react";

interface StatCardProps {
  title: string;
  value: number;
  total?: number;
  icon: LucideIcon;
  colorClass: string;
  href: string;
  sparkline?: SparklinePoint[];
  sparklineColor?: string;
}

export function StatCard({ title, value, total, icon: Icon, colorClass, href, sparkline, sparklineColor }: StatCardProps) {
  return (
    <Link to={href}>
      <Card className="transition-colors border border-border hover:border-primary/40 hover:bg-secondary/50 cursor-pointer h-full">
        <CardHeader className="flex flex-row items-center justify-between pb-1 sm:pb-2 p-3 sm:p-4">
          <CardTitle className="text-xs sm:text-sm font-medium text-muted-foreground truncate pr-2">
            {title}
          </CardTitle>
          <Icon className={`h-3.5 w-3.5 sm:h-4 sm:w-4 ${colorClass} shrink-0`} />
        </CardHeader>
        <CardContent className="p-3 sm:p-4 pt-0 space-y-1">
          <div className="text-xl sm:text-2xl font-bold">
            {value}
            {total !== undefined && (
              <span className="text-xs sm:text-sm font-normal text-muted-foreground">
                /{total}
              </span>
            )}
          </div>
          {sparkline && sparkline.length >= 2 && (
            <SparklineChart data={sparkline} color={sparklineColor} height={28} />
          )}
        </CardContent>
      </Card>
    </Link>
  );
}
