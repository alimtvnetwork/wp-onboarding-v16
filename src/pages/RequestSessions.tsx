import { useState, useMemo } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { format, formatDistanceStrict } from "date-fns";
import {
  Activity,
  Clock,
  Loader2,
  Trash2,
  RefreshCw,
  Search,
  AlertCircle,
  CheckCircle2,
  XCircle,
  Filter,
  Download,
  ChevronRight,
  ArrowUpDown,
  FileJson,
  Eraser,
} from "lucide-react";
import { api, RequestSessionRecord, RequestSessionListResponse, requireSuccess } from "@/lib/api";
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

function JsonViewer({ content, label }: { content: string; label: string }) {
  if (!content) return <p className="text-sm text-muted-foreground italic">No {label}</p>;

  let formatted = content;
  try {
    formatted = JSON.stringify(JSON.parse(content), null, 2);
  } catch {
    // not JSON, show raw
  }

  return (
    <pre className="text-xs font-mono bg-muted/50 rounded-md p-3 overflow-auto max-h-[400px] whitespace-pre-wrap break-all">
      {formatted}
    </pre>
  );
}

export default function RequestSessions() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detailTab, setDetailTab] = useState<string>("response");

  // Fetch sessions
  const { data: sessionsData, isLoading, refetch } = useQuery({
    queryKey: ["request-sessions"],
    queryFn: async () => {
      const response = await api.getRequestSessions({ limit: 200 });
      return requireSuccess(response, { endpoint: "/request-sessions" });
    },
  });

  const sessions = (sessionsData as RequestSessionListResponse)?.sessions || [];

  // Fetch single session detail
  const { data: selectedSession, isLoading: detailLoading } = useQuery({
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
          (s.error && s.error.toLowerCase().includes(q))
        );
      }
      return true;
    });
  }, [sessions, statusFilter, searchQuery]);

  // Stats
  const stats = useMemo(() => {
    const total = sessions.length;
    const errors = sessions.filter((s: RequestSessionRecord) => s.statusCode >= 400).length;
    const avgDuration = total > 0
      ? Math.round(sessions.reduce((sum: number, s: RequestSessionRecord) => sum + s.durationMs, 0) / total)
      : 0;
    return { total, errors, avgDuration };
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

        {/* Stats bar */}
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
                <div className="space-y-px px-4 pb-4">
                  {filteredSessions.map((session: RequestSessionRecord) => {
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
                            <span className="font-mono text-sm truncate">{session.path}</span>
                          </div>
                          <Badge
                            variant="outline"
                            className={cn("font-mono text-xs shrink-0", getStatusBg(session.statusCode), getStatusColor(session.statusCode))}
                          >
                            {session.statusCode}
                          </Badge>
                        </div>
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
                  <CardTitle className="text-lg flex items-center gap-2">
                    {getMethodBadge(detail.method)}
                    <span className="font-mono">{detail.path}</span>
                    {detail.query && (
                      <span className="text-muted-foreground font-mono text-sm">?{detail.query}</span>
                    )}
                  </CardTitle>
                  <div className="flex items-center gap-1">
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
                  <span className="font-mono text-muted-foreground">{detail.id.slice(0, 8)}</span>
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
                        <JsonViewer content={detail.responseBody || ""} label="response body" />
                      </TabsContent>
                      <TabsContent value="request" className="mt-0">
                        <JsonViewer content={detail.requestBody || ""} label="request body" />
                      </TabsContent>
                      <TabsContent value="headers" className="mt-0">
                        {detail.headers && Object.keys(detail.headers).length > 0 ? (
                          <div className="space-y-1">
                            {Object.entries(detail.headers).map(([key, value]) => (
                              <div key={key} className="flex gap-2 text-xs font-mono">
                                <span className="text-muted-foreground shrink-0">{key}:</span>
                                <span className="break-all">{String(value)}</span>
                              </div>
                            ))}
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
