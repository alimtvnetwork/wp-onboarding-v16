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
  RefreshCw,
  Filter,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useWebSocket } from "@/hooks/useWebSocket";

interface LogEntry {
  id: string;
  timestamp: string;
  level: "info" | "warn" | "error" | "debug";
  source: string;
  message: string;
  details?: Record<string, unknown>;
}

const mockLogs: LogEntry[] = [
  {
    id: "1",
    timestamp: new Date().toISOString(),
    level: "info",
    source: "sync",
    message: "Starting sync check for plugin 'my-plugin'",
  },
  {
    id: "2",
    timestamp: new Date(Date.now() - 1000).toISOString(),
    level: "debug",
    source: "watcher",
    message: "File change detected: src/includes/class-main.php",
  },
  {
    id: "3",
    timestamp: new Date(Date.now() - 2000).toISOString(),
    level: "info",
    source: "sync",
    message: "Comparing 45 local files with remote",
  },
  {
    id: "4",
    timestamp: new Date(Date.now() - 3000).toISOString(),
    level: "warn",
    source: "sync",
    message: "3 files differ from remote version",
  },
  {
    id: "5",
    timestamp: new Date(Date.now() - 4000).toISOString(),
    level: "info",
    source: "publish",
    message: "Uploading changed files as zip archive",
  },
  {
    id: "6",
    timestamp: new Date(Date.now() - 5000).toISOString(),
    level: "info",
    source: "publish",
    message: "Upload complete: 125KB transferred",
  },
  {
    id: "7",
    timestamp: new Date(Date.now() - 10000).toISOString(),
    level: "error",
    source: "connection",
    message: "Failed to connect to site 'staging.example.com'",
    details: { code: 401, reason: "Invalid credentials" },
  },
];

const levelConfig = {
  info: { icon: Info, color: "text-blue-500", bg: "bg-blue-500/10" },
  warn: { icon: AlertTriangle, color: "text-amber-500", bg: "bg-amber-500/10" },
  error: { icon: AlertCircle, color: "text-destructive", bg: "bg-destructive/10" },
  debug: { icon: Bug, color: "text-muted-foreground", bg: "bg-muted" },
};

export default function Logs() {
  const [logs, setLogs] = useState<LogEntry[]>(mockLogs);
  const [filter, setFilter] = useState("");
  const [levelFilter, setLevelFilter] = useState<string>("all");
  const [sourceFilter, setSourceFilter] = useState<string>("all");
  const [isPaused, setIsPaused] = useState(false);
  const [autoScroll, setAutoScroll] = useState(true);
  const scrollRef = useRef<HTMLDivElement>(null);
  const { lastMessage } = useWebSocket();

  // Add new log entries from WebSocket
  useEffect(() => {
    if (lastMessage && lastMessage.type === "log" && !isPaused) {
      setLogs((prev) => [lastMessage.data as LogEntry, ...prev].slice(0, 1000));
    }
  }, [lastMessage, isPaused]);

  // Auto-scroll to bottom
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
    const content = filteredLogs
      .map((l) => `[${l.timestamp}] [${l.level.toUpperCase()}] [${l.source}] ${l.message}`)
      .join("\n");
    const blob = new Blob([content], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `logs-${new Date().toISOString().split("T")[0]}.txt`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const formatTime = (timestamp: string) => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString("en-US", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: false,
    });
  };

  return (
    <div className="space-y-6 h-full flex flex-col">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold">Logs</h1>
          <p className="text-muted-foreground">
            Real-time activity logs and operation history
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
                <p>No log entries</p>
                <p className="text-xs mt-1">
                  Logs will appear here as operations occur
                </p>
              </div>
            ) : (
              filteredLogs.map((log) => {
                const config = levelConfig[log.level];
                const Icon = config.icon;
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
                    <Badge variant="outline" className="text-xs px-1.5 py-0">
                      {log.source}
                    </Badge>
                    <span className="flex-1 break-all">{log.message}</span>
                    {log.details && (
                      <code className="text-xs text-muted-foreground bg-muted px-2 py-1 rounded hidden group-hover:block">
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
