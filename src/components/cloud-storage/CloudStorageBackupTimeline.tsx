import { useState, useEffect, useCallback } from "react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  RefreshCw,
  Loader2,
  Circle,
  CircleDot,
  MoreVertical,
  Download,
  RotateCcw,
  Trash2,
  ExternalLink,
  Database,
  GitBranch,
} from "lucide-react";
import { api } from "@/lib/api";
import type {
  CloudStorageBackupHistoryRecord,
  CloudStorageBackupStatus,
} from "@/types/cloudStorage";

interface CloudStorageBackupTimelineProps {
  accountId: number;
  onRestore?: (backupId: number) => void;
}

interface WeekGroup {
  weekLabel: string;
  fullBackup: CloudStorageBackupHistoryRecord | null;
  incrementals: CloudStorageBackupHistoryRecord[];
}

const STATUS_COLORS: Record<CloudStorageBackupStatus, string> = {
  pending: "bg-muted text-muted-foreground",
  uploading: "bg-primary/20 text-primary",
  success: "bg-emerald-500/20 text-emerald-600",
  failed: "bg-destructive/20 text-destructive",
};

function formatBytes(bytes: number): string {
  const isSmall = bytes < 1024;
  if (isSmall) return `${bytes} B`;

  const isMedium = bytes < 1024 * 1024;
  if (isMedium) return `${(bytes / 1024).toFixed(1)} KB`;

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(iso: string): string {
  const date = new Date(iso);

  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
}

function getISOWeek(date: Date): string {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
  const week1 = new Date(d.getFullYear(), 0, 4);
  const weekNum = 1 + Math.round(((d.getTime() - week1.getTime()) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7);

  return `${d.getFullYear()}-W${String(weekNum).padStart(2, "0")}`;
}

function groupByWeek(records: CloudStorageBackupHistoryRecord[]): WeekGroup[] {
  const groups = new Map<string, WeekGroup>();

  for (const record of records) {
    const weekKey = getISOWeek(new Date(record.createdAt));
    const existing = groups.get(weekKey);
    const hasGroup = !!existing;

    if (!hasGroup) {
      groups.set(weekKey, {
        weekLabel: weekKey,
        fullBackup: null,
        incrementals: [],
      });
    }

    const group = groups.get(weekKey)!;
    const isFull = record.backupType === "full";

    if (isFull) {
      group.fullBackup = record;
    } else {
      group.incrementals.push(record);
    }
  }

  // Sort weeks descending
  const sorted = Array.from(groups.values()).sort((a, b) =>
    b.weekLabel.localeCompare(a.weekLabel)
  );

  return sorted;
}

export function CloudStorageBackupTimeline({
  accountId,
  onRestore,
}: CloudStorageBackupTimelineProps) {
  const [records, setRecords] = useState<CloudStorageBackupHistoryRecord[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);

  const perPage = 50;

  const loadHistory = useCallback(async (pageNum: number) => {
    setIsLoading(true);
    try {
      const res = await api.getCloudStorageBackupHistory(accountId, pageNum, perPage);
      const isSuccess = res.success && res.data;

      if (isSuccess) {
        setRecords(res.data!.backupHistory || []);
        setTotal(res.data!.total || 0);
        setPage(pageNum);
      }
    } catch {
      // Silent fail
    } finally {
      setIsLoading(false);
    }
  }, [accountId]);

  useEffect(() => {
    loadHistory(1);
  }, [loadHistory]);

  const weeks = groupByWeek(records);
  const hasMore = page * perPage < total;

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold flex items-center gap-2">
          <Database className="h-4 w-4 text-muted-foreground" />
          Backup History
        </h3>
        <Button
          variant="outline"
          size="sm"
          onClick={() => loadHistory(1)}
          disabled={isLoading}
        >
          {isLoading
            ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
            : <RefreshCw className="h-3.5 w-3.5" />}
          <span className="ml-1.5">Refresh</span>
        </Button>
      </div>

      {/* Empty state */}
      {!isLoading && records.length === 0 && (
        <div className="text-center py-8 text-muted-foreground text-sm">
          No backup history yet. Backups will appear here after the first scheduled or manual run.
        </div>
      )}

      {/* Timeline */}
      {weeks.map((week) => (
        <div key={week.weekLabel} className="space-y-1">
          <div className="text-xs font-medium text-muted-foreground uppercase tracking-wide px-1">
            {week.weekLabel}
          </div>

          <div className="border border-border rounded-lg divide-y divide-border">
            {/* Full backup */}
            {week.fullBackup && (
              <BackupEntry
                record={week.fullBackup}
                onRestore={onRestore}
              />
            )}

            {/* Incrementals */}
            {week.incrementals.map((incr) => (
              <BackupEntry
                key={incr.id}
                record={incr}
                onRestore={onRestore}
              />
            ))}
          </div>
        </div>
      ))}

      {/* Load more */}
      {hasMore && (
        <Button
          variant="ghost"
          className="w-full"
          onClick={() => loadHistory(page + 1)}
          disabled={isLoading}
        >
          Load more...
        </Button>
      )}
    </div>
  );
}

// ── Individual backup entry ─────────────────────────────────────

function BackupEntry({
  record,
  onRestore,
}: {
  record: CloudStorageBackupHistoryRecord;
  onRestore?: (backupId: number) => void;
}) {
  const isFull = record.backupType === "full";
  const Icon = isFull ? CircleDot : Circle;
  const tablesChanged = record.tablesChanged ? JSON.parse(record.tablesChanged) as string[] : [];
  const hasRemoteUrl = record.remoteUrl.length > 0;

  return (
    <div className="flex items-center gap-3 px-3 py-2.5 group">
      {/* Type icon */}
      <Icon className={`h-4 w-4 shrink-0 ${isFull ? "text-primary" : "text-muted-foreground"}`} />

      {/* Details */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium truncate">
            {isFull ? "Full Backup" : "Incremental"}
          </span>
          <Badge variant="outline" className={`text-[10px] px-1.5 py-0 ${STATUS_COLORS[record.status]}`}>
            {record.status}
          </Badge>
        </div>
        <div className="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
          <span>{formatDate(record.createdAt)}</span>
          <span>{formatBytes(record.fileSizeBytes)}</span>
          <span className="flex items-center gap-1">
            <GitBranch className="h-3 w-3" />
            {record.BranchName}
          </span>
          {!isFull && tablesChanged.length > 0 && (
            <span>{tablesChanged.length} tables · {record.rowsChanged} rows</span>
          )}
        </div>
      </div>

      {/* Actions */}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="h-7 w-7 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <MoreVertical className="h-3.5 w-3.5" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={() => onRestore?.(record.id)}>
            <RotateCcw className="h-3.5 w-3.5 mr-2" />
            Restore
          </DropdownMenuItem>
          {hasRemoteUrl && (
            <DropdownMenuItem asChild>
              <a href={record.remoteUrl} target="_blank" rel="noopener noreferrer">
                <ExternalLink className="h-3.5 w-3.5 mr-2" />
                View on {record.BranchName === "main" ? "provider" : "branch"}
              </a>
            </DropdownMenuItem>
          )}
          <DropdownMenuItem>
            <Download className="h-3.5 w-3.5 mr-2" />
            Download
          </DropdownMenuItem>
          <DropdownMenuItem className="text-destructive">
            <Trash2 className="h-3.5 w-3.5 mr-2" />
            Delete
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}