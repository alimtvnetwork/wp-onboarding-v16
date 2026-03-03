import { useState, useMemo, useEffect, useCallback, useRef } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { format, isToday, isYesterday } from "date-fns";
import {
  Activity,
  Clock,
  Loader2,
  Trash2,
  RefreshCw,
  Search,
  AlertCircle,
  XCircle,
  Filter,
  Download,
  ArrowUpDown,
  FileJson,
  Eraser,
  Radio,
  Copy,
  Check,
} from "lucide-react";
import { api, RequestSessionRecord, RequestSessionListResponse, requireSuccess } from "@/lib/api";
import { toAbsoluteUrl } from "@/lib/endpoints";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";
import { cn } from "@/lib/utils";

type StatusFilter = "all" | "2xx" | "3xx" | "4xx" | "5xx";

function getStatusCategory(code: number): StatusFilter {
  if (code < 300) return "2xx";
  if (code < 400) return "3xx";
  if (code < 500) return "4xx";
  return "5xx";
}

function getStatusColor(code: number) {
  if (code < 300) return "text-emerald-600 dark:text-emerald-400";
  if (code < 400) return "text-blue-600 dark:text-blue-400";
  if (code < 500) return "text-amber-600 dark:text-amber-400";
  return "text-destructive";
}

function getStatusBg(code: number) {
  if (code < 300) return "bg-emerald-500/10 border-emerald-500/20";
  if (code < 400) return "bg-blue-500/10 border-blue-500/20";
  if (code < 500) return "bg-amber-500/10 border-amber-500/20";
  return "bg-destructive/10 border-destructive/20";
}

function getMethodBadge(method: string) {
  const colors: Record<string, string> = {
    GET: "bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20",
    POST: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20",
    PUT: "bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20",
    PATCH: "bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20",
    DELETE: "bg-destructive/10 text-destructive border-destructive/20",
  };
  return (
    <Badge variant="outline" className={cn("font-mono text-xs px-1.5", colors[method] || "")}>
      {method}
    </Badge>
  );
}

function useCopyAction() {
  const [copied, setCopied] = useState(false);
  const copy = useCallback((text: string) => {
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      toast.success("Copied to clipboard");
      setTimeout(() => setCopied(false), 2000);
    });
  }, []);
  return { copied, copy };
}

function formatUnknown(value: unknown): string {
  if (value === null || value === undefined) return "";
  if (typeof value === "string") {
    // Try to parse and re-format if it's a JSON string
    try {
      return JSON.stringify(JSON.parse(value), null, 2);
    } catch {
      return value;
    }
  }
  return JSON.stringify(value, null, 2);
}

function JsonViewer({ content, label }: { content: unknown; label: string }) {
  const formatted = formatUnknown(content);
  const { copied, copy } = useCopyAction();

  if (!formatted) return <p className="text-sm text-muted-foreground italic">No {label}</p>;

  // Simple syntax highlighting
  const highlighted = formatted
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"([^"]*)"(\s*:)/g, '<span class="text-sky-600 dark:text-sky-400">"$1"</span>$2')
    .replace(/:\s*"([^"]*)"/g, ': <span class="text-emerald-600 dark:text-emerald-400">"$1"</span>')
    .replace(/:\s*(true|false)/g, ': <span class="text-amber-600 dark:text-amber-400">$1</span>')
    .replace(/:\s*(\d+\.?\d*)/g, ': <span class="text-purple-600 dark:text-purple-400">$1</span>')
    .replace(/:\s*(null)/g, ': <span class="text-muted-foreground">$1</span>');

  const lines = formatted.split("\n");

  return (
    <div className="relative group">
      <button
        onClick={() => copy(formatted)}
        className="absolute top-2 right-2 z-10 p-1.5 rounded-md bg-muted/80 hover:bg-muted border border-border opacity-0 group-hover:opacity-100 transition-opacity"
        title="Copy"
      >
        {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5 text-muted-foreground" />}
      </button>
      <div className="flex text-xs font-mono bg-muted/50 rounded-md overflow-auto max-h-[500px] border border-border">
        {/* Line numbers */}
        <div className="py-3 px-2 text-right select-none border-r border-border bg-muted/30 sticky left-0">
          {lines.map((_, i) => (
            <div key={i} className="text-muted-foreground/50 leading-5">{i + 1}</div>
          ))}
        </div>
        {/* Code */}
        <pre
          className="py-3 px-3 whitespace-pre-wrap break-all leading-5 flex-1"
          dangerouslySetInnerHTML={{ __html: highlighted }}
        />
      </div>
    </div>
  );
}

/** Groups sessions by date then hour, returning ordered entries */
function groupByDateHour(sessions: RequestSessionRecord[]): Array<{ label: string; sessions: RequestSessionRecord[] }> {
  const groups = new Map<string, RequestSessionRecord[]>();

  for (const s of sessions) {
    const d = new Date(s.startedAt);
    const dateKey = format(d, "yyyy-MM-dd");
    const hourKey = format(d, "HH:00");
    const key = `${dateKey}|${hourKey}`;
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key)!.push(s);
  }

  return Array.from(groups.entries()).map(([key, items]) => {
    const [dateKey, hourKey] = key.split("|");
    const d = new Date(dateKey + "T00:00:00");
    let dateLabel: string;
    if (isToday(d)) dateLabel = "Today";
    else if (isYesterday(d)) dateLabel = "Yesterday";
    else dateLabel = format(d, "MMM d, yyyy");
    return { label: `${dateLabel} · ${hourKey}`, sessions: items };
  });
}

const LIVE_POLL_INTERVAL = 3000;

export default function RequestSessions() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detailTab, setDetailTab] = useState<string>("response");
  const [liveMode, setLiveMode] = useState(false);

  // Fetch sessions
  const { data: sessionsData, isLoading, refetch } = useQuery({
    queryKey: ["request-sessions"],
    queryFn: async () => {
      const response = await api.getRequestSessions({ limit: 200 });
      return requireSuccess(response, { endpoint: "/request-sessions" });
    },
    refetchInterval: liveMode ? LIVE_POLL_INTERVAL : false,
  });

  const sessions = (sessionsData as RequestSessionListResponse)?.sessions || [];

  // Fetch single session detail
  const { data: selectedSession } = useQuery({
    queryKey: ["request-session", selectedId],
    queryFn: async () => {
      if (!selectedId) return null;
      const response = await api.getRequestSession(selectedId);
      return requireSuccess(response, { endpoint: `/request-sessions/${selectedId}` });
    },
    enabled: !!selectedId,
  });

  // Delete mutation
  const deleteMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (id: string) => {
      const response = await api.deleteRequestSession(id);
      return requireSuccess(response, { endpoint: `/request-sessions/${id}`, method: "DELETE" });
    },
    onSuccess: (_, id) => {
      toast.success("Request session deleted");
      queryClient.invalidateQueries({ queryKey: ["request-sessions"] });
      if (selectedId === id) setSelectedId(null);
    },
    onError: () => toast.error("Failed to delete"),
  });

  // Clear all mutation
  const clearMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async () => {
      const response = await api.clearRequestSessions();
      return requireSuccess(response, { endpoint: "/request-sessions", method: "DELETE" });
    },
    onSuccess: () => {
      toast.success("All request sessions cleared");
      queryClient.invalidateQueries({ queryKey: ["request-sessions"] });
      setSelectedId(null);
    },
    onError: () => toast.error("Failed to clear sessions"),
  });

  // Filter & search
  const filteredSessions = useMemo(() => {
    return sessions.filter((s: RequestSessionRecord) => {
      if (statusFilter !== "all" && getStatusCategory(s.statusCode) !== statusFilter) return false;
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        return (
          s.path.toLowerCase().includes(q) ||
          s.method.toLowerCase().includes(q) ||
          s.id.toLowerCase().includes(q) ||
          (s.title && s.title.toLowerCase().includes(q)) ||
          (s.error && s.error.toLowerCase().includes(q))
        );
      }
      return true;
    });
  }, [sessions, statusFilter, searchQuery]);

  // Timeline groups
  const timelineGroups = useMemo(() => groupByDateHour(filteredSessions), [filteredSessions]);

  // Stats & histogram
  const { stats, histogram } = useMemo(() => {
    const total = sessions.length;
    const errors = sessions.filter((s: RequestSessionRecord) => s.statusCode >= 400).length;
    const durations = sessions.map((s: RequestSessionRecord) => s.durationMs);
    const avgDuration = total > 0
      ? Math.round(durations.reduce((sum, d) => sum + d, 0) / total)
      : 0;

    // Build histogram buckets (logarithmic-ish)
    const bucketEdges = [0, 10, 25, 50, 100, 250, 500, 1000, 2500, Infinity];
    const bucketLabels = ["<10", "10-25", "25-50", "50-100", "100-250", "250-500", "500-1s", "1-2.5s", ">2.5s"];
    const counts = new Array(bucketEdges.length - 1).fill(0);
    for (const d of durations) {
      for (let i = 0; i < bucketEdges.length - 1; i++) {
        if (d >= bucketEdges[i] && d < bucketEdges[i + 1]) {
          counts[i]++;
          break;
        }
      }
    }
    const maxCount = Math.max(...counts, 1);
    const histogram = counts.map((count, i) => ({
      label: bucketLabels[i],
      count,
      pct: count / maxCount,
    }));

    return { stats: { total, errors, avgDuration }, histogram };
  }, [sessions]);

  const detail = selectedSession as RequestSessionRecord | null;

  return (
    <div className="h-full flex flex-col">
      <div className="p-6 pb-4">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h1 className="text-2xl font-bold">Request Sessions</h1>
            <p className="text-muted-foreground">
              Per-API-call request logs with full request/response inspection
            </p>
          </div>
          <div className="flex items-center gap-2">
            {/* Live toggle */}
            <div className="flex items-center gap-2 mr-2">
              <Switch
                checked={liveMode}
                onCheckedChange={setLiveMode}
                id="live-toggle"
              />
              <label
                htmlFor="live-toggle"
                className={cn(
                  "flex items-center gap-1.5 text-sm font-medium cursor-pointer select-none",
                  liveMode ? "text-emerald-600 dark:text-emerald-400" : "text-muted-foreground"
                )}
              >
                {liveMode && (
                  <span className="relative flex h-2 w-2">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                  </span>
                )}
                Live
              </label>
            </div>
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="outline" size="sm" disabled={sessions.length === 0}>
                  <Eraser className="h-4 w-4 mr-2" />
                  Clear All
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Clear All Request Sessions</AlertDialogTitle>
                  <AlertDialogDescription>
                    This will permanently remove all {sessions.length} request session logs.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() => clearMutation.mutate()}
                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  >
                    Clear All
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
            <Button variant="outline" size="sm" onClick={() => refetch()}>
              <RefreshCw className="h-4 w-4 mr-2" />
              Refresh
            </Button>
          </div>
        </div>

        {/* Stats bar with histogram */}
        <div className="flex items-center gap-6 text-sm text-muted-foreground mb-4">
          <span className="flex items-center gap-1.5">
            <Activity className="h-4 w-4" />
            {stats.total} requests
          </span>
          <span className="flex items-center gap-1.5">
            <AlertCircle className="h-4 w-4" />
            {stats.errors} errors
          </span>
          <span className="flex items-center gap-1.5">
            <Clock className="h-4 w-4" />
            {stats.avgDuration}ms avg
          </span>

          {/* Duration histogram */}
          {stats.total > 0 && (
            <div className="flex items-end gap-[2px] h-6 ml-2 border-l border-border pl-4" title="Response time distribution">
              {histogram.map((bucket, i) => (
                <div key={i} className="flex flex-col items-center group relative">
                  <div
                    className={cn(
                      "w-3 rounded-t-sm transition-all",
                      bucket.count === 0
                        ? "bg-muted/30"
                        : bucket.label.includes("s") ? "bg-amber-500/70" : "bg-primary/60"
                    )}
                    style={{ height: `${Math.max(bucket.pct * 20, bucket.count > 0 ? 2 : 1)}px` }}
                  />
                  {/* Tooltip */}
                  <div className="absolute bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-20">
                    <div className="bg-popover text-popover-foreground border border-border rounded px-2 py-1 text-[10px] font-mono whitespace-nowrap shadow-md">
                      {bucket.label}ms: {bucket.count}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <div className="flex-1 px-6 pb-6 flex gap-4 overflow-hidden">
        {/* Sessions List */}
        <Card className="w-[420px] flex flex-col flex-shrink-0">
          <CardHeader className="pb-3 space-y-3">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search path, method, ID..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>
            <div className="flex items-center gap-2">
              <Filter className="h-3.5 w-3.5 text-muted-foreground" />
              <Select value={statusFilter} onValueChange={(v) => setStatusFilter(v as StatusFilter)}>
                <SelectTrigger className="h-8 text-xs w-auto">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="2xx">2xx Success</SelectItem>
                  <SelectItem value="3xx">3xx Redirect</SelectItem>
                  <SelectItem value="4xx">4xx Client Error</SelectItem>
                  <SelectItem value="5xx">5xx Server Error</SelectItem>
                </SelectContent>
              </Select>
              <span className="text-xs text-muted-foreground ml-auto">
                {filteredSessions.length} shown
              </span>
            </div>
          </CardHeader>
          <CardContent className="flex-1 overflow-hidden p-0">
            <ScrollArea className="h-full">
              {isLoading ? (
                <div className="flex items-center justify-center py-12">
                  <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                </div>
              ) : filteredSessions.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 text-center px-4">
                  <Activity className="h-12 w-12 text-muted-foreground/50 mb-2" />
                  <p className="text-muted-foreground">No request sessions found</p>
                </div>
              ) : (
                <div className="px-4 pb-4">
                  {timelineGroups.map((group) => (
                    <div key={group.label} className="mb-3">
                      {/* Timeline group header */}
                      <div className="sticky top-0 z-10 flex items-center gap-2 py-1.5 bg-card">
                        <div className="h-px flex-1 bg-border" />
                        <span className="text-[11px] font-medium text-muted-foreground whitespace-nowrap px-1">
                          {group.label}
                          <span className="ml-1.5 text-muted-foreground/60">({group.sessions.length})</span>
                        </span>
                        <div className="h-px flex-1 bg-border" />
                      </div>

                      {/* Sessions in this group */}
                      <div className="space-y-px">
                        {group.sessions.map((session) => {
                          const isSelected = session.id === selectedId;
                          return (
                            <div
                              key={session.id}
                              className={cn(
                                "p-3 rounded-lg cursor-pointer transition-colors border",
                                isSelected
                                  ? "bg-primary/10 border-primary/30"
                                  : "hover:bg-muted/50 border-transparent"
                              )}
                              onClick={() => {
                                setSelectedId(session.id);
                                setDetailTab("response");
                              }}
                            >
                              <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2 min-w-0">
                                  {getMethodBadge(session.method)}
                                  <span className="font-mono text-sm truncate">{session.title || toAbsoluteUrl(session.path)}</span>
                                </div>
                                <Badge
                                  variant="outline"
                                  className={cn("font-mono text-xs shrink-0", getStatusBg(session.statusCode), getStatusColor(session.statusCode))}
                                >
                                  {session.statusCode}
                                </Badge>
                              </div>
                              {session.title && (
                                <div className="text-[10px] font-mono text-muted-foreground truncate mt-0.5">
                                  {toAbsoluteUrl(session.path)}
                                </div>
                              )}
                              <div className="flex items-center gap-3 mt-1.5 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1">
                                  <Clock className="h-3 w-3" />
                                  {format(new Date(session.startedAt), "HH:mm:ss.SSS")}
                                </span>
                                <span>{session.durationMs}ms</span>
                                {session.error && (
                                  <span className="text-destructive truncate flex items-center gap-1">
                                    <XCircle className="h-3 w-3 shrink-0" />
                                    {session.error.slice(0, 40)}
                                  </span>
                                )}
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </ScrollArea>
          </CardContent>
        </Card>

        {/* Detail Panel */}
        <Card className="flex-1 flex flex-col overflow-hidden">
          {detail ? (
            <>
              <CardHeader className="pb-3 flex-shrink-0">
                <div className="flex items-center justify-between">
                  <div className="min-w-0">
                    {detail.title && (
                      <p className="text-sm font-semibold text-foreground mb-0.5">{detail.title}</p>
                    )}
                    <CardTitle className="text-lg flex items-center gap-2">
                      {getMethodBadge(detail.method)}
                      <span className="font-mono">{toAbsoluteUrl(detail.path)}</span>
                      {detail.query && (
                        <span className="text-muted-foreground font-mono text-sm">?{detail.query}</span>
                      )}
                    </CardTitle>
                  </div>
                  <div className="flex items-center gap-1">
                    <Button
                      variant="ghost"
                      size="sm"
                      title="Copy full session JSON"
                      onClick={() => {
                        navigator.clipboard.writeText(JSON.stringify(detail, null, 2));
                        toast.success("Session copied to clipboard");
                      }}
                    >
                      <Copy className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        const blob = new Blob([JSON.stringify(detail, null, 2)], { type: "application/json" });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement("a");
                        a.href = url;
                        a.download = `request-session-${detail.id}.json`;
                        a.click();
                        URL.revokeObjectURL(url);
                      }}
                    >
                      <Download className="h-4 w-4" />
                    </Button>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Delete Request Session</AlertDialogTitle>
                          <AlertDialogDescription>
                            This will permanently delete this request session log.
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Cancel</AlertDialogCancel>
                          <AlertDialogAction
                            onClick={() => deleteMutation.mutate(detail.id)}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                          >
                            Delete
                          </AlertDialogAction>
                        </AlertDialogFooter>
                      </AlertDialogContent>
                    </AlertDialog>
                  </div>
                </div>
                <CardDescription className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                  <Badge
                    variant="outline"
                    className={cn("font-mono", getStatusBg(detail.statusCode), getStatusColor(detail.statusCode))}
                  >
                    {detail.statusCode}
                  </Badge>
                  <span className="flex items-center gap-1">
                    <Clock className="h-3 w-3" />
                    {format(new Date(detail.startedAt), "MMM d, yyyy HH:mm:ss.SSS")}
                  </span>
                  <span>{detail.durationMs}ms</span>
                  <span className="font-mono text-muted-foreground">{detail.id?.slice(0, 8) ?? '—'}</span>
                </CardDescription>

                {detail.error && (
                  <div className="mt-2 p-2.5 rounded-md bg-destructive/10 border border-destructive/20 flex items-start gap-2">
                    <AlertCircle className="h-4 w-4 text-destructive flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-destructive break-all">{detail.error}</p>
                  </div>
                )}
              </CardHeader>

              <CardContent className="flex-1 overflow-hidden p-0 flex flex-col">
                <Tabs value={detailTab} onValueChange={setDetailTab} className="flex-1 flex flex-col overflow-hidden">
                  <div className="px-4 border-b">
                    <TabsList className="bg-transparent h-9">
                      <TabsTrigger value="response" className="text-xs gap-1.5">
                        <FileJson className="h-3 w-3" />
                        Response
                      </TabsTrigger>
                      <TabsTrigger value="request" className="text-xs gap-1.5">
                        <ArrowUpDown className="h-3 w-3" />
                        Request
                      </TabsTrigger>
                      <TabsTrigger value="headers" className="text-xs gap-1.5">
                        <Activity className="h-3 w-3" />
                        Headers
                      </TabsTrigger>
                    </TabsList>
                  </div>

                  <ScrollArea className="flex-1">
                    <div className="p-4">
                      <TabsContent value="response" className="mt-0">
                        <JsonViewer content={detail.responseBody} label="response body" />
                      </TabsContent>
                      <TabsContent value="request" className="mt-0">
                        <JsonViewer content={detail.requestBody} label="request body" />
                      </TabsContent>
                      <TabsContent value="headers" className="mt-0">
                        {detail.headers && Object.keys(detail.headers).length > 0 ? (
                          <div className="relative group">
                            <button
                              onClick={() => {
                                const text = Object.entries(detail.headers!)
                                  .map(([k, v]) => `${k}: ${v}`)
                                  .join("\n");
                                navigator.clipboard.writeText(text);
                                toast.success("Headers copied to clipboard");
                              }}
                              className="absolute top-2 right-2 z-10 p-1.5 rounded-md bg-muted/80 hover:bg-muted border border-border opacity-0 group-hover:opacity-100 transition-opacity"
                              title="Copy headers"
                            >
                              <Copy className="h-3.5 w-3.5 text-muted-foreground" />
                            </button>
                            <div className="space-y-1 bg-muted/50 rounded-md p-3 border border-border">
                              {Object.entries(detail.headers).map(([key, value]) => (
                                <div key={key} className="flex gap-2 text-xs font-mono">
                                  <span className="text-sky-600 dark:text-sky-400 shrink-0">{key}:</span>
                                  <span className="break-all">{String(value)}</span>
                                </div>
                              ))}
                            </div>
                          </div>
                        ) : (
                          <p className="text-sm text-muted-foreground italic">No headers captured</p>
                        )}
                      </TabsContent>
                    </div>
                  </ScrollArea>
                </Tabs>
              </CardContent>
            </>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center text-muted-foreground">
              <FileJson className="h-12 w-12 mb-2 opacity-50" />
              <p>Select a request session to inspect</p>
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}
