import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { api, requireSuccess } from "@/lib/api";
import { toast } from "sonner";
import { SnapshotRecord } from "@/lib/api/types";
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  AlertTriangle,
  Database,
  RotateCcw,
  Loader2,
  Layers,
  Clock,
  HardDrive,
  ShieldAlert,
} from "lucide-react";
import { format } from "date-fns";

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

interface Props {
  snapshot: SnapshotRecord | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onRestoreComplete: () => void;
}

export function SnapshotRestoreDialog({ snapshot, open, onOpenChange, onRestoreComplete }: Props) {
  const [restoring, setRestoring] = useState(false);

  if (!snapshot) return null;

  const tableList = snapshot.tables?.split(",").filter(Boolean).map((t) => t.trim()) ?? [];

  const handleRestore = async () => {
    setRestoring(true);
    try {
      const res = await api.restoreRemoteSnapshot(0, snapshot.id);
      requireSuccess(res, { endpoint: `/sites/0/snapshots/${snapshot.id}/restore`, method: "POST" });
      toast.success(`Snapshot #${snapshot.sequence} restore initiated successfully`);
      onOpenChange(false);
      onRestoreComplete();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : String(err);
      toast.error(`Restore failed: ${message}`);
    } finally {
      setRestoring(false);
    }
  };

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent className="max-w-lg">
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2">
            <ShieldAlert className="h-5 w-5 text-amber-500" />
            Restore Snapshot #{snapshot.sequence}
          </AlertDialogTitle>
          <AlertDialogDescription>
            This will overwrite your current database with data from this snapshot. Review the impact below.
          </AlertDialogDescription>
        </AlertDialogHeader>

        <div className="space-y-4 py-2">
          {/* Impact summary */}
          <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 space-y-2">
            <p className="text-xs font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
              <AlertTriangle className="h-3.5 w-3.5" />
              Impact Analysis
            </p>
            <div className="grid grid-cols-3 gap-2">
              <div className="text-center space-y-0.5">
                <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                  <Layers className="h-3 w-3" />
                  Tables
                </div>
                <p className="text-sm font-mono font-semibold">{tableList.length}</p>
              </div>
              <div className="text-center space-y-0.5">
                <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                  <Database className="h-3 w-3" />
                  Rows
                </div>
                <p className="text-sm font-mono font-semibold">{snapshot.totalRows?.toLocaleString() ?? "—"}</p>
              </div>
              <div className="text-center space-y-0.5">
                <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                  <HardDrive className="h-3 w-3" />
                  Size
                </div>
                <p className="text-sm font-mono font-semibold">{snapshot.fileSize ? formatBytes(snapshot.fileSize) : "—"}</p>
              </div>
            </div>
          </div>

          {/* Snapshot info */}
          <div className="flex items-center justify-between text-xs text-muted-foreground">
            <span className="flex items-center gap-1">
              <Clock className="h-3 w-3" />
              Created
            </span>
            <span className="font-mono">
              {snapshot.createdAt ? format(new Date(snapshot.createdAt), "PPpp") : "—"}
            </span>
          </div>

          <Separator />

          {/* Tables to be overwritten */}
          <div className="space-y-2">
            <p className="text-xs font-medium flex items-center gap-1.5">
              <AlertTriangle className="h-3.5 w-3.5 text-destructive" />
              Tables that will be overwritten ({tableList.length})
            </p>
            {tableList.length > 0 ? (
              <div className="max-h-40 overflow-y-auto rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-muted/40">
                      <TableHead className="text-[11px] py-1.5">Table Name</TableHead>
                      <TableHead className="text-[11px] py-1.5 w-[80px] text-right">Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {tableList.map((table) => (
                      <TableRow key={table}>
                        <TableCell className="py-1.5">
                          <span className="font-mono text-xs flex items-center gap-1.5">
                            <Database className="h-3 w-3 text-muted-foreground shrink-0" />
                            {table}
                          </span>
                        </TableCell>
                        <TableCell className="py-1.5 text-right">
                          <span className="text-[10px] font-medium text-amber-600 dark:text-amber-400 uppercase">
                            Overwrite
                          </span>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : (
              <p className="text-xs text-muted-foreground">No table details available for this snapshot.</p>
            )}
          </div>

          {/* Warning */}
          <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-3">
            <p className="text-xs text-destructive font-medium">
              ⚠ This action cannot be undone. All current data in the listed tables will be replaced with snapshot data.
              Consider creating a backup before proceeding.
            </p>
          </div>
        </div>

        <AlertDialogFooter>
          <AlertDialogCancel disabled={restoring}>Cancel</AlertDialogCancel>
          <Button
            onClick={handleRestore}
            disabled={restoring}
            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          >
            {restoring ? (
              <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />
            ) : (
              <RotateCcw className="h-3.5 w-3.5 mr-1.5" />
            )}
            Confirm Restore
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
