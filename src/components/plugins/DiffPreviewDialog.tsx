import { useState, useEffect, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Input } from "@/components/ui/input";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Checkbox } from "@/components/ui/checkbox";
import {
  FilePlus,
  FileEdit,
  FileX,
  Files,
  Search,
  Loader2,
  AlertCircle,
  Upload,
  FolderOpen,
  HardDrive,
  CheckSquare,
  Square,
  Eye,
  ArrowRight,
  Tag,
} from "lucide-react";
import { api, FilePreview, PublishPreview } from "@/lib/api";
import { cn } from "@/lib/utils";
import { ContentDiffViewer } from "./ContentDiffViewer";

interface DiffPreviewDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pluginId: number;
  pluginName: string;
  siteId: number;
  siteName: string;
  onConfirm: (selectedFiles?: string[]) => void;
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

function getFileIcon(changeType: string) {
  switch (changeType) {
    case "added":
      return <FilePlus className="h-4 w-4 text-green-500" />;
    case "modified":
      return <FileEdit className="h-4 w-4 text-yellow-500" />;
    case "deleted":
      return <FileX className="h-4 w-4 text-red-500" />;
    default:
      return <Files className="h-4 w-4 text-muted-foreground" />;
  }
}

function getChangeTypeLabel(changeType: string) {
  switch (changeType) {
    case "added":
      return "New";
    case "modified":
      return "Modified";
    case "deleted":
      return "Deleted";
    default:
      return changeType;
  }
}

function getChangeTypeColor(changeType: string) {
  switch (changeType) {
    case "added":
      return "bg-green-500/10 text-green-600 border-green-500/20";
    case "modified":
      return "bg-yellow-500/10 text-yellow-600 border-yellow-500/20";
    case "deleted":
      return "bg-red-500/10 text-red-600 border-red-500/20";
    default:
      return "";
  }
}

export function DiffPreviewDialog({
  open,
  onOpenChange,
  pluginId,
  pluginName,
  siteId,
  siteName,
  onConfirm,
}: DiffPreviewDialogProps) {
  const [searchQuery, setSearchQuery] = useState("");
  const [activeTab, setActiveTab] = useState<string>("all");
  const [selectedFiles, setSelectedFiles] = useState<Set<string>>(new Set());
  const [diffViewerOpen, setDiffViewerOpen] = useState(false);
  const [diffViewerFile, setDiffViewerFile] = useState<FilePreview | null>(null);

  const { data: preview, isLoading, error } = useQuery({
    queryKey: ["publish-preview", pluginId, siteId],
    queryFn: async () => {
      const response = await api.previewPublish(pluginId, siteId);
      if (!response.success || !response.data) {
        throw new Error(response.error?.message || "Failed to load preview");
      }
      return response.data;
    },
    enabled: open,
    staleTime: 30000, // Cache for 30 seconds
  });

  // Initialize all files as selected when preview loads
  useEffect(() => {
    if (preview?.files) {
      setSelectedFiles(new Set(preview.files.map(f => f.path)));
    }
  }, [preview]);

  // Reset state when dialog closes
  useEffect(() => {
    if (!open) {
      setSearchQuery("");
      setActiveTab("all");
    }
  }, [open]);

  // Filter files based on search and tab
  const filteredFiles = useMemo(() => {
    return preview?.files.filter((file) => {
      const matchesSearch = file.path.toLowerCase().includes(searchQuery.toLowerCase());
      const matchesTab =
        activeTab === "all" ||
        (activeTab === "added" && file.changeType === "added") ||
        (activeTab === "modified" && file.changeType === "modified") ||
        (activeTab === "deleted" && file.changeType === "deleted");
      return matchesSearch && matchesTab;
    }) || [];
  }, [preview, searchQuery, activeTab]);

  // Group files by directory for display
  const filesByDir = useMemo(() => {
    return filteredFiles.reduce<Record<string, FilePreview[]>>((acc, file) => {
      const parts = file.path.split("/");
      const dir = parts.length > 1 ? parts.slice(0, -1).join("/") : ".";
      if (!acc[dir]) acc[dir] = [];
      acc[dir].push(file);
      return acc;
    }, {});
  }, [filteredFiles]);

  // Calculate selection stats
  const selectionStats = useMemo(() => {
    if (!preview?.files) return { selected: 0, total: 0, selectedSize: 0 };
    const selected = preview.files.filter(f => selectedFiles.has(f.path));
    return {
      selected: selected.length,
      total: preview.files.length,
      selectedSize: selected.reduce((sum, f) => sum + f.size, 0),
    };
  }, [preview, selectedFiles]);

  // Check if all visible files are selected
  const allVisibleSelected = filteredFiles.length > 0 && 
    filteredFiles.every(f => selectedFiles.has(f.path));
  const someVisibleSelected = filteredFiles.some(f => selectedFiles.has(f.path));

  const handleToggleFile = (path: string) => {
    setSelectedFiles(prev => {
      const next = new Set(prev);
      if (next.has(path)) {
        next.delete(path);
      } else {
        next.add(path);
      }
      return next;
    });
  };

  const handleSelectAll = () => {
    if (allVisibleSelected) {
      // Deselect all visible files
      setSelectedFiles(prev => {
        const next = new Set(prev);
        filteredFiles.forEach(f => next.delete(f.path));
        return next;
      });
    } else {
      // Select all visible files
      setSelectedFiles(prev => {
        const next = new Set(prev);
        filteredFiles.forEach(f => next.add(f.path));
        return next;
      });
    }
  };

  const handleSelectNone = () => {
    setSelectedFiles(new Set());
  };

  const handleSelectAllFiles = () => {
    if (preview?.files) {
      setSelectedFiles(new Set(preview.files.map(f => f.path)));
    }
  };

  const handleConfirm = () => {
    const selected = Array.from(selectedFiles);
    onOpenChange(false);
    // If all files are selected, pass undefined to indicate full publish
    if (preview && selected.length === preview.files.length) {
      onConfirm();
    } else {
      onConfirm(selected);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="w-[95vw] sm:max-w-2xl h-[95vh] sm:h-auto sm:max-h-[85vh] flex flex-col p-4 sm:p-6">
        <DialogHeader className="shrink-0">
          <DialogTitle className="flex items-center gap-2 text-base sm:text-lg">
            <Files className="h-4 w-4 sm:h-5 sm:w-5 text-primary shrink-0" />
            <span className="truncate">Publish Preview</span>
          </DialogTitle>
          <DialogDescription className="text-xs sm:text-sm">
            Select files to deploy from <strong className="truncate">{pluginName}</strong> to{" "}
            <strong className="truncate">{siteName}</strong>
          </DialogDescription>
        </DialogHeader>

        {isLoading && (
          <div className="flex-1 flex items-center justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            <span className="ml-2 text-muted-foreground">Scanning files...</span>
          </div>
        )}

        {error && (
          <div className="flex-1 flex flex-col items-center justify-center py-12 text-center">
            <AlertCircle className="h-12 w-12 text-destructive mb-4" />
            <p className="text-destructive font-medium">Failed to load preview</p>
            <p className="text-sm text-muted-foreground mt-1">
              {error instanceof Error ? error.message : "Unknown error"}
            </p>
          </div>
        )}

        {preview && (
          <>
            {/* Version Comparison Banner - responsive */}
            {(preview.localVersion || preview.remoteVersion) && (
              <div className="flex flex-wrap items-center justify-center gap-2 sm:gap-3 py-2 sm:py-3 px-3 sm:px-4 rounded-lg bg-muted/50 border shrink-0">
                <div className="flex items-center gap-1 sm:gap-2">
                  <Tag className="h-3 w-3 sm:h-4 sm:w-4 text-muted-foreground shrink-0" />
                  <span className="text-xs sm:text-sm text-muted-foreground">Version:</span>
                </div>
                <div className="flex items-center gap-1.5 sm:gap-2">
                  {preview.remoteVersion ? (
                    <Badge variant="outline" className="text-xs font-mono px-1.5 sm:px-2">
                      {preview.remoteVersion}
                    </Badge>
                  ) : (
                    <Badge variant="outline" className="text-xs text-muted-foreground italic px-1.5 sm:px-2">
                      Not installed
                    </Badge>
                  )}
                  <ArrowRight className="h-3 w-3 sm:h-4 sm:w-4 text-primary shrink-0" />
                  <Badge variant="default" className="text-xs font-mono bg-primary px-1.5 sm:px-2">
                    {preview.localVersion || "Unknown"}
                  </Badge>
                </div>
                {preview.localVersion && preview.remoteVersion && preview.localVersion !== preview.remoteVersion && (
                  <Badge variant="secondary" className="text-xs bg-green-500/10 text-green-600 border-green-500/20">
                    Upgrade
                  </Badge>
                )}
                {!preview.remoteVersion && (
                  <Badge variant="secondary" className="text-xs bg-blue-500/10 text-blue-600 border-blue-500/20">
                    New
                  </Badge>
                )}
              </div>
            )}

            {/* Summary Stats - responsive grid */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 py-2 sm:py-3 border-b shrink-0">
              <div className="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
                <CheckSquare className="h-3 w-3 sm:h-4 sm:w-4 text-primary shrink-0" />
                <span className="text-muted-foreground hidden sm:inline">Selected:</span>
                <span className="font-medium">{selectionStats.selected}/{selectionStats.total}</span>
              </div>
              <div className="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
                <FilePlus className="h-3 w-3 sm:h-4 sm:w-4 text-green-500 shrink-0" />
                <span className="text-muted-foreground hidden sm:inline">Added:</span>
                <span className="font-medium text-green-600">{preview.added}</span>
              </div>
              <div className="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
                <FileEdit className="h-3 w-3 sm:h-4 sm:w-4 text-yellow-500 shrink-0" />
                <span className="text-muted-foreground hidden sm:inline">Modified:</span>
                <span className="font-medium text-yellow-600">{preview.modified}</span>
              </div>
              <div className="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
                <HardDrive className="h-3 w-3 sm:h-4 sm:w-4 text-muted-foreground shrink-0" />
                <span className="text-muted-foreground hidden sm:inline">Size:</span>
                <span className="font-medium">{formatBytes(selectionStats.selectedSize)}</span>
              </div>
            </div>

            {/* Selection Controls - responsive */}
            <div className="flex flex-col sm:flex-row gap-2 py-2 items-stretch sm:items-center justify-between shrink-0">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search files..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-9 text-sm h-9"
                />
              </div>
              <div className="flex gap-1 justify-end">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleSelectAllFiles}
                  className="text-xs h-8 px-2"
                >
                  <CheckSquare className="h-3 w-3 mr-1" />
                  All
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleSelectNone}
                  className="text-xs h-8 px-2"
                >
                  <Square className="h-3 w-3 mr-1" />
                  None
                </Button>
              </div>
            </div>

            {/* Tabs for filtering - horizontally scrollable on mobile */}
            <Tabs value={activeTab} onValueChange={setActiveTab} className="flex-1 flex flex-col overflow-hidden min-h-0">
              <div className="overflow-x-auto touch-pan-x shrink-0 -mx-4 sm:mx-0 px-4 sm:px-0">
                <TabsList className="grid w-full min-w-[320px] grid-cols-4">
                  <TabsTrigger value="all" className="text-xs px-2">
                    All ({preview.totalFiles})
                  </TabsTrigger>
                  <TabsTrigger value="added" className="text-xs px-2">
                    <span className="hidden sm:inline">Added</span> ({preview.added})
                  </TabsTrigger>
                  <TabsTrigger value="modified" className="text-xs px-2">
                    <span className="hidden sm:inline">Mod</span> ({preview.modified})
                  </TabsTrigger>
                  <TabsTrigger value="deleted" className="text-xs px-2">
                    <span className="hidden sm:inline">Del</span> ({preview.deleted})
                  </TabsTrigger>
                </TabsList>
              </div>

              <TabsContent value={activeTab} className="flex-1 overflow-hidden mt-2 min-h-0">
                {/* Select all visible toggle */}
                {filteredFiles.length > 0 && (
                  <div className="flex items-center gap-2 px-2 py-1.5 border-b mb-2 shrink-0">
                    <Checkbox
                      id="select-visible"
                      checked={allVisibleSelected}
                      onCheckedChange={handleSelectAll}
                      className="data-[state=checked]:bg-primary"
                    />
                    <label 
                      htmlFor="select-visible" 
                      className="text-xs text-muted-foreground cursor-pointer"
                    >
                      {allVisibleSelected ? "Deselect" : "Select"} visible ({filteredFiles.length})
                    </label>
                  </div>
                )}
                
                <ScrollArea className="h-[200px] sm:h-[280px] rounded-md border touch-pan-y">
                  {filteredFiles.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                      <FolderOpen className="h-12 w-12 mb-2 opacity-50" />
                      <p>No files match your filter</p>
                    </div>
                  ) : (
                    <div className="p-2 space-y-3">
                      {Object.entries(filesByDir).map(([dir, files]) => (
                        <div key={dir}>
                          <div className="text-xs text-muted-foreground font-mono mb-1 px-2">
                            {dir === "." ? "Root" : dir}/
                          </div>
                          <div className="space-y-1">
                            {files.map((file) => (
                              <div
                                key={file.path}
                                className={cn(
                                  "flex items-center justify-between px-2 py-1.5 rounded-md",
                                  "hover:bg-muted/50 transition-colors cursor-pointer",
                                  selectedFiles.has(file.path) && "bg-primary/5"
                                )}
                                onClick={() => handleToggleFile(file.path)}
                              >
                                <div className="flex items-center gap-2 min-w-0 flex-1">
                                  <Checkbox
                                    checked={selectedFiles.has(file.path)}
                                    onCheckedChange={() => handleToggleFile(file.path)}
                                    onClick={(e) => e.stopPropagation()}
                                    className="data-[state=checked]:bg-primary"
                                  />
                                  {getFileIcon(file.changeType)}
                                  <span className="text-sm font-mono truncate">
                                    {file.path.split("/").pop()}
                                  </span>
                                </div>
                                <div className="flex items-center gap-2 flex-shrink-0">
                                  {file.changeType === "modified" && (
                                    <Button
                                      variant="ghost"
                                      size="sm"
                                      className="h-6 px-2"
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        setDiffViewerFile(file);
                                        setDiffViewerOpen(true);
                                      }}
                                    >
                                      <Eye className="h-3 w-3" />
                                    </Button>
                                  )}
                                  <span className="text-xs text-muted-foreground">
                                    {formatBytes(file.size)}
                                  </span>
                                  <Badge
                                    variant="outline"
                                    className={cn("text-xs", getChangeTypeColor(file.changeType))}
                                  >
                                    {getChangeTypeLabel(file.changeType)}
                                  </Badge>
                                </div>
                              </div>
                            ))}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </ScrollArea>
              </TabsContent>
            </Tabs>

            {/* Remote slug info */}
            <div className="text-xs text-muted-foreground pt-2 border-t shrink-0 truncate">
              Deploy to: <code className="bg-muted px-1 rounded text-xs">{preview.remoteSlug}</code>
            </div>
          </>
        )}

        <DialogFooter className="flex-col sm:flex-row gap-2 shrink-0">
          <Button variant="outline" onClick={() => onOpenChange(false)} className="w-full sm:w-auto">
            Cancel
          </Button>
          <Button
            onClick={handleConfirm}
            disabled={isLoading || !!error || selectionStats.selected === 0}
            className="w-full sm:w-auto"
          >
            <Upload className="h-4 w-4 mr-2" />
            Publish {selectionStats.selected} File{selectionStats.selected !== 1 ? "s" : ""}
          </Button>
        </DialogFooter>
      </DialogContent>

      {/* Content Diff Viewer */}
      {diffViewerFile && (
        <ContentDiffViewer
          open={diffViewerOpen}
          onOpenChange={setDiffViewerOpen}
          pluginId={pluginId}
          siteId={siteId}
          filePath={diffViewerFile.path}
          changeType={diffViewerFile.changeType}
        />
      )}
    </Dialog>
  );
}
