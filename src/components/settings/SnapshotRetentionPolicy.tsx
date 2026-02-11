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
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { api, requireSuccess } from "@/lib/api";
import { toast } from "sonner";
import {
  Shield,
  Trash2,
  Clock,
  Hash,
  Loader2,
  AlertTriangle,
} from "lucide-react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

export interface RetentionConfig {
  enabled: boolean;
  mode: "age" | "count" | "both";
  maxAgeDays: number;
  maxCount: number;
  autoCleanup: boolean;
}

const AGE_PRESETS = [
  { value: 7, label: "7 days" },
  { value: 14, label: "14 days" },
  { value: 30, label: "30 days" },
  { value: 60, label: "60 days" },
  { value: 90, label: "90 days" },
  { value: 180, label: "180 days" },
  { value: 365, label: "1 year" },
];

interface Props {
  config: RetentionConfig;
  onChange: (config: RetentionConfig) => void;
}

export function SnapshotRetentionPolicy({ config, onChange }: Props) {
  const [cleaning, setCleaning] = useState(false);

  const update = (patch: Partial<RetentionConfig>) =>
    onChange({ ...config, ...patch });

  const handleCleanupNow = async () => {
    setCleaning(true);
    try {
      const opts: Record<string, unknown> = {};
      if (config.mode === "age" || config.mode === "both") {
        opts.maxAgeDays = config.maxAgeDays;
      }
      if (config.mode === "count" || config.mode === "both") {
        opts.maxCount = config.maxCount;
      }
      const res = await api.cleanupRemoteSnapshots(0, opts);
      const result = requireSuccess(res, { endpoint: "/sites/0/snapshots/cleanup", method: "POST" });
      const deleted = (result as any)?.deleted ?? 0;
      toast.success(`Cleanup complete: ${deleted} snapshot${deleted !== 1 ? "s" : ""} removed`);
    } catch (err: any) {
      toast.error(`Cleanup failed: ${err.message}`);
    } finally {
      setCleaning(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <Shield className="h-4 w-4" />
          Retention Policy
        </div>
        <Switch
          checked={config.enabled}
          onCheckedChange={(v) => update({ enabled: v })}
        />
      </div>
      <p className="text-xs text-muted-foreground">
        Automatically remove old snapshots based on age or count limits
      </p>

      {config.enabled && (
        <>
          {/* Mode selector */}
          <div className="space-y-2">
            <Label className="text-xs">Cleanup Mode</Label>
            <div className="grid grid-cols-3 gap-2">
              {(["age", "count", "both"] as const).map((mode) => (
                <button
                  key={mode}
                  type="button"
                  onClick={() => update({ mode })}
                  className={cn(
                    "flex items-center justify-center gap-1.5 p-2 rounded-lg border text-xs font-medium transition-all",
                    config.mode === mode
                      ? "border-primary bg-primary/5 shadow-sm"
                      : "hover:bg-accent/50"
                  )}
                >
                  {mode === "age" && <Clock className="h-3.5 w-3.5" />}
                  {mode === "count" && <Hash className="h-3.5 w-3.5" />}
                  {mode === "both" && <Shield className="h-3.5 w-3.5" />}
                  <span className="capitalize">{mode === "both" ? "Both" : `By ${mode}`}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Age-based settings */}
          {(config.mode === "age" || config.mode === "both") && (
            <div className="space-y-2 rounded-lg border p-3 bg-accent/10">
              <div className="flex items-center gap-1.5 text-xs font-medium">
                <Clock className="h-3.5 w-3.5 text-primary" />
                Age Limit
              </div>
              <p className="text-[11px] text-muted-foreground">
                Delete snapshots older than this
              </p>
              <Select
                value={String(config.maxAgeDays)}
                onValueChange={(v) => update({ maxAgeDays: parseInt(v) })}
              >
                <SelectTrigger className="h-8 text-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {AGE_PRESETS.map((p) => (
                    <SelectItem key={p.value} value={String(p.value)}>
                      {p.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {/* Count-based settings */}
          {(config.mode === "count" || config.mode === "both") && (
            <div className="space-y-2 rounded-lg border p-3 bg-accent/10">
              <div className="flex items-center gap-1.5 text-xs font-medium">
                <Hash className="h-3.5 w-3.5 text-primary" />
                Count Limit
              </div>
              <p className="text-[11px] text-muted-foreground">
                Keep only the most recent N snapshots
              </p>
              <Input
                type="number"
                min={1}
                max={1000}
                value={config.maxCount}
                onChange={(e) => update({ maxCount: parseInt(e.target.value) || 10 })}
                className="h-8 text-xs"
              />
            </div>
          )}

          {/* Auto cleanup toggle */}
          <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
            <div>
              <p className="text-xs font-medium">Auto Cleanup</p>
              <p className="text-[11px] text-muted-foreground">
                Run cleanup automatically after each new snapshot
              </p>
            </div>
            <Switch
              checked={config.autoCleanup}
              onCheckedChange={(v) => update({ autoCleanup: v })}
            />
          </div>

          <Separator />

          {/* Manual cleanup */}
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className="w-full text-xs border-destructive/30 text-destructive hover:bg-destructive/10"
                disabled={cleaning}
              >
                {cleaning ? (
                  <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />
                ) : (
                  <Trash2 className="h-3.5 w-3.5 mr-1.5" />
                )}
                Run Cleanup Now
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle className="flex items-center gap-2">
                  <AlertTriangle className="h-5 w-5 text-amber-500" />
                  Confirm Snapshot Cleanup
                </AlertDialogTitle>
                <AlertDialogDescription className="space-y-2">
                  <span>This will permanently delete snapshots matching the current policy:</span>
                  <ul className="list-disc list-inside text-xs space-y-1 mt-2">
                    {(config.mode === "age" || config.mode === "both") && (
                      <li>Snapshots older than <strong>{config.maxAgeDays} days</strong></li>
                    )}
                    {(config.mode === "count" || config.mode === "both") && (
                      <li>Keeping only the <strong>{config.maxCount} most recent</strong></li>
                    )}
                  </ul>
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                  onClick={handleCleanupNow}
                  className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                >
                  Delete Matching Snapshots
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </>
      )}
    </div>
  );
}
