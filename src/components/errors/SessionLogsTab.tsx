import { useState, useEffect } from "react";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { api, SessionDiagnostics, SessionStackFrame } from "@/lib/api";
import { toast } from "sonner";
import {
  Copy, Download, RefreshCw, FileText, Clock, AlertCircle,
  ArrowUpRight, ArrowDownLeft, Layers, Code2
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toClipboardText, unescapeEmbeddedNewlines } from "@/lib/logText";

interface SessionLogsTabProps {
  sessionId?: string;
  sessionType?: string;
}

interface SessionState {
  logs: string | null;
  diagnostics: SessionDiagnostics | null;
  loading: boolean;
  error: string | null;
}

export function SessionLogsTab({ sessionId, sessionType }: SessionLogsTabProps) {
  const [state, setState] = useState<SessionState>({
    logs: null,
    diagnostics: null,
    loading: false,
    error: null,
  });

  const fetchData = async () => {
    if (!sessionId) return;
    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const [logsRes, diagRes] = await Promise.all([
        api.getSessionLogs(sessionId),
        api.getSessionDiagnostics(sessionId),
      ]);

      setState({
        logs: logsRes.success ? logsRes.data?.logs ?? null : null,
        diagnostics: diagRes.success ? diagRes.data ?? null : null,
        loading: false,
        error: (!logsRes.success && !diagRes.success)
          ? (logsRes.error?.message || "Failed to fetch session data")
          : null,
      });
    } catch (err) {
      setState({
        logs: null,
        diagnostics: null,
        loading: false,
        error: err instanceof Error ? err.message : "Failed to fetch session data",
      });
    }
  };

  useEffect(() => {
    if (sessionId) fetchData();
  }, [sessionId]);

  const copyLogs = () => {
    if (!state.logs) return;
    navigator.clipboard.writeText(toClipboardText(state.logs));
    toast.success("Session logs copied to clipboard");
  };

  const downloadLogs = () => {
    if (!state.logs || !sessionId) return;
    const blob = new Blob([state.logs], { type: "text/plain" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `session-${sessionId.slice(0, 8)}-${new Date().toISOString().slice(0, 10)}.log`;
    link.click();
    window.URL.revokeObjectURL(url);
    toast.success("Session logs downloaded");
  };

  if (!sessionId) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        <FileText className="h-8 w-8 mx-auto mb-2 opacity-50" />
        <p className="text-sm">No session ID associated with this error</p>
        <p className="text-xs mt-1">
          Session logs are available for publish, sync, and connection test operations
        </p>
      </div>
    );
  }

  if (state.loading) {
    return (
      <div className="text-center py-8">
        <RefreshCw className="h-6 w-6 mx-auto mb-2 animate-spin text-primary" />
        <p className="text-sm text-muted-foreground">Loading session data...</p>
      </div>
    );
  }

  if (state.error && !state.logs && !state.diagnostics) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        <AlertCircle className="h-8 w-8 mx-auto mb-2 text-destructive opacity-70" />
        <p className="text-sm text-destructive">{state.error}</p>
        <Button variant="outline" size="sm" onClick={fetchData} className="mt-3">
          <RefreshCw className="h-4 w-4 mr-1" />
          Retry
        </Button>
      </div>
    );
  }

  const diag = state.diagnostics;
  const hasRequest = !!diag?.request;
  const hasResponse = !!diag?.response;
  const hasStackTrace = !!(diag?.stackTrace?.golang?.length || diag?.stackTrace?.php?.length || diag?.phpStackTraceLog);

  return (
    <div className="space-y-3">
      {/* Session Info Header */}
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div className="flex items-center gap-2">
          <Badge variant="outline" className="font-mono text-xs">
            <Clock className="h-3 w-3 mr-1" />
            {sessionId.slice(0, 8)}...
          </Badge>
          {sessionType && (
            <Badge variant="secondary" className="capitalize">
              {sessionType}
            </Badge>
          )}
        </div>
        <div className="flex items-center gap-1">
          <Button variant="ghost" size="sm" onClick={fetchData}>
            <RefreshCw className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="sm" onClick={copyLogs} disabled={!state.logs}>
            <Copy className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="sm" onClick={downloadLogs} disabled={!state.logs}>
            <Download className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Sub-tabs */}
      <Tabs defaultValue="logs" className="w-full">
        <TabsList className="w-full grid grid-cols-4 h-8">
          <TabsTrigger value="logs" className="text-xs gap-1 px-1">
            <FileText className="h-3 w-3" />
            Logs
          </TabsTrigger>
          <TabsTrigger value="request" className="text-xs gap-1 px-1" disabled={!hasRequest}>
            <ArrowUpRight className="h-3 w-3" />
            Request
          </TabsTrigger>
          <TabsTrigger value="response" className="text-xs gap-1 px-1" disabled={!hasResponse}>
            <ArrowDownLeft className="h-3 w-3" />
            Response
          </TabsTrigger>
          <TabsTrigger value="stacktrace" className="text-xs gap-1 px-1" disabled={!hasStackTrace}>
            <Layers className="h-3 w-3" />
            Stack Trace
          </TabsTrigger>
        </TabsList>

        {/* Logs sub-tab */}
        <TabsContent value="logs" className="mt-2">
          <ScrollArea className="h-64 rounded-md border bg-muted">
            <pre className="p-3 text-xs font-mono whitespace-pre-wrap break-words">
              {state.logs ? <LogContent logs={state.logs} /> : (
                <span className="text-muted-foreground italic">No logs available</span>
              )}
            </pre>
          </ScrollArea>
          {state.logs && (
            <div className="flex items-center gap-4 text-xs text-muted-foreground mt-2">
              <span>{state.logs.split("\n").length} lines</span>
              <span>{(new Blob([state.logs]).size / 1024).toFixed(1)} KB</span>
            </div>
          )}
        </TabsContent>

        {/* Request sub-tab */}
        <TabsContent value="request" className="mt-2">
          {diag?.request ? (
            <RequestPanel request={diag.request} />
          ) : (
            <EmptyPanel label="No request data captured" />
          )}
        </TabsContent>

        {/* Response sub-tab */}
        <TabsContent value="response" className="mt-2">
          {diag?.response ? (
            <ResponsePanel response={diag.response} />
          ) : (
            <EmptyPanel label="No response data captured" />
          )}
        </TabsContent>

        {/* Stack Trace sub-tab */}
        <TabsContent value="stacktrace" className="mt-2">
          {hasStackTrace ? (
            <StackTracePanel stackTrace={diag!.stackTrace} phpStackTraceLog={diag!.phpStackTraceLog} />
          ) : (
            <EmptyPanel label="No stack traces captured" />
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}

/* ─── Sub-panels ─── */

function EmptyPanel({ label }: { label: string }) {
  return (
    <div className="text-center py-6 text-muted-foreground text-sm">
      {label}
    </div>
  );
}

function RequestPanel({ request }: { request: NonNullable<SessionDiagnostics["request"]> }) {
  const copyJson = () => {
    navigator.clipboard.writeText(JSON.stringify(request, null, 2));
    toast.success("Request JSON copied");
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Badge variant="outline" className="font-mono text-xs">{request.method}</Badge>
          <span className="text-xs font-mono text-muted-foreground truncate max-w-[300px]">
            {request.url}
          </span>
        </div>
        <Button variant="ghost" size="sm" onClick={copyJson}>
          <Copy className="h-3 w-3" />
        </Button>
      </div>
      {request.body && Object.keys(request.body).length > 0 && (
        <ScrollArea className="h-48 rounded-md border bg-muted">
          <pre className="p-3 text-xs font-mono whitespace-pre-wrap break-all">
            {JSON.stringify(request.body, null, 2)}
          </pre>
        </ScrollArea>
      )}
    </div>
  );
}

function ResponsePanel({ response }: { response: NonNullable<SessionDiagnostics["response"]> }) {
  const copyJson = () => {
    navigator.clipboard.writeText(JSON.stringify(response, null, 2));
    toast.success("Response JSON copied");
  };

  const isError = response.statusCode >= 400;

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Badge variant={isError ? "destructive" : "default"} className="font-mono text-xs">
            {response.statusCode}
          </Badge>
          <span className="text-xs font-mono text-muted-foreground truncate max-w-[300px]">
            {response.requestUrl}
          </span>
        </div>
        <Button variant="ghost" size="sm" onClick={copyJson}>
          <Copy className="h-3 w-3" />
        </Button>
      </div>
      {response.body && (
        <ScrollArea className="h-48 rounded-md border bg-muted">
          <pre className="p-3 text-xs font-mono whitespace-pre-wrap break-all">
            {typeof response.body === "string" ? response.body : JSON.stringify(response.body, null, 2)}
          </pre>
        </ScrollArea>
      )}
    </div>
  );
}

function StackTracePanel({ stackTrace, phpStackTraceLog }: {
  stackTrace?: SessionDiagnostics["stackTrace"];
  phpStackTraceLog?: string;
}) {
  type ViewType = "golang" | "php" | "phplog";
  const defaultView: ViewType = stackTrace?.golang?.length ? "golang" : stackTrace?.php?.length ? "php" : "phplog";
  const [view, setView] = useState<ViewType>(defaultView);

  const goCount = stackTrace?.golang?.length ?? 0;
  const phpCount = stackTrace?.php?.length ?? 0;
  const hasPhpLog = !!phpStackTraceLog;

  return (
    <div className="space-y-2">
      {/* Toggle */}
      <div className="flex items-center gap-1 flex-wrap">
        {goCount > 0 && (
          <Button
            variant={view === "golang" ? "default" : "outline"}
            size="sm"
            className="text-xs h-7 gap-1"
            onClick={() => setView("golang")}
          >
            <Code2 className="h-3 w-3" />
            Go ({goCount})
          </Button>
        )}
        {phpCount > 0 && (
          <Button
            variant={view === "php" ? "default" : "outline"}
            size="sm"
            className="text-xs h-7 gap-1"
            onClick={() => setView("php")}
          >
            <Code2 className="h-3 w-3" />
            PHP ({phpCount})
          </Button>
        )}
        {hasPhpLog && (
          <Button
            variant={view === "phplog" ? "default" : "outline"}
            size="sm"
            className="text-xs h-7 gap-1"
            onClick={() => setView("phplog")}
          >
            <FileText className="h-3 w-3" />
            PHP Log
          </Button>
        )}
      </div>

      {/* Frames or raw log */}
      <ScrollArea className="h-56 rounded-md border bg-muted">
        {view === "phplog" ? (
          <pre className="p-3 text-xs font-mono whitespace-pre-wrap break-words text-orange-600 dark:text-orange-400">
            {phpStackTraceLog}
          </pre>
        ) : (
          <div className="p-3 space-y-1">
            {(view === "golang" ? stackTrace?.golang : stackTrace?.php)?.map((frame, i) => (
              <StackFrameRow key={i} index={i} frame={frame} variant={view as "golang" | "php"} />
            ))}
          </div>
        )}
      </ScrollArea>
    </div>
  );
}

function StackFrameRow({ index, frame, variant }: { index: number; frame: SessionStackFrame; variant: "golang" | "php" }) {
  const fnColor = variant === "golang" ? "text-blue-500 dark:text-blue-400" : "text-orange-500 dark:text-orange-400";

  return (
    <div className="text-xs font-mono leading-relaxed">
      <span className="text-muted-foreground mr-1">#{index}</span>
      <span className={cn("font-semibold", fnColor)}>
        {frame.class ? `${frame.class}::${frame.function}` : frame.function}
      </span>
      {frame.file && (
        <span className="text-muted-foreground ml-1">
          at {frame.file}{frame.line ? `:${frame.line}` : ""}
        </span>
      )}
    </div>
  );
}

/* ─── Log rendering (unchanged) ─── */

function LogContent({ logs }: { logs: string }) {
  const lines = unescapeEmbeddedNewlines(logs).split("\n");
  return (
    <>
      {lines.map((line, idx) => (
        <LogLine key={idx} line={line} />
      ))}
    </>
  );
}

function LogLine({ line }: { line: string }) {
  const isStageHeader = line.includes("STAGE:") || line.match(/^[─═]+$/);
  if (isStageHeader) {
    return <div className="text-primary font-semibold">{line}</div>;
  }

  const isError = line.includes("[ERROR]") || line.includes("[FATAL]");
  if (isError) {
    return <div className="text-destructive">{line}</div>;
  }

  const isWarning = line.includes("[WARN]");
  if (isWarning) {
    return <div className="text-amber-600 dark:text-amber-400">{line}</div>;
  }

  const isSuccess = line.includes("✓") || line.includes("completed") || line.includes("success");
  if (isSuccess) {
    return <div className="text-green-600 dark:text-green-400">{line}</div>;
  }

  const stageEndMatch = line.match(/STAGE END: (\w+) - (\w+) \((\d+)ms\)/);
  if (stageEndMatch) {
    const [, , status] = stageEndMatch;
    return (
      <div className={cn(
        status === "success" ? "text-green-600 dark:text-green-400" : "text-destructive"
      )}>
        {line}
      </div>
    );
  }

  return <div>{line}</div>;
}
