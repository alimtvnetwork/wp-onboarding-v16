import { useState } from "react";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Loader2,
  AlertCircle,
  FileText,
  FilePlus,
  FileX,
  FileEdit,
  ChevronDown,
  ChevronRight,
  Copy,
  Check,
} from "lucide-react";
import { api, requireSuccess } from "@/lib/api";
import { cn } from "@/lib/utils";

interface ContentDiffViewerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pluginId: number;
  siteId: number;
  filePath: string;
  changeType: "added" | "modified" | "deleted" | "unchanged";
}

interface DiffLine {
  type: "unchanged" | "added" | "removed" | "header";
  content: string;
  lineNumber?: number;
  oldLineNumber?: number;
  newLineNumber?: number;
}

function computeSimpleDiff(oldContent: string, newContent: string): DiffLine[] {
  const oldLines = oldContent.split("\n");
  const newLines = newContent.split("\n");
  const result: DiffLine[] = [];

  // Simple line-by-line diff using LCS-like approach
  let oldIdx = 0;
  let newIdx = 0;
  let oldLineNum = 1;
  let newLineNum = 1;

  // Build a map of lines to indices for faster lookup
  const newLinesMap = new Map<string, number[]>();
  newLines.forEach((line, idx) => {
    if (!newLinesMap.has(line)) {
      newLinesMap.set(line, []);
    }
    newLinesMap.get(line)!.push(idx);
  });

  while (oldIdx < oldLines.length || newIdx < newLines.length) {
    if (oldIdx >= oldLines.length) {
      // Remaining new lines are additions
      result.push({
        type: "added",
        content: newLines[newIdx],
        newLineNumber: newLineNum++,
      });
      newIdx++;
    } else if (newIdx >= newLines.length) {
      // Remaining old lines are removals
      result.push({
        type: "removed",
        content: oldLines[oldIdx],
        oldLineNumber: oldLineNum++,
      });
      oldIdx++;
    } else if (oldLines[oldIdx] === newLines[newIdx]) {
      // Lines match
      result.push({
        type: "unchanged",
        content: oldLines[oldIdx],
        oldLineNumber: oldLineNum++,
        newLineNumber: newLineNum++,
      });
      oldIdx++;
      newIdx++;
    } else {
      // Find if old line appears later in new content
      const oldLineInNew = newLinesMap.get(oldLines[oldIdx]);
      const newLineInOld = oldLines.indexOf(newLines[newIdx], oldIdx);

      if (oldLineInNew && oldLineInNew.some(i => i > newIdx)) {
        // Current old line exists later in new - this line was removed
        result.push({
          type: "removed",
          content: oldLines[oldIdx],
          oldLineNumber: oldLineNum++,
        });
        oldIdx++;
      } else if (newLineInOld > oldIdx) {
        // Current new line exists later in old - this line was added
        result.push({
          type: "added",
          content: newLines[newIdx],
          newLineNumber: newLineNum++,
        });
        newIdx++;
      } else {
        // Line was modified - show as removed then added
        result.push({
          type: "removed",
          content: oldLines[oldIdx],
          oldLineNumber: oldLineNum++,
        });
        result.push({
          type: "added",
          content: newLines[newIdx],
          newLineNumber: newLineNum++,
        });
        oldIdx++;
        newIdx++;
      }
    }
  }

  return result;
}

export function ContentDiffViewer({
  open,
  onOpenChange,
  pluginId,
  siteId,
  filePath,
  changeType,
}: ContentDiffViewerProps) {
  const [activeTab, setActiveTab] = useState<"diff" | "local" | "remote">("diff");
  const [copied, setCopied] = useState(false);

  // Fetch file content
  const { data, isLoading, error } = useQuery({
    queryKey: ["file-diff", pluginId, siteId, filePath],
    queryFn: async () => {
      const response = await api.getFileDiff(pluginId, siteId, filePath);
      return requireSuccess(response, { endpoint: `/plugins/${pluginId}/sites/${siteId}/file-diff` });
    },
    enabled: open && changeType === "modified",
  });

  // For added files, just fetch local content
  const { data: localData, isLoading: localLoading } = useQuery({
    queryKey: ["local-file", pluginId, filePath],
    queryFn: async () => {
      const response = await api.getLocalFileContent(pluginId, filePath);
      return requireSuccess(response, { endpoint: `/plugins/${pluginId}/file` });
    },
    enabled: open && (changeType === "added" || changeType === "deleted"),
  });

  const isLoadingAny = isLoading || localLoading;

  // Compute diff lines
  const diffLines = data?.localContent && data?.remoteContent
    ? computeSimpleDiff(data.remoteContent, data.localContent)
    : [];

  const addedCount = diffLines.filter(l => l.type === "added").length;
  const removedCount = diffLines.filter(l => l.type === "removed").length;

  const handleCopy = async (content: string) => {
    await navigator.clipboard.writeText(content);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const getChangeIcon = () => {
    switch (changeType) {
      case "added":
        return <FilePlus className="h-4 w-4 text-green-500" />;
      case "modified":
        return <FileEdit className="h-4 w-4 text-yellow-500" />;
      case "deleted":
        return <FileX className="h-4 w-4 text-red-500" />;
      default:
        return <FileText className="h-4 w-4" />;
    }
  };

  const renderFileContent = (content: string, showLineNumbers = true) => (
    <ScrollArea className="h-[400px] rounded-md border bg-muted/30">
      <pre className="p-4 text-xs font-mono">
        {content.split("\n").map((line, i) => (
          <div key={i} className="flex">
            {showLineNumbers && (
              <span className="w-10 pr-4 text-muted-foreground select-none text-right">
                {i + 1}
              </span>
            )}
            <span className="flex-1">{line || " "}</span>
          </div>
        ))}
      </pre>
    </ScrollArea>
  );

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-4xl max-h-[85vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {getChangeIcon()}
            File Diff
          </DialogTitle>
          <DialogDescription className="font-mono text-xs break-all">
            {filePath}
          </DialogDescription>
        </DialogHeader>

        {isLoadingAny && (
          <div className="flex-1 flex items-center justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            <span className="ml-2 text-muted-foreground">Loading file content...</span>
          </div>
        )}

        {error && (
          <div className="flex-1 flex flex-col items-center justify-center py-12 text-center">
            <AlertCircle className="h-12 w-12 text-destructive mb-4" />
            <p className="text-destructive font-medium">Failed to load file</p>
            <p className="text-sm text-muted-foreground mt-1">
              {error instanceof Error ? error.message : "Unknown error"}
            </p>
          </div>
        )}

        {/* Added file - show local content only */}
        {!isLoadingAny && changeType === "added" && localData && (
          <div className="flex-1 flex flex-col overflow-hidden">
            <div className="flex items-center justify-between mb-2">
              <Badge variant="outline" className="bg-green-500/10 text-green-600 border-green-500/20">
                <FilePlus className="h-3 w-3 mr-1" />
                New File
              </Badge>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleCopy(localData.content)}
              >
                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              </Button>
            </div>
            {renderFileContent(localData.content)}
          </div>
        )}

        {/* Deleted file - show remote content only */}
        {!isLoadingAny && changeType === "deleted" && localData && (
          <div className="flex-1 flex flex-col overflow-hidden">
            <div className="flex items-center justify-between mb-2">
              <Badge variant="outline" className="bg-red-500/10 text-red-600 border-red-500/20">
                <FileX className="h-3 w-3 mr-1" />
                Deleted File
              </Badge>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleCopy(localData.content)}
              >
                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              </Button>
            </div>
            {renderFileContent(localData.content)}
          </div>
        )}

        {/* Modified file - show diff */}
        {!isLoadingAny && changeType === "modified" && data && (
          <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as typeof activeTab)} className="flex-1 flex flex-col overflow-hidden">
            <div className="flex items-center justify-between mb-2">
              <TabsList>
                <TabsTrigger value="diff" className="text-xs">
                  Diff
                  <Badge variant="secondary" className="ml-1.5 text-xs px-1.5">
                    +{addedCount} -{removedCount}
                  </Badge>
                </TabsTrigger>
                <TabsTrigger value="local" className="text-xs">Local</TabsTrigger>
                <TabsTrigger value="remote" className="text-xs">Remote</TabsTrigger>
              </TabsList>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleCopy(
                  activeTab === "remote" ? data.remoteContent : data.localContent
                )}
              >
                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
              </Button>
            </div>

            <TabsContent value="diff" className="flex-1 overflow-hidden m-0">
              <ScrollArea className="h-[400px] rounded-md border bg-muted/30">
                <div className="p-2 font-mono text-xs">
                  {diffLines.map((line, i) => (
                    <div
                      key={i}
                      className={cn(
                        "flex px-2 py-0.5",
                        line.type === "added" && "bg-green-500/10 text-green-700 dark:text-green-400",
                        line.type === "removed" && "bg-red-500/10 text-red-700 dark:text-red-400"
                      )}
                    >
                      <span className="w-8 text-muted-foreground select-none text-right pr-2">
                        {line.oldLineNumber || ""}
                      </span>
                      <span className="w-8 text-muted-foreground select-none text-right pr-2">
                        {line.newLineNumber || ""}
                      </span>
                      <span className="w-4 select-none">
                        {line.type === "added" ? "+" : line.type === "removed" ? "-" : " "}
                      </span>
                      <span className="flex-1 whitespace-pre">{line.content}</span>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            </TabsContent>

            <TabsContent value="local" className="flex-1 overflow-hidden m-0">
              {renderFileContent(data.localContent)}
            </TabsContent>

            <TabsContent value="remote" className="flex-1 overflow-hidden m-0">
              {renderFileContent(data.remoteContent)}
            </TabsContent>
          </Tabs>
        )}
      </DialogContent>
    </Dialog>
  );
}
