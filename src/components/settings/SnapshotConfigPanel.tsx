import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Slider } from "@/components/ui/slider";
import { Separator } from "@/components/ui/separator";
import {
  Database,
  HardDrive,
  Layers,
  Cpu,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { SnapshotRetentionPolicy, type RetentionConfig } from "./SnapshotRetentionPolicy";

interface SnapshotConfigPanelProps {
  storageMode: "single" | "per-table";
  onStorageModeChange: (mode: "single" | "per-table") => void;
  workerCount: number;
  onWorkerCountChange: (count: number) => void;
  batchSize: number;
  onBatchSizeChange: (size: number) => void;
  retention?: RetentionConfig;
  onRetentionChange?: (config: RetentionConfig) => void;
  showRetention?: boolean;
}

export function SnapshotConfigPanel({
  storageMode,
  onStorageModeChange,
  workerCount,
  onWorkerCountChange,
  batchSize,
  onBatchSizeChange,
  retention,
  onRetentionChange,
  showRetention = true,
}: SnapshotConfigPanelProps) {
  return (
    <div className="space-y-4">
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
            onClick={() => onStorageModeChange("single")}
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
            onClick={() => onStorageModeChange("per-table")}
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
              onValueChange={([v]) => onWorkerCountChange(v)}
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
              onChange={(e) => onBatchSizeChange(parseInt(e.target.value) || 10)}
              className="h-9"
            />
            <p className="text-xs text-muted-foreground">
              Number of rows processed per batch during backup
            </p>
          </div>
        </div>
      </div>

      {showRetention && retention && onRetentionChange && (
        <>
          <Separator />
          <SnapshotRetentionPolicy
            config={retention}
            onChange={onRetentionChange}
          />
        </>
      )}
    </div>
  );
}
