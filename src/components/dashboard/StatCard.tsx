import { Link } from "react-router-dom";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { LucideIcon } from "lucide-react";

interface StatCardProps {
  title: string;
  value: number;
  total?: number;
  icon: LucideIcon;
  colorClass: string;
  href: string;
}

export function StatCard({ title, value, total, icon: Icon, colorClass, href }: StatCardProps) {
  return (
    <Link to={href}>
      <Card className="hover:bg-accent/50 transition-colors cursor-pointer h-full">
        <CardHeader className="flex flex-row items-center justify-between pb-1 sm:pb-2 p-3 sm:p-4">
          <CardTitle className="text-xs sm:text-sm font-medium text-muted-foreground truncate pr-2">
            {title}
          </CardTitle>
          <Icon className={`h-3.5 w-3.5 sm:h-4 sm:w-4 ${colorClass} shrink-0`} />
        </CardHeader>
        <CardContent className="p-3 sm:p-4 pt-0">
          <div className="text-xl sm:text-2xl font-bold">
            {value}
            {total !== undefined && (
              <span className="text-xs sm:text-sm font-normal text-muted-foreground">
                /{total}
              </span>
            )}
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}
