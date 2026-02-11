import { useState, useEffect } from "react";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
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
import { useSettings, useSaveSettings } from "@/hooks/useSettings";
import { SnapshotInterval, SnapshotSchedule, SnapshotRecord, SnapshotCronJob } from "@/lib/api/types";
import { useApiQuery } from "@/hooks/useApiQuery";
import { api, requireSuccess } from "@/lib/api";
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
          // Auto-sync cron jobs after saving schedules
          try {
            await api.syncSnapshotCronJobs(0);
          } catch {
            // silent — cron panel will show stale state until refresh
          }
        },
        onError: (err) => toast.error(`Failed to save: ${err.message}`),
      }
    );
  };

  const addSchedule = () => {
    // Pick first interval not already used
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
          <CronJobsPanel />
        </>
      )}

      <Separator />

      {/* Snapshot History */}
      <SnapshotHistoryViewer />
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
/*  Snapshot History Viewer                                                    */
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
  // Use siteId=0 as a sentinel — the settings page isn't site-scoped.
  // The backend's /snapshots/history endpoint returns global history.
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

  const records = snapshots ?? [];

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <History className="h-4 w-4" />
          Snapshot History
        </div>
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
      <p className="text-xs text-muted-foreground">
        Recent snapshot runs and their outcomes
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
                <TableRow key={snap.id}>
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
    </div>
  );
}
