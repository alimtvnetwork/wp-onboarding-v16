import { useState, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { api, ActivityEntry, ActivityType } from "@/lib/api";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Activity,
  Search,
  Globe,
  Clock,
  Upload,
  Database,
  Package,
  Settings2,
  Plug,
  ChevronDown,
  ChevronUp,
  Loader2,
  Server,
} from "lucide-react";
import { formatDistanceToNow, parseISO, format } from "date-fns";
import { MOCK_ACTIVITY } from "@/lib/activityMockData";
import {
  getActivityTypeBadgeClasses,
  getActivityActionBadgeClasses,
  formatActivityAction,
  getMetadataEntries,
  ACTIVITY_TYPE_OPTIONS,
} from "@/lib/activityUtils";

const TYPE_ICONS: Record<ActivityType, React.ElementType> = {
  publish: Upload,
  snapshot: Database,
  plugin: Package,
  config: Settings2,
  connection: Plug,
};

export default function ActivityFeed() {
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState<ActivityType | "all">("all");
  const [siteFilter, setSiteFilter] = useState<string>("all");
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [page, setPage] = useState(0);
  const limit = 25;

  // TODO: Replace mock with real API once E2.1 Go endpoint is built
  // const { data, isLoading } = useQuery({
  //   queryKey: ["activity-feed", page, typeFilter, siteFilter, search],
  //   queryFn: () => api.getActivityFeed({ limit, offset: page * limit, type: typeFilter === "all" ? undefined : typeFilter, search: search || undefined }),
  // });

  const isLoading = false;
  const allEntries = MOCK_ACTIVITY;

  // Client-side filtering (will be server-side once E2.1 is live)
  const filtered = useMemo(() => {
    let result = allEntries;
    if (typeFilter !== "all") result = result.filter((e) => e.type === typeFilter);
    if (siteFilter !== "all") result = result.filter((e) => String(e.siteId) === siteFilter);
    if (search) {
      const q = search.toLowerCase();
      result = result.filter((e) => e.title.toLowerCase().includes(q) || e.action.toLowerCase().includes(q));
    }
    return result;
  }, [allEntries, typeFilter, siteFilter, search]);

  const paged = filtered.slice(page * limit, (page + 1) * limit);
  const totalPages = Math.ceil(filtered.length / limit);

  // Unique sites for site filter
  const siteOptions = useMemo(() => {
    const sites = new Map<number, string>();
    allEntries.forEach((e) => sites.set(e.siteId, e.siteName));
    return Array.from(sites, ([id, name]) => ({ value: String(id), label: name }));
  }, [allEntries]);

  // Summary stats
  const stats = useMemo(() => {
    const today = new Date().toDateString();
    const todayCount = allEntries.filter((e) => new Date(e.timestamp).toDateString() === today).length;
    const typeBreakdown: Partial<Record<ActivityType, number>> = {};
    allEntries.forEach((e) => { typeBreakdown[e.type] = (typeBreakdown[e.type] || 0) + 1; });
    const mostActiveType = Object.entries(typeBreakdown).sort(([, a], [, b]) => b - a)[0];
    return { todayCount, total: allEntries.length, mostActiveType };
  }, [allEntries]);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-primary/10">
            <Activity className="h-6 w-6 text-primary" />
          </div>
          <div>
            <h1 className="text-2xl font-bold">Activity Feed</h1>
            <p className="text-sm text-muted-foreground">Fleet-wide audit log of all actions across sites</p>
          </div>
        </div>
        <Badge variant="outline" className="text-xs font-mono">
          Mock Data
        </Badge>
      </div>

      {/* Stats bar */}
      <div className="grid grid-cols-3 gap-4">
        <Card>
          <CardContent className="p-4 flex items-center gap-3">
            <div className="p-2 rounded-lg bg-teal-500/10">
              <Clock className="h-4 w-4 text-teal-600 dark:text-teal-400" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.todayCount}</p>
              <p className="text-xs text-muted-foreground">Actions Today</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4 flex items-center gap-3">
            <div className="p-2 rounded-lg bg-primary/10">
              <Activity className="h-4 w-4 text-primary" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats.total}</p>
              <p className="text-xs text-muted-foreground">Total Events</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4 flex items-center gap-3">
            <div className="p-2 rounded-lg bg-indigo-500/10">
              <Globe className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
              <p className="text-2xl font-bold">{siteOptions.length}</p>
              <p className="text-xs text-muted-foreground">Active Sites</p>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-wrap gap-3">
            <div className="relative flex-1 min-w-[200px]">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search activity..."
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(0); }}
                className="pl-9"
              />
            </div>
            <Select value={typeFilter} onValueChange={(v) => { setTypeFilter(v as ActivityType | "all"); setPage(0); }}>
              <SelectTrigger className="w-[150px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {ACTIVITY_TYPE_OPTIONS.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={siteFilter} onValueChange={(v) => { setSiteFilter(v); setPage(0); }}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="All Sites" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Sites</SelectItem>
                {siteOptions.map((s) => (
                  <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Activity timeline */}
      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base flex items-center justify-between">
            <span>Timeline ({filtered.length} events)</span>
            {totalPages > 1 && (
              <span className="text-xs font-normal text-muted-foreground">
                Page {page + 1} of {totalPages}
              </span>
            )}
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : paged.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
              <Activity className="h-8 w-8 mb-2 opacity-50" />
              <p className="text-sm">No activity found</p>
            </div>
          ) : (
            <div className="divide-y">
              {paged.map((entry) => {
                const Icon = TYPE_ICONS[entry.type] || Activity;
                const isExpanded = expandedId === entry.id;
                return (
                  <div key={entry.id} className="group">
                    <button
                      className="w-full flex items-start gap-3 px-4 py-3 text-left hover:bg-muted/50 transition-colors"
                      onClick={() => setExpandedId(isExpanded ? null : entry.id)}
                    >
                      {/* Icon */}
                      <div className={`mt-0.5 p-1.5 rounded-md shrink-0 ${getActivityTypeBadgeClasses(entry.type)}`}>
                        <Icon className="h-3.5 w-3.5" />
                      </div>

                      {/* Content */}
                      <div className="flex-1 min-w-0 space-y-1">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider border ${getActivityActionBadgeClasses(entry.type, entry.action)}`}>
                            {formatActivityAction(entry.action)}
                          </span>
                          <span className="text-sm font-medium truncate">{entry.title}</span>
                        </div>
                        <div className="flex items-center gap-3 text-xs text-muted-foreground">
                          <span className="inline-flex items-center gap-1">
                            <Globe className="h-3 w-3" />
                            {entry.siteName}
                          </span>
                          <span className="inline-flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {formatDistanceToNow(parseISO(entry.timestamp), { addSuffix: true })}
                          </span>
                          {entry.machineName && (
                            <span className="inline-flex items-center gap-1">
                              <Server className="h-3 w-3" />
                              <code className="font-mono text-[10px]">{entry.machineName}</code>
                            </span>
                          )}
                          <Badge variant="outline" className="text-[10px] font-mono px-1 py-0">
                            {entry.source}
                          </Badge>
                        </div>
                      </div>

                      {/* Expand indicator */}
                      {isExpanded ? (
                        <ChevronUp className="h-4 w-4 text-muted-foreground shrink-0 mt-1" />
                      ) : (
                        <ChevronDown className="h-4 w-4 text-muted-foreground shrink-0 mt-1 opacity-0 group-hover:opacity-100 transition-opacity" />
                      )}
                    </button>

                    {/* Expanded metadata */}
                    {isExpanded && (
                      <div className="px-4 pb-3 pl-[52px]">
                        <div className="rounded-md bg-muted/50 border p-3 text-xs space-y-1.5">
                          <p className="text-muted-foreground font-medium mb-2">Details</p>
                          <p className="text-muted-foreground">
                            <span className="font-medium text-foreground">Timestamp:</span>{" "}
                            {format(parseISO(entry.timestamp), "PPpp")}
                          </p>
                          {entry.version && (
                            <p className="text-muted-foreground">
                              <span className="font-medium text-foreground">Version:</span> {entry.version}
                            </p>
                          )}
                          {getMetadataEntries(entry.metadata).map(([key, val]) => (
                            <p key={key} className="text-muted-foreground">
                              <span className="font-medium text-foreground">{key}:</span>{" "}
                              {typeof val === "object" ? JSON.stringify(val) : String(val)}
                            </p>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t">
              <Button variant="outline" size="sm" disabled={page === 0} onClick={() => setPage(page - 1)}>
                Previous
              </Button>
              <span className="text-xs text-muted-foreground">
                {page * limit + 1}–{Math.min((page + 1) * limit, filtered.length)} of {filtered.length}
              </span>
              <Button variant="outline" size="sm" disabled={page >= totalPages - 1} onClick={() => setPage(page + 1)}>
                Next
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
