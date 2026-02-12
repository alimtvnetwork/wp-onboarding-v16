import { useState } from "react";
import type { DashboardStats } from "@/hooks/useDashboardStats";
import { useDashboardStats } from "@/hooks/useDashboardStats";
import { AddSiteDialog } from "@/components/sites/AddSiteDialog";
import { StatCard } from "@/components/dashboard/StatCard";
import { QuickActions } from "@/components/dashboard/QuickActions";
import { RecentActivity } from "@/components/dashboard/RecentActivity";
import { RecentPublishes } from "@/components/dashboard/RecentPublishes";
import { Globe, Package, RefreshCw, AlertCircle, Rocket } from "lucide-react";
import { useQueryClient } from "@tanstack/react-query";

export default function Dashboard() {
  const { data: stats } = useDashboardStats();
  const [showAddSite, setShowAddSite] = useState(false);
  const queryClient = useQueryClient();

  const pendingChanges = stats?.plugins.pendingChanges ?? 0;
  const recentErrors = stats?.errors.recent ?? 0;

  const statCards = [
    {
      title: "Connected Sites",
      value: stats?.sites.connected ?? 0,
      total: stats?.sites.total ?? 0,
      icon: Globe,
      colorClass: "text-primary",
      href: "/sites",
    },
    {
      title: "Watching Plugins",
      value: stats?.plugins.watching ?? 0,
      total: stats?.plugins.total ?? 0,
      icon: Package,
      colorClass: "text-accent-foreground",
      href: "/plugins",
    },
    {
      title: "Pending Changes",
      value: pendingChanges,
      icon: RefreshCw,
      colorClass: pendingChanges > 0 ? "text-warning" : "text-muted-foreground",
      href: "/plugins",
    },
    {
      title: "Recent Errors",
      value: recentErrors,
      icon: AlertCircle,
      colorClass: recentErrors > 0 ? "text-destructive" : "text-muted-foreground",
      href: "/errors",
      sparkline: stats?.trends.errors,
      sparklineColor: "hsl(var(--destructive))",
    },
    {
      title: "Total Publishes",
      value: stats?.publish?.totalPublishes ?? 0,
      icon: Rocket,
      colorClass: "text-primary",
      href: "/publish-history",
      sparkline: stats?.trends.publishes,
      sparklineColor: "hsl(var(--primary))",
    },
  ];

  // Build recent activity from sites + plugins data (fetched via stats hook)
  // Stats hook aggregates counts; for activity feed we still need the raw lists.
  // We piggyback on the cached queries populated by useDashboardStats.
  const sitesData = queryClient.getQueryData<DashboardStats>(["dashboard-stats"]);

  return (
    <div className="space-y-4 sm:space-y-6">
      <div>
        <h1 className="text-xl sm:text-2xl font-bold">Dashboard</h1>
        <p className="text-sm text-muted-foreground">
          Overview of your WordPress plugin development workflow
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid gap-3 sm:gap-4 grid-cols-2 lg:grid-cols-5">
        {statCards.map((s) => (
          <StatCard key={s.title} {...s} />
        ))}
      </div>

      {/* Main content grid */}
      <div className="grid gap-4 sm:gap-6 md:grid-cols-2">
        <QuickActions onAddSite={() => setShowAddSite(true)} />
        <RecentPublishes
          entries={stats?.recentPublishes ?? []}
          stats={stats?.publish ?? null}
        />
      </div>

      {/* Add Site Dialog */}
      <AddSiteDialog
        open={showAddSite}
        onOpenChange={(open) => {
          if (!open) queryClient.invalidateQueries({ queryKey: ["dashboard-stats"] });
          setShowAddSite(open);
        }}
      />
    </div>
  );
}
