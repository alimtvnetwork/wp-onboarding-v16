import { useEffect } from "react";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import { toast } from "sonner";
import { CheckCircle2, XCircle, Database } from "lucide-react";
import { useQueryClient } from "@tanstack/react-query";

/**
 * Hook that listens for snapshot_complete WebSocket events and shows
 * toast notifications with a link to view the snapshot details.
 */
export function useSnapshotNotifications(onViewSnapshot?: (snapshotId: number) => void) {
  const queryClient = useQueryClient();

  useEffect(() => {
    const unsub = wsClient.on(WS_EVENTS.SNAPSHOT_COMPLETE, (data: unknown) => {
      const d = data as {
        snapshotId?: number;
        success: boolean;
        error?: string;
        totalRows?: number;
        totalTables?: number;
      };

      // Invalidate snapshot history cache so list refreshes
      queryClient.invalidateQueries({ queryKey: ["snapshot-history"] });

      if (d.success) {
        toast.success("Snapshot Completed", {
          description: `${d.totalTables ?? 0} tables · ${(d.totalRows ?? 0).toLocaleString()} rows backed up`,
          icon: <CheckCircle2 className="h-4 w-4 text-emerald-500" />,
          duration: 6000,
          action: d.snapshotId
            ? {
                label: "View Details",
                onClick: () => onViewSnapshot?.(d.snapshotId!),
              }
            : undefined,
        });
      } else {
        toast.error("Snapshot Failed", {
          description: d.error?.slice(0, 120) || "An unknown error occurred during the backup",
          icon: <XCircle className="h-4 w-4" />,
          duration: 10000,
          action: d.snapshotId
            ? {
                label: "View Details",
                onClick: () => onViewSnapshot?.(d.snapshotId!),
              }
            : undefined,
        });
      }
    });

    return unsub;
  }, [queryClient, onViewSnapshot]);
}
