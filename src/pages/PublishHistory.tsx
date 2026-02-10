import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, PublishHistoryEntry } from "@/lib/api";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Trash2, Search, BarChart3, Clock, CheckCircle2, XCircle, AlertTriangle, ExternalLink, RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { formatDistanceToNow } from "date-fns";
import { EnvelopePagination } from "@/components/shared/EnvelopePagination";
import { formatActionLabel, getActionBadgeClasses, getPluginBadgeClasses } from "@/lib/publishHistoryUtils";

export default function PublishHistory() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<string>("all");
  const [page, setPage] = useState(0);
  const limit = 25;

  const { data, isLoading } = useQuery({
    queryKey: ["publish-history", page, statusFilter, search],
    queryFn: () => api.getPublishHistory({
      limit,
      offset: page * limit,
      status: statusFilter === "all" ? undefined : statusFilter,
      search: search || undefined,
    }),
  });

  const { data: stats } = useQuery({
    queryKey: ["publish-history-stats"],
    queryFn: () => api.getPublishHistoryStats(),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.deletePublishHistoryEntry(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["publish-history"] });
      queryClient.invalidateQueries({ queryKey: ["publish-history-stats"] });
      toast.success("Entry deleted");
    },
  });

  const clearMutation = useMutation({
    mutationFn: () => api.clearPublishHistory(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["publish-history"] });
      queryClient.invalidateQueries({ queryKey: ["publish-history-stats"] });
      toast.success("History cleared");
    },
  });

  const entries = data?.data?.entries ?? [];
  const total = data?.data?.total ?? 0;
  const totalPages = Math.ceil(total / limit);

  const statusIcon = (status: string) => {
    switch (status) {
      case "success": return <CheckCircle2 className="h-4 w-4 text-green-500" />;
      case "failed": return <XCircle className="h-4 w-4 text-destructive" />;
      case "partial": return <AlertTriangle className="h-4 w-4 text-amber-500" />;
      default: return <Clock className="h-4 w-4 text-muted-foreground" />;
    }
  };

  const s = stats?.data;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold">Publish History</h1>
            {entries.length > 0 && entries[0]?.version && (
              <Badge variant="secondary" className="font-mono text-xs px-2 py-0.5">
                v{entries[0].version}
              </Badge>
            )}
          </div>
          <p className="text-muted-foreground text-sm">Audit log of all publish operations</p>
        </div>
        <Button variant="destructive" size="sm" onClick={() => clearMutation.mutate()} disabled={total === 0}>
          Clear All
        </Button>
      </div>

      {/* Stats Cards */}
      {s && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Card>
            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-muted-foreground">Total</CardTitle></CardHeader>
            <CardContent><div className="text-2xl font-bold">{s.totalPublishes}</div></CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-muted-foreground">Success</CardTitle></CardHeader>
            <CardContent><div className="text-2xl font-bold">{s.successCount}</div></CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-muted-foreground">Failed</CardTitle></CardHeader>
            <CardContent><div className="text-2xl font-bold">{s.failureCount}</div></CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-muted-foreground">Avg Duration</CardTitle></CardHeader>
            <CardContent><div className="text-2xl font-bold">{(s.avgDurationMs / 1000).toFixed(1)}s</div></CardContent>
          </Card>
        </div>
      )}

      {/* Filters */}
      <div className="flex gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input placeholder="Search plugins, sites, errors..." className="pl-9" value={search} onChange={(e) => { setSearch(e.target.value); setPage(0); }} />
        </div>
        <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setPage(0); }}>
          <SelectTrigger className="w-[140px]"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Status</SelectItem>
            <SelectItem value="success">Success</SelectItem>
            <SelectItem value="failed">Failed</SelectItem>
            <SelectItem value="partial">Partial</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Table */}
      <Card>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Status</TableHead>
              <TableHead>Action</TableHead>
              <TableHead>Plugin / Target</TableHead>
              <TableHead>Version</TableHead>
              <TableHead className="w-6 px-0"></TableHead>
              <TableHead>New Ver.</TableHead>
              <TableHead>Site</TableHead>
              <TableHead>Files</TableHead>
              <TableHead>Duration</TableHead>
              <TableHead>Machine</TableHead>
              <TableHead>Rollback</TableHead>
              <TableHead>When</TableHead>
              <TableHead className="w-10"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={13} className="text-center py-8 text-muted-foreground">Loading...</TableCell></TableRow>
            ) : entries.length === 0 ? (
              <TableRow><TableCell colSpan={13} className="text-center py-8 text-muted-foreground">No publish history yet</TableCell></TableRow>
            ) : entries.map((e: PublishHistoryEntry) => {
              const actionLabel = formatActionLabel(e.actionType || e.mode);
              const actionClasses = getActionBadgeClasses(e.actionType || e.mode);
              const pluginClasses = getPluginBadgeClasses();

              return (
                <TableRow key={e.id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      {statusIcon(e.status)}
                      <Badge variant={e.status === "success" ? "default" : e.status === "failed" ? "destructive" : "secondary"} className="text-xs">
                        {e.status}
                      </Badge>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1.5">
                      {e.isSelfUpdate && (
                        <RefreshCw className="h-3.5 w-3.5 text-amber-500 shrink-0" />
                      )}
                      <Badge className={actionClasses}>
                        {actionLabel}
                      </Badge>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline" className={pluginClasses}>
                      {e.pluginName}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {e.version ? (
                      <Badge variant="secondary" className="text-[10px] px-1.5 py-0 font-mono">
                        v{e.version}
                      </Badge>
                    ) : (
                      <span className="text-muted-foreground text-xs">—</span>
                    )}
                  </TableCell>
                  <TableCell className="text-center px-0">
                    {e.newVersion && (
                      <span className="text-muted-foreground text-xs font-semibold">→</span>
                    )}
                  </TableCell>
                  <TableCell>
                    {e.newVersion ? (
                      <Badge className="bg-emerald-500/15 text-emerald-700 border border-emerald-500/30 font-mono text-[10px] px-1.5 py-0 dark:text-emerald-400">
                        v{e.newVersion}
                      </Badge>
                    ) : (
                      <span className="text-muted-foreground text-xs">—</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      <span className="text-sm">{e.siteName}</span>
                      {e.siteUrl && (
                        <a href={e.siteUrl} target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-foreground">
                          <ExternalLink className="h-3 w-3" />
                        </a>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>{e.filesUpdated}</TableCell>
                  <TableCell className="text-muted-foreground">{(e.durationMs / 1000).toFixed(1)}s</TableCell>
                  <TableCell className="text-xs text-muted-foreground">
                    {e.machineName && (
                      <Badge variant="outline" className="font-mono text-[10px] px-1.5 py-0">
                        {e.machineName}
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    {e.rollbackStatus && e.rollbackStatus !== "" && (
                      <Badge variant={e.rollbackStatus === "success" ? "default" : e.rollbackStatus === "failed" ? "destructive" : "outline"} className="text-xs">
                        {e.rollbackStatus}
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-muted-foreground text-xs">
                    {formatDistanceToNow(new Date(e.createdAt), { addSuffix: true })}
                  </TableCell>
                  <TableCell>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => deleteMutation.mutate(e.id)}>
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </Card>

      {/* Pagination */}
      {data?.envelope?.attributes?.TotalPages ? (
        <EnvelopePagination
          meta={{ attributes: data.envelope.attributes, navigation: data.envelope.navigation }}
          onPageChange={(p) => setPage(p - 1)}
        />
      ) : totalPages > 1 && (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>Showing {page * limit + 1}–{Math.min((page + 1) * limit, total)} of {total}</span>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" disabled={page === 0} onClick={() => setPage(p => p - 1)}>Previous</Button>
            <Button variant="outline" size="sm" disabled={page >= totalPages - 1} onClick={() => setPage(p => p + 1)}>Next</Button>
          </div>
        </div>
      )}
    </div>
  );
}
