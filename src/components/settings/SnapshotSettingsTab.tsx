import { useState, useEffect, useCallback } from "react";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { SnapshotComparisonView } from "./SnapshotComparisonView";
import { useSnapshotNotifications } from "./useSnapshotNotifications";
import { SnapshotRetentionPolicy, type RetentionConfig } from "./SnapshotRetentionPolicy";
import { SnapshotRestoreDialog } from "./SnapshotRestoreDialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Slider } from "@/components/ui/slider";
import { Progress } from "@/components/ui/progress";
import { useSettings, useSaveSettings } from "@/hooks/useSettings";
import { SnapshotInterval, SnapshotSchedule, SnapshotRecord, SnapshotCronJob } from "@/lib/api/types";
import { useApiQuery } from "@/hooks/useApiQuery";
import { api, requireSuccess } from "@/lib/api";
import { wsClient, WS_EVENTS } from "@/lib/ws";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet";
import {
  Database,
  Plus,
  Trash2,
  Loader2,
  HardDrive,
  Layers,
  Clock,
  Cpu,
  History,
  CheckCircle2,
  XCircle,
  AlertCircle,
  RefreshCw,
  Play,
  Pause,
  Zap,
  Radio,
  Download,
  RotateCcw,
  Activity,
  Server,
} from "lucide-react";
import { toast } from "sonner";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { formatDistanceToNow, format } from "date-fns";

const INTERVAL_OPTIONS: { value: SnapshotInterval; label: string }[] = [
  { value: "hourly", label: "Every Hour" },
  { value: "3h", label: "Every 3 Hours" },
  { value: "6h", label: "Every 6 Hours" },
  { value: "12h", label: "Every 12 Hours" },
  { value: "daily", label: "Daily" },
  { value: "weekly", label: "Weekly" },
  { value: "monthly", label: "Monthly" },
  { value: "yearly", label: "Yearly" },
];

function generateId() {
  return `sched_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 6)}`;
}

export function SnapshotSettingsTab() {
  const { data: settings } = useSettings();
  const saveSettings = useSaveSettings();

  const [enabled, setEnabled] = useState(false);
  const [schedules, setSchedules] = useState<SnapshotSchedule[]>([]);
  const [storageMode, setStorageMode] = useState<"single" | "per-table">("single");
  const [workerCount, setWorkerCount] = useState(4);
  const [batchSize, setBatchSize] = useState(10);
  const [isDirty, setIsDirty] = useState(false);
  const [retention, setRetention] = useState<RetentionConfig>({
    enabled: false,
    mode: "age",
    maxAgeDays: 30,
    maxCount: 50,
    autoCleanup: false,
  });

  useEffect(() => {
    if (settings?.snapshots) {
      setEnabled(settings.snapshots.enabled);
      setSchedules(settings.snapshots.schedules || []);
      setStorageMode(settings.snapshots.storageMode || "single");
      setWorkerCount(settings.snapshots.workerCount || 4);
      setBatchSize(settings.snapshots.batchSize || 10);
    }
  }, [settings]);

  const markDirty = () => setIsDirty(true);

  const handleSave = async () => {
    saveSettings.mutate(
      {
        snapshots: {
          enabled,
          schedules,
          storageMode,
          workerCount,
          batchSize,
        },
      },
      {
        onSuccess: async () => {
          setIsDirty(false);
          toast.success("Snapshot settings saved", {
            style: {
              background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
              color: "white",
              border: "none",
            },
          });
          try {
            await api.syncSnapshotCronJobs(0);
          } catch {
            // silent
          }
        },
        onError: (err) => toast.error(`Failed to save: ${err.message}`),
      }
    );
  };

  const addSchedule = () => {
    const usedIntervals = new Set(schedules.map((s) => s.interval));
    const available = INTERVAL_OPTIONS.find((o) => !usedIntervals.has(o.value));
    const interval = available?.value || "daily";
    setSchedules((prev) => [
      ...prev,
      { id: generateId(), interval, enabled: true },
    ]);
    markDirty();
  };

  const removeSchedule = (id: string) => {
    setSchedules((prev) => prev.filter((s) => s.id !== id));
    markDirty();
  };

  const updateSchedule = (id: string, patch: Partial<SnapshotSchedule>) => {
    setSchedules((prev) =>
      prev.map((s) => (s.id === id ? { ...s, ...patch } : s))
    );
    markDirty();
  };

  return (
    <div className="space-y-4 sm:space-y-6">
      <div>
        <h2 className="text-base sm:text-lg font-semibold mb-1">Database Snapshots</h2>
        <p className="text-xs sm:text-sm text-muted-foreground">
          Configure automatic database snapshots via cron jobs
        </p>
      </div>

      {/* Auto Snapshot Toggle */}
      <div className="flex items-center justify-between gap-3">
        <div className="min-w-0">
          <Label className="text-sm">Auto Snapshot</Label>
          <p className="text-xs text-muted-foreground">
            Enable scheduled database snapshots
          </p>
        </div>
        <Switch
          checked={enabled}
          onCheckedChange={(v) => {
            setEnabled(v);
            markDirty();
          }}
          className="shrink-0"
        />
      </div>

      {enabled && (
        <>
          <Separator />

          {/* Schedules */}
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2 text-sm font-medium">
                <Clock className="h-4 w-4" />
                Schedules
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={addSchedule}
                disabled={schedules.length >= INTERVAL_OPTIONS.length}
                className="text-xs h-7"
              >
                <Plus className="h-3.5 w-3.5 mr-1" />
                Add Schedule
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              Multiple schedules can run simultaneously via separate cron jobs
            </p>

            {schedules.length === 0 && (
              <div className="text-center py-6 text-muted-foreground text-xs border rounded-md border-dashed">
                No schedules configured. Add one to get started.
              </div>
            )}

            <div className="space-y-2">
              {schedules.map((schedule) => (
                <div
                  key={schedule.id}
                  className={cn(
                    "flex items-center gap-3 p-3 rounded-lg border transition-colors",
                    schedule.enabled
                      ? "bg-accent/30 border-primary/20"
                      : "bg-muted/30 opacity-60"
                  )}
                >
                  <Switch
                    checked={schedule.enabled}
                    onCheckedChange={(v) =>
                      updateSchedule(schedule.id, { enabled: v })
                    }
                    className="shrink-0"
                  />
                  <Select
                    value={schedule.interval}
                    onValueChange={(v) =>
                      updateSchedule(schedule.id, {
                        interval: v as SnapshotInterval,
                      })
                    }
                  >
                    <SelectTrigger className="h-8 text-xs flex-1">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {INTERVAL_OPTIONS.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => removeSchedule(schedule.id)}
                    className="h-8 w-8 p-0 text-destructive hover:text-destructive shrink-0"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              ))}
            </div>
          </div>

          <Separator />

          {/* Storage Mode */}
          <div className="space-y-3">
            <div className="flex items-center gap-2 text-sm font-medium">
              <HardDrive className="h-4 w-4" />
              Storage Mode
            </div>
            <p className="text-xs text-muted-foreground">
              How snapshot data is stored on disk
            </p>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button
                type="button"
                onClick={() => {
                  setStorageMode("single");
                  markDirty();
                }}
                className={cn(
                  "flex items-start gap-3 p-3 rounded-lg border transition-all text-left",
                  storageMode === "single"
                    ? "border-primary bg-primary/5 shadow-sm"
                    : "hover:bg-accent/50"
                )}
              >
                <Database className="h-5 w-5 mt-0.5 shrink-0 text-primary" />
                <div>
                  <p className="text-sm font-medium">Single File</p>
                  <p className="text-xs text-muted-foreground">
                    All tables in one SQLite database. Simpler management.
                  </p>
                </div>
              </button>

              <button
                type="button"
                onClick={() => {
                  setStorageMode("per-table");
                  markDirty();
                }}
                className={cn(
                  "flex items-start gap-3 p-3 rounded-lg border transition-all text-left",
                  storageMode === "per-table"
                    ? "border-primary bg-primary/5 shadow-sm"
                    : "hover:bg-accent/50"
                )}
              >
                <Layers className="h-5 w-5 mt-0.5 shrink-0 text-primary" />
                <div>
                  <p className="text-sm font-medium">Per-Table Files</p>
                  <p className="text-xs text-muted-foreground">
                    Separate SQLite file per table. Parallel backup via worker pool.
                  </p>
                </div>
              </button>
            </div>
          </div>

          <Separator />

          {/* Worker Pool Settings */}
          <div className="space-y-4">
            <div className="flex items-center gap-2 text-sm font-medium">
              <Cpu className="h-4 w-4" />
              Worker Pool
            </div>
            <p className="text-xs text-muted-foreground">
              Controls parallel execution when using per-table storage mode
            </p>

            <div className="space-y-4">
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label className="text-xs">Worker Count</Label>
                  <span className="text-xs font-mono text-muted-foreground">
                    {workerCount}
                  </span>
                </div>
                <Slider
                  value={[workerCount]}
                  onValueChange={([v]) => {
                    setWorkerCount(v);
                    markDirty();
                  }}
                  min={1}
                  max={16}
                  step={1}
                  className="w-full"
                />
                <p className="text-xs text-muted-foreground">
                  Number of concurrent backup workers (1–16)
                </p>
              </div>

              <div className="space-y-2">
                <Label className="text-xs">Batch Size</Label>
                <Input
                  type="number"
                  min={1}
                  max={100}
                  value={batchSize}
                  onChange={(e) => {
                    setBatchSize(parseInt(e.target.value) || 10);
                    markDirty();
                  }}
                  className="h-9"
                />
                <p className="text-xs text-muted-foreground">
                  Number of rows processed per batch during backup
                </p>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Save button */}
      {isDirty && (
        <div className="pt-2">
          <Button
            onClick={handleSave}
            disabled={saveSettings.isPending}
            className="w-full"
          >
            {saveSettings.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin mr-2" />
            ) : (
              <Database className="h-4 w-4 mr-2" />
            )}
            Save Snapshot Settings
          </Button>
        </div>
      )}

      {enabled && (
        <>
          <Separator />

          {/* Retention Policy */}
          <SnapshotRetentionPolicy
            config={retention}
            onChange={(c) => { setRetention(c); markDirty(); }}
          />

          <Separator />
          <CronJobsPanel />
        </>
      )}

      <Separator />

      {/* Live Progress Panel */}
      <SnapshotProgressPanel />

      <Separator />

      {/* Snapshot History */}
      <SnapshotHistoryViewer />
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Phase 3: Live Worker-Pool Progress Panel                                  */
/* -------------------------------------------------------------------------- */

interface WorkerTableStatus {
  table: string;
  status: "pending" | "running" | "completed" | "failed";
  rowsProcessed: number;
  totalRows: number;
  workerId?: number;
  error?: string;
}

interface SnapshotProgress {
  snapshotId?: number;
  status: "idle" | "running" | "completed" | "failed";
  totalTables: number;
  completedTables: number;
  totalRows: number;
  processedRows: number;
  activeWorkers: number;
  tables: WorkerTableStatus[];
  startedAt?: string;
  error?: string;
}

function SnapshotProgressPanel() {
  const [progress, setProgress] = useState<SnapshotProgress>({
    status: "idle",
    totalTables: 0,
    completedTables: 0,
    totalRows: 0,
    processedRows: 0,
    activeWorkers: 0,
    tables: [],
  });

  useEffect(() => {
    const unsubStarted = wsClient.on(WS_EVENTS.SNAPSHOT_STARTED, (data: unknown) => {
      const d = data as {
        snapshotId?: number;
        totalTables: number;
        totalRows: number;
        tables: string[];
        workerCount: number;
      };
      setProgress({
        snapshotId: d.snapshotId,
        status: "running",
        totalTables: d.totalTables,
        completedTables: 0,
        totalRows: d.totalRows,
        processedRows: 0,
        activeWorkers: d.workerCount,
        tables: d.tables.map((t) => ({
          table: t,
          status: "pending",
          rowsProcessed: 0,
          totalRows: 0,
        })),
        startedAt: new Date().toISOString(),
      });
    });

    const unsubProgress = wsClient.on(WS_EVENTS.SNAPSHOT_PROGRESS, (data: unknown) => {
      const d = data as {
        table: string;
        workerId: number;
        rowsProcessed: number;
        totalRows: number;
        status: "running" | "completed" | "failed";
        error?: string;
      };
      setProgress((prev) => {
        if (prev.status !== "running") return prev;
        const tables = prev.tables.map((t) =>
          t.table === d.table
            ? { ...t, status: d.status, rowsProcessed: d.rowsProcessed, totalRows: d.totalRows, workerId: d.workerId, error: d.error }
            : t
        );
        const processedRows = tables.reduce((sum, t) => sum + t.rowsProcessed, 0);
        const completedTables = tables.filter((t) => t.status === "completed" || t.status === "failed").length;
        const activeWorkers = tables.filter((t) => t.status === "running").length;
        return { ...prev, tables, processedRows, completedTables, activeWorkers };
      });
    });

    const unsubTableComplete = wsClient.on(WS_EVENTS.SNAPSHOT_TABLE_COMPLETE, (data: unknown) => {
      const d = data as { table: string; rowsProcessed: number; workerId: number };
      setProgress((prev) => {
        const tables = prev.tables.map((t) =>
          t.table === d.table
            ? { ...t, status: "completed" as const, rowsProcessed: d.rowsProcessed }
            : t
        );
        const completedTables = tables.filter((t) => t.status === "completed" || t.status === "failed").length;
        return { ...prev, tables, completedTables };
      });
    });

    const unsubComplete = wsClient.on(WS_EVENTS.SNAPSHOT_COMPLETE, (data: unknown) => {
      const d = data as { snapshotId?: number; success: boolean; error?: string; totalRows: number };
      setProgress((prev) => ({
        ...prev,
        status: d.success ? "completed" : "failed",
        processedRows: d.success ? prev.totalRows : prev.processedRows,
        completedTables: d.success ? prev.totalTables : prev.completedTables,
        activeWorkers: 0,
        error: d.error,
      }));
    });

    return () => {
      unsubStarted();
      unsubProgress();
      unsubTableComplete();
      unsubComplete();
    };
  }, []);

  if (progress.status === "idle") {
    return (
      <div className="space-y-3">
        <div className="flex items-center gap-2 text-sm font-medium">
          <Activity className="h-4 w-4" />
          Live Progress
        </div>
        <div className="text-center py-6 text-muted-foreground text-xs border rounded-md border-dashed">
          No snapshot running. Progress will appear here when a backup starts.
        </div>
      </div>
    );
  }

  const overallPercent = progress.totalRows > 0
    ? Math.round((progress.processedRows / progress.totalRows) * 100)
    : progress.totalTables > 0
      ? Math.round((progress.completedTables / progress.totalTables) * 100)
      : 0;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <Activity className={cn("h-4 w-4", progress.status === "running" && "animate-pulse text-blue-500")} />
          Live Progress
        </div>
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          {progress.status === "running" && (
            <>
              <Server className="h-3.5 w-3.5" />
              <span>{progress.activeWorkers} worker{progress.activeWorkers !== 1 ? "s" : ""} active</span>
            </>
          )}
          <span
            className={cn(
              "text-[10px] uppercase font-medium tracking-wider",
              progress.status === "running" && "text-blue-500",
              progress.status === "completed" && "text-emerald-500",
              progress.status === "failed" && "text-destructive",
            )}
          >
            {progress.status}
          </span>
        </div>
      </div>

      {/* Overall progress bar */}
      <div className="space-y-1.5">
        <div className="flex items-center justify-between text-xs text-muted-foreground">
          <span>{progress.completedTables}/{progress.totalTables} tables</span>
          <span>{overallPercent}%</span>
        </div>
        <Progress value={overallPercent} className="h-2" />
        <div className="flex items-center justify-between text-[11px] text-muted-foreground">
          <span>{progress.processedRows.toLocaleString()} / {progress.totalRows.toLocaleString()} rows</span>
          {progress.startedAt && (
            <span>Started {formatDistanceToNow(new Date(progress.startedAt), { addSuffix: true })}</span>
          )}
        </div>
      </div>

      {progress.error && (
        <div className="text-xs text-destructive bg-destructive/10 rounded-md p-2 border border-destructive/20">
          {progress.error}
        </div>
      )}

      {/* Per-table worker status */}
      {progress.tables.length > 0 && (
        <div className="max-h-48 overflow-y-auto rounded-md border">
          <Table>
            <TableHeader>
              <TableRow className="bg-muted/40">
                <TableHead className="text-[11px] py-1.5">Table</TableHead>
                <TableHead className="text-[11px] py-1.5 w-[60px]">Worker</TableHead>
                <TableHead className="text-[11px] py-1.5">Progress</TableHead>
                <TableHead className="text-[11px] py-1.5 w-[70px]">Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {progress.tables.map((t) => {
                const pct = t.totalRows > 0 ? Math.round((t.rowsProcessed / t.totalRows) * 100) : 0;
                return (
                  <TableRow key={t.table} className="text-xs">
                    <TableCell className="py-1.5 font-mono text-[11px] truncate max-w-[150px]" title={t.table}>
                      {t.table}
                    </TableCell>
                    <TableCell className="py-1.5 text-muted-foreground font-mono text-[11px]">
                      {t.workerId != null ? `#${t.workerId}` : "—"}
                    </TableCell>
                    <TableCell className="py-1.5">
                      <div className="flex items-center gap-2">
                        <Progress value={t.status === "completed" ? 100 : pct} className="h-1.5 flex-1" />
                        <span className="text-[10px] text-muted-foreground w-[30px] text-right">
                          {t.status === "completed" ? "100%" : `${pct}%`}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell className="py-1.5">
                      <span
                        className={cn(
                          "text-[10px] font-medium uppercase",
                          t.status === "running" && "text-blue-500",
                          t.status === "completed" && "text-emerald-500",
                          t.status === "failed" && "text-destructive",
                          t.status === "pending" && "text-muted-foreground",
                        )}
                      >
                        {t.status}
                      </span>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>
      )}

      {progress.status !== "running" && (
        <Button
          variant="ghost"
          size="sm"
          className="text-xs h-7"
          onClick={() =>
            setProgress({
              status: "idle",
              totalTables: 0,
              completedTables: 0,
              totalRows: 0,
              processedRows: 0,
              activeWorkers: 0,
              tables: [],
            })
          }
        >
          Dismiss
        </Button>
      )}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Cron Jobs Panel                                                           */
/* -------------------------------------------------------------------------- */

const CRON_STATUS_CONFIG: Record<string, { className: string; label: string }> = {
  active: { className: "text-emerald-500", label: "Active" },
  paused: { className: "text-amber-500", label: "Paused" },
  error: { className: "text-destructive", label: "Error" },
};

function CronJobsPanel() {
  const {
    data: cronJobs,
    isLoading,
    refetch,
    isFetching,
  } = useApiQuery<SnapshotCronJob[]>({
    queryKey: ["snapshot-cron-jobs"],
    apiFn: () => api.getSnapshotCronJobs(0),
    endpoint: "/sites/0/snapshots/cron",
  });

  const [syncing, setSyncing] = useState(false);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const jobs = cronJobs ?? [];

  const handleSync = async () => {
    setSyncing(true);
    try {
      const res = await api.syncSnapshotCronJobs(0);
      const result = requireSuccess(res, { endpoint: "/sites/0/snapshots/cron/sync", method: "POST" });
      toast.success(`Cron sync: ${result.created} created, ${result.updated} updated, ${result.removed} removed`);
      refetch();
    } catch (err: any) {
      toast.error(`Cron sync failed: ${err.message}`);
    } finally {
      setSyncing(false);
    }
  };

  const handleTrigger = async (cronId: string) => {
    setActionLoading(cronId);
    try {
      const res = await api.triggerSnapshotCronJob(0, cronId);
      requireSuccess(res, { endpoint: `/sites/0/snapshots/cron/${cronId}/trigger`, method: "POST" });
      toast.success("Snapshot triggered");
      refetch();
    } catch (err: any) {
      toast.error(`Trigger failed: ${err.message}`);
    } finally {
      setActionLoading(null);
    }
  };

  const handleTogglePause = async (job: SnapshotCronJob) => {
    setActionLoading(job.id);
    try {
      const apiFn = job.status === "paused" ? api.resumeSnapshotCronJob : api.pauseSnapshotCronJob;
      const res = await apiFn(0, job.id);
      requireSuccess(res, { endpoint: `/sites/0/snapshots/cron/${job.id}/${job.status === "paused" ? "resume" : "pause"}`, method: "POST" });
      toast.success(job.status === "paused" ? "Cron job resumed" : "Cron job paused");
      refetch();
    } catch (err: any) {
      toast.error(`Action failed: ${err.message}`);
    } finally {
      setActionLoading(null);
    }
  };

  const intervalLabel = (interval: string) =>
    INTERVAL_OPTIONS.find((o) => o.value === interval)?.label ?? interval;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <Radio className="h-4 w-4" />
          Cron Jobs
        </div>
        <div className="flex items-center gap-1.5">
          <Button
            variant="outline"
            size="sm"
            onClick={handleSync}
            disabled={syncing}
            className="h-7 text-xs"
          >
            <Zap className={cn("h-3.5 w-3.5 mr-1", syncing && "animate-spin")} />
            Sync
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => refetch()}
            disabled={isFetching}
            className="h-7 text-xs"
          >
            <RefreshCw className={cn("h-3.5 w-3.5 mr-1", isFetching && "animate-spin")} />
            Refresh
          </Button>
        </div>
      </div>
      <p className="text-xs text-muted-foreground">
        Active cron jobs running on the backend. Sync to match current schedules.
      </p>

      {isLoading ? (
        <div className="flex items-center justify-center py-8 text-muted-foreground text-xs gap-2">
          <Loader2 className="h-4 w-4 animate-spin" />
          Loading cron jobs…
        </div>
      ) : jobs.length === 0 ? (
        <div className="text-center py-6 text-muted-foreground text-xs border rounded-md border-dashed">
          No cron jobs registered. Save settings with schedules, then sync.
        </div>
      ) : (
        <div className="space-y-2">
          {jobs.map((job) => {
            const statusCfg = CRON_STATUS_CONFIG[job.status] ?? { className: "text-muted-foreground", label: job.status };
            const isThisLoading = actionLoading === job.id;
            return (
              <div
                key={job.id}
                className={cn(
                  "flex items-center gap-3 p-3 rounded-lg border transition-colors",
                  job.status === "active" ? "bg-accent/20 border-primary/15" : "bg-muted/30"
                )}
              >
                <div className="flex-1 min-w-0 space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{intervalLabel(job.interval)}</span>
                    <span className={cn("text-[10px] font-medium uppercase tracking-wider", statusCfg.className)}>
                      {statusCfg.label}
                    </span>
                  </div>
                  <div className="flex items-center gap-3 text-[11px] text-muted-foreground">
                    <span className="font-mono">{job.cronExpression}</span>
                    {job.nextRunAt && (
                      <span>
                        Next: {formatDistanceToNow(new Date(job.nextRunAt), { addSuffix: true })}
                      </span>
                    )}
                    {job.lastRunAt && (
                      <span>
                        Last: {formatDistanceToNow(new Date(job.lastRunAt), { addSuffix: true })}
                      </span>
                    )}
                    <span>Runs: {job.runCount}</span>
                  </div>
                  {job.lastError && (
                    <p className="text-[10px] text-destructive truncate max-w-[300px]" title={job.lastError}>
                      {job.lastError}
                    </p>
                  )}
                </div>
                <div className="flex items-center gap-1 shrink-0">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleTogglePause(job)}
                    disabled={isThisLoading}
                    className="h-7 w-7 p-0"
                    title={job.status === "paused" ? "Resume" : "Pause"}
                  >
                    {isThisLoading ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : job.status === "paused" ? (
                      <Play className="h-3.5 w-3.5" />
                    ) : (
                      <Pause className="h-3.5 w-3.5" />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleTrigger(job.id)}
                    disabled={isThisLoading}
                    className="h-7 w-7 p-0"
                    title="Trigger now"
                  >
                    <Zap className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Snapshot History Viewer + Phase 4: Detail Drawer                           */
/* -------------------------------------------------------------------------- */

function formatBytes(bytes: number): string {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

const STATUS_CONFIG: Record<string, { icon: typeof CheckCircle2; className: string; label: string }> = {
  completed: { icon: CheckCircle2, className: "text-emerald-500", label: "Completed" },
  success: { icon: CheckCircle2, className: "text-emerald-500", label: "Success" },
  failed: { icon: XCircle, className: "text-destructive", label: "Failed" },
  error: { icon: XCircle, className: "text-destructive", label: "Error" },
  running: { icon: RefreshCw, className: "text-blue-500 animate-spin", label: "Running" },
  in_progress: { icon: RefreshCw, className: "text-blue-500 animate-spin", label: "In Progress" },
  pending: { icon: AlertCircle, className: "text-amber-500", label: "Pending" },
};

function StatusBadge({ status }: { status: string }) {
  const config = STATUS_CONFIG[status?.toLowerCase()] ?? {
    icon: AlertCircle,
    className: "text-muted-foreground",
    label: status || "Unknown",
  };
  const Icon = config.icon;
  return (
    <span className={cn("inline-flex items-center gap-1 text-xs font-medium", config.className)}>
      <Icon className="h-3.5 w-3.5" />
      {config.label}
    </span>
  );
}

function SnapshotHistoryViewer() {
  const {
    data: snapshots,
    isLoading,
    refetch,
    isFetching,
  } = useApiQuery<SnapshotRecord[]>({
    queryKey: ["snapshot-history"],
    apiFn: () => api.getRemoteSnapshots(0),
    endpoint: "/sites/0/snapshots",
  });

  const [selectedSnapshot, setSelectedSnapshot] = useState<SnapshotRecord | null>(null);

  const records = snapshots ?? [];

  // Snapshot completion/failure notifications with link to view details
  const handleViewFromNotification = useCallback((snapshotId: number) => {
    const snap = records.find((s) => s.id === snapshotId);
    if (snap) setSelectedSnapshot(snap);
  }, [records]);

  useSnapshotNotifications(handleViewFromNotification);

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <History className="h-4 w-4" />
          Snapshot History
        </div>
        <div className="flex items-center gap-1.5">
          <SnapshotComparisonView snapshots={records} />
          <Button
            variant="ghost"
            size="sm"
            onClick={() => refetch()}
            disabled={isFetching}
            className="h-7 text-xs"
          >
            <RefreshCw className={cn("h-3.5 w-3.5 mr-1", isFetching && "animate-spin")} />
            Refresh
          </Button>
        </div>
      </div>
      <p className="text-xs text-muted-foreground">
        Recent snapshot runs and their outcomes. Click a row for details.
      </p>

      {isLoading ? (
        <div className="flex items-center justify-center py-10 text-muted-foreground text-xs gap-2">
          <Loader2 className="h-4 w-4 animate-spin" />
          Loading history…
        </div>
      ) : records.length === 0 ? (
        <div className="text-center py-8 text-muted-foreground text-xs border rounded-md border-dashed">
          No snapshot history yet. Snapshots will appear here after the first run.
        </div>
      ) : (
        <div className="rounded-md border overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-muted/40">
                <TableHead className="text-xs w-[50px]">#</TableHead>
                <TableHead className="text-xs">Status</TableHead>
                <TableHead className="text-xs">Scope</TableHead>
                <TableHead className="text-xs hidden sm:table-cell">Tables</TableHead>
                <TableHead className="text-xs hidden md:table-cell">Rows</TableHead>
                <TableHead className="text-xs hidden md:table-cell">Size</TableHead>
                <TableHead className="text-xs">Created</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {records.map((snap) => (
                <TableRow
                  key={snap.id}
                  className="cursor-pointer hover:bg-accent/40 transition-colors"
                  onClick={() => setSelectedSnapshot(snap)}
                >
                  <TableCell className="font-mono text-xs text-muted-foreground">
                    {snap.sequence}
                  </TableCell>
                  <TableCell>
                    <StatusBadge status={snap.status} />
                    {snap.error && (
                      <p className="text-[10px] text-destructive mt-0.5 truncate max-w-[200px]" title={snap.error}>
                        {snap.error}
                      </p>
                    )}
                  </TableCell>
                  <TableCell className="text-xs capitalize">{snap.scope}</TableCell>
                  <TableCell className="text-xs hidden sm:table-cell">
                    <span className="font-mono">{snap.tables?.split(",").length ?? 0}</span>
                  </TableCell>
                  <TableCell className="text-xs font-mono hidden md:table-cell">
                    {snap.total_rows?.toLocaleString() ?? "—"}
                  </TableCell>
                  <TableCell className="text-xs font-mono hidden md:table-cell">
                    {snap.file_size ? formatBytes(snap.file_size) : "—"}
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground">
                    <span title={snap.created_at ? format(new Date(snap.created_at), "PPpp") : ""}>
                      {snap.created_at
                        ? formatDistanceToNow(new Date(snap.created_at), { addSuffix: true })
                        : "—"}
                    </span>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {/* Phase 4: Snapshot Detail Drawer */}
      <SnapshotDetailDrawer
        snapshot={selectedSnapshot}
        onClose={() => setSelectedSnapshot(null)}
        onRefresh={() => refetch()}
      />
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/*  Phase 4: Snapshot Detail Drawer                                           */
/* -------------------------------------------------------------------------- */

function SnapshotDetailDrawer({
  snapshot,
  onClose,
  onRefresh,
}: {
  snapshot: SnapshotRecord | null;
  onClose: () => void;
  onRefresh: () => void;
}) {
  const [restoreDialogOpen, setRestoreDialogOpen] = useState(false);

  const tableList = snapshot?.tables?.split(",").filter(Boolean) ?? [];

  const handleDownload = () => {
    if (!snapshot) return;
    const url = api.getRemoteSnapshotExportUrl(0, snapshot.id);
    window.open(url, "_blank");
  };

  return (
    <>
      <Sheet open={!!snapshot} onOpenChange={(open) => !open && onClose()}>
        <SheetContent side="right" className="w-full sm:max-w-lg overflow-y-auto">
          <SheetHeader>
            <SheetTitle className="flex items-center gap-2">
              <Database className="h-5 w-5" />
              Snapshot #{snapshot?.sequence}
            </SheetTitle>
            <SheetDescription>
              {snapshot?.created_at
                ? format(new Date(snapshot.created_at), "PPpp")
                : "Unknown date"}
            </SheetDescription>
          </SheetHeader>

          {snapshot && (
            <div className="mt-6 space-y-5">
              {/* Status & Overview */}
              <div className="grid grid-cols-2 gap-3">
                <div className="rounded-lg border p-3 space-y-1">
                  <p className="text-[11px] text-muted-foreground uppercase tracking-wider">Status</p>
                  <StatusBadge status={snapshot.status} />
                </div>
                <div className="rounded-lg border p-3 space-y-1">
                  <p className="text-[11px] text-muted-foreground uppercase tracking-wider">Scope</p>
                  <p className="text-sm font-medium capitalize">{snapshot.scope}</p>
                </div>
                <div className="rounded-lg border p-3 space-y-1">
                  <p className="text-[11px] text-muted-foreground uppercase tracking-wider">Total Rows</p>
                  <p className="text-sm font-mono font-medium">{snapshot.total_rows?.toLocaleString() ?? "—"}</p>
                </div>
                <div className="rounded-lg border p-3 space-y-1">
                  <p className="text-[11px] text-muted-foreground uppercase tracking-wider">File Size</p>
                  <p className="text-sm font-mono font-medium">{snapshot.file_size ? formatBytes(snapshot.file_size) : "—"}</p>
                </div>
              </div>

              {/* Provider & Filename */}
              <div className="space-y-2">
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Provider</span>
                  <span className="font-mono">{snapshot.provider || "—"}</span>
                </div>
                <div className="flex items-center justify-between text-xs">
                  <span className="text-muted-foreground">Filename</span>
                  <span className="font-mono text-[11px] truncate max-w-[250px]" title={snapshot.filename}>
                    {snapshot.filename || "—"}
                  </span>
                </div>
              </div>

              {/* Error Details */}
              {snapshot.error && (
                <div className="space-y-1.5">
                  <p className="text-xs font-medium text-destructive flex items-center gap-1.5">
                    <XCircle className="h-3.5 w-3.5" />
                    Error Details
                  </p>
                  <div className="text-xs text-destructive bg-destructive/10 rounded-md p-3 border border-destructive/20 whitespace-pre-wrap break-words font-mono">
                    {snapshot.error}
                  </div>
                </div>
              )}

              <Separator />

              {/* Table List */}
              <div className="space-y-2">
                <p className="text-xs font-medium flex items-center gap-1.5">
                  <Layers className="h-3.5 w-3.5" />
                  Tables ({tableList.length})
                </p>
                {tableList.length > 0 ? (
                  <div className="max-h-60 overflow-y-auto rounded-md border">
                    <div className="divide-y">
                      {tableList.map((table) => (
                        <div key={table} className="flex items-center gap-2 px-3 py-2 text-xs">
                          <Database className="h-3 w-3 text-muted-foreground shrink-0" />
                          <span className="font-mono truncate">{table.trim()}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  <p className="text-xs text-muted-foreground">No table information available.</p>
                )}
              </div>

              <Separator />

              {/* Actions */}
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleDownload}
                  className="flex-1 text-xs"
                >
                  <Download className="h-3.5 w-3.5 mr-1.5" />
                  Download
                </Button>
                <Button
                  variant="default"
                  size="sm"
                  onClick={() => setRestoreDialogOpen(true)}
                  disabled={snapshot.status === "running" || snapshot.status === "in_progress"}
                  className="flex-1 text-xs"
                >
                  <RotateCcw className="h-3.5 w-3.5 mr-1.5" />
                  Restore
                </Button>
              </div>
            </div>
          )}
        </SheetContent>
      </Sheet>

      <SnapshotRestoreDialog
        snapshot={snapshot}
        open={restoreDialogOpen}
        onOpenChange={setRestoreDialogOpen}
        onRestoreComplete={onRefresh}
      />
    </>
  );
}
