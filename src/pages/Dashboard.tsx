import { useState } from "react";
import { Link } from "react-router-dom";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useSites } from "@/hooks/useSites";
import { usePlugins } from "@/hooks/usePlugins";
import { useErrors } from "@/hooks/useErrors";
import { AddSiteDialog } from "@/components/sites/AddSiteDialog";
import { 
  Globe, 
  Package, 
  AlertCircle, 
  RefreshCw, 
  Plus,
  FolderPlus,
  Clock,
  ArrowRight,
  Settings,
  FileCode,
  Activity
} from "lucide-react";

export default function Dashboard() {
  const { data: sites, refetch: refetchSites } = useSites();
  const { data: plugins, refetch: refetchPlugins } = usePlugins();
  const { data: errors } = useErrors(10);
  const [showAddSite, setShowAddSite] = useState(false);

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
      color: "text-primary",
      href: "/sites",
    },
    {
      title: "Watching Plugins",
      value: watchingPlugins,
      total: plugins?.length ?? 0,
      icon: Package,
      color: "text-accent-foreground",
      href: "/plugins",
    },
    {
      title: "Pending Changes",
      value: pendingChanges,
      icon: RefreshCw,
      color: pendingChanges > 0 ? "text-warning" : "text-muted-foreground",
      href: "/plugins",
    },
    {
      title: "Recent Errors",
      value: recentErrors,
      icon: AlertCircle,
      color: recentErrors > 0 ? "text-destructive" : "text-muted-foreground",
      href: "/errors",
    },
  ];

  const quickActions = [
    {
      title: "Add WordPress Site",
      description: "Connect a new WordPress site for plugin deployment",
      icon: Plus,
      action: () => setShowAddSite(true),
    },
    {
      title: "Register Plugin",
      description: "Add a local plugin directory to watch for changes",
      icon: FolderPlus,
      href: "/plugins",
    },
    {
      title: "View Logs",
      description: "Check application logs and debug issues",
      icon: FileCode,
      href: "/logs",
    },
    {
      title: "Settings",
      description: "Configure application preferences",
      icon: Settings,
      href: "/settings",
    },
  ];

  // Get recent activity from sites and plugins
  const recentActivity = [
    ...(sites?.slice(0, 3).map(s => ({
      type: "site" as const,
      name: s.name,
      status: s.connectionStatus,
      time: s.lastTestedAt || s.createdAt,
    })) ?? []),
    ...(plugins?.slice(0, 3).map(p => ({
      type: "plugin" as const,
      name: p.name,
      status: p.watchEnabled ? "watching" : "inactive",
      time: p.lastScannedAt || p.createdAt,
    })) ?? []),
  ]
    .sort((a, b) => new Date(b.time || 0).getTime() - new Date(a.time || 0).getTime())
    .slice(0, 5);

  return (
    <div className="space-y-4 sm:space-y-6">
      <div>
        <h1 className="text-xl sm:text-2xl font-bold">Dashboard</h1>
        <p className="text-sm text-muted-foreground">
          Overview of your WordPress plugin development workflow
        </p>
      </div>

      {/* Stats Grid - responsive columns */}
      <div className="grid gap-3 sm:gap-4 grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Link to={stat.href} key={stat.title}>
            <Card className="hover:bg-accent/50 transition-colors cursor-pointer h-full">
              <CardHeader className="flex flex-row items-center justify-between pb-1 sm:pb-2 p-3 sm:p-4">
                <CardTitle className="text-xs sm:text-sm font-medium text-muted-foreground truncate pr-2">
                  {stat.title}
                </CardTitle>
                <stat.icon className={`h-3.5 w-3.5 sm:h-4 sm:w-4 ${stat.color} shrink-0`} />
              </CardHeader>
              <CardContent className="p-3 sm:p-4 pt-0">
                <div className="text-xl sm:text-2xl font-bold">
                  {stat.value}
                  {stat.total !== undefined && (
                    <span className="text-xs sm:text-sm font-normal text-muted-foreground">
                      /{stat.total}
                    </span>
                  )}
                </div>
              </CardContent>
            </Card>
          </Link>
        ))}
      </div>

      <div className="grid gap-4 sm:gap-6 md:grid-cols-2">
        {/* Quick Actions */}
        <Card>
          <CardHeader className="p-4 sm:p-6 pb-2 sm:pb-4">
            <CardTitle className="text-base sm:text-lg flex items-center gap-2">
              <Activity className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
              Quick Actions
            </CardTitle>
          </CardHeader>
          <CardContent className="grid gap-2 p-4 sm:p-6 pt-0">
            {quickActions.map((action) => (
              action.href ? (
                <Link to={action.href} key={action.title}>
                  <Button 
                    variant="outline" 
                    className="w-full justify-start h-auto py-2.5 sm:py-3 hover:bg-accent"
                  >
                    <action.icon className="h-4 w-4 mr-2 sm:mr-3 text-primary shrink-0" />
                    <div className="text-left min-w-0 flex-1">
                      <p className="font-medium text-sm">{action.title}</p>
                      <p className="text-xs text-muted-foreground truncate">{action.description}</p>
                    </div>
                    <ArrowRight className="h-4 w-4 ml-auto text-muted-foreground shrink-0 hidden sm:block" />
                  </Button>
                </Link>
              ) : (
                <Button 
                  key={action.title}
                  variant="outline" 
                  className="w-full justify-start h-auto py-2.5 sm:py-3 hover:bg-accent"
                  onClick={action.action}
                >
                  <action.icon className="h-4 w-4 mr-2 sm:mr-3 text-primary shrink-0" />
                  <div className="text-left min-w-0 flex-1">
                    <p className="font-medium text-sm">{action.title}</p>
                    <p className="text-xs text-muted-foreground truncate">{action.description}</p>
                  </div>
                  <ArrowRight className="h-4 w-4 ml-auto text-muted-foreground shrink-0 hidden sm:block" />
                </Button>
              )
            ))}
          </CardContent>
        </Card>

        {/* Recent Activity */}
        <Card>
          <CardHeader className="p-4 sm:p-6 pb-2 sm:pb-4">
            <CardTitle className="text-base sm:text-lg flex items-center gap-2">
              <Clock className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
              Recent Activity
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 sm:p-6 pt-0">
            {recentActivity.length > 0 ? (
              <div className="space-y-2 sm:space-y-3">
                {recentActivity.map((activity, idx) => (
                  <div 
                    key={`${activity.type}-${activity.name}-${idx}`}
                    className="flex items-center gap-2 sm:gap-3 p-2 rounded-lg hover:bg-accent/50"
                  >
                    {activity.type === "site" ? (
                      <Globe className="h-4 w-4 text-primary shrink-0" />
                    ) : (
                      <Package className="h-4 w-4 text-accent-foreground shrink-0" />
                    )}
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{activity.name}</p>
                      <p className="text-xs text-muted-foreground capitalize">
                        {activity.status}
                      </p>
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
      </div>

      {/* Add Site Dialog */}
      <AddSiteDialog
        open={showAddSite}
        onOpenChange={(open) => {
          if (!open) {
            refetchSites();
          }
          setShowAddSite(open);
        }}
      />
    </div>
  );
}
