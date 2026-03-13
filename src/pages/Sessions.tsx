import { useState } from "react";
import { useCaptureOnError } from "@/hooks/useCaptureQueryError";
import { SessionStatus } from "@/lib/constants";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { format, formatDistance } from "date-fns";
import {
  Activity,
  Clock,
  CheckCircle,
  XCircle,
  Loader2,
  Trash2,
  FileText,
  RefreshCw,
  Package,
  Globe,
  ChevronRight,
  Search,
  AlertCircle,
  FileJson,
  Timer,
} from "lucide-react";
import { api, SessionSummary, SessionInfo, requireSuccess } from "@/lib/api";
import { EnvelopePagination } from "@/components/shared/EnvelopePagination";
import { requireSuccessWithEnvelope } from "@/lib/apiHelpers";
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
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { LogViewer } from "@/components/shared/LogViewer";

const SESSION_TYPE_ICONS: Record<string, typeof Activity> = {
  publish: Package,
  sync: RefreshCw,
  connect: Globe,
  backup: FileText,
  bulk_publish: Package,
  remote_plugin_action: Globe,
};

const SESSION_TYPE_LABELS: Record<string, string> = {
  publish: "Publish",
  sync: "Sync",
  connect: "Connection Test",
  backup: "Backup",
  bulk_publish: "Bulk Publish",
  remote_plugin_action: "Remote Plugin Action",
};

function getStatusBadge(status: string) {
  switch (status) {
    case SessionStatus.Completed:
      return (
        <Badge variant="outline" className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20">
          <CheckCircle className="h-3 w-3 mr-1" />
          Completed
        </Badge>
      );
    case SessionStatus.Error:
      return (
        <Badge variant="outline" className="bg-destructive/10 text-destructive border-destructive/20">
          <XCircle className="h-3 w-3 mr-1" />
          Error
        </Badge>
      );
    case SessionStatus.Running:
      return (
        <Badge variant="outline" className="bg-primary/10 text-primary border-primary/20">
          <Loader2 className="h-3 w-3 mr-1 animate-spin" />
          Running
        </Badge>
      );
    default:
      return <Badge variant="outline">{status}</Badge>;
  }
}

function formatDuration(startedAt: string, endedAt?: string): string {
  const start = new Date(startedAt);
  const end = endedAt ? new Date(endedAt) : new Date();
  return formatDistance(start, end, { includeSeconds: true });
}

export default function Sessions() {
  const queryClient = useQueryClient();
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedSessionId, setSelectedSessionId] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<string>("all");
  const [detailTab, setDetailTab] = useState<string>("logs");
  const [currentPage, setCurrentPage] = useState(1);

  const captureDeleteError = useCaptureOnError({ source: "Sessions.deleteMutation", endpoint: "/sessions", method: "DELETE", triggerComponent: "Sessions" });

  // Fetch sessions list
  const { data: sessionsResult, isLoading: sessionsLoading, refetch } = useQuery({
    queryKey: ["sessions", currentPage],
    queryFn: async () => {
      const response = await api.getSessions(100);
      return requireSuccessWithEnvelope<SessionSummary[]>(response, { endpoint: "/sessions" });
    },
  });

  const sessions = sessionsResult?.data;
  const sessionsEnvelope = sessionsResult?.envelope;

  // Fetch full session info (includes errorMsg + metadata)
  const { data: sessionInfo } = useQuery({
    queryKey: ["session-info", selectedSessionId],
    queryFn: async () => {
      if (!selectedSessionId) return null;
      const response = await api.getSession(selectedSessionId);
      return requireSuccess(response, { endpoint: `/sessions/${selectedSessionId}` });
    },
    enabled: !!selectedSessionId,
  });

  // Fetch selected session logs
  const { data: sessionLogs, isLoading: logsLoading } = useQuery({
    queryKey: ["session-logs", selectedSessionId],
    queryFn: async () => {
      if (!selectedSessionId) return null;
      const response = await api.getSessionLogs(selectedSessionId);
      return requireSuccess(response, { endpoint: `/sessions/${selectedSessionId}/logs` });
    },
    enabled: !!selectedSessionId,
  });

  // Delete session mutation
  const deleteMutation = useMutation({
    meta: { suppressGlobalError: true },
    mutationFn: async (sessionId: string) => {
      const response = await api.deleteSession(sessionId);
      return requireSuccess(response, { endpoint: `/sessions/${sessionId}`, method: "DELETE" });
    },
    onSuccess: (_, sessionId) => {
      toast.success("Session deleted");
      queryClient.invalidateQueries({ queryKey: ["sessions"] });
      if (selectedSessionId === sessionId) {
        setSelectedSessionId(null);
      }
    },
    onError: (error: Error) => {
      captureDeleteError(error);
      toast.error("Failed to delete session");
    },
  });

  // Filter sessions
  const filteredSessions = (sessions || []).filter((session: SessionSummary) => {
    const matchesSearch =
      session.pluginName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      session.siteName?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      session.sessionId.toLowerCase().includes(searchQuery.toLowerCase()) ||
      session.type.toLowerCase().includes(searchQuery.toLowerCase());

    const matchesTab =
      activeTab === "all" ||
      (activeTab === SessionStatus.Running && session.status === SessionStatus.Running) ||
      (activeTab === SessionStatus.Completed && session.status === SessionStatus.Completed) ||
      (activeTab === SessionStatus.Error && session.status === SessionStatus.Error);

    return matchesSearch && matchesTab;
  });

  // Use sessionInfo if available, fall back to summary from list
  const selectedSession: SessionInfo | SessionSummary | undefined =
    sessionInfo || sessions?.find((s: SessionSummary) => s.sessionId === selectedSessionId);

  // Parse logs into entries for LogViewer
  const logEntries = sessionLogs?.logs
    ? sessionLogs.logs.split("\n").filter(Boolean).map((line: string) => {
        const tsMatch = line.match(/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/);
        const levelMatch = line.match(/\[(ERROR|WARN|INFO|DEBUG)\]/i);
        const stepMatch = line.match(/\[([^\]]+)\]\s+/g);

        return {
          timestamp: tsMatch ? tsMatch[1] : new Date().toISOString(),
          level: (levelMatch
            ? levelMatch[1].toLowerCase() as "error" | "warn" | "info" | "debug"
            : "info") as "error" | "warn" | "info" | "debug",
          step: stepMatch && stepMatch.length > 1
            ? stepMatch[1]?.replace(/[\[\]]/g, "") || "log"
            : "log",
          message: line,
        };
      })
    : [];

  // Separate error logs
  const errorLogEntries = logEntries.filter((e) => e.level === "error");

  // Reset detail tab when switching sessions
  const handleSelectSession = (sessionId: string) => {
    setSelectedSessionId(sessionId);
    setDetailTab("logs");
  };

  return (
    <div className="h-full flex flex-col">
      <div className="p-6 pb-4">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold">Sessions</h1>
            <p className="text-muted-foreground">
              Browse and view historical operation logs
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            <RefreshCw className="h-4 w-4 mr-2" />
            Refresh
          </Button>
        </div>
      </div>

      <div className="flex-1 px-6 pb-6 flex gap-4 overflow-hidden">
        {/* Sessions List */}
        <Card className="w-96 flex flex-col flex-shrink-0">
          <CardHeader className="pb-3">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search sessions..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>
          </CardHeader>
          <CardContent className="flex-1 overflow-hidden flex flex-col p-0">
            <Tabs value={activeTab} onValueChange={setActiveTab} className="flex-1 flex flex-col overflow-hidden px-4">
              <TabsList className="grid w-full grid-cols-4">
                <TabsTrigger value="all" className="text-xs">All</TabsTrigger>
                <TabsTrigger value="running" className="text-xs">Running</TabsTrigger>
                <TabsTrigger value="completed" className="text-xs">Done</TabsTrigger>
                <TabsTrigger value="error" className="text-xs">Error</TabsTrigger>
              </TabsList>

              <TabsContent value={activeTab} className="flex-1 overflow-hidden mt-2">
                <ScrollArea className="h-full">
                  {sessionsLoading ? (
                    <div className="flex items-center justify-center py-12">
                      <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                    </div>
                  ) : filteredSessions.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                      <Activity className="h-12 w-12 text-muted-foreground/50 mb-2" />
                      <p className="text-muted-foreground">No sessions found</p>
                    </div>
                  ) : (
                    <div className="space-y-1 pr-4 pb-4">
                      {filteredSessions.map((session: SessionSummary) => {
                        const TypeIcon = SESSION_TYPE_ICONS[session.type] || Activity;
                        const isSelected = session.sessionId === selectedSessionId;

                        return (
                          <div
                            key={session.sessionId}
                            className={cn(
                              "p-3 rounded-lg cursor-pointer transition-colors border",
                              isSelected
                                ? "bg-primary/10 border-primary/30"
                                : "hover:bg-muted/50 border-transparent"
                            )}
                            onClick={() => handleSelectSession(session.sessionId)}
                          >
                            <div className="flex items-start justify-between gap-2">
                              <div className="flex items-center gap-2 min-w-0">
                                <TypeIcon className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                                <span className="font-medium text-sm truncate">
                                  {SESSION_TYPE_LABELS[session.type] || session.type}
                                </span>
                              </div>
                              {getStatusBadge(session.status)}
                            </div>

                            <div className="mt-1.5 space-y-1">
                              {session.pluginName && (
                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                  <Package className="h-3 w-3" />
                                  <span className="truncate">{session.pluginName}</span>
                                </div>
                              )}
                              {session.siteName && (
                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                  <Globe className="h-3 w-3" />
                                  <span className="truncate">{session.siteName}</span>
                                </div>
                              )}
                              <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1">
                                  <Clock className="h-3 w-3" />
                                  {format(new Date(session.startedAt), "MMM d, HH:mm:ss")}
                                </span>
                                {session.endedAt && (
                                  <span className="flex items-center gap-1">
                                    <Timer className="h-3 w-3" />
                                    {formatDuration(session.startedAt, session.endedAt)}
                                  </span>
                                )}
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </ScrollArea>
              </TabsContent>
            </Tabs>
            {/* Sessions Pagination */}
            <div className="px-4 pb-3">
              <EnvelopePagination
                meta={sessionsEnvelope ? { attributes: sessionsEnvelope.attributes, navigation: sessionsEnvelope.navigation } : null}
                onPageChange={setCurrentPage}
              />
            </div>
          </CardContent>
        </Card>

        {/* Session Details */}
        <Card className="flex-1 flex flex-col overflow-hidden">
          {selectedSession ? (
            <>
              <CardHeader className="pb-3 flex-shrink-0">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <CardTitle className="text-lg flex items-center gap-2">
                      {(() => {
                        const TypeIcon = SESSION_TYPE_ICONS[selectedSession.type] || Activity;
                        return <TypeIcon className="h-5 w-5" />;
                      })()}
                      {SESSION_TYPE_LABELS[selectedSession.type] || selectedSession.type}
                    </CardTitle>
                    {getStatusBadge(selectedSession.status)}
                  </div>
                  <AlertDialog>
                    <AlertDialogTrigger asChild>
                      <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Delete Session</AlertDialogTitle>
                        <AlertDialogDescription>
                          This will permanently delete the session logs. This action cannot be undone.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                          onClick={() => deleteMutation.mutate(selectedSession.sessionId)}
                          className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                          Delete
                        </AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                </div>
                <CardDescription className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                  {selectedSession.pluginName && (
                    <span className="flex items-center gap-1">
                      <Package className="h-3 w-3" />
                      {selectedSession.pluginName}
                    </span>
                  )}
                  {selectedSession.siteName && (
                    <span className="flex items-center gap-1">
                      <Globe className="h-3 w-3" />
                      {selectedSession.siteName}
                    </span>
                  )}
                  <span className="flex items-center gap-1">
                    <Clock className="h-3 w-3" />
                    {format(new Date(selectedSession.startedAt), "MMM d, yyyy HH:mm:ss")}
                  </span>
                  {selectedSession.endedAt && (
                    <span className="flex items-center gap-1">
                      <Timer className="h-3 w-3" />
                      {formatDuration(selectedSession.startedAt, selectedSession.endedAt)}
                    </span>
                  )}
                  <span className="font-mono text-muted-foreground">
                    {selectedSession.sessionId?.slice(0, 8) ?? '—'}
                  </span>
                </CardDescription>

                {/* Error message banner */}
                {"errorMsg" in selectedSession && selectedSession.errorMsg && (
                  <div className="mt-2 p-2.5 rounded-md bg-destructive/10 border border-destructive/20 flex items-start gap-2">
                    <AlertCircle className="h-4 w-4 text-destructive flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-destructive break-all">{selectedSession.errorMsg}</p>
                  </div>
                )}
              </CardHeader>

              <CardContent className="flex-1 overflow-hidden p-0 flex flex-col">
                {/* Detail tabs */}
                <Tabs value={detailTab} onValueChange={setDetailTab} className="flex-1 flex flex-col overflow-hidden">
                  <div className="px-4 border-b">
                    <TabsList className="bg-transparent h-9">
                      <TabsTrigger value="logs" className="text-xs gap-1.5">
                        <FileText className="h-3 w-3" />
                        Logs
                        <Badge variant="secondary" className="text-[10px] h-4 px-1">{logEntries.length}</Badge>
                      </TabsTrigger>
                      {errorLogEntries.length > 0 && (
                        <TabsTrigger value="errors" className="text-xs gap-1.5">
                          <AlertCircle className="h-3 w-3" />
                          Errors
                          <Badge variant="destructive" className="text-[10px] h-4 px-1">{errorLogEntries.length}</Badge>
                        </TabsTrigger>
                      )}
                      {"metadata" in selectedSession && selectedSession.metadata && Object.keys(selectedSession.metadata).length > 0 && (
                        <TabsTrigger value="metadata" className="text-xs gap-1.5">
                          <FileJson className="h-3 w-3" />
                          Metadata
                        </TabsTrigger>
                      )}
                    </TabsList>
                  </div>

                  <TabsContent value="logs" className="flex-1 overflow-hidden m-0 p-4 pt-2">
                    {logsLoading ? (
                      <div className="flex items-center justify-center h-full">
                        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                      </div>
                    ) : (
                      <LogViewer
                        logs={logEntries}
                        title="Session Logs"
                        height="h-full"
                        showToggle={false}
                        autoScroll={false}
                      />
                    )}
                  </TabsContent>

                  <TabsContent value="errors" className="flex-1 overflow-hidden m-0 p-4 pt-2">
                    <LogViewer
                      logs={errorLogEntries}
                      title="Error Logs"
                      height="h-full"
                      showToggle={false}
                      autoScroll={false}
                      emptyMessage="No errors in this session"
                    />
                  </TabsContent>

                  <TabsContent value="metadata" className="flex-1 overflow-hidden m-0 p-4 pt-2">
                    {"metadata" in selectedSession && selectedSession.metadata && (
                      <ScrollArea className="h-full">
                        <pre className="text-xs font-mono bg-muted/50 p-4 rounded-lg whitespace-pre-wrap break-all">
                          {JSON.stringify(selectedSession.metadata, null, 2)}
                        </pre>
                      </ScrollArea>
                    )}
                  </TabsContent>
                </Tabs>
              </CardContent>
            </>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center text-center">
              <ChevronRight className="h-12 w-12 text-muted-foreground/30 mb-4" />
              <p className="text-muted-foreground">Select a session to view logs</p>
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}
