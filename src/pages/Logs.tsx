import { useState, useEffect, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  ScrollText,
  Search,
  Trash2,
  Download,
  Pause,
  Play,
  AlertCircle,
  Info,
  AlertTriangle,
  Bug,
  Wifi,
  WifiOff,
  Upload,
  CloudUpload,
  GitBranch,
  Link,
  FileText,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useWebSocket } from "@/hooks/useWebSocket";
import { useVersionInfo } from "@/hooks/useWhatsNew";
import { formatTime24h } from "@/lib/logText";

interface LogEntry {
  id: string;
  timestamp: string;
  level: "info" | "warn" | "error" | "debug";
  source: string;
  message: string;
  details?: Record<string, unknown>;
}

const levelConfig = {
  info: { icon: Info, color: "text-blue-500", bg: "bg-blue-500/10" },
  warn: { icon: AlertTriangle, color: "text-amber-500", bg: "bg-amber-500/10" },
  error: { icon: AlertCircle, color: "text-destructive", bg: "bg-destructive/10" },
  debug: { icon: Bug, color: "text-muted-foreground", bg: "bg-muted" },
};

// Map WebSocket event types to log entries
function wsEventToLogEntry(message: { type: string; data: unknown; timestamp: string }): LogEntry | null {
  const id = `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
  const data = message.data as Record<string, unknown>;

  switch (message.type) {
    // Connection events
    case "connection":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "connection",
        message: `WebSocket ${data?.status || "connected"}`,
        details: data,
      };

    // File change events
    case "file_change":
      const summary = data?.summary as Record<string, number> | undefined;
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "watcher",
        message: `File changes detected in plugin ${data?.pluginId}: ${summary?.created || 0} created, ${summary?.modified || 0} modified, ${summary?.deleted || 0} deleted`,
        details: data,
      };

    // Publish events
    case "publish_started":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "publish",
        message: `Publishing plugin ${data?.pluginId} to site ${data?.siteId}`,
        details: data,
      };
    case "publish_progress":
      return {
        id,
        timestamp: message.timestamp,
        level: "debug",
        source: "publish",
        message: `[${data?.stage}] ${data?.message || `Progress: ${data?.progress}%`}`,
        details: data,
      };
    case "publish_complete":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "publish",
        message: `Publish complete: ${data?.filesUpdated || 0} files updated`,
        details: data,
      };

    // Auto-publish events
    case "auto_publish_triggered":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "auto-publish",
        message: `Auto-publish triggered for "${data?.pluginName}" (${data?.changes} changes → ${data?.sites} sites)`,
        details: data,
      };
    case "auto_publish_complete":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "auto-publish",
        message: `Auto-published to "${data?.siteName}": ${data?.filesUpdated} files updated`,
        details: data,
      };
    case "auto_publish_failed":
      return {
        id,
        timestamp: message.timestamp,
        level: "error",
        source: "auto-publish",
        message: `Auto-publish failed for "${data?.siteName}": ${data?.error}`,
        details: data,
      };

    // Sync events
    case "sync_started":
    case "sync_progress":
    case "sync_complete":
      return {
        id,
        timestamp: message.timestamp,
        level: message.type === "sync_complete" ? "info" : "debug",
        source: "sync",
        message: data?.message as string || `Sync ${message.type.replace("sync_", "")}`,
        details: data,
      };

    // Scan events
    case "scan_started":
    case "scan_progress":
    case "scan_complete":
      return {
        id,
        timestamp: message.timestamp,
        level: "debug",
        source: "scan",
        message: data?.currentFile 
          ? `Scanning: ${data.currentFile}` 
          : `Scan ${message.type.replace("scan_", "")}`,
        details: data,
      };

    // Git events
    case "git_pull_started":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "git",
        message: `Git pull started for plugin ${data?.pluginId}`,
        details: data,
      };
    case "git_pull_complete":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "git",
        message: `Git pull complete: ${data?.filesChanged || 0} files changed`,
        details: data,
      };
    case "git_pull_failed":
      return {
        id,
        timestamp: message.timestamp,
        level: "error",
        source: "git",
        message: `Git pull failed: ${data?.error}`,
        details: data,
      };
    case "git_commit_complete":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "git",
        message: `Committed: ${data?.message}`,
        details: data,
      };
    case "git_push_complete":
      return {
        id,
        timestamp: message.timestamp,
        level: "info",
        source: "git",
        message: "Git push complete",
        details: data,
      };

    // Connection test events
    case "connection_test_progress":
      return {
        id,
        timestamp: message.timestamp,
        level: data?.status === "error" ? "error" : data?.status === "warning" ? "warn" : "info",
        source: "connection",
        message: `[${data?.step}] ${data?.message}`,
        details: data,
      };

    // Direct log events
    case "log":
      return {
        id,
        timestamp: message.timestamp,
        level: (data?.level as LogEntry["level"]) || "info",
        source: (data?.context as Record<string, unknown>)?.source as string || "backend",
        message: data?.message as string || "Unknown log",
        details: data?.context as Record<string, unknown>,
      };

    // Error events
    case "error":
      return {
        id,
        timestamp: message.timestamp,
        level: "error",
        source: "error",
        message: `[${data?.code}] ${data?.message}`,
        details: data,
      };

    default:
      // Log unknown events as debug
      return {
        id,
        timestamp: message.timestamp,
        level: "debug",
        source: "unknown",
        message: `Event: ${message.type}`,
        details: data,
      };
  }
}

export default function Logs() {
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [filter, setFilter] = useState("");
  const [levelFilter, setLevelFilter] = useState<string>("all");
  const [sourceFilter, setSourceFilter] = useState<string>("all");
  const [isPaused, setIsPaused] = useState(false);
  const [autoScroll, setAutoScroll] = useState(true);
  const scrollRef = useRef<HTMLDivElement>(null);
  const { lastMessage, isConnected } = useWebSocket();
  const { data: versionInfo } = useVersionInfo();

  const appName = versionInfo?.appName || "WP Plugin Publish";
  const appVersion = versionInfo?.version || "0.0.0";

  // Add connection status log on mount
  useEffect(() => {
    const entry: LogEntry = {
      id: "initial-status",
      timestamp: new Date().toISOString(),
      level: isConnected ? "info" : "warn",
      source: "connection",
      message: isConnected 
        ? "Connected to backend WebSocket - listening for live logs" 
        : "WebSocket disconnected - logs will appear when connected",
    };
    setLogs((prev) => [entry, ...prev.filter(l => l.id !== "initial-status")]);
  }, [isConnected]);

  // Add new log entries from WebSocket
  useEffect(() => {
    if (lastMessage && !isPaused) {
      const logEntry = wsEventToLogEntry(lastMessage);
      if (logEntry) {
        setLogs((prev) => [logEntry, ...prev].slice(0, 1000));
      }
    }
  }, [lastMessage, isPaused]);

  // Auto-scroll to top (newest logs first)
  useEffect(() => {
    if (autoScroll && scrollRef.current) {
      scrollRef.current.scrollTop = 0;
    }
  }, [logs, autoScroll]);

  const filteredLogs = logs.filter((log) => {
    if (levelFilter !== "all" && log.level !== levelFilter) return false;
    if (sourceFilter !== "all" && log.source !== sourceFilter) return false;
    if (filter && !log.message.toLowerCase().includes(filter.toLowerCase())) return false;
    return true;
  });

  const uniqueSources = Array.from(new Set(logs.map((l) => l.source)));

  const handleClearLogs = () => {
    setLogs([]);
  };

  const handleExportLogs = () => {
    const headerLines = [
      `=== ${appName} Logs ===`,
      `App Version: v${appVersion}`,
      `Exported: ${new Date().toISOString()}`,
      "",
    ];

    const content = headerLines
      .concat(
        filteredLogs.map(
          (l) =>
            `[${l.timestamp}] [${l.level.toUpperCase()}] [${l.source}] ${l.message}`
        )
      )
      .join("\n");
    const blob = new Blob([content], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `logs-${new Date().toISOString().split("T")[0]}.txt`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const formatTime = (timestamp: string) => formatTime24h(timestamp);

  const getSourceIcon = (source: string) => {
    switch (source) {
      case "publish":
        return Upload;
      case "sync":
        return CloudUpload;
      case "git":
        return GitBranch;
      case "connection":
        return Link;
      case "watcher":
      case "scan":
        return FileText;
      case "auto-publish":
        return Upload;
      default:
        return Info;
    }
  };

  return (
    <div className="space-y-6 h-full flex flex-col">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            Logs
            {isConnected ? (
              <Badge variant="secondary" className="bg-primary/10 text-primary">
                <Wifi className="h-3 w-3 mr-1" />
                Live
              </Badge>
            ) : (
              <Badge variant="secondary" className="bg-muted">
                <WifiOff className="h-3 w-3 mr-1" />
                Disconnected
              </Badge>
            )}
          </h1>
          <p className="text-muted-foreground">
            Real-time activity logs from the backend
          </p>
          <p className="text-xs text-muted-foreground mt-1">
            {appName} <span className="font-mono">v{appVersion}</span>
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant={isPaused ? "default" : "outline"}
            size="sm"
            onClick={() => setIsPaused(!isPaused)}
          >
            {isPaused ? (
              <>
                <Play className="h-4 w-4 mr-1" />
                Resume
              </>
            ) : (
              <>
                <Pause className="h-4 w-4 mr-1" />
                Pause
              </>
            )}
          </Button>
          <Button variant="outline" size="sm" onClick={handleExportLogs}>
            <Download className="h-4 w-4 mr-1" />
            Export
          </Button>
          <Button variant="outline" size="sm" onClick={handleClearLogs}>
            <Trash2 className="h-4 w-4 mr-1" />
            Clear
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="py-4">
          <div className="flex flex-wrap gap-4">
            <div className="flex-1 min-w-[200px]">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search logs..."
                  value={filter}
                  onChange={(e) => setFilter(e.target.value)}
                  className="pl-9"
                />
              </div>
            </div>
            <Select value={levelFilter} onValueChange={setLevelFilter}>
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder="Level" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Levels</SelectItem>
                <SelectItem value="info">Info</SelectItem>
                <SelectItem value="warn">Warning</SelectItem>
                <SelectItem value="error">Error</SelectItem>
                <SelectItem value="debug">Debug</SelectItem>
              </SelectContent>
            </Select>
            <Select value={sourceFilter} onValueChange={setSourceFilter}>
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder="Source" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Sources</SelectItem>
                {uniqueSources.map((source) => (
                  <SelectItem key={source} value={source}>
                    {source}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <div className="flex items-center gap-2">
              <Checkbox
                id="auto-scroll"
                checked={autoScroll}
                onCheckedChange={(checked) => setAutoScroll(!!checked)}
              />
              <label htmlFor="auto-scroll" className="text-sm cursor-pointer">
                Auto-scroll
              </label>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Log Entries */}
      <Card className="flex-1 min-h-0">
        <CardHeader className="py-3 border-b">
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm font-medium">
              <ScrollText className="h-4 w-4 inline mr-2" />
              {filteredLogs.length} entries
              {isPaused && (
                <Badge variant="secondary" className="ml-2">
                  Paused
                </Badge>
              )}
            </CardTitle>
            <div className="flex gap-2">
              {["info", "warn", "error", "debug"].map((level) => {
                const count = logs.filter((l) => l.level === level).length;
                const config = levelConfig[level as keyof typeof levelConfig];
                return (
                  <Badge
                    key={level}
                    variant="outline"
                    className={cn("cursor-pointer", config.color)}
                    onClick={() => setLevelFilter(level === levelFilter ? "all" : level)}
                  >
                    {level}: {count}
                  </Badge>
                );
              })}
            </div>
          </div>
        </CardHeader>
        <ScrollArea className="h-[calc(100vh-380px)]" ref={scrollRef}>
          <div className="p-4 space-y-1 font-mono text-sm">
            {filteredLogs.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                <ScrollText className="h-12 w-12 mx-auto mb-4 opacity-30" />
                <p>No log entries yet</p>
                <p className="text-xs mt-1">
                  {isConnected 
                    ? "Logs will appear here as operations occur" 
                    : "Connect to the backend to see live logs"}
                </p>
              </div>
            ) : (
              filteredLogs.map((log) => {
                const config = levelConfig[log.level];
                const Icon = config.icon;
                const SourceIcon = getSourceIcon(log.source);
                return (
                  <div
                    key={log.id}
                    className={cn(
                      "flex items-start gap-3 px-3 py-2 rounded-md hover:bg-muted/50 transition-colors group",
                      config.bg
                    )}
                  >
                    <span className="text-muted-foreground text-xs whitespace-nowrap pt-0.5">
                      {formatTime(log.timestamp)}
                    </span>
                    <Icon className={cn("h-4 w-4 flex-shrink-0 mt-0.5", config.color)} />
                    <Badge variant="outline" className="text-xs px-1.5 py-0 flex items-center gap-1">
                      <SourceIcon className="h-3 w-3" />
                      {log.source}
                    </Badge>
                    <span className="flex-1 break-all">{log.message}</span>
                    {log.details && Object.keys(log.details).length > 0 && (
                      <code className="text-xs text-muted-foreground bg-muted px-2 py-1 rounded hidden group-hover:block max-w-[300px] truncate">
                        {JSON.stringify(log.details)}
                      </code>
                    )}
                  </div>
                );
              })
            )}
          </div>
        </ScrollArea>
      </Card>
    </div>
  );
}
