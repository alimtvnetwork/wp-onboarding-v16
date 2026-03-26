import { useState, useMemo, useCallback, useRef, useEffect, Fragment } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Copy,
  Search,
  X,
  ChevronUp,
  ChevronDown,
  AlertTriangle,
  Filter,
} from "lucide-react";
import { toast } from "sonner";
import type { LogRetrieveFileData } from "@/lib/api/types";
import { cn } from "@/lib/utils";

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}

// ── Log-line severity detection ────────────────────────────────
type Severity = "error" | "warning" | "info" | "debug" | "date" | "plain";

const SEV_PATTERNS: [RegExp, Severity][] = [
  [/\b(fatal|exception|critical)\b/i, "error"],
  [/\b(error|err|fail(ed|ure)?)\b/i, "error"],
  [/\b(warn(ing)?)\b/i, "warning"],
  [/\b(notice|info)\b/i, "info"],
  [/\b(debug|trace)\b/i, "debug"],
  [/^\d{4}[-/]\d{2}[-/]\d{2}[T ]\d{2}:\d{2}/, "date"],
];

function detectSeverity(line: string): Severity {
  for (const [re, sev] of SEV_PATTERNS) {
    if (re.test(line)) return sev;
  }
  return "plain";
}

const SEV_CLASSES: Record<Severity, string> = {
  error:   "text-red-400",
  warning: "text-amber-400",
  info:    "text-sky-400",
  debug:   "text-muted-foreground/70",
  date:    "text-emerald-400",
  plain:   "text-foreground",
};

const FILTER_OPTIONS: { value: string; label: string }[] = [
  { value: "all",     label: "All" },
  { value: "error",   label: "Errors" },
  { value: "warning", label: "Warnings" },
  { value: "info",    label: "Info" },
  { value: "debug",   label: "Debug" },
];

// ── Highlighted line rendering ─────────────────────────────────
function HighlightedLine({
  line,
  lineNumber,
  searchTerm,
  isMatch,
  isCurrentMatch,
}: {
  line: string;
  lineNumber: number;
  searchTerm: string;
  isMatch: boolean;
  isCurrentMatch: boolean;
}) {
  const severity = detectSeverity(line);
  const colorClass = SEV_CLASSES[severity];

  // Build the line content with search highlights
  const parts = useMemo(() => {
    if (!searchTerm) return [{ text: line, highlight: false }];
    const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`, "gi");
    const segments: { text: string; highlight: boolean }[] = [];
    let last = 0;
    let match: RegExpExecArray | null;
    while ((match = regex.exec(line)) !== null) {
      if (match.index > last) segments.push({ text: line.slice(last, match.index), highlight: false });
      segments.push({ text: match[1], highlight: true });
      last = regex.lastIndex;
    }
    if (last < line.length) segments.push({ text: line.slice(last), highlight: false });
    return segments.length ? segments : [{ text: line, highlight: false }];
  }, [line, searchTerm]);

  return (
    <div
      className={cn(
        "flex gap-0 leading-[1.6] transition-colors duration-150",
        isCurrentMatch && "bg-amber-500/20 rounded",
        isMatch && !isCurrentMatch && "bg-amber-500/8 rounded",
      )}
    >
      <span className="select-none w-12 shrink-0 text-right pr-3 text-muted-foreground/50 text-[11px] tabular-nums">
        {lineNumber}
      </span>
      <span className={cn("flex-1", colorClass)}>
        {parts.map((p, i) =>
          p.highlight ? (
            <mark key={i} className="bg-amber-400/40 text-inherit rounded-sm px-0.5">{p.text}</mark>
          ) : (
            <Fragment key={i}>{p.text}</Fragment>
          )
        )}
      </span>
    </div>
  );
}

// ── Main LogContentViewer ──────────────────────────────────────
interface LogContentViewerProps {
  file?: LogRetrieveFileData;
  label: string;
}

export function LogContentViewer({ file, label }: LogContentViewerProps) {
  const [searchTerm, setSearchTerm] = useState("");
  const [showSearch, setShowSearch] = useState(false);
  const [severityFilter, setSeverityFilter] = useState("all");
  const [currentMatchIdx, setCurrentMatchIdx] = useState(0);
  const scrollRef = useRef<HTMLDivElement>(null);
  const matchRefs = useRef<Map<number, HTMLDivElement>>(new Map());

  if (!file) return <p className="text-sm text-muted-foreground py-4 text-center">Not requested</p>;
  if (!file.Exists) return <p className="text-sm text-muted-foreground py-4 text-center">No {label} file found.</p>;

  const handleCopy = () => {
    navigator.clipboard.writeText(file.Content);
    toast.success(`${label} copied to clipboard`);
  };

  // Parse lines with severity
  const allLines = useMemo(() => {
    const raw = (file.Content || "").split("\n");
    return raw.map((text, i) => ({
      text,
      lineNumber: i + 1,
      severity: detectSeverity(text),
    }));
  }, [file.Content]);

  // Apply severity filter
  const filteredLines = useMemo(() => {
    if (severityFilter === "all") return allLines;
    return allLines.filter((l) => l.severity === severityFilter);
  }, [allLines, severityFilter]);

  // Find search matches (indices into filteredLines)
  const matchIndices = useMemo(() => {
    if (!searchTerm) return [];
    const term = searchTerm.toLowerCase();
    return filteredLines
      .map((l, idx) => (l.text.toLowerCase().includes(term) ? idx : -1))
      .filter((i) => i !== -1);
  }, [filteredLines, searchTerm]);

  const matchCount = matchIndices.length;

  // Clamp currentMatchIdx
  useEffect(() => {
    if (currentMatchIdx >= matchCount) setCurrentMatchIdx(Math.max(0, matchCount - 1));
  }, [matchCount, currentMatchIdx]);

  // Scroll to current match
  useEffect(() => {
    if (matchCount === 0) return;
    const lineIdx = matchIndices[currentMatchIdx];
    const el = matchRefs.current.get(lineIdx);
    el?.scrollIntoView({ block: "center", behavior: "smooth" });
  }, [currentMatchIdx, matchIndices, matchCount]);

  const navigateMatch = useCallback(
    (dir: 1 | -1) => {
      setCurrentMatchIdx((prev) => {
        const next = prev + dir;
        if (next < 0) return matchCount - 1;
        if (next >= matchCount) return 0;
        return next;
      });
    },
    [matchCount]
  );

  // Keyboard shortcut
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === "f" && file.Exists) {
        e.preventDefault();
        setShowSearch(true);
      }
      if (e.key === "Escape") setShowSearch(false);
      if (showSearch && e.key === "Enter") {
        e.preventDefault();
        navigateMatch(e.shiftKey ? -1 : 1);
      }
    };
    window.addEventListener("keydown", handler);
    return () => window.removeEventListener("keydown", handler);
  }, [showSearch, navigateMatch, file.Exists]);

  // Severity counts
  const counts = useMemo(() => {
    const c = { error: 0, warning: 0, info: 0, debug: 0 };
    for (const l of allLines) {
      if (l.severity in c) c[l.severity as keyof typeof c]++;
    }
    return c;
  }, [allLines]);

  return (
    <div className="space-y-3">
      {/* Metadata row */}
      <div className="flex items-center gap-2 flex-wrap text-xs text-muted-foreground">
        <Badge variant="outline" className="text-xs font-mono border-primary/30 bg-primary/10 text-primary">
          {file.Lines} / {file.TotalLines} lines
        </Badge>
        <Badge variant="outline" className="text-xs font-mono border-border/60 bg-muted/50">
          {formatBytes(file.TotalSize)}
        </Badge>
        {file.Truncated && <Badge variant="destructive" className="text-xs">Truncated</Badge>}

        {/* Severity counts */}
        {counts.error > 0 && (
          <Badge
            variant="outline"
            className="text-xs cursor-pointer border-red-500/30 bg-red-500/10 text-red-400 hover:bg-red-500/20"
            onClick={() => setSeverityFilter(severityFilter === "error" ? "all" : "error")}
          >
            {counts.error} errors
          </Badge>
        )}
        {counts.warning > 0 && (
          <Badge
            variant="outline"
            className="text-xs cursor-pointer border-amber-500/30 bg-amber-500/10 text-amber-400 hover:bg-amber-500/20"
            onClick={() => setSeverityFilter(severityFilter === "warning" ? "all" : "warning")}
          >
            {counts.warning} warnings
          </Badge>
        )}

        <div className="ml-auto flex items-center gap-1">
          <Button size="sm" variant="ghost" className="h-6 px-2" onClick={() => setShowSearch((s) => !s)}>
            <Search className="h-3 w-3 mr-1" /> Search
          </Button>
          <Button size="sm" variant="ghost" className="h-6 px-2" onClick={handleCopy}>
            <Copy className="h-3 w-3 mr-1" /> Copy
          </Button>
        </div>
      </div>

      {/* Search bar */}
      {showSearch && (
        <div className="flex items-center gap-2 rounded-xl border border-border/60 bg-muted/30 px-3 py-2">
          <Search className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
          <Input
            placeholder="Search logs…"
            value={searchTerm}
            onChange={(e) => { setSearchTerm(e.target.value); setCurrentMatchIdx(0); }}
            className="h-7 border-0 bg-transparent text-sm focus-visible:ring-0 px-0"
            autoFocus
          />
          {searchTerm && (
            <span className="text-xs text-muted-foreground shrink-0 tabular-nums">
              {matchCount > 0 ? `${currentMatchIdx + 1}/${matchCount}` : "0 results"}
            </span>
          )}
          <div className="flex items-center gap-0.5">
            <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={() => navigateMatch(-1)} disabled={matchCount === 0}>
              <ChevronUp className="h-3.5 w-3.5" />
            </Button>
            <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={() => navigateMatch(1)} disabled={matchCount === 0}>
              <ChevronDown className="h-3.5 w-3.5" />
            </Button>
          </div>
          {/* Severity filter */}
          <Select value={severityFilter} onValueChange={setSeverityFilter}>
            <SelectTrigger className="h-7 w-[100px] text-xs border-border/40">
              <Filter className="h-3 w-3 mr-1 text-muted-foreground" />
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {FILTER_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value} className="text-xs">{opt.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button size="sm" variant="ghost" className="h-6 w-6 p-0" onClick={() => { setShowSearch(false); setSearchTerm(""); }}>
            <X className="h-3.5 w-3.5" />
          </Button>
        </div>
      )}

      {file.Truncated && (
        <div className="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-muted-foreground">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-amber-600" />
          Showing last {file.Lines} of {file.TotalLines} lines. Increase max lines to see more.
        </div>
      )}

      {/* Filter active indicator */}
      {severityFilter !== "all" && (
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <Filter className="h-3 w-3" />
          Showing <span className="font-medium text-foreground">{filteredLines.length}</span> of {allLines.length} lines
          (filter: <span className="capitalize font-medium text-foreground">{severityFilter}</span>)
          <Button size="sm" variant="ghost" className="h-5 px-1.5 text-xs" onClick={() => setSeverityFilter("all")}>
            Clear
          </Button>
        </div>
      )}

      {/* Content */}
      <ScrollArea className="h-[460px] rounded-xl border border-border/60 bg-muted/20 shadow-sm" ref={scrollRef}>
        <div className="p-4 font-mono text-[13px] leading-[1.6]">
          {filteredLines.length === 0 ? (
            <p className="text-center text-sm text-muted-foreground py-8">
              {severityFilter !== "all" ? "No lines match this filter." : "(empty)"}
            </p>
          ) : (
            filteredLines.map((line, idx) => {
              const isMatch = matchIndices.includes(idx);
              const isCurrentMatch = matchCount > 0 && matchIndices[currentMatchIdx] === idx;
              return (
                <div
                  key={line.lineNumber}
                  ref={(el) => {
                    if (el && isMatch) matchRefs.current.set(idx, el);
                    else matchRefs.current.delete(idx);
                  }}
                >
                  <HighlightedLine
                    line={line.text}
                    lineNumber={line.lineNumber}
                    searchTerm={searchTerm}
                    isMatch={isMatch}
                    isCurrentMatch={isCurrentMatch}
                  />
                </div>
              );
            })
          )}
        </div>
      </ScrollArea>
    </div>
  );
}