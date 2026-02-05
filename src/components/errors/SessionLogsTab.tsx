import { useState, useEffect } from "react";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { api } from "@/lib/api";
import { toast } from "sonner";
import { Copy, Download, RefreshCw, FileText, Clock, AlertCircle } from "lucide-react";
import { cn } from "@/lib/utils";
import { toClipboardText, unescapeEmbeddedNewlines } from "@/lib/logText";

interface SessionLogsTabProps {
  sessionId?: string;
  sessionType?: string;
}

interface SessionState {
  logs: string | null;
  loading: boolean;
  error: string | null;
}

/**
 * Tab content for displaying session logs fetched from the backend.
 * Shows loading state, handles errors, and provides copy/download utilities.
 */
export function SessionLogsTab({ sessionId, sessionType }: SessionLogsTabProps) {
  const [state, setState] = useState<SessionState>({
    logs: null,
    loading: false,
    error: null,
  });

  const fetchLogs = async () => {
    if (!sessionId) return;

    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const response = await api.getSessionLogs(sessionId);
      if (response.success && response.data) {
        setState({
          logs: response.data.logs,
          loading: false,
          error: null,
        });
      } else {
        setState({
          logs: null,
          loading: false,
          error: response.error?.message || "Failed to fetch session logs",
        });
      }
    } catch (err) {
      setState({
        logs: null,
        loading: false,
        error: err instanceof Error ? err.message : "Failed to fetch session logs",
      });
    }
  };

  useEffect(() => {
    if (sessionId) {
      fetchLogs();
    }
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

  // No session ID available
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

  // Loading state
  if (state.loading) {
    return (
      <div className="text-center py-8">
        <RefreshCw className="h-6 w-6 mx-auto mb-2 animate-spin text-primary" />
        <p className="text-sm text-muted-foreground">Loading session logs...</p>
      </div>
    );
  }

  // Error state
  if (state.error) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        <AlertCircle className="h-8 w-8 mx-auto mb-2 text-destructive opacity-70" />
        <p className="text-sm text-destructive">{state.error}</p>
        <Button
          variant="outline"
          size="sm"
          onClick={fetchLogs}
          className="mt-3"
        >
          <RefreshCw className="h-4 w-4 mr-1" />
          Retry
        </Button>
      </div>
    );
  }

  // Success state with logs
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
          <Button variant="ghost" size="sm" onClick={fetchLogs}>
            <RefreshCw className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="sm" onClick={copyLogs}>
            <Copy className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="sm" onClick={downloadLogs}>
            <Download className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Log Content */}
      <ScrollArea className="h-64 rounded-md border bg-muted">
        <pre className="p-3 text-xs font-mono whitespace-pre-wrap break-words">
          {state.logs ? (
            <LogContent logs={state.logs} />
          ) : (
            <span className="text-muted-foreground italic">No logs available</span>
          )}
        </pre>
      </ScrollArea>

      {/* Log Stats */}
      {state.logs && (
        <div className="flex items-center gap-4 text-xs text-muted-foreground">
          <span>{state.logs.split('\n').length} lines</span>
          <span>{(new Blob([state.logs]).size / 1024).toFixed(1)} KB</span>
        </div>
      )}
    </div>
  );
}

/**
 * Renders log content with syntax highlighting for stages and levels
 */
function LogContent({ logs }: { logs: string }) {
  const lines = unescapeEmbeddedNewlines(logs).split('\n');
  
  return (
    <>
      {lines.map((line, idx) => (
        <LogLine key={idx} line={line} />
      ))}
    </>
  );
}

function LogLine({ line }: { line: string }) {
  // Stage headers (separator lines or STAGE: lines)
  const isStageHeader = line.includes('STAGE:') || line.match(/^[─═]+$/);
  if (isStageHeader) {
    return (
      <div className="text-primary font-semibold">
        {line}
      </div>
    );
  }

  // Error lines
  const isError = line.includes('[ERROR]') || line.includes('[FATAL]');
  if (isError) {
    return (
      <div className="text-destructive">
        {line}
      </div>
    );
  }

  // Warning lines
  const isWarning = line.includes('[WARN]');
  if (isWarning) {
    return (
      <div className="text-amber-600 dark:text-amber-400">
        {line}
      </div>
    );
  }

  // Success/complete lines
  const isSuccess = line.includes('✓') || line.includes('completed') || line.includes('success');
  if (isSuccess) {
    return (
      <div className="text-green-600 dark:text-green-400">
        {line}
      </div>
    );
  }

  // Stage end with timing
  const stageEndMatch = line.match(/STAGE END: (\w+) - (\w+) \((\d+)ms\)/);
  if (stageEndMatch) {
    const [, , status] = stageEndMatch;
    return (
      <div className={cn(
        status === 'success' ? "text-green-600 dark:text-green-400" : "text-destructive"
      )}>
        {line}
      </div>
    );
  }

  // Default
  return <div>{line}</div>;
}
