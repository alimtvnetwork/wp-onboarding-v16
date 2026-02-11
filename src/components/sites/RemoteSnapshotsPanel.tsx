import { useState, useCallback, useRef } from "react";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Separator } from "@/components/ui/separator";
import { Checkbox } from "@/components/ui/checkbox";
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
  Settings,
  CheckCircle,
  Zap,
  Download,
  Eye,
  Upload,
  GitBranch,
  ArrowRight,
  Copy,
} from "lucide-react";
import { Site, SnapshotRecord, SnapshotSchedule, SnapshotInterval, api } from "@/lib/api";
import { useRemoteSnapshots } from "@/hooks/useRemoteSnapshots";
import { toClipboardText } from "@/lib/logText";
import { toast } from "sonner";
import { SnapshotConfigPanel } from "@/components/settings/SnapshotConfigPanel";

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

function SnapshotRow({
  snapshot,
  siteId,
  onRestore,
  onDelete,
  onViewDetail,
  isRestoring,
  isDeleting,
}: {
  snapshot: SnapshotRecord;
  siteId: number;
  onRestore: (s: SnapshotRecord) => void;
  onDelete: (s: SnapshotRecord) => void;
  onViewDetail: (s: SnapshotRecord) => void;
  isRestoring: boolean;
  isDeleting: boolean;
}) {
  const isRunning = snapshot.status === "running" || snapshot.status === "in_progress";

  const statusBadge = (() => {
    switch (snapshot.status) {
      case "complete":
        return (
          <Badge className="bg-primary/10 text-primary border-primary/20 text-xs gap-1">
            <CheckCircle className="h-3 w-3" />
            Complete
          </Badge>
        );
      case "running":
      case "in_progress":
        return (
          <Badge className="bg-amber-500/10 text-amber-600 border-amber-500/20 text-xs gap-1 animate-pulse">
            <Loader2 className="h-3 w-3 animate-spin" />
            Running
          </Badge>
        );
      case "failed":
        return (
          <Badge variant="destructive" className="text-xs gap-1">
            <AlertCircle className="h-3 w-3" />
            Failed
          </Badge>
        );
      default:
        return <Badge variant="secondary" className="text-xs">{snapshot.status}</Badge>;
    }
  })();

  const scopeColors: Record<string, string> = {
    all: "bg-purple-500/10 text-purple-600 border-purple-500/20",
    wordpress: "bg-blue-500/10 text-blue-600 border-blue-500/20",
    content: "bg-green-500/10 text-green-600 border-green-500/20",
    custom: "bg-orange-500/10 text-orange-600 border-orange-500/20",
  };

  return (
    <div className="border rounded-lg p-3 space-y-2 hover:bg-muted/30 transition-colors animate-fade-in">
      {/* Row 1: Name + Status + Actions */}
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          <FileText className="h-4 w-4 text-muted-foreground shrink-0" />
          <span className="text-sm font-medium truncate">
            #{snapshot.sequence} — {snapshot.filename}
          </span>
        </div>
        <div className="flex items-center gap-1 shrink-0">
          {statusBadge}
          {snapshot.status === "complete" && (
            <>
              <Button
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 text-muted-foreground hover:text-foreground"
                onClick={() => onViewDetail(snapshot)}
                title="View details"
              >
                <Eye className="h-3.5 w-3.5" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 text-muted-foreground hover:text-foreground"
                asChild
                title="Download snapshot"
              >
                <a href={api.getRemoteSnapshotExportUrl(siteId, snapshot.id)} download>
                  <Download className="h-3.5 w-3.5" />
                </a>
              </Button>
              <Button
                variant="ghost"
                size="sm"
                className="h-7 w-7 p-0 text-primary hover:text-primary hover:bg-primary/10"
                onClick={() => onRestore(snapshot)}
                disabled={isRestoring}
                title="Restore this snapshot"
              >
                <RotateCcw className="h-3.5 w-3.5" />
              </Button>
            </>
          )}
          <Button
            variant="ghost"
            size="sm"
            className="h-7 w-7 p-0 text-destructive hover:text-destructive hover:bg-destructive/10"
            onClick={() => onDelete(snapshot)}
            disabled={isDeleting || isRunning}
            title="Delete snapshot"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>

      {/* Row 2: Metadata */}
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
        <Badge className={`${scopeColors[snapshot.scope] || "bg-muted text-muted-foreground"} text-xs`}>
          {snapshot.scope}
        </Badge>
        {snapshot.file_size > 0 && (
          <span className="flex items-center gap-1">
            <HardDrive className="h-3 w-3" />
            {formatBytes(snapshot.file_size)}
          </span>
        )}
        {snapshot.total_rows > 0 && (
          <span className="flex items-center gap-1">
            <Table className="h-3 w-3" />
            {snapshot.total_rows.toLocaleString()} rows
          </span>
        )}
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
        <div className="flex items-center gap-1.5">
          <p className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1 flex-1">
            {snapshot.error}
          </p>
          <Button
            variant="ghost"
            size="sm"
            className="h-6 w-6 p-0 text-muted-foreground hover:text-foreground shrink-0"
            onClick={(e) => {
              e.stopPropagation();
              navigator.clipboard.writeText(toClipboardText(snapshot.error || ""));
              toast.success("Error copied to clipboard");
            }}
            title="Copy error"
          >
            <Copy className="h-3 w-3" />
          </Button>
        </div>
      )}
    </div>
  );
}

function SnapshotSettingsTab({
  siteId,
}: {
  siteId: number;
}) {
  const {
    settings,
    isLoadingSettings,
    providers,
    isLoadingProviders,
    updateSettings,
    isUpdatingSettings,
    cleanupSnapshots,
    isCleaningUp,
  } = useRemoteSnapshots(siteId);

  const [localSettings, setLocalSettings] = useState<Record<string, unknown> | null>(null);

  // Use local overrides if user has edited, otherwise show fetched settings
  const current = localSettings || (settings as unknown as Record<string, unknown>);

  const handleChange = (key: string, value: unknown) => {
    setLocalSettings((prev) => ({ ...(prev || (settings as unknown as Record<string, unknown>) || {}), [key]: value }));
  };

  const handleSave = () => {
    if (localSettings) {
      updateSettings(localSettings);
      setLocalSettings(null);
    }
  };

  if (isLoadingSettings || isLoadingProviders) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (!current) {
    return (
      <div className="flex flex-col items-center justify-center py-12 gap-2 text-muted-foreground">
        <AlertCircle className="h-8 w-8" />
        <p className="text-sm">Settings not available</p>
      </div>
    );
  }

  const hasChanges = localSettings !== null;

  return (
    <div className="space-y-4 py-2">
      {/* Provider */}
      <div className="space-y-1.5">
        <Label className="text-xs font-medium">Snapshot Provider</Label>
        <Select
          value={(current.provider as string) || "native"}
          onValueChange={(v) => handleChange("provider", v)}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {providers.map((p) => (
              <SelectItem key={p.id} value={p.id} disabled={!p.available}>
                <span className="flex items-center gap-2">
                  {p.name}
                  {!p.available && <span className="text-muted-foreground">(unavailable)</span>}
                </span>
              </SelectItem>
            ))}
            {providers.length === 0 && <SelectItem value="native">Native SQLite</SelectItem>}
          </SelectContent>
        </Select>
      </div>

      <Separator />

      {/* Multi-Schedule */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label className="text-xs font-medium flex items-center gap-1.5">
            <Clock className="h-3.5 w-3.5" />
            Schedules
          </Label>
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              const intervals: SnapshotInterval[] = ["hourly", "3h", "6h", "12h", "daily", "weekly", "monthly", "yearly"];
              const existingSchedules = (current.schedules as SnapshotSchedule[]) || [];
              const usedIntervals = new Set(existingSchedules.map((s) => s.interval));
              const available = intervals.find((i) => !usedIntervals.has(i));
              if (!available) return;
              const newSchedule: SnapshotSchedule = {
                id: `sched_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 6)}`,
                interval: available,
                enabled: true,
              };
              handleChange("schedules", [...existingSchedules, newSchedule]);
            }}
            disabled={((current.schedules as SnapshotSchedule[]) || []).length >= 8}
            className="h-6 text-[10px] px-2"
          >
            <Plus className="h-3 w-3 mr-0.5" />
            Add
          </Button>
        </div>
        <p className="text-[10px] text-muted-foreground">
          Add multiple schedules — each becomes a separate cron job
        </p>

        {((current.schedules as SnapshotSchedule[]) || []).length === 0 && (
          <div className="text-center py-3 text-muted-foreground text-[10px] border rounded-md border-dashed">
            No schedules. Snapshots run manually only.
          </div>
        )}

        <div className="space-y-1.5">
          {((current.schedules as SnapshotSchedule[]) || []).map((schedule) => {
            const intervalLabels: Record<string, string> = {
              hourly: "Every Hour", "3h": "Every 3h", "6h": "Every 6h", "12h": "Every 12h",
              daily: "Daily", weekly: "Weekly", monthly: "Monthly", yearly: "Yearly",
            };
            return (
              <div
                key={schedule.id}
                className={`flex items-center gap-2 p-2 rounded-lg border transition-colors ${
                  schedule.enabled ? "bg-accent/30 border-primary/20" : "bg-muted/30 opacity-60"
                }`}
              >
                <Switch
                  checked={schedule.enabled}
                  onCheckedChange={(v) => {
                    const updated = ((current.schedules as SnapshotSchedule[]) || []).map((s) =>
                      s.id === schedule.id ? { ...s, enabled: v } : s
                    );
                    handleChange("schedules", updated);
                  }}
                  className="shrink-0 scale-75"
                />
                <Select
                  value={schedule.interval}
                  onValueChange={(v) => {
                    const updated = ((current.schedules as SnapshotSchedule[]) || []).map((s) =>
                      s.id === schedule.id ? { ...s, interval: v as SnapshotInterval } : s
                    );
                    handleChange("schedules", updated);
                  }}
                >
                  <SelectTrigger className="h-7 text-[11px] flex-1">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {Object.entries(intervalLabels).map(([val, label]) => (
                      <SelectItem key={val} value={val}>{label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    const filtered = ((current.schedules as SnapshotSchedule[]) || []).filter(
                      (s) => s.id !== schedule.id
                    );
                    handleChange("schedules", filtered);
                  }}
                  className="h-7 w-7 p-0 text-destructive hover:text-destructive shrink-0"
                >
                  <Trash2 className="h-3 w-3" />
                </Button>
              </div>
            );
          })}
        </div>
      </div>

      {/* Default Scope */}
      <div className="space-y-1.5">
        <Label className="text-xs font-medium">Default Scope</Label>
        <Select
          value={(current.scope as string) || "wordpress"}
          onValueChange={(v) => handleChange("scope", v)}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Tables</SelectItem>
            <SelectItem value="wordpress">WordPress Core</SelectItem>
            <SelectItem value="content">Content Only</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <Separator />

      {/* Retention */}
      <div className="space-y-1.5">
        <Label className="text-xs font-medium">Retention Policy</Label>
        <Select
          value={(current.retention_type as string) || "count"}
          onValueChange={(v) => handleChange("retention_type", v)}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">No Automatic Cleanup</SelectItem>
            <SelectItem value="days">Days-based</SelectItem>
            <SelectItem value="count">Count-based</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {(current.retention_type as string) === "days" && (
        <div className="space-y-1.5">
          <Label className="text-xs font-medium">Keep snapshots for (days)</Label>
          <Input
            type="number"
            className="h-8 text-xs"
            value={(current.retention_days as number) || 30}
            onChange={(e) => handleChange("retention_days", parseInt(e.target.value) || 30)}
            min={1}
            max={365}
          />
        </div>
      )}

      {(current.retention_type as string) === "count" && (
        <div className="space-y-1.5">
          <Label className="text-xs font-medium">Keep last N snapshots</Label>
          <Input
            type="number"
            className="h-8 text-xs"
            value={(current.retention_max as number) || 10}
            onChange={(e) => handleChange("retention_max", parseInt(e.target.value) || 10)}
            min={1}
            max={100}
          />
        </div>
      )}

      <Separator />

      {/* Parallel Execution & Storage Config */}
      <SnapshotConfigPanel
        storageMode={((current.storage_mode as string) || "single") as "single" | "per-table"}
        onStorageModeChange={(mode) => handleChange("storage_mode", mode)}
        workerCount={(current.worker_count as number) || 4}
        onWorkerCountChange={(count) => handleChange("worker_count", count)}
        batchSize={(current.batch_size as number) || 10}
        onBatchSizeChange={(size) => handleChange("batch_size", size)}
        showRetention={false}
      />

      <Separator />

      {/* Safety */}
      <div className="flex items-center justify-between">
        <Label className="text-xs font-medium">Pre-Restore Backup</Label>
        <Switch
          checked={(current.pre_restore_backup as boolean) !== false}
          onCheckedChange={(v) => handleChange("pre_restore_backup", v)}
        />
      </div>

      {/* Save Button */}
      {hasChanges && (
        <Button
          size="sm"
          onClick={handleSave}
          disabled={isUpdatingSettings}
          className="w-full h-8 animate-fade-in"
        >
          {isUpdatingSettings ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" />
          ) : (
            <CheckCircle className="h-3.5 w-3.5 mr-1" />
          )}
          Save Settings
        </Button>
      )}

      <Separator />

      {/* Manual Cleanup */}
      <div className="space-y-1.5">
        <Label className="text-xs font-medium">Manual Cleanup</Label>
        <p className="text-xs text-muted-foreground">
          Run retention cleanup, remove orphan files, and mark stuck snapshots as failed.
        </p>
        <Button
          size="sm"
          variant="outline"
          onClick={() => cleanupSnapshots({})}
          disabled={isCleaningUp}
          className="w-full h-8"
        >
          {isCleaningUp ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" />
          ) : (
            <Trash2 className="h-3.5 w-3.5 mr-1" />
          )}
          Run Cleanup Now
        </Button>
      </div>
    </div>
  );
}

export function RemoteSnapshotsPanel({ site, open, onOpenChange }: RemoteSnapshotsPanelProps) {
  const {
    snapshots,
    isLoading,
    isError,
    error: snapshotError,
    refetch,
    hasRunningSnapshots,
    createSnapshot,
    isCreating,
    deleteSnapshot,
    isDeleting,
    restoreSnapshot,
    isRestoring,
    availableTables,
    isLoadingTables,
    fetchTables,
    fullBackup,
    isFullBackupPending,
    incrementalBackup,
    isIncrementalPending,
    importSnapshot,
    isImporting,
  } = useRemoteSnapshots(site.id, open);

  const fileInputRef = useRef<HTMLInputElement>(null);

  const [deleteTarget, setDeleteTarget] = useState<SnapshotRecord | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<SnapshotRecord | null>(null);
  const [detailTarget, setDetailTarget] = useState<SnapshotRecord | null>(null);
  const [createScope, setCreateScope] = useState<string>("wordpress");
  const [customTables, setCustomTables] = useState<string[]>([]);
  const [showTablePicker, setShowTablePicker] = useState(false);
  const [restoreMode, setRestoreMode] = useState<"full" | "selective">("full");
  const [restoreTables, setRestoreTables] = useState<string[]>([]);

  const handleScopeChange = useCallback((scope: string) => {
    setCreateScope(scope);
    if (scope === "custom" && availableTables.length === 0) {
      fetchTables();
    }
    if (scope === "custom") {
      setShowTablePicker(true);
    } else {
      setShowTablePicker(false);
    }
  }, [availableTables.length, fetchTables]);

  const handleCreate = () => {
    if (createScope === "custom" && customTables.length > 0) {
      createSnapshot({ scope: "custom", tables: customTables });
    } else {
      createSnapshot({ scope: createScope });
    }
  };

  const handleDelete = () => {
    if (deleteTarget) {
      deleteSnapshot(deleteTarget.id);
      setDeleteTarget(null);
    }
  };

  const handleRestore = () => {
    if (restoreTarget) {
      const opts: Record<string, unknown> = { snapshotId: restoreTarget.id };
      if (restoreMode === "selective" && restoreTables.length > 0) {
        opts.opts = { mode: "selective", tables: restoreTables };
      }
      restoreSnapshot(opts as { snapshotId: number; opts?: Record<string, unknown> });
      setRestoreTarget(null);
      setRestoreMode("full");
      setRestoreTables([]);
    }
  };

  const handleOpenRestore = useCallback((s: SnapshotRecord) => {
    setRestoreTarget(s);
    setRestoreMode("full");
    // Parse tables from snapshot for selective restore
    const snapshotTables = typeof s.tables === "string"
      ? s.tables.split(",").map((t) => t.trim()).filter(Boolean)
      : Array.isArray(s.tables) ? s.tables : [];
    setRestoreTables(snapshotTables);
  }, []);

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-2xl max-h-[85vh] flex flex-col">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Database className="h-5 w-5 text-primary" />
              Snapshots — {site.name}
              {hasRunningSnapshots && (
                <Badge className="bg-amber-500/10 text-amber-600 border-amber-500/20 text-xs gap-1 animate-pulse ml-1">
                  <Zap className="h-3 w-3" />
                  Active
                </Badge>
              )}
            </DialogTitle>
            <DialogDescription>
              Manage database snapshots on this WordPress site
            </DialogDescription>
          </DialogHeader>

          <Tabs defaultValue="snapshots" className="flex-1 flex flex-col min-h-0">
            <TabsList className="w-full grid grid-cols-3 h-8">
              <TabsTrigger value="snapshots" className="text-xs gap-1">
                <Database className="h-3.5 w-3.5" />
                Snapshots
                {snapshots.length > 0 && (
                  <Badge variant="secondary" className="h-4 text-[10px] px-1 ml-1">{snapshots.length}</Badge>
                )}
              </TabsTrigger>
              <TabsTrigger value="timeline" className="text-xs gap-1">
                <GitBranch className="h-3.5 w-3.5" />
                Timeline
              </TabsTrigger>
              <TabsTrigger value="settings" className="text-xs gap-1">
                <Settings className="h-3.5 w-3.5" />
                Settings
              </TabsTrigger>
            </TabsList>

            <TabsContent value="snapshots" className="flex-1 flex flex-col min-h-0 mt-2">
              {/* Create Snapshot Controls */}
              <div className="space-y-2 pb-2 border-b mb-2">
                <div className="flex items-center gap-2 flex-wrap">
                  <Select value={createScope} onValueChange={handleScopeChange}>
                    <SelectTrigger className="w-[140px] h-8 text-xs">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Tables</SelectItem>
                      <SelectItem value="wordpress">WordPress Core</SelectItem>
                      <SelectItem value="content">Content Only</SelectItem>
                      <SelectItem value="custom">Custom Tables</SelectItem>
                    </SelectContent>
                  </Select>
                  <Button
                    size="sm"
                    onClick={handleCreate}
                    disabled={isCreating || (createScope === "custom" && customTables.length === 0)}
                    className="h-8"
                  >
                    {isCreating ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin mr-1" />
                    ) : (
                      <Plus className="h-3.5 w-3.5 mr-1" />
                    )}
                    Create
                    {createScope === "custom" && customTables.length > 0 && (
                      <Badge variant="secondary" className="h-4 text-[10px] px-1 ml-1">{customTables.length}</Badge>
                    )}
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

                {/* Advanced Backup Actions */}
                <div className="flex items-center gap-2 flex-wrap">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => fullBackup({})}
                    disabled={isFullBackupPending || hasRunningSnapshots}
                    className="h-7 text-xs"
                  >
                    {isFullBackupPending ? (
                      <Loader2 className="h-3 w-3 animate-spin mr-1" />
                    ) : (
                      <Database className="h-3 w-3 mr-1" />
                    )}
                    Full Backup
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => incrementalBackup({})}
                    disabled={isIncrementalPending || hasRunningSnapshots}
                    className="h-7 text-xs"
                  >
                    {isIncrementalPending ? (
                      <Loader2 className="h-3 w-3 animate-spin mr-1" />
                    ) : (
                      <GitBranch className="h-3 w-3 mr-1" />
                    )}
                    Incremental
                  </Button>
                  <div className="flex-1" />
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".zip"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) {
                        importSnapshot(file);
                        e.target.value = "";
                      }
                    }}
                  />
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={isImporting}
                    className="h-7 text-xs"
                  >
                    {isImporting ? (
                      <Loader2 className="h-3 w-3 animate-spin mr-1" />
                    ) : (
                      <Upload className="h-3 w-3 mr-1" />
                    )}
                    Import ZIP
                  </Button>
                </div>

                {/* Custom Table Picker */}
                {showTablePicker && (
                  <div className="border rounded-md p-2 space-y-1.5 animate-fade-in">
                    <div className="flex items-center justify-between">
                      <Label className="text-xs font-medium text-muted-foreground">Select tables to include</Label>
                      {customTables.length > 0 && (
                        <Button variant="ghost" size="sm" className="h-5 text-[10px] px-1" onClick={() => setCustomTables([])}>
                          Clear
                        </Button>
                      )}
                    </div>
                    {isLoadingTables ? (
                      <div className="flex items-center justify-center py-3">
                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                      </div>
                    ) : availableTables.length === 0 ? (
                      <p className="text-xs text-muted-foreground py-2">No tables found</p>
                    ) : (
                      <ScrollArea className="max-h-32">
                        <div className="space-y-1">
                          {availableTables.map((table) => (
                            <label key={table.name} className="flex items-center gap-2 text-xs hover:bg-muted/50 rounded px-1 py-0.5 cursor-pointer">
                              <Checkbox
                                checked={customTables.includes(table.name)}
                                onCheckedChange={(checked) => {
                                  setCustomTables((prev) =>
                                    checked ? [...prev, table.name] : prev.filter((t) => t !== table.name)
                                  );
                                }}
                              />
                              <span className="font-mono text-[11px] flex-1">{table.name}</span>
                              <span className="text-muted-foreground text-[10px]">
                                {table.rows.toLocaleString()} rows
                              </span>
                              {table.is_core && (
                                <Badge variant="outline" className="text-[9px] h-3.5 px-1">core</Badge>
                              )}
                            </label>
                          ))}
                        </div>
                      </ScrollArea>
                    )}
                  </div>
                )}
              </div>

              {/* Snapshot List */}
              <ScrollArea className="flex-1 min-h-0">
                {isLoading ? (
                  <div className="flex items-center justify-center py-12">
                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                  </div>
                ) : isError ? (
                  <div className="flex flex-col items-center justify-center py-12 gap-2 text-muted-foreground">
                    <AlertCircle className="h-8 w-8 text-destructive/60" />
                    <p className="text-sm font-medium">Failed to load snapshots</p>
                    {snapshotError?.message && (
                      <p className="text-xs text-destructive/80 max-w-[300px] text-center break-all">{snapshotError.message}</p>
                    )}
                    <Button variant="outline" size="sm" onClick={() => refetch()}>
                      Retry
                    </Button>
                  </div>
                ) : snapshots.length === 0 ? (
                  <div className="flex flex-col items-center justify-center py-12 gap-2 text-muted-foreground animate-fade-in">
                    <Database className="h-10 w-10 opacity-40" />
                    <p className="text-sm font-medium">No snapshots yet</p>
                    <p className="text-xs text-center max-w-[240px]">
                      Create your first database snapshot to enable point-in-time recovery
                    </p>
                  </div>
                ) : (
                  <div className="space-y-2 pr-2">
                    {snapshots.map((snapshot) => (
                      <SnapshotRow
                        key={snapshot.id}
                        snapshot={snapshot}
                        siteId={site.id}
                        onRestore={handleOpenRestore}
                        onDelete={setDeleteTarget}
                        onViewDetail={setDetailTarget}
                        isRestoring={isRestoring}
                        isDeleting={isDeleting}
                      />
                    ))}
                  </div>
                )}
              </ScrollArea>
            </TabsContent>

            {/* Timeline Tab - Visual chain of snapshots */}
            <TabsContent value="timeline" className="flex-1 min-h-0 mt-2">
              <ScrollArea className="h-full">
                {isLoading ? (
                  <div className="flex items-center justify-center py-12">
                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                  </div>
                ) : snapshots.length === 0 ? (
                  <div className="flex flex-col items-center justify-center py-12 gap-2 text-muted-foreground">
                    <GitBranch className="h-10 w-10 opacity-40" />
                    <p className="text-sm font-medium">No backup history</p>
                    <p className="text-xs text-center max-w-[240px]">
                      Create a full backup to start the timeline
                    </p>
                  </div>
                ) : (
                  <div className="space-y-0 pr-2">
                    {snapshots
                      .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
                      .map((snapshot, index) => {
                        const isMaster = snapshot.filename?.includes("_full_") || index === snapshots.length - 1;
                        const isIncremental = snapshot.filename?.includes("incremental") || snapshot.filename?.includes("inc_");
                        const isImported = snapshot.filename?.includes("imported");

                        return (
                          <div key={snapshot.id} className="flex gap-3 animate-fade-in">
                            {/* Timeline line + dot */}
                            <div className="flex flex-col items-center">
                              <div
                                className={`w-3 h-3 rounded-full border-2 shrink-0 ${
                                  isMaster
                                    ? "bg-primary border-primary"
                                    : isIncremental
                                    ? "bg-accent border-accent-foreground/30"
                                    : isImported
                                    ? "bg-muted-foreground border-muted-foreground"
                                    : "bg-secondary border-secondary-foreground/30"
                                }`}
                              />
                              {index < snapshots.length - 1 && (
                                <div className="w-0.5 flex-1 min-h-[32px] bg-border" />
                              )}
                            </div>

                            {/* Card */}
                            <div className="flex-1 pb-4">
                              <div className="border rounded-lg p-2.5 space-y-1.5 hover:bg-muted/30 transition-colors">
                                <div className="flex items-center justify-between gap-2">
                                  <div className="flex items-center gap-1.5 min-w-0">
                                    <span className="text-xs font-medium truncate">
                                      #{snapshot.sequence}
                                    </span>
                                    {isMaster && (
                                      <Badge className="bg-primary/10 text-primary border-primary/20 text-[10px] h-4">
                                        Master
                                      </Badge>
                                    )}
                                    {isIncremental && (
                                      <Badge variant="secondary" className="text-[10px] h-4">
                                        Incremental
                                      </Badge>
                                    )}
                                    {isImported && (
                                      <Badge variant="outline" className="text-[10px] h-4">
                                        Imported
                                      </Badge>
                                    )}
                                  </div>
                                  <span className="text-[10px] text-muted-foreground shrink-0">
                                    {relativeTime(snapshot.created_at)}
                                  </span>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-muted-foreground">
                                  {snapshot.total_rows > 0 && (
                                    <span className="flex items-center gap-0.5">
                                      <Table className="h-2.5 w-2.5" />
                                      {snapshot.total_rows.toLocaleString()} rows
                                    </span>
                                  )}
                                  {snapshot.file_size > 0 && (
                                    <span className="flex items-center gap-0.5">
                                      <HardDrive className="h-2.5 w-2.5" />
                                      {formatBytes(snapshot.file_size)}
                                    </span>
                                  )}
                                  <Badge
                                    variant={snapshot.status === "complete" ? "secondary" : "destructive"}
                                    className="text-[9px] h-3.5"
                                  >
                                    {snapshot.status}
                                  </Badge>
                                </div>

                                {/* Incremental chain arrow */}
                                {isIncremental && index < snapshots.length - 1 && (
                                  <div className="flex items-center gap-1 text-[10px] text-muted-foreground/60">
                                    <ArrowRight className="h-2.5 w-2.5" />
                                    <span>delta from master</span>
                                  </div>
                                )}
                              </div>
                            </div>
                          </div>
                        );
                      })}
                  </div>
                )}
              </ScrollArea>
            </TabsContent>

            <TabsContent value="settings" className="flex-1 min-h-0 mt-2">
              <ScrollArea className="h-full">
                <SnapshotSettingsTab siteId={site.id} />
              </ScrollArea>
            </TabsContent>
          </Tabs>
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
      <AlertDialog open={!!restoreTarget} onOpenChange={(o) => { if (!o) { setRestoreTarget(null); setRestoreMode("full"); } }}>
        <AlertDialogContent className="max-w-md">
          <AlertDialogHeader>
            <AlertDialogTitle>Restore Snapshot #{restoreTarget?.sequence}</AlertDialogTitle>
            <AlertDialogDescription>
              A pre-restore backup will be created automatically. This will overwrite database tables.
            </AlertDialogDescription>
          </AlertDialogHeader>

          <div className="space-y-3 py-2">
            {/* Restore Mode */}
            <div className="space-y-1.5">
              <Label className="text-xs font-medium">Restore Mode</Label>
              <Select value={restoreMode} onValueChange={(v) => setRestoreMode(v as "full" | "selective")}>
                <SelectTrigger className="h-8 text-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="full">Full Restore (all tables)</SelectItem>
                  <SelectItem value="selective">Selective (choose tables)</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Selective Table Picker */}
            {restoreMode === "selective" && restoreTarget && (
              <div className="border rounded-md p-2 space-y-1.5 animate-fade-in">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-medium text-muted-foreground">
                    Tables to restore ({restoreTables.length})
                  </Label>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="sm" className="h-5 text-[10px] px-1" onClick={() => {
                      const allTables = typeof restoreTarget.tables === "string"
                        ? restoreTarget.tables.split(",").map((t) => t.trim()).filter(Boolean)
                        : Array.isArray(restoreTarget.tables) ? restoreTarget.tables : [];
                      setRestoreTables(allTables);
                    }}>
                      All
                    </Button>
                    <Button variant="ghost" size="sm" className="h-5 text-[10px] px-1" onClick={() => setRestoreTables([])}>
                      None
                    </Button>
                  </div>
                </div>
                <ScrollArea className="max-h-40">
                  <div className="space-y-1">
                    {(typeof restoreTarget.tables === "string"
                      ? restoreTarget.tables.split(",").map((t) => t.trim()).filter(Boolean)
                      : Array.isArray(restoreTarget.tables) ? restoreTarget.tables : []
                    ).map((table) => (
                      <label key={table} className="flex items-center gap-2 text-xs hover:bg-muted/50 rounded px-1 py-0.5 cursor-pointer">
                        <Checkbox
                          checked={restoreTables.includes(table)}
                          onCheckedChange={(checked) => {
                            setRestoreTables((prev) =>
                              checked ? [...prev, table] : prev.filter((t) => t !== table)
                            );
                          }}
                        />
                        <span className="font-mono text-[11px]">{table}</span>
                      </label>
                    ))}
                  </div>
                </ScrollArea>
              </div>
            )}
          </div>

          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleRestore}
              disabled={restoreMode === "selective" && restoreTables.length === 0}
            >
              {restoreMode === "selective" ? `Restore ${restoreTables.length} tables` : "Restore All"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Snapshot Detail Dialog */}
      <Dialog open={!!detailTarget} onOpenChange={(o) => !o && setDetailTarget(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5 text-primary" />
              Snapshot #{detailTarget?.sequence}
            </DialogTitle>
            <DialogDescription>{detailTarget?.filename}</DialogDescription>
          </DialogHeader>
          {detailTarget && (
            <div className="space-y-3 text-sm">
              <div className="grid grid-cols-2 gap-2">
                <div className="text-muted-foreground">Status</div>
                <div className="font-medium capitalize">{detailTarget.status}</div>
                <div className="text-muted-foreground">Scope</div>
                <div className="font-medium capitalize">{detailTarget.scope}</div>
                <div className="text-muted-foreground">Provider</div>
                <div className="font-medium">{detailTarget.provider}</div>
                {detailTarget.file_size > 0 && (
                  <>
                    <div className="text-muted-foreground">File Size</div>
                    <div className="font-medium">{formatBytes(detailTarget.file_size)}</div>
                  </>
                )}
                {detailTarget.total_rows > 0 && (
                  <>
                    <div className="text-muted-foreground">Total Rows</div>
                    <div className="font-medium">{detailTarget.total_rows.toLocaleString()}</div>
                  </>
                )}
                <div className="text-muted-foreground">Created</div>
                <div className="font-medium">{relativeTime(detailTarget.created_at)}</div>
              </div>

              {/* Tables list */}
              {detailTarget.tables && (
                <div className="space-y-1.5">
                  <div className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                    <Table className="h-3 w-3" />
                    Tables Included
                  </div>
                  <div className="bg-muted/50 rounded-md p-2 max-h-40 overflow-y-auto">
                    <div className="flex flex-wrap gap-1">
                      {(typeof detailTarget.tables === "string"
                        ? detailTarget.tables.split(",").map((t) => t.trim()).filter(Boolean)
                        : Array.isArray(detailTarget.tables) ? detailTarget.tables : []
                      ).map((table, i) => (
                        <Badge key={i} variant="outline" className="text-[10px] h-5 font-mono">
                          {table}
                        </Badge>
                      ))}
                    </div>
                  </div>
                </div>
              )}

              {detailTarget.error && (
                <div className="flex items-center gap-1.5">
                  <div className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1.5 flex-1">
                    {detailTarget.error}
                  </div>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-6 w-6 p-0 text-muted-foreground hover:text-foreground shrink-0"
                    onClick={() => {
                      navigator.clipboard.writeText(toClipboardText(detailTarget.error || ""));
                      toast.success("Error copied to clipboard");
                    }}
                    title="Copy error"
                  >
                    <Copy className="h-3 w-3" />
                  </Button>
                </div>
              )}

              {detailTarget.status === "complete" && (
                <Button size="sm" className="w-full" asChild>
                  <a href={api.getRemoteSnapshotExportUrl(site.id, detailTarget.id)} download>
                    <Download className="h-3.5 w-3.5 mr-1.5" />
                    Download ZIP
                  </a>
                </Button>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}
