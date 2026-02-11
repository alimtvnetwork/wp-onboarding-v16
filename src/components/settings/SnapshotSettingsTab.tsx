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
import { SnapshotInterval, SnapshotSchedule } from "@/lib/api/types";
import {
  Database,
  Plus,
  Trash2,
  Loader2,
  HardDrive,
  Layers,
  Clock,
  Cpu,
} from "lucide-react";
import { toast } from "sonner";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";

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

  const handleSave = () => {
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
        onSuccess: () => {
          setIsDirty(false);
          toast.success("Snapshot settings saved", {
            style: {
              background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
              color: "white",
              border: "none",
            },
          });
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
    </div>
  );
}
