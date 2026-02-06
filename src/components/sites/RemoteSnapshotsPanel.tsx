import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
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
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Loader2,
  RefreshCw,
  Trash2,
  Database,
  Plus,
  RotateCcw,
  AlertCircle,
  HardDrive,
  Clock,
  FileText,
  Table,
} from "lucide-react";
import { Site, SnapshotRecord } from "@/lib/api";
import { useRemoteSnapshots } from "@/hooks/useRemoteSnapshots";

interface RemoteSnapshotsPanelProps {
  site: Site;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
}

function formatDate(dateStr: string): string {
  try {
    const d = new Date(dateStr);
    return d.toLocaleString(undefined, {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return dateStr;
  }
}

function relativeTime(dateStr: string): string {
  try {
    const d = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return "just now";
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays}d ago`;
  } catch {
    return "";
  }
}

export function RemoteSnapshotsPanel({ site, open, onOpenChange }: RemoteSnapshotsPanelProps) {
  const {
    snapshots,
    isLoading,
    isError,
    refetch,
    createSnapshot,
    isCreating,
    deleteSnapshot,
    isDeleting,
    restoreSnapshot,
    isRestoring,
  } = useRemoteSnapshots(site.id);

  const [deleteTarget, setDeleteTarget] = useState<SnapshotRecord | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<SnapshotRecord | null>(null);
  const [createScope, setCreateScope] = useState<string>("wordpress");

  const handleCreate = () => {
    createSnapshot({ scope: createScope });
  };

  const handleDelete = () => {
    if (deleteTarget) {
      deleteSnapshot(deleteTarget.id);
      setDeleteTarget(null);
    }
  };

  const handleRestore = () => {
    if (restoreTarget) {
      restoreSnapshot({ snapshotId: restoreTarget.id });
      setRestoreTarget(null);
    }
  };

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "complete":
        return <Badge className="bg-primary/10 text-primary border-primary/20 text-xs">Complete</Badge>;
      case "running":
      case "in_progress":
        return <Badge className="bg-amber-500/10 text-amber-600 border-amber-500/20 text-xs">Running</Badge>;
      case "failed":
        return <Badge variant="destructive" className="text-xs">Failed</Badge>;
      default:
        return <Badge variant="secondary" className="text-xs">{status}</Badge>;
    }
  };

  const getScopeBadge = (scope: string) => {
    const colors: Record<string, string> = {
      all: "bg-purple-500/10 text-purple-600 border-purple-500/20",
      wordpress: "bg-blue-500/10 text-blue-600 border-blue-500/20",
      content: "bg-green-500/10 text-green-600 border-green-500/20",
      custom: "bg-orange-500/10 text-orange-600 border-orange-500/20",
    };
    return (
      <Badge className={`${colors[scope] || "bg-muted text-muted-foreground"} text-xs`}>
        {scope}
      </Badge>
    );
  };

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Database className="h-5 w-5 text-primary" />
              Snapshots — {site.name}
            </DialogTitle>
            <DialogDescription>
              Manage database snapshots on this WordPress site
            </DialogDescription>
          </DialogHeader>

          {/* Create Snapshot Controls */}
          <div className="flex items-center gap-2 pb-2 border-b">
            <Select value={createScope} onValueChange={setCreateScope}>
              <SelectTrigger className="w-[140px] h-8 text-xs">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Tables</SelectItem>
                <SelectItem value="wordpress">WordPress Core</SelectItem>
                <SelectItem value="content">Content Only</SelectItem>
              </SelectContent>
            </Select>
            <Button
              size="sm"
              onClick={handleCreate}
              disabled={isCreating}
              className="h-8"
            >
              {isCreating ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" />
              ) : (
                <Plus className="h-3.5 w-3.5 mr-1" />
              )}
              Create Snapshot
            </Button>
            <div className="flex-1" />
            <Button
              variant="ghost"
              size="sm"
              onClick={() => refetch()}
              disabled={isLoading}
              className="h-8"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? "animate-spin" : ""}`} />
            </Button>
          </div>

          {/* Snapshot List */}
          <ScrollArea className="flex-1 min-h-0">
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
              </div>
            ) : isError ? (
              <div className="flex flex-col items-center justify-center py-12 gap-2 text-muted-foreground">
                <AlertCircle className="h-8 w-8" />
                <p className="text-sm">Failed to load snapshots</p>
                <Button variant="outline" size="sm" onClick={() => refetch()}>
                  Retry
                </Button>
              </div>
            ) : snapshots.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 gap-2 text-muted-foreground">
                <Database className="h-8 w-8" />
                <p className="text-sm">No snapshots yet</p>
                <p className="text-xs">Create your first snapshot to get started</p>
              </div>
            ) : (
              <div className="space-y-2 pr-2">
                {snapshots.map((snapshot) => (
                  <div
                    key={snapshot.id}
                    className="border rounded-lg p-3 space-y-2 hover:bg-muted/30 transition-colors"
                  >
                    {/* Row 1: Name + Status + Actions */}
                    <div className="flex items-center justify-between gap-2">
                      <div className="flex items-center gap-2 min-w-0">
                        <FileText className="h-4 w-4 text-muted-foreground shrink-0" />
                        <span className="text-sm font-medium truncate">
                          #{snapshot.sequence} — {snapshot.filename}
                        </span>
                      </div>
                      <div className="flex items-center gap-1.5 shrink-0">
                        {getStatusBadge(snapshot.status)}
                        {snapshot.status === "complete" && (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 p-0 text-primary hover:text-primary hover:bg-primary/10"
                            onClick={() => setRestoreTarget(snapshot)}
                            disabled={isRestoring}
                            title="Restore this snapshot"
                          >
                            <RotateCcw className="h-3.5 w-3.5" />
                          </Button>
                        )}
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-7 w-7 p-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                          onClick={() => setDeleteTarget(snapshot)}
                          disabled={isDeleting}
                          title="Delete snapshot"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </div>

                    {/* Row 2: Metadata */}
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                      {getScopeBadge(snapshot.scope)}
                      <span className="flex items-center gap-1">
                        <HardDrive className="h-3 w-3" />
                        {formatBytes(snapshot.file_size)}
                      </span>
                      <span className="flex items-center gap-1">
                        <Table className="h-3 w-3" />
                        {snapshot.total_rows.toLocaleString()} rows
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        {relativeTime(snapshot.created_at)}
                      </span>
                      <Badge variant="outline" className="text-[10px] h-4">
                        {snapshot.provider}
                      </Badge>
                    </div>

                    {/* Error message */}
                    {snapshot.error && (
                      <p className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1">
                        {snapshot.error}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            )}
          </ScrollArea>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <AlertDialog open={!!deleteTarget} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Snapshot</AlertDialogTitle>
            <AlertDialogDescription>
              Delete snapshot #{deleteTarget?.sequence} ({deleteTarget?.filename})? This cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Restore Confirmation */}
      <AlertDialog open={!!restoreTarget} onOpenChange={(o) => !o && setRestoreTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Restore Snapshot</AlertDialogTitle>
            <AlertDialogDescription>
              Restore database from snapshot #{restoreTarget?.sequence}? A pre-restore backup will be created automatically. This will overwrite current database tables.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleRestore}>
              Restore
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
