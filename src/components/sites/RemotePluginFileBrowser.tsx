import { useState, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Input } from "@/components/ui/input";
import {
  Loader2,
  Search,
  File,
  Folder,
  FolderOpen,
  ChevronRight,
  ChevronDown,
  Copy,
  Download,
  RefreshCw,
  FileCode,
  FileText,
  FileJson,
  FileCog,
  Hash,
} from "lucide-react";
import { api, RemotePluginFile, requireSuccess } from "@/lib/api";
import { toast } from "sonner";
import { cn } from "@/lib/utils";
import { SyntaxHighlighter } from "@/components/shared/SyntaxHighlighter";

interface RemotePluginFileBrowserProps {
  siteId: number;
  siteName: string;
  pluginSlug: string;
  pluginName: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

interface TreeNode {
  name: string;
  path: string;
  type: "file" | "folder";
  size?: number;
  hash?: string;
  children?: TreeNode[];
}

// Build tree structure from flat file list
function buildTree(files: RemotePluginFile[]): TreeNode[] {
  const root: Record<string, TreeNode> = {};

  for (const file of files) {
    const parts = file.path.split("/");
    let current: Record<string, TreeNode> = root;

    for (let i = 0; i < parts.length; i++) {
      const part = parts[i];
      const isLast = i === parts.length - 1;
      const currentPath = parts.slice(0, i + 1).join("/");

      if (!current[part]) {
        current[part] = {
          name: part,
          path: currentPath,
          type: isLast ? "file" : "folder",
          size: isLast ? file.size : undefined,
          hash: isLast ? file.hash : undefined,
          children: isLast ? undefined : [],
        };
      }

      if (!isLast && current[part].children) {
        // Convert children array to a lookup object for building
        const childMap: Record<string, TreeNode> = {};
        for (const child of current[part].children!) {
          childMap[child.name] = child;
        }
        current = childMap;
        // Update the children array reference
        current[part] = current[part] || { name: part, path: currentPath, type: "folder", children: [] };
      }
    }
  }

  // Convert root object to sorted array
  function toSortedArray(obj: Record<string, TreeNode>): TreeNode[] {
    return Object.values(obj)
      .sort((a, b) => {
        if (a.type !== b.type) return a.type === "folder" ? -1 : 1;
        return a.name.localeCompare(b.name);
      });
  }

  return toSortedArray(root);
}

// Get file icon based on extension
function getFileIcon(fileName: string) {
  const ext = fileName.split(".").pop()?.toLowerCase();
  switch (ext) {
    case "php":
      return <FileCode className="h-4 w-4 text-purple-500 dark:text-purple-400" />;
    case "js":
    case "ts":
    case "jsx":
    case "tsx":
      return <FileCode className="h-4 w-4 text-amber-500 dark:text-amber-400" />;
    case "css":
    case "scss":
    case "less":
      return <FileCode className="h-4 w-4 text-sky-500 dark:text-sky-400" />;
    case "json":
      return <FileJson className="h-4 w-4 text-emerald-500 dark:text-emerald-400" />;
    case "md":
    case "txt":
    case "readme":
      return <FileText className="h-4 w-4 text-muted-foreground" />;
    case "yml":
    case "yaml":
    case "xml":
      return <FileCog className="h-4 w-4 text-orange-500 dark:text-orange-400" />;
    default:
      return <File className="h-4 w-4 text-muted-foreground" />;
  }
}

// Format file size
function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

// Tree node component
function TreeNodeItem({
  node,
  depth,
  expandedPaths,
  onToggle,
  onSelect,
  selectedPath,
  searchQuery,
}: {
  node: TreeNode;
  depth: number;
  expandedPaths: Set<string>;
  onToggle: (path: string) => void;
  onSelect: (node: TreeNode) => void;
  selectedPath: string | null;
  searchQuery: string;
}) {
  const isExpanded = expandedPaths.has(node.path);
  const isSelected = selectedPath === node.path;
  const matchesSearch = searchQuery && node.name.toLowerCase().includes(searchQuery.toLowerCase());

  // Filter children if search is active
  const visibleChildren = useMemo(() => {
    if (!node.children || !searchQuery) return node.children;
    return node.children.filter(
      (child) =>
        child.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (child.children && child.children.some((gc) => gc.name.toLowerCase().includes(searchQuery.toLowerCase())))
    );
  }, [node.children, searchQuery]);

  // Auto-expand when search matches
  const shouldShow = !searchQuery || matchesSearch || (visibleChildren && visibleChildren.length > 0);
  if (!shouldShow) return null;

  return (
    <div>
      <div
        className={cn(
          "flex items-center gap-1 py-1 px-2 cursor-pointer rounded hover:bg-accent/50 transition-colors",
          isSelected && "bg-accent",
          matchesSearch && "bg-yellow-500/10"
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
            <span className="w-3" /> {/* Spacer for alignment */}
            {getFileIcon(node.name)}
          </>
        )}
        <span className="text-sm truncate flex-1">{node.name}</span>
        {node.type === "file" && node.size !== undefined && (
          <span className="text-xs text-muted-foreground shrink-0">{formatSize(node.size)}</span>
        )}
      </div>
      {node.type === "folder" && isExpanded && visibleChildren && (
        <div>
          {visibleChildren.map((child) => (
            <TreeNodeItem
              key={child.path}
              node={child}
              depth={depth + 1}
              expandedPaths={expandedPaths}
              onToggle={onToggle}
              onSelect={onSelect}
              selectedPath={selectedPath}
              searchQuery={searchQuery}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export function RemotePluginFileBrowser({
  siteId,
  siteName,
  pluginSlug,
  pluginName,
  open,
  onOpenChange,
}: RemotePluginFileBrowserProps) {
  const [searchQuery, setSearchQuery] = useState("");
  const [expandedPaths, setExpandedPaths] = useState<Set<string>>(new Set());
  const [selectedFile, setSelectedFile] = useState<TreeNode | null>(null);
  const [fileContent, setFileContent] = useState<string | null>(null);
  const [loadingContent, setLoadingContent] = useState(false);

  const queryKey = ["sites", siteId, "remote-plugins", pluginSlug, "files"];

  const { data: filesResult, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey,
    queryFn: async () => {
      const response = await api.getRemotePluginFiles(siteId, pluginSlug);
      return requireSuccess(response, { endpoint: `/sites/${siteId}/remote-plugins/${pluginSlug}/files`, method: "GET" });
    },
    enabled: open,
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: false,
    meta: { suppressGlobalError: true },
  });

  const tree = useMemo(() => {
    if (!filesResult?.files) return [];
    return buildTree(filesResult.files);
  }, [filesResult?.files]);

  const toggleExpand = (path: string) => {
    setExpandedPaths((prev) => {
      const next = new Set(prev);
      if (next.has(path)) {
        next.delete(path);
      } else {
        next.add(path);
      }
      return next;
    });
  };

  const handleSelectFile = async (node: TreeNode) => {
    if (node.type !== "file") return;
    setSelectedFile(node);
    setFileContent(null);
    setLoadingContent(true);

    try {
      const response = await api.getRemotePluginFileContent(siteId, pluginSlug, node.path);
      const data = requireSuccess(response, {
        endpoint: `/sites/${siteId}/remote-plugins/${pluginSlug}/file`,
        method: "POST",
      });
      setFileContent(data.content);
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : "Failed to load file content");
      setFileContent(null);
    } finally {
      setLoadingContent(false);
    }
  };

  const copyContent = () => {
    if (fileContent) {
      navigator.clipboard.writeText(fileContent);
      toast.success("Content copied to clipboard");
    }
  };

  const downloadFile = () => {
    if (fileContent && selectedFile) {
      const blob = new Blob([fileContent], { type: "text/plain" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = selectedFile.name;
      a.click();
      URL.revokeObjectURL(url);
      toast.success(`Downloaded ${selectedFile.name}`);
    }
  };

  const expandAll = () => {
    if (!tree.length) return;
    const allPaths = new Set<string>();
    const addPaths = (nodes: TreeNode[]) => {
      for (const node of nodes) {
        if (node.type === "folder") {
          allPaths.add(node.path);
          if (node.children) addPaths(node.children);
        }
      }
    };
    addPaths(tree);
    setExpandedPaths(allPaths);
  };

  const collapseAll = () => {
    setExpandedPaths(new Set());
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-5xl max-h-[85vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Folder className="h-5 w-5 text-amber-500 dark:text-amber-400" />
            {pluginName} Files
          </DialogTitle>
          <DialogDescription>
            Browsing remote plugin files on {siteName}
          </DialogDescription>
        </DialogHeader>

        <div className="flex items-center gap-2 mb-2">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search files..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9"
            />
          </div>
          <Button variant="outline" size="sm" onClick={expandAll}>
            Expand All
          </Button>
          <Button variant="outline" size="sm" onClick={collapseAll}>
            Collapse
          </Button>
          <Button variant="outline" size="icon" onClick={() => refetch()} disabled={isFetching}>
            <RefreshCw className={cn("h-4 w-4", isFetching && "animate-spin")} />
          </Button>
        </div>

        {filesResult && (
          <div className="flex items-center gap-2 mb-2 text-sm text-muted-foreground">
            <Badge variant="secondary">{filesResult.totalFiles} files</Badge>
            <span>•</span>
            <span>
              Total size:{" "}
              {formatSize(filesResult.files.reduce((sum, f) => sum + f.size, 0))}
            </span>
          </div>
        )}

        <div className="flex-1 flex gap-4 min-h-0">
          {/* File tree panel */}
          <div className="w-1/3 border rounded-md overflow-hidden">
            <ScrollArea className="h-[400px]">
              {isLoading ? (
                <div className="flex items-center justify-center h-full text-muted-foreground">
                  <Loader2 className="h-5 w-5 animate-spin mr-2" />
                  Loading files...
                </div>
              ) : isError ? (
                <div className="flex flex-col items-center justify-center h-full text-muted-foreground p-4">
                  <p>Failed to load files</p>
                  <Button variant="outline" size="sm" className="mt-2" onClick={() => refetch()}>
                    Retry
                  </Button>
                </div>
              ) : tree.length === 0 ? (
                <div className="flex items-center justify-center h-full text-muted-foreground">
                  No files found
                </div>
              ) : (
                <div className="py-2">
                  {tree.map((node) => (
                    <TreeNodeItem
                      key={node.path}
                      node={node}
                      depth={0}
                      expandedPaths={expandedPaths}
                      onToggle={toggleExpand}
                      onSelect={handleSelectFile}
                      selectedPath={selectedFile?.path || null}
                      searchQuery={searchQuery}
                    />
                  ))}
                </div>
              )}
            </ScrollArea>
          </div>

          {/* File content panel */}
          <div className="flex-1 border rounded-md overflow-hidden flex flex-col">
            {selectedFile ? (
              <>
                <div className="flex items-center justify-between px-3 py-2 border-b bg-muted/30">
                  <div className="flex items-center gap-2 min-w-0">
                    {getFileIcon(selectedFile.name)}
                    <span className="text-sm font-medium truncate">{selectedFile.path}</span>
                  </div>
                  <div className="flex items-center gap-1 shrink-0">
                    {selectedFile.hash && (
                      <Badge variant="outline" className="text-xs font-mono">
                        <Hash className="h-3 w-3 mr-1" />
                        {selectedFile.hash.slice(0, 8)}
                      </Badge>
                    )}
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={copyContent} disabled={!fileContent}>
                      <Copy className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={downloadFile} disabled={!fileContent}>
                      <Download className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>
                <ScrollArea className="flex-1 h-[400px]">
                  {loadingContent ? (
                    <div className="flex items-center justify-center h-full text-muted-foreground py-8">
                      <Loader2 className="h-5 w-5 animate-spin mr-2" />
                      Loading content...
                    </div>
                  ) : fileContent !== null ? (
                    <SyntaxHighlighter
                      code={fileContent}
                      fileName={selectedFile.name}
                      showLineNumbers={true}
                    />
                  ) : (
                    <div className="flex items-center justify-center h-full text-muted-foreground py-8">
                      Failed to load content
                    </div>
                  )}
                </ScrollArea>
              </>
            ) : (
              <div className="flex items-center justify-center h-full text-muted-foreground">
                Select a file to view its content
              </div>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
