import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, Plugin, PluginVersion } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  History,
  ChevronDown,
  ChevronRight,
  RotateCcw,
  Trash2,
  Clock,
  FileText,
  GitCommit,
  Loader2,
  Globe,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";
import { formatDistanceToNow, parseISO } from "date-fns";

interface VersionHistoryPanelProps {
  plugin: Plugin;
  siteId?: number;
}

export function VersionHistoryPanel({ plugin, siteId }: VersionHistoryPanelProps) {
  const queryClient = useQueryClient();
  const [isOpen, setIsOpen] = useState(false);
  const [isRollingBack, setIsRollingBack] = useState<number | null>(null);
  const [showRollbackConfirm, setShowRollbackConfirm] = useState<PluginVersion | null>(null);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<PluginVersion | null>(null);

  const { data: versions, isLoading } = useQuery({
    queryKey: ["plugin-versions", plugin.id, siteId],
    queryFn: async () => {
      const response = await api.getPluginVersions(plugin.id, siteId, 20);
      return response.success ? (response.data ?? []) : [];
    },
    enabled: isOpen,
  });

  const handleRollback = async (version: PluginVersion) => {
    setShowRollbackConfirm(null);
    setIsRollingBack(version.id);
    
    try {
      const response = await api.rollbackPluginVersion(plugin.id, version.id);
      if (response.success) {
        toast.success(`Rolled back to version ${version.version}`);
        queryClient.invalidateQueries({ queryKey: ["plugin-versions", plugin.id] });
      } else {
        toast.error(response.error?.message || "Rollback failed");
      }
    } catch (err) {
      toast.error("Failed to rollback version");
    } finally {
      setIsRollingBack(null);
    }
  };

  const handleDelete = async (version: PluginVersion) => {
    setShowDeleteConfirm(null);
    
    try {
      const response = await api.deletePluginVersion(plugin.id, version.id);
      if (response.success) {
        toast.success("Version deleted");
        queryClient.invalidateQueries({ queryKey: ["plugin-versions", plugin.id] });
      } else {
        toast.error(response.error?.message || "Delete failed");
      }
    } catch (err) {
      toast.error("Failed to delete version");
    }
  };

  const formatDate = (dateString: string) => {
    try {
      return formatDistanceToNow(parseISO(dateString), { addSuffix: true });
    } catch {
      return dateString;
    }
  };

  return (
    <>
      <Collapsible open={isOpen} onOpenChange={setIsOpen}>
        <CollapsibleTrigger asChild>
          <Button variant="ghost" size="sm" className="w-full justify-between gap-2 h-8">
            <span className="flex items-center gap-2 text-xs font-medium">
              <History className="h-3.5 w-3.5" />
              Version History
            </span>
            {isOpen ? (
              <ChevronDown className="h-4 w-4 text-muted-foreground" />
            ) : (
              <ChevronRight className="h-4 w-4 text-muted-foreground" />
            )}
          </Button>
        </CollapsibleTrigger>

        <CollapsibleContent className="mt-2">
          {isLoading ? (
            <div className="flex items-center justify-center py-4">
              <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
            </div>
          ) : !versions || versions.length === 0 ? (
            <div className="text-center py-4 text-sm text-muted-foreground">
              No version history available
            </div>
          ) : (
            <ScrollArea className="h-48">
              <div className="space-y-2 pr-3">
                {versions.map((version, index) => (
                  <div
                    key={version.id}
                    className={cn(
                      "rounded-md border p-2 text-xs",
                      index === 0 && "border-primary/50 bg-primary/5"
                    )}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <div className="flex items-center gap-2">
                        <Badge variant={index === 0 ? "default" : "secondary"} className="text-[10px] h-5">
                          v{version.version}
                        </Badge>
                        {version.publishType && (
                          <Badge variant="outline" className="text-[10px] h-5">
                            {version.publishType}
                          </Badge>
                        )}
                      </div>
                      <div className="flex items-center gap-1">
                        {version.backupPath && index > 0 && (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 w-6 p-0"
                            disabled={isRollingBack === version.id}
                            onClick={() => setShowRollbackConfirm(version)}
                            title="Rollback to this version"
                          >
                            {isRollingBack === version.id ? (
                              <Loader2 className="h-3 w-3 animate-spin" />
                            ) : (
                              <RotateCcw className="h-3 w-3" />
                            )}
                          </Button>
                        )}
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-6 w-6 p-0 text-destructive hover:text-destructive"
                          onClick={() => setShowDeleteConfirm(version)}
                          title="Delete version"
                        >
                          <Trash2 className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground">
                      <span className="flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        {formatDate(version.createdAt)}
                      </span>
                      <span className="flex items-center gap-1">
                        <FileText className="h-3 w-3" />
                        {version.filesUpdated} files
                      </span>
                      {version.siteName && (
                        <span className="flex items-center gap-1">
                          <Globe className="h-3 w-3" />
                          {version.siteName}
                        </span>
                      )}
                      {version.gitCommitHash && (
                        <span className="flex items-center gap-1 font-mono">
                          <GitCommit className="h-3 w-3" />
                          {version.gitCommitHash.substring(0, 7)}
                        </span>
                      )}
                    </div>

                    {version.notes && (
                      <p className="mt-1 text-muted-foreground truncate" title={version.notes}>
                        {version.notes}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            </ScrollArea>
          )}
        </CollapsibleContent>
      </Collapsible>

      {/* Rollback Confirmation */}
      <AlertDialog open={!!showRollbackConfirm} onOpenChange={() => setShowRollbackConfirm(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Rollback to Version {showRollbackConfirm?.version}?</AlertDialogTitle>
            <AlertDialogDescription>
              This will restore the plugin to version {showRollbackConfirm?.version} on the target site.
              The current version will be backed up before rollback.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={() => showRollbackConfirm && handleRollback(showRollbackConfirm)}>
              Rollback
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Delete Confirmation */}
      <AlertDialog open={!!showDeleteConfirm} onOpenChange={() => setShowDeleteConfirm(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Version {showDeleteConfirm?.version}?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete this version record and its backup file.
              This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => showDeleteConfirm && handleDelete(showDeleteConfirm)}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
