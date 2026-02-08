import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Clock, Globe, Package } from "lucide-react";

interface ActivityItem {
  type: "site" | "plugin";
  name: string;
  status: string;
  time: string | null;
}

interface RecentActivityProps {
  items: ActivityItem[];
}

export function RecentActivity({ items }: RecentActivityProps) {
  return (
    <Card>
      <CardHeader className="p-4 sm:p-6 pb-2 sm:pb-4">
        <CardTitle className="text-base sm:text-lg flex items-center gap-2">
          <Clock className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
          Recent Activity
        </CardTitle>
      </CardHeader>
      <CardContent className="p-4 sm:p-6 pt-0">
        {items.length > 0 ? (
          <div className="space-y-2 sm:space-y-3">
            {items.map((activity, idx) => (
              <div
                key={`${activity.type}-${activity.name}-${idx}`}
                className="flex items-center gap-2 sm:gap-3 p-2 rounded-lg border-l-2 border-l-transparent transition-colors hover:bg-secondary/50 hover:border-l-primary/60"
              >
                {activity.type === "site" ? (
                  <Globe className="h-4 w-4 text-primary shrink-0" />
                ) : (
                  <Package className="h-4 w-4 text-accent-foreground shrink-0" />
                )}
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{activity.name}</p>
                  <p className="text-xs text-muted-foreground capitalize">{activity.status}</p>
                </div>
                {activity.time && (
                  <span className="text-xs text-muted-foreground shrink-0">
                    {new Date(activity.time).toLocaleDateString()}
                  </span>
                )}
              </div>
            ))}
          </div>
        ) : (
          <p className="text-xs sm:text-sm text-muted-foreground">
            No recent activity. Start by adding a WordPress site.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
