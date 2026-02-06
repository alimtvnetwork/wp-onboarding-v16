import { useState, useMemo } from "react";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import {
  Search,
  File,
  Folder,
  FolderOpen,
  ChevronRight,
  ChevronDown,
  Check,
  ArrowUp,
  ArrowDown,
  Plus,
  Minus,
  FileCode,
  FileText,
  FileJson,
  FileCog,
} from "lucide-react";
import { cn } from "@/lib/utils";
import type { FileChange } from "@/lib/api";

interface SyncTreeViewProps {
  changes: FileChange[];
  localFiles: number;
  remoteFiles: number;
  added: number;
  modified: number;
  deleted: number;
  inSync: boolean;
}

interface SyncTreeNode {
  name: string;
  path: string;
  type: "file" | "folder";
  change?: FileChange;
  children?: SyncTreeNode[];
  // Aggregated counts for folders
  addedCount?: number;
  modifiedCount?: number;
  deletedCount?: number;
}

function buildSyncTree(changes: FileChange[]): SyncTreeNode[] {
  const root: Record<string, SyncTreeNode> = {};

  for (const change of changes) {
    const parts = change.path.split("/");
    let current = root;

    for (let i = 0; i < parts.length; i++) {
      const part = parts[i];
      const isLast = i === parts.length - 1;
      const currentPath = parts.slice(0, i + 1).join("/");

      if (!current[part]) {
        current[part] = {
          name: part,
          path: currentPath,
          type: isLast ? "file" : "folder",
          change: isLast ? change : undefined,
          children: isLast ? undefined : [],
        };
      }

      if (!isLast) {
        if (!current[part].children) current[part].children = [];
        const childMap: Record<string, SyncTreeNode> = {};
        for (const child of current[part].children!) {
          childMap[child.name] = child;
        }
        current = childMap;
        // Sync back
        const parentNode = Object.values(root).length ? current : root;
        void parentNode; // Tree building continues via childMap
      }
    }
  }

  function toSortedArray(obj: Record<string, SyncTreeNode>): SyncTreeNode[] {
    const nodes = Object.values(obj);
    // Calculate folder aggregate counts
    for (const node of nodes) {
      if (node.type === "folder" && node.children) {
        node.children = toSortedArray(
          node.children.reduce((acc, c) => ({ ...acc, [c.name]: c }), {} as Record<string, SyncTreeNode>)
        );
        const countAll = (n: SyncTreeNode): { a: number; m: number; d: number } => {
          if (n.type === "file") {
            return {
              a: n.change?.status === "added" ? 1 : 0,
              m: n.change?.status === "modified" ? 1 : 0,
              d: n.change?.status === "deleted" ? 1 : 0,
            };
          }
          return (n.children || []).reduce(
            (acc, c) => {
              const cc = countAll(c);
              return { a: acc.a + cc.a, m: acc.m + cc.m, d: acc.d + cc.d };
            },
            { a: 0, m: 0, d: 0 }
          );
        };
        const counts = countAll(node);
        node.addedCount = counts.a;
        node.modifiedCount = counts.m;
        node.deletedCount = counts.d;
      }
    }
    return nodes.sort((a, b) => {
      if (a.type !== b.type) return a.type === "folder" ? -1 : 1;
      return a.name.localeCompare(b.name);
    });
  }

  return toSortedArray(root);
}

function getFileIcon(fileName: string) {
  const ext = fileName.split(".").pop()?.toLowerCase();
  switch (ext) {
    case "php":
      return <FileCode className="h-4 w-4 text-purple-500 dark:text-purple-400" />;
    case "js": case "ts": case "jsx": case "tsx":
      return <FileCode className="h-4 w-4 text-amber-500 dark:text-amber-400" />;
    case "css": case "scss": case "less":
      return <FileCode className="h-4 w-4 text-sky-500 dark:text-sky-400" />;
    case "json":
      return <FileJson className="h-4 w-4 text-emerald-500 dark:text-emerald-400" />;
    case "md": case "txt":
      return <FileText className="h-4 w-4 text-muted-foreground" />;
    case "yml": case "yaml": case "xml":
      return <FileCog className="h-4 w-4 text-orange-500 dark:text-orange-400" />;
    default:
      return <File className="h-4 w-4 text-muted-foreground" />;
  }
}

function getStatusIcon(change?: FileChange) {
  if (!change) return null;
  switch (change.status) {
    case "added":
      return <Plus className="h-3.5 w-3.5 text-emerald-500" />;
    case "deleted":
      return <Minus className="h-3.5 w-3.5 text-destructive" />;
    case "modified":
      if (change.direction === "remote_newer") {
        return <ArrowDown className="h-3.5 w-3.5 text-sky-500" />;
      }
      return <ArrowUp className="h-3.5 w-3.5 text-orange-500" />;
    default:
      return <Check className="h-3.5 w-3.5 text-primary" />;
  }
}

function getStatusBadge(change?: FileChange) {
  if (!change) return null;
  switch (change.direction) {
    case "local_newer":
      return <Badge variant="outline" className="text-[10px] px-1 py-0 text-orange-500 border-orange-500/30">Local newer</Badge>;
    case "remote_newer":
      return <Badge variant="outline" className="text-[10px] px-1 py-0 text-sky-500 border-sky-500/30">Remote newer</Badge>;
    case "local_only":
      return <Badge variant="outline" className="text-[10px] px-1 py-0 text-emerald-500 border-emerald-500/30">Local only</Badge>;
    case "remote_only":
      return <Badge variant="outline" className="text-[10px] px-1 py-0 text-destructive border-destructive/30">Remote only</Badge>;
    default:
      return null;
  }
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function formatTimestamp(iso?: string): string {
  if (!iso) return "—";
  try {
    const d = new Date(iso);
    return d.toLocaleString(undefined, {
      month: "short", day: "numeric", hour: "2-digit", minute: "2-digit", second: "2-digit",
    });
  } catch {
    return iso;
  }
}

function SyncTreeNodeItem({
  node,
  depth,
  expandedPaths,
  onToggle,
  selectedPath,
  onSelect,
  searchQuery,
}: {
  node: SyncTreeNode;
  depth: number;
  expandedPaths: Set<string>;
  onToggle: (path: string) => void;
  selectedPath: string | null;
  onSelect: (node: SyncTreeNode) => void;
  searchQuery: string;
}) {
  const isExpanded = expandedPaths.has(node.path);
  const isSelected = selectedPath === node.path;
  const matchesSearch = searchQuery && node.name.toLowerCase().includes(searchQuery.toLowerCase());

  const visibleChildren = useMemo(() => {
    if (!node.children || !searchQuery) return node.children;
    return node.children.filter(
      (child) =>
        child.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (child.children && child.children.some((gc) => gc.name.toLowerCase().includes(searchQuery.toLowerCase())))
    );
  }, [node.children, searchQuery]);

  const shouldShow = !searchQuery || matchesSearch || (visibleChildren && visibleChildren.length > 0);
  if (!shouldShow) return null;

  return (
    <div>
      <div
        className={cn(
          "flex items-center gap-1 py-1 px-2 cursor-pointer rounded hover:bg-accent/50 transition-colors",
          isSelected && "bg-accent",
          matchesSearch && "bg-yellow-500/10",
          node.change?.status === "added" && "bg-emerald-500/5",
          node.change?.status === "deleted" && "bg-destructive/5",
          node.change?.status === "modified" && "bg-orange-500/5",
        )}
        style={{ paddingLeft: `${depth * 16 + 8}px` }}
        onClick={() => {
          if (node.type === "folder") {
            onToggle(node.path);
          } else {
            onSelect(node);
          }
        }}
      >
        {node.type === "folder" ? (
          <>
            {isExpanded ? (
              <ChevronDown className="h-3 w-3 text-muted-foreground shrink-0" />
            ) : (
              <ChevronRight className="h-3 w-3 text-muted-foreground shrink-0" />
            )}
            {isExpanded ? (
              <FolderOpen className="h-4 w-4 text-amber-500 dark:text-amber-400 shrink-0" />
            ) : (
              <Folder className="h-4 w-4 text-amber-500 dark:text-amber-400 shrink-0" />
            )}
          </>
        ) : (
          <>
            <span className="w-3 flex justify-center shrink-0">{getStatusIcon(node.change)}</span>
            {getFileIcon(node.name)}
          </>
        )}
        <span className="text-sm truncate flex-1">{node.name}</span>
        {node.type === "folder" && (
          <div className="flex items-center gap-1 shrink-0">
            {(node.addedCount || 0) > 0 && (
              <span className="text-[10px] text-emerald-500">+{node.addedCount}</span>
            )}
            {(node.modifiedCount || 0) > 0 && (
              <span className="text-[10px] text-orange-500">~{node.modifiedCount}</span>
            )}
            {(node.deletedCount || 0) > 0 && (
              <span className="text-[10px] text-destructive">-{node.deletedCount}</span>
            )}
          </div>
        )}
        {node.type === "file" && getStatusBadge(node.change)}
      </div>
      {node.type === "folder" && isExpanded && visibleChildren && (
        <div>
          {visibleChildren.map((child) => (
            <SyncTreeNodeItem
              key={child.path}
              node={child}
              depth={depth + 1}
              expandedPaths={expandedPaths}
              onToggle={onToggle}
              selectedPath={selectedPath}
              onSelect={onSelect}
              searchQuery={searchQuery}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export function SyncTreeView({
  changes,
  localFiles,
  remoteFiles,
  added,
  modified,
  deleted,
  inSync,
}: SyncTreeViewProps) {
  const [searchQuery, setSearchQuery] = useState("");
  const [expandedPaths, setExpandedPaths] = useState<Set<string>>(new Set());
  const [selectedPath, setSelectedPath] = useState<string | null>(null);

  const tree = useMemo(() => buildSyncTree(changes), [changes]);

  const selectedChange = useMemo(() => {
    if (!selectedPath) return null;
    return changes.find((c) => c.path === selectedPath) || null;
  }, [selectedPath, changes]);

  const toggleExpand = (path: string) => {
    setExpandedPaths((prev) => {
      const next = new Set(prev);
      if (next.has(path)) next.delete(path);
      else next.add(path);
      return next;
    });
  };

  const expandAll = () => {
    const allPaths = new Set<string>();
    const addPaths = (nodes: SyncTreeNode[]) => {
      for (const n of nodes) {
        if (n.type === "folder") {
          allPaths.add(n.path);
          if (n.children) addPaths(n.children);
        }
      }
    };
    addPaths(tree);
    setExpandedPaths(allPaths);
  };

  if (inSync && changes.length === 0) {
    return (
      <div className="rounded-lg border border-primary/30 bg-primary/5 p-6 text-center">
        <Check className="h-8 w-8 text-primary mx-auto mb-2" />
        <p className="font-medium">All files are in sync</p>
        <p className="text-sm text-muted-foreground mt-1">
          {localFiles} local files • {remoteFiles} remote files
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {/* Summary stats */}
      <div className="flex flex-wrap gap-2">
        <Badge variant="secondary" className="text-xs">
          {localFiles} local
        </Badge>
        <Badge variant="secondary" className="text-xs">
          {remoteFiles} remote
        </Badge>
        {added > 0 && (
          <Badge className="text-xs bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30" variant="outline">
            <Plus className="h-3 w-3 mr-1" />{added} added
          </Badge>
        )}
        {modified > 0 && (
          <Badge className="text-xs bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30" variant="outline">
            <ArrowUp className="h-3 w-3 mr-1" />{modified} modified
          </Badge>
        )}
        {deleted > 0 && (
          <Badge className="text-xs bg-destructive/15 text-destructive border-destructive/30" variant="outline">
            <Minus className="h-3 w-3 mr-1" />{deleted} deleted
          </Badge>
        )}
      </div>

      {/* Search & controls */}
      <div className="flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search files..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-9 h-8 text-sm"
          />
        </div>
        <Button variant="outline" size="sm" className="text-xs h-8" onClick={expandAll}>
          Expand All
        </Button>
        <Button variant="outline" size="sm" className="text-xs h-8" onClick={() => setExpandedPaths(new Set())}>
          Collapse
        </Button>
      </div>

      {/* Tree + Detail panel */}
      <div className="flex gap-3 min-h-0">
        {/* Tree */}
        <div className="flex-1 border rounded-md overflow-hidden">
          <ScrollArea className="h-[280px]">
            <div className="py-1">
              {tree.map((node) => (
                <SyncTreeNodeItem
                  key={node.path}
                  node={node}
                  depth={0}
                  expandedPaths={expandedPaths}
                  onToggle={toggleExpand}
                  selectedPath={selectedPath}
                  onSelect={(n) => setSelectedPath(n.path)}
                  searchQuery={searchQuery}
                />
              ))}
            </div>
          </ScrollArea>
        </div>

        {/* Detail panel */}
        {selectedChange && (
          <div className="w-56 border rounded-md p-3 space-y-3 text-sm shrink-0">
            <div>
              <p className="text-xs text-muted-foreground">File</p>
              <p className="font-medium truncate">{selectedChange.path.split("/").pop()}</p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground">Status</p>
              <div className="flex items-center gap-1.5 mt-0.5">
                {getStatusIcon(selectedChange)}
                <span className="capitalize">{selectedChange.status}</span>
              </div>
            </div>
            {selectedChange.direction && (
              <div>
                <p className="text-xs text-muted-foreground">Priority</p>
                {getStatusBadge(selectedChange)}
              </div>
            )}
            {selectedChange.localModifiedAt && (
              <div>
                <p className="text-xs text-muted-foreground">Local Modified</p>
                <p className="text-xs">{formatTimestamp(selectedChange.localModifiedAt)}</p>
              </div>
            )}
            {selectedChange.remoteModifiedAt && (
              <div>
                <p className="text-xs text-muted-foreground">Remote Modified</p>
                <p className="text-xs">{formatTimestamp(selectedChange.remoteModifiedAt)}</p>
              </div>
            )}
            {(selectedChange.localSize || selectedChange.remoteSize) ? (
              <div>
                <p className="text-xs text-muted-foreground">Size</p>
                <p className="text-xs">
                  {selectedChange.localSize ? formatSize(selectedChange.localSize) : "—"}
                  {selectedChange.remoteSize ? ` → ${formatSize(selectedChange.remoteSize)}` : ""}
                </p>
              </div>
            ) : null}
            {selectedChange.localHash && (
              <div>
                <p className="text-xs text-muted-foreground">Local Hash</p>
                <p className="text-xs font-mono truncate">{selectedChange.localHash.slice(0, 12)}…</p>
              </div>
            )}
            {selectedChange.remoteHash && (
              <div>
                <p className="text-xs text-muted-foreground">Remote Hash</p>
                <p className="text-xs font-mono truncate">{selectedChange.remoteHash.slice(0, 12)}…</p>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
