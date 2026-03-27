import { useState, useEffect, useMemo } from "react";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { RefreshCw, Route, Filter, Search, ChevronDown } from "lucide-react";
import { useSites } from "@/hooks/useSites";
import { useRemoteDebugRoutes } from "@/hooks/useRemoteDebugRoutes";
import { useQueryClient } from "@tanstack/react-query";

const methodColors: Record<string, string> = {
  GET: "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20",
  POST: "bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/20",
  PUT: "bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/20",
  PATCH: "bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/20",
  DELETE: "bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/20",
};

const categoryColors: Record<string, string> = {
  core: "bg-primary/15 text-primary border-primary/20",
  snapshot: "bg-violet-500/15 text-violet-600 dark:text-violet-400 border-violet-500/20",
  agent: "bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border-cyan-500/20",
  plugin: "bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20",
  user: "bg-pink-500/15 text-pink-600 dark:text-pink-400 border-pink-500/20",
  log: "bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/20",
  machine: "bg-slate-500/15 text-slate-600 dark:text-slate-400 border-slate-500/20",
  debug: "bg-yellow-500/15 text-yellow-600 dark:text-yellow-400 border-yellow-500/20",
  utility: "bg-gray-500/15 text-gray-600 dark:text-gray-400 border-gray-500/20",
  site_settings: "bg-teal-500/15 text-teal-600 dark:text-teal-400 border-teal-500/20",
  cloud_storage: "bg-sky-500/15 text-sky-600 dark:text-sky-400 border-sky-500/20",
  post: "bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/20",
  lifecycle: "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20",
  other: "bg-muted text-muted-foreground border-border",
};

export function DebugRoutesPanel() {
  const { data: sites } = useSites();
  const [selectedSiteId, setSelectedSiteId] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [categoryFilter, setCategoryFilter] = useState<string>("all");
  const [isOpen, setIsOpen] = useState(false);
  const queryClient = useQueryClient();

  const { data, isLoading, isFetching } = useRemoteDebugRoutes(selectedSiteId);

  // Auto-select first site
  useEffect(() => {
    if (sites && sites.length > 0 && selectedSiteId === null) {
      setSelectedSiteId(sites[0].id);
    }
  }, [sites, selectedSiteId]);

  const categories = data?.categories ? Object.keys(data.categories).sort() : [];
  const filteredRoutes = useMemo(() => {
    if (!data?.routes) return [];
    const q = searchQuery.toLowerCase().trim();
    return data.routes.filter((r) => {
      const matchesCategory = categoryFilter === "all" || r.category === categoryFilter;
      const matchesSearch = !q || r.path.toLowerCase().includes(q) || r.methods.some((m) => m.toLowerCase().includes(q));
      return matchesCategory && matchesSearch;
    });
  }, [data?.routes, categoryFilter, searchQuery]);

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Route className="h-5 w-5 text-primary" />
            <CardTitle className="text-base">Debug Routes</CardTitle>
            {data && (
              <Badge variant="secondary" className="text-xs">
                {data.totalRoutes} routes
              </Badge>
            )}
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <Select
              value={selectedSiteId ? String(selectedSiteId) : ""}
              onValueChange={(v) => setSelectedSiteId(Number(v))}
            >
              <SelectTrigger className="w-[180px] h-8 text-xs">
                <SelectValue placeholder="Select site" />
              </SelectTrigger>
              <SelectContent>
                {sites?.map((site) => (
                  <SelectItem key={site.id} value={String(site.id)}>
                    {site.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            {categories.length > 0 && (
              <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                <SelectTrigger className="w-[140px] h-8 text-xs">
                  <Filter className="h-3 w-3 mr-1" />
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All categories</SelectItem>
                  {categories.map((cat) => (
                    <SelectItem key={cat} value={cat}>
                      {cat} ({data?.categories[cat]})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}

            <Button
              variant="outline"
              size="sm"
              className="h-8"
              disabled={!selectedSiteId || isFetching}
              onClick={() =>
                queryClient.invalidateQueries({
                  queryKey: ["debug-routes", selectedSiteId],
                })
              }
            >
              <RefreshCw
                className={`h-3.5 w-3.5 ${isFetching ? "animate-spin" : ""}`}
              />
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {!selectedSiteId ? (
          <p className="text-sm text-muted-foreground text-center py-8">
            Select a site to view registered REST API routes
          </p>
        ) : isLoading ? (
          <div className="space-y-2">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-8 w-full" />
            ))}
          </div>
        ) : !data ? (
          <p className="text-sm text-muted-foreground text-center py-8">
            No route data available — the plugin may not support /debug/routes
          </p>
        ) : (
          <>
            {/* Search input */}
            <div className="relative mb-3">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
              <Input
                placeholder="Search routes… e.g. /snapshots, POST"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-8 h-8 text-xs"
              />
            </div>
            {/* Category summary chips */}
            <div className="flex flex-wrap gap-1.5 mb-4">
              {Object.entries(data.categories)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([cat, count]) => (
                  <button
                    key={cat}
                    onClick={() =>
                      setCategoryFilter(categoryFilter === cat ? "all" : cat)
                    }
                    className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium border transition-opacity cursor-pointer ${
                      categoryColors[cat] ?? categoryColors.other
                    } ${
                      categoryFilter !== "all" && categoryFilter !== cat
                        ? "opacity-40"
                        : ""
                    }`}
                  >
                    {cat}
                    <span className="opacity-60">({count})</span>
                  </button>
                ))}
            </div>

            {/* Routes table */}
            <div className="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="text-xs w-[100px]">Category</TableHead>
                    <TableHead className="text-xs">Path</TableHead>
                    <TableHead className="text-xs w-[160px]">Methods</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredRoutes.map((route, i) => (
                    <TableRow key={i}>
                      <TableCell className="py-1.5">
                        <span
                          className={`inline-block rounded-full px-2 py-0.5 text-[10px] font-medium border ${
                            categoryColors[route.category] ??
                            categoryColors.other
                          }`}
                        >
                          {route.category}
                        </span>
                      </TableCell>
                      <TableCell className="py-1.5 font-mono text-xs">
                        {route.path}
                      </TableCell>
                      <TableCell className="py-1.5">
                        <div className="flex gap-1 flex-wrap">
                          {route.methods.map((m) => (
                            <span
                              key={m}
                              className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold border ${
                                methodColors[m] ?? "bg-muted text-muted-foreground"
                              }`}
                            >
                              {m}
                            </span>
                          ))}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                  {filteredRoutes.length === 0 && (
                    <TableRow>
                      <TableCell
                        colSpan={3}
                        className="text-center text-sm text-muted-foreground py-6"
                      >
                        No routes in this category
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>

            {/* Footer info */}
            <div className="flex items-center justify-between mt-3 text-[11px] text-muted-foreground">
              <span>Namespace: {data.namespace}</span>
              <span>Plugin v{data.version}</span>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
}
