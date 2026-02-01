import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useSites } from "@/hooks/useSites";
import { usePlugins } from "@/hooks/usePlugins";
import { useErrors } from "@/hooks/useErrors";
import { Globe, Package, AlertCircle, RefreshCw } from "lucide-react";

export default function Dashboard() {
  const { data: sites } = useSites();
  const { data: plugins } = usePlugins();
  const { data: errors } = useErrors(10);

  const connectedSites = sites?.filter((s) => s.connectionStatus === "connected").length ?? 0;
  const watchingPlugins = plugins?.filter((p) => p.watchEnabled).length ?? 0;
  const pendingChanges = plugins?.reduce((acc, p) => acc + (p.modifiedCount || 0), 0) ?? 0;
  const recentErrors = errors?.length ?? 0;

  const stats = [
    {
      title: "Connected Sites",
      value: connectedSites,
      total: sites?.length ?? 0,
      icon: Globe,
      color: "text-green-500",
    },
    {
      title: "Watching Plugins",
      value: watchingPlugins,
      total: plugins?.length ?? 0,
      icon: Package,
      color: "text-blue-500",
    },
    {
      title: "Pending Changes",
      value: pendingChanges,
      icon: RefreshCw,
      color: pendingChanges > 0 ? "text-yellow-500" : "text-muted-foreground",
    },
    {
      title: "Recent Errors",
      value: recentErrors,
      icon: AlertCircle,
      color: recentErrors > 0 ? "text-red-500" : "text-muted-foreground",
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Dashboard</h1>
        <p className="text-muted-foreground">
          Overview of your WordPress plugin development workflow
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.title}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                {stat.title}
              </CardTitle>
              <stat.icon className={`h-4 w-4 ${stat.color}`} />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {stat.value}
                {stat.total !== undefined && (
                  <span className="text-sm font-normal text-muted-foreground">
                    /{stat.total}
                  </span>
                )}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Quick Actions</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <p className="text-sm text-muted-foreground">
              Use the sidebar to navigate to Sites, Plugins, or Sync pages to manage your workflow.
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">
              No recent activity. Start by adding a WordPress site and registering a plugin.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
