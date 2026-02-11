import { useState, useRef, useEffect } from "react";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Badge } from "@/components/ui/badge";
import { Terminal, ChevronDown, ChevronUp, Copy, Download } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { formatTime24h, toClipboardText, unescapeEmbeddedNewlines } from "@/lib/logText";

export interface LogEntry {
  timestamp: string;
  level: "debug" | "info" | "warn" | "error";
  step: string;
  message: string;
  details?: Record<string, unknown>;
}

interface LogViewerProps {
  logs: LogEntry[];
  title?: string;
  height?: string;
  showToggle?: boolean;
  defaultExpanded?: boolean;
  autoScroll?: boolean;
  className?: string;
  emptyMessage?: string;
}

export function LogViewer({
  logs,
  title = "Live Logs",
  height = "h-32",
  showToggle = true,
  defaultExpanded = true,
  autoScroll = true,
  className,
  emptyMessage = "Waiting for logs...",
}: LogViewerProps) {
  const logsEndRef = useRef<HTMLDivElement>(null);
  const [expanded, setExpanded] = useState(defaultExpanded);

  // Auto-scroll to bottom when new logs arrive
  useEffect(() => {
    if (autoScroll && expanded && logsEndRef.current) {
      logsEndRef.current.scrollIntoView({ behavior: "smooth" });
    }
  }, [logs, expanded, autoScroll]);

  const formatTs = (ts: string) => formatTime24h(ts);

  const formatLogText = () => {
    return logs
      .map((l) => {
        const base = `[${formatTs(l.timestamp)}] [${l.level.toUpperCase()}] [${l.step}] ${unescapeEmbeddedNewlines(l.message)}`;
        if (l.details && Object.keys(l.details).length > 0) {
          const detailsText = unescapeEmbeddedNewlines(JSON.stringify(l.details, null, 2));
          return `${base}\n${detailsText}`;
        }
        return base;
      })
      .join("\n\n");
  };

  const copyLogs = () => {
    navigator.clipboard.writeText(toClipboardText(formatLogText()));
    toast.success("Logs copied to clipboard");
  };

  const downloadLogs = () => {
    const text = formatLogText();
    const blob = new Blob([text], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `publish-logs-${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    toast.success("Logs downloaded");
  };

  const getLevelColor = (level: LogEntry["level"]) => {
    switch (level) {
      case "error":
        return "text-destructive";
      case "warn":
        return "text-warning";
      case "info":
        return "text-foreground";
      case "debug":
        return "text-muted-foreground";
      default:
        return "text-foreground";
    }
  };

  return (
    <div className={cn("border rounded-lg overflow-hidden", className)}>
      {/* Header */}
      <div
        className={cn(
          "flex items-center justify-between p-3 bg-muted/50",
          showToggle && "cursor-pointer hover:bg-muted transition-colors"
        )}
        onClick={showToggle ? () => setExpanded(!expanded) : undefined}
      >
        <div className="flex items-center gap-2">
          <Terminal className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm font-medium">{title}</span>
          <Badge variant="secondary" className="text-xs">
            {logs.length}
          </Badge>
        </div>
        <div className="flex items-center gap-2">
          {logs.length > 0 && (
            <>
              <Button
                variant="ghost"
                size="sm"
                className="h-6 px-2"
                onClick={(e) => {
                  e.stopPropagation();
                  downloadLogs();
                }}
                title="Download logs"
              >
                <Download className="h-3 w-3" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                className="h-6 px-2"
                onClick={(e) => {
                  e.stopPropagation();
                  copyLogs();
                }}
                title="Copy logs"
              >
                <Copy className="h-3 w-3" />
              </Button>
            </>
          )}
          {showToggle && (
            <span className="text-xs text-muted-foreground flex items-center gap-1">
              {expanded ? (
                <>
                  Hide <ChevronUp className="h-3 w-3" />
                </>
              ) : (
                <>
                  Show <ChevronDown className="h-3 w-3" />
                </>
              )}
            </span>
          )}
        </div>
      </div>

      {/* Log content */}
      {expanded && (
        <ScrollArea className={cn(height, "bg-background")}>
          <div className="p-2 space-y-1 text-xs font-mono">
            {logs.length === 0 ? (
              <p className="text-muted-foreground text-center py-4">
                {emptyMessage}
              </p>
            ) : (
              logs.map((log, idx) => (
                <div
                  key={idx}
                  className={cn("py-0.5 px-1 rounded", getLevelColor(log.level))}
                >
                  <span className="text-muted-foreground">
                    [{formatTs(log.timestamp)}]
                  </span>
                  <span className="text-primary ml-1">[{log.step}]</span>
                  <span className="ml-1 whitespace-pre-wrap break-words">
                    {unescapeEmbeddedNewlines(log.message)}
                  </span>
                  {log.details && Object.keys(log.details).length > 0 && (
                    <pre className="mt-1 ml-4 text-muted-foreground whitespace-pre-wrap break-words">
                      {unescapeEmbeddedNewlines(JSON.stringify(log.details, null, 2))}
                    </pre>
                  )}
                </div>
              ))
            )}
            <div ref={logsEndRef} />
          </div>
        </ScrollArea>
      )}
    </div>
  );
}
