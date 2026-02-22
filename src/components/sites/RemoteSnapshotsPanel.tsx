import { useState, useCallback, useRef, useMemo, useEffect } from "react";
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
import { Progress } from "@/components/ui/progress";
import { Slider } from "@/components/ui/slider";
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
  Cpu,
  Archive,
} from "lucide-react";
import { Site, SnapshotRecord, SnapshotSchedule, SnapshotInterval, SnapshotSettings, SnapshotScope, CreateSnapshotOptions, RestoreSnapshotOptions, api } from "@/lib/api";
import { SnapshotRunStatus, SnapshotTypeValues } from "@/lib/constants";
import { useRemoteSnapshots } from "@/hooks/useRemoteSnapshots";
import { toClipboardText } from "@/lib/logText";
import { toast } from "sonner";
import { SnapshotConfigPanel } from "@/components/settings/SnapshotConfigPanel";
import { useErrorStore } from "@/stores/errorStore";
import { wsClient, WS_EVENTS } from "@/lib/ws";

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
  isNested = false,
}: {
  snapshot: SnapshotRecord;
  siteId: number;
  onRestore: (s: SnapshotRecord) => void;
  onDelete: (s: SnapshotRecord) => void;
  onViewDetail: (s: SnapshotRecord) => void;
  isRestoring: boolean;
  isDeleting: boolean;
  isNested?: boolean;
}) {
  const [downloading, setDownloading] = useState(false);
  const isRunning = snapshot.status === SnapshotRunStatus.Running || snapshot.status === SnapshotRunStatus.InProgress;
  const isIncremental = snapshot.snapshotType === SnapshotTypeValues.Incremental || snapshot.scope === "Incremental";

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

  // isIncremental already declared above

  return (
    <div className={`border rounded-lg p-3 space-y-2 hover:bg-muted/30 transition-colors animate-fade-in ${isNested ? "ml-6 border-l-2 border-l-primary/20" : ""}`}>
      {/* Row 1: Name + Status + Actions */}
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          {isIncremental ? (
            <GitBranch className="h-4 w-4 text-primary/60 shrink-0" />
          ) : (
            <FileText className="h-4 w-4 text-muted-foreground shrink-0" />
          )}
          <span className="text-sm font-medium truncate">
            #{snapshot.sequence} — {snapshot.filename}
          </span>
          {isIncremental && (
            <Badge variant="outline" className="text-[10px] h-4 px-1 border-primary/30 text-primary/70">
              incremental
            </Badge>
          )}
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
              {/* Download ZIP — full snapshots only */}
              {!isIncremental && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-7 w-7 p-0 text-muted-foreground hover:text-foreground"
                  disabled={downloading}
                  title={downloading ? "Building ZIP…" : "Download ZIP"}
                  onClick={async () => {
                    setDownloading(true);
                    try {
                      const { blob, filename, cached, size } = await api.downloadSnapshotZip(siteId, snapshot.id);
                      // Trigger browser download
                      const url = URL.createObjectURL(blob);
                      const a = document.createElement("a");
                      a.href = url;
                      a.download = filename;
                      document.body.appendChild(a);
                      a.click();
                      URL.revokeObjectURL(url);
                      a.remove();
                      toast.success(
                        `ZIP downloaded${cached ? " (cached)" : ""} — ${formatBytes(size)}`,
                      );
                    } catch (err: unknown) {
                      const message = err instanceof Error ? err.message : String(err);
                      toast.error(`Download failed: ${message}`);
                    } finally {
                      setDownloading(false);
                    }
                  }}
                >
                  {downloading ? (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  ) : (
                    <Download className="h-3.5 w-3.5" />
                  )}
                </Button>
              )}
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
        {snapshot.fileSize > 0 && (
          <span className="flex items-center gap-1">
            <HardDrive className="h-3 w-3" />
            {formatBytes(snapshot.fileSize)}
          </span>
        )}
        {snapshot.totalRows > 0 && (
          <span className="flex items-center gap-1">
            <Table className="h-3 w-3" />
            {snapshot.totalRows.toLocaleString()} rows
          </span>
        )}
        <span className="flex items-center gap-1">
          <Clock className="h-3 w-3" />
          {relativeTime(snapshot.createdAt)}
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

/** Detail dialog content with ZIP export metadata section */
function SnapshotDetailContent({ snapshot, siteId }: { snapshot: SnapshotRecord; siteId: number }) {
  const [zipMeta, setZipMeta] = useState<{ cached: boolean; size: number; filename: string } | null>(null);
  const [zipLoading, setZipLoading] = useState(false);
  const [zipError, setZipError] = useState<string | null>(null);

  const isIncremental = snapshot.snapshotType === SnapshotTypeValues.Incremental || snapshot.scope === "Incremental";

  const handleDownload = async () => {
    setZipLoading(true);
    setZipError(null);
    try {
      const { blob, filename, cached, size } = await api.downloadSnapshotZip(siteId, snapshot.id);
      setZipMeta({ cached, size, filename });
      // Trigger browser download
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      URL.revokeObjectURL(url);
      a.remove();
      toast.success(`ZIP downloaded${cached ? " (cached)" : ""} — ${formatBytes(size)}`);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : String(err);
      setZipError(message);
      toast.error(`Download failed: ${message}`);
    } finally {
      setZipLoading(false);
    }
  };

  return (
    <div className="space-y-3 text-sm">
      <div className="grid grid-cols-2 gap-2">
        <div className="text-muted-foreground">Status</div>
        <div className="font-medium capitalize">{snapshot.status}</div>
        <div className="text-muted-foreground">Scope</div>
        <div className="font-medium capitalize">{snapshot.scope}</div>
        <div className="text-muted-foreground">Provider</div>
        <div className="font-medium">{snapshot.provider}</div>
        {snapshot.fileSize > 0 && (
          <>
            <div className="text-muted-foreground">File Size</div>
            <div className="font-medium">{formatBytes(snapshot.fileSize)}</div>
          </>
        )}
        {snapshot.totalRows > 0 && (
          <>
            <div className="text-muted-foreground">Total Rows</div>
            <div className="font-medium">{snapshot.totalRows.toLocaleString()}</div>
          </>
        )}
        <div className="text-muted-foreground">Created</div>
        <div className="font-medium">{relativeTime(snapshot.createdAt)}</div>
        {snapshot.snapshotType && (
          <>
            <div className="text-muted-foreground">Type</div>
            <div className="font-medium capitalize">{snapshot.snapshotType}</div>
          </>
        )}
        {snapshot.incrementalCount != null && snapshot.incrementalCount > 0 && (
          <>
            <div className="text-muted-foreground">Incrementals</div>
            <div className="font-medium">{snapshot.incrementalCount}</div>
          </>
        )}
      </div>

      {/* Tables list */}
      {snapshot.tables && (
        <div className="space-y-1.5">
          <div className="text-xs font-medium text-muted-foreground flex items-center gap-1">
            <Table className="h-3 w-3" />
            Tables Included
          </div>
          <div className="bg-muted/50 rounded-md p-2 max-h-40 overflow-y-auto">
            <div className="flex flex-wrap gap-1">
              {(typeof snapshot.tables === "string"
                ? snapshot.tables.split(",").map((t) => t.trim()).filter(Boolean)
                : Array.isArray(snapshot.tables) ? snapshot.tables : []
              ).map((table, i) => (
                <Badge key={i} variant="outline" className="text-[10px] h-5 font-mono">
                  {table}
                </Badge>
              ))}
            </div>
          </div>
        </div>
      )}

      {snapshot.error && (
        <div className="flex items-center gap-1.5">
          <div className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1.5 flex-1">
            {snapshot.error}
          </div>
          <Button
            variant="ghost"
            size="sm"
            className="h-6 w-6 p-0 text-muted-foreground hover:text-foreground shrink-0"
            onClick={() => {
              navigator.clipboard.writeText(toClipboardText(snapshot.error || ""));
              toast.success("Error copied to clipboard");
            }}
            title="Copy error"
          >
            <Copy className="h-3 w-3" />
          </Button>
        </div>
      )}

      {/* ZIP Export Metadata — full snapshots only */}
      {snapshot.status === "complete" && !isIncremental && (
        <div className="space-y-2 border-t pt-3">
          <div className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
            <Archive className="h-3.5 w-3.5" />
            ZIP Export
          </div>

          {zipMeta && (
            <div className="bg-muted/40 rounded-md p-2.5 space-y-1.5">
              <div className="grid grid-cols-2 gap-1 text-xs">
                <span className="text-muted-foreground">Filename</span>
                <span className="font-mono truncate text-[11px]" title={zipMeta.filename}>{zipMeta.filename}</span>
                <span className="text-muted-foreground">Size</span>
                <span className="font-medium">{formatBytes(zipMeta.size)}</span>
                <span className="text-muted-foreground">Status</span>
                <span>
                  {zipMeta.cached ? (
                    <Badge className="bg-primary/10 text-primary border-primary/20 text-[10px] h-4 px-1.5">
                      <CheckCircle className="h-2.5 w-2.5 mr-0.5" />
                      Cached
                    </Badge>
                  ) : (
                    <Badge className="bg-amber-500/10 text-amber-600 border-amber-500/20 text-[10px] h-4 px-1.5">
                      Fresh Build
                    </Badge>
                  )}
                </span>
              </div>
            </div>
          )}

          {zipError && (
            <div className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1.5">
              {zipError}
            </div>
          )}

          <Button
            size="sm"
            className="w-full"
            onClick={handleDownload}
            disabled={zipLoading}
          >
            {zipLoading ? (
              <Loader2 className="h-3.5 w-3.5 mr-1.5 animate-spin" />
            ) : (
              <Download className="h-3.5 w-3.5 mr-1.5" />
            )}
            {zipLoading ? "Building ZIP…" : zipMeta ? "Re-download ZIP" : "Download ZIP"}
          </Button>
        </div>
      )}

      {/* Fallback download for incremental snapshots — use legacy export */}
      {snapshot.status === "complete" && isIncremental && (
        <Button size="sm" className="w-full" asChild>
          <a href={api.getRemoteSnapshotExportUrl(siteId, snapshot.id)} download>
            <Download className="h-3.5 w-3.5 mr-1.5" />
            Export Snapshot
          </a>
        </Button>
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
    settingsDataUpdatedAt,
    isLoadingSettings,
    providers,
    isLoadingProviders,
    updateSettings,
    isUpdatingSettings,
    cleanupSnapshots,
    isCleaningUp,
  } = useRemoteSnapshots(siteId);

  const [localSettings, setLocalSettings] = useState<Partial<SnapshotSettings> | null>(null);

  // Use local overrides if user has edited, otherwise show fetched settings
  const current = localSettings || (settings as Partial<SnapshotSettings> | undefined);

  const handleChange = (key: string, value: unknown) => {
    setLocalSettings((prev) => ({ ...(prev || (settings as Partial<SnapshotSettings>) || {}), [key]: value }));
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
      {/* Sync Indicator */}
      {settingsDataUpdatedAt > 0 && (
        <div className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-accent/40 border border-border/50">
          <CheckCircle className="h-3 w-3 text-primary shrink-0" />
          <span className="text-[10px] text-muted-foreground">
            Last synced: {(() => {
              const d = new Date(settingsDataUpdatedAt);
              const now = new Date();
              const diffMs = now.getTime() - d.getTime();
              const diffSecs = Math.floor(diffMs / 1000);
              if (diffSecs < 10) return "just now";
              if (diffSecs < 60) return `${diffSecs}s ago`;
              const diffMins = Math.floor(diffSecs / 60);
              if (diffMins < 60) return `${diffMins}m ago`;
              const diffHours = Math.floor(diffMins / 60);
              if (diffHours < 24) return `${diffHours}h ago`;
              return d.toLocaleDateString();
            })()}
          </span>
          {localSettings !== null && (
            <Badge variant="outline" className="text-[9px] h-4 px-1 ml-auto border-destructive/30 text-destructive">
              Unsaved Changes
            </Badge>
          )}
        </div>
      )}
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
          value={(current.retentionType as string) || "count"}
          onValueChange={(v) => handleChange("retentionType", v)}
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

      {(current.retentionType as string) === "days" && (
        <div className="space-y-1.5">
          <Label className="text-xs font-medium">Keep snapshots for (days)</Label>
          <Input
            type="number"
            className="h-8 text-xs"
            value={(current.retentionDays as number) || 30}
            onChange={(e) => handleChange("retentionDays", parseInt(e.target.value) || 30)}
            min={1}
            max={365}
          />
        </div>
      )}

      {(current.retentionType as string) === "count" && (
        <div className="space-y-1.5">
          <Label className="text-xs font-medium">Keep last N snapshots</Label>
          <Input
            type="number"
            className="h-8 text-xs"
            value={(current.retentionMax as number) || 10}
            onChange={(e) => handleChange("retentionMax", parseInt(e.target.value) || 10)}
            min={1}
            max={100}
          />
        </div>
      )}

      <Separator />

      {/* Parallel Execution & Storage Config */}
      <SnapshotConfigPanel
        storageMode={((current.storageMode as string) || "single") as "single" | "per-table"}
        onStorageModeChange={(mode) => handleChange("storageMode", mode)}
        workerCount={(current.workerCount as number) || 4}
        onWorkerCountChange={(count) => handleChange("workerCount", count)}
        batchSize={(current.batchSize as number) || 10}
        onBatchSizeChange={(size) => handleChange("batchSize", size)}
        showRetention={false}
      />

      <Separator />

      {/* Safety */}
      <div className="flex items-center justify-between">
        <Label className="text-xs font-medium">Pre-Restore Backup</Label>
        <Switch
          checked={(current.preRestoreBackup as boolean) !== false}
          onCheckedChange={(v) => handleChange("preRestoreBackup", v)}
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

// --- C4: Snapshot Progress Types ---
interface SnapshotProgressState {
  snapshotId?: number;
  status: "idle" | "running" | "completed" | "failed";
  totalTables: number;
  completedTables: number;
  totalRows: number;
  processedRows: number;
  activeWorkers: number;
  tables: Array<{
    table: string;
    status: "pending" | "running" | "completed" | "failed";
    rowsProcessed: number;
    totalRows: number;
    workerId?: number;
    error?: string;
  }>;
  startedAt?: string;
  error?: string;
}

const INITIAL_PROGRESS: SnapshotProgressState = {
  status: "idle",
  totalTables: 0,
  completedTables: 0,
  totalRows: 0,
  processedRows: 0,
  activeWorkers: 0,
  tables: [],
};

export function RemoteSnapshotsPanel({ site, open, onOpenChange }: RemoteSnapshotsPanelProps) {
  const {
    snapshots,
    isLoading,
    isError,
    error: snapshotError,
    refetch,
    hasRunningSnapshots,
    settings,
    createSnapshot,
    isCreating,
    deleteSnapshot,
    isDeleting,
    restoreSnapshot,
    isRestoring,
    updateSettings,
    isUpdatingSettings,
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

  const { captureException, openErrorModal } = useErrorStore();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [deleteTarget, setDeleteTarget] = useState<SnapshotRecord | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<SnapshotRecord | null>(null);
  const [detailTarget, setDetailTarget] = useState<SnapshotRecord | null>(null);
  const [createName, setCreateName] = useState("");
  const [createType, setCreateType] = useState<"full" | "incremental">("full");
  const [createScope, setCreateScope] = useState<string>("wordpress");
  const [parentSnapshotId, setParentSnapshotId] = useState<string>("");
  const [customTables, setCustomTables] = useState<string[]>([]);
  const [showTablePicker, setShowTablePicker] = useState(false);
  const [restoreMode, setRestoreMode] = useState<"full" | "selective">("full");
  const [restoreTables, setRestoreTables] = useState<string[]>([]);

  // C5: Initial load flag — suppress error on first fetch
  const initialLoadRef = useRef(true);
  useEffect(() => {
    if (!isLoading && initialLoadRef.current) {
      initialLoadRef.current = false;
    }
  }, [isLoading]);

  // C3: Inline worker count from settings
  const currentWorkerCount = (settings as SnapshotSettings | undefined)?.workerCount as number || 4;

  // C4: Real-time progress via WebSocket
  const [progress, setProgress] = useState<SnapshotProgressState>(INITIAL_PROGRESS);

  useEffect(() => {
    if (!open) return;

    const unsubStarted = wsClient.on(WS_EVENTS.SNAPSHOT_STARTED, (data: unknown) => {
      const d = data as { snapshotId?: number; totalTables: number; totalRows: number; tables: string[]; workerCount: number };
      setProgress({
        snapshotId: d.snapshotId,
        status: "running",
        totalTables: d.totalTables,
        completedTables: 0,
        totalRows: d.totalRows,
        processedRows: 0,
        activeWorkers: d.workerCount,
        tables: d.tables.map((t) => ({ table: t, status: "pending", rowsProcessed: 0, totalRows: 0 })),
        startedAt: new Date().toISOString(),
      });
    });

    const unsubProgress = wsClient.on(WS_EVENTS.SNAPSHOT_PROGRESS, (data: unknown) => {
      const d = data as { table: string; workerId: number; rowsProcessed: number; totalRows: number; status: "running" | "completed" | "failed"; error?: string };
      setProgress((prev) => {
        if (prev.status !== "running") return prev;
        const tables = prev.tables.map((t) =>
          t.table === d.table ? { ...t, status: d.status, rowsProcessed: d.rowsProcessed, totalRows: d.totalRows, workerId: d.workerId, error: d.error } : t
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
          t.table === d.table ? { ...t, status: "completed" as const, rowsProcessed: d.rowsProcessed } : t
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
  }, [open]);

  // Available parent snapshots for incremental backups (completed full snapshots only)
  const completedFullSnapshots = useMemo(
    () => snapshots.filter(
      (s) => s.status === "complete" && s.snapshotType !== SnapshotTypeValues.Incremental && s.scope !== "Incremental"
    ),
    [snapshots]
  );

  const handleScopeChange = useCallback((scope: string) => {
    setCreateScope(scope);
    if (scope === "custom" && availableTables.length === 0) {
      fetchTables();
    }
    setShowTablePicker(scope === "custom");
  }, [availableTables.length, fetchTables]);

  const handleCreate = () => {
    const opts: CreateSnapshotOptions = {};
    if (createName.trim()) opts.name = createName.trim();
    if (createScope === "custom" && customTables.length > 0) {
      opts.scope = "Custom";
      opts.tables = customTables;
    } else {
      opts.scope = createScope as SnapshotScope;
    }

    if (createType === "incremental") {
      if (parentSnapshotId) opts.parentId = Number(parentSnapshotId);
      incrementalBackup(opts);
    } else {
      fullBackup(opts);
    }
    setCreateName("");
  };

  const handleDelete = () => {
    if (deleteTarget) {
      deleteSnapshot(deleteTarget.id);
      setDeleteTarget(null);
    }
  };

  const handleRestore = () => {
    if (restoreTarget) {
      const restoreOpts: { snapshotId: number; opts?: Omit<RestoreSnapshotOptions, "confirm"> } = { snapshotId: restoreTarget.id };
      if (restoreMode === "selective" && restoreTables.length > 0) {
        restoreOpts.opts = { mode: "selective" as const, tables: restoreTables };
      }
      restoreSnapshot(restoreOpts);
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
            <TabsList className="w-full grid grid-cols-3 h-8 shrink-0 overflow-hidden">
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
              <div className="space-y-2.5 pb-3 border-b mb-2">
                {/* Row 1: Name + Refresh */}
                <div className="flex items-center gap-2">
                  <Input
                    placeholder="Snapshot name (optional)"
                    value={createName}
                    onChange={(e) => setCreateName(e.target.value)}
                    className="h-8 text-xs flex-1"
                  />
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => refetch()}
                    disabled={isLoading}
                    className="h-8 w-8 p-0 shrink-0"
                  >
                    <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? "animate-spin" : ""}`} />
                  </Button>
                </div>

                {/* Row 2: Type + Scope + Create */}
                <div className="flex items-center gap-2 flex-wrap">
                  <Select value={createType} onValueChange={(v) => {
                    setCreateType(v as "full" | "incremental");
                    if (v === "incremental" && completedFullSnapshots.length > 0 && !parentSnapshotId) {
                      setParentSnapshotId(String(completedFullSnapshots[0].id));
                    }
                  }}>
                    <SelectTrigger className="w-[130px] h-8 text-xs">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="full">
                        <span className="flex items-center gap-1.5">
                          <Database className="h-3 w-3" />
                          Full Backup
                        </span>
                      </SelectItem>
                      <SelectItem value="incremental" disabled={completedFullSnapshots.length === 0}>
                        <span className="flex items-center gap-1.5">
                          <GitBranch className="h-3 w-3" />
                          Incremental
                        </span>
                      </SelectItem>
                    </SelectContent>
                  </Select>

                  <Select value={createScope} onValueChange={handleScopeChange}>
                    <SelectTrigger className="w-[130px] h-8 text-xs">
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
                    disabled={
                      (createType === "full" ? isFullBackupPending : isIncrementalPending) ||
                      hasRunningSnapshots ||
                      (createScope === "custom" && customTables.length === 0) ||
                      (createType === "incremental" && !parentSnapshotId)
                    }
                    className="h-8"
                  >
                    {(createType === "full" ? isFullBackupPending : isIncrementalPending) ? (
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
                    className="h-8 text-xs"
                  >
                    {isImporting ? (
                      <Loader2 className="h-3 w-3 animate-spin mr-1" />
                    ) : (
                      <Upload className="h-3 w-3 mr-1" />
                    )}
                    Import
                  </Button>
                </div>

                {/* Incremental: Parent Snapshot Picker */}
                {createType === "incremental" && (
                  <div className="space-y-1.5 animate-fade-in border rounded-md p-2 bg-accent/20">
                    <Label className="text-xs font-medium flex items-center gap-1.5 text-muted-foreground">
                      <GitBranch className="h-3 w-3" />
                      Base on Full Snapshot
                    </Label>
                    {completedFullSnapshots.length === 0 ? (
                      <p className="text-xs text-muted-foreground">No completed full snapshots available. Create a full backup first.</p>
                    ) : (
                      <Select value={parentSnapshotId} onValueChange={setParentSnapshotId}>
                        <SelectTrigger className="h-8 text-xs">
                          <SelectValue placeholder="Select parent snapshot…" />
                        </SelectTrigger>
                        <SelectContent>
                          {completedFullSnapshots.map((s) => (
                            <SelectItem key={s.id} value={String(s.id)}>
                              <span className="flex items-center gap-2">
                                <span className="font-medium">#{s.sequence}</span>
                                <span className="text-muted-foreground">—</span>
                                <span className="truncate">{s.filename}</span>
                                <span className="text-muted-foreground text-[10px] shrink-0">{relativeTime(s.createdAt)}</span>
                              </span>
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    )}
                  </div>
                )}

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
                              {table.isCore && (
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

              {/* C3: Inline Worker Pool Quick-Set */}
              <div className="flex items-center gap-3 py-2 px-1">
                <Cpu className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                <Label className="text-xs text-muted-foreground shrink-0">Workers</Label>
                <Slider
                  value={[currentWorkerCount]}
                  onValueChange={([v]) => {
                    updateSettings({ workerCount: v });
                  }}
                  min={1}
                  max={10}
                  step={1}
                  className="flex-1 max-w-[120px]"
                  disabled={isUpdatingSettings}
                />
                <span className="text-xs font-mono text-muted-foreground w-5 text-right">{currentWorkerCount}</span>
              </div>

              {/* C4: Progress Banner */}
              {(progress.status === "running" || progress.status === "completed" || progress.status === "failed") && (
                <div className="border rounded-lg p-3 space-y-2 animate-fade-in bg-accent/20">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 text-xs font-medium">
                      {progress.status === "running" ? (
                        <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />
                      ) : progress.status === "completed" ? (
                        <CheckCircle className="h-3.5 w-3.5 text-primary" />
                      ) : (
                        <AlertCircle className="h-3.5 w-3.5 text-destructive" />
                      )}
                      <span>
                        {progress.status === "running" ? "Snapshot in progress…" :
                         progress.status === "completed" ? "Snapshot completed" : "Snapshot failed"}
                      </span>
                    </div>
                    <div className="flex items-center gap-2 text-[10px] text-muted-foreground">
                      {progress.activeWorkers > 0 && (
                        <span>{progress.activeWorkers} worker{progress.activeWorkers !== 1 ? "s" : ""}</span>
                      )}
                      <span>{progress.completedTables}/{progress.totalTables} tables</span>
                      {progress.status !== "running" && (
                        <Button variant="ghost" size="sm" className="h-5 px-1 text-[10px]" onClick={() => setProgress(INITIAL_PROGRESS)}>
                          Dismiss
                        </Button>
                      )}
                    </div>
                  </div>
                  <Progress
                    value={
                      progress.totalRows > 0
                        ? Math.round((progress.processedRows / progress.totalRows) * 100)
                        : progress.totalTables > 0
                        ? Math.round((progress.completedTables / progress.totalTables) * 100)
                        : 0
                    }
                    className="h-1.5"
                  />
                  {progress.tables.length > 0 && progress.status === "running" && (
                    <div className="flex flex-wrap gap-1">
                      {progress.tables.map((t) => (
                        <Badge
                          key={t.table}
                          variant="outline"
                          className={`text-[9px] h-4 px-1 font-mono ${
                            t.status === "completed" ? "border-primary/30 text-primary/70" :
                            t.status === "running" ? "border-primary bg-primary/10 text-primary animate-pulse" :
                            t.status === "failed" ? "border-destructive/30 text-destructive" :
                            "text-muted-foreground"
                          }`}
                        >
                          {t.table.replace(/^wp_/, "")}
                        </Badge>
                      ))}
                    </div>
                  )}
                  {progress.error && (
                    <p className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1">{progress.error}</p>
                  )}
                </div>
              )}

              {/* Snapshot List */}
              <ScrollArea className="flex-1 min-h-0">
                {isLoading ? (
                  <div className="flex items-center justify-center py-12">
                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                  </div>
                ) : isError && !initialLoadRef.current ? (
                  <div className="flex flex-col items-center justify-center py-12 gap-2 text-muted-foreground">
                    <AlertCircle className="h-8 w-8 text-destructive/60" />
                    <p className="text-sm font-medium">Failed to load snapshots</p>
                    {snapshotError?.message && (
                      <p className="text-xs text-destructive/80 max-w-[300px] text-center break-all">{snapshotError.message}</p>
                    )}
                    <div className="flex gap-2 mt-1">
                      <Button variant="outline" size="sm" onClick={() => refetch()}>
                        Retry
                      </Button>
                      {snapshotError && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => {
                            const captured = captureException(snapshotError, {
                              source: 'RemoteSnapshotsPanel.fetchSnapshots',
                              endpoint: `/sites/${site.id}/snapshots`,
                              method: 'GET',
                            });
                            openErrorModal(captured);
                          }}
                        >
                          View Error
                        </Button>
                      )}
                    </div>
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
                    {(() => {
                      // Group snapshots: full snapshots at top level, incrementals nested under parent
                      const fullSnapshots = snapshots.filter(
                        (s) => s.snapshotType !== SnapshotTypeValues.Incremental && s.scope !== "Incremental"
                      );
                      const incrementals = snapshots.filter(
                        (s) => s.snapshotType === SnapshotTypeValues.Incremental || s.scope === "Incremental"
                      );

                      // Build a map from parent directory to incremental snapshots
                      const incrementalsByParent = new Map<string, SnapshotRecord[]>();
                      for (const inc of incrementals) {
                        const parentDir = inc.parentDir || "";
                        if (!incrementalsByParent.has(parentDir)) {
                          incrementalsByParent.set(parentDir, []);
                        }
                        incrementalsByParent.get(parentDir)!.push(inc);
                      }

                      // Unmatched incrementals (no parentDir or parent not found)
                      const unmatchedIncrementals = incrementals.filter(
                        (inc) => !inc.parentDir
                      );

                      return (
                        <>
                          {fullSnapshots.map((snapshot) => {
                            const children = incrementalsByParent.get(snapshot.filename) || [];
                            return (
                              <div key={snapshot.id}>
                                <SnapshotRow
                                  snapshot={snapshot}
                                  siteId={site.id}
                                  onRestore={handleOpenRestore}
                                  onDelete={setDeleteTarget}
                                  onViewDetail={setDetailTarget}
                                  isRestoring={isRestoring}
                                  isDeleting={isDeleting}
                                />
                                {children.length > 0 && (
                                  <div className="space-y-1.5 mt-1.5">
                                    {children.map((child) => (
                                      <SnapshotRow
                                        key={child.id}
                                        snapshot={child}
                                        siteId={site.id}
                                        onRestore={handleOpenRestore}
                                        onDelete={setDeleteTarget}
                                        onViewDetail={setDetailTarget}
                                        isRestoring={isRestoring}
                                        isDeleting={isDeleting}
                                        isNested
                                      />
                                    ))}
                                  </div>
                                )}
                              </div>
                            );
                          })}
                          {/* Render any unmatched incrementals at the end */}
                          {unmatchedIncrementals.map((snapshot) => (
                            <SnapshotRow
                              key={snapshot.id}
                              snapshot={snapshot}
                              siteId={site.id}
                              onRestore={handleOpenRestore}
                              onDelete={setDeleteTarget}
                              onViewDetail={setDetailTarget}
                              isRestoring={isRestoring}
                              isDeleting={isDeleting}
                              isNested
                            />
                          ))}
                        </>
                      );
                    })()}
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
                      .sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
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
                                    {relativeTime(snapshot.createdAt)}
                                  </span>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-muted-foreground">
                                  {snapshot.totalRows > 0 && (
                                    <span className="flex items-center gap-0.5">
                                      <Table className="h-2.5 w-2.5" />
                                      {snapshot.totalRows.toLocaleString()} rows
                                    </span>
                                  )}
                                  {snapshot.fileSize > 0 && (
                                    <span className="flex items-center gap-0.5">
                                      <HardDrive className="h-2.5 w-2.5" />
                                      {formatBytes(snapshot.fileSize)}
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
            <AlertDialogDescription className="space-y-2">
              <span>
                Delete snapshot #{deleteTarget?.sequence} ({deleteTarget?.filename})? This cannot be undone.
              </span>
              {deleteTarget && deleteTarget.snapshotType !== SnapshotTypeValues.Incremental && deleteTarget.scope !== "Incremental" && (deleteTarget.incrementalCount ?? 0) > 0 && (
                <span className="block text-destructive font-medium">
                  ⚠ This is a full snapshot with {deleteTarget.incrementalCount} incremental backup(s). Deleting it will also remove all its incremental children.
                </span>
              )}
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
            <SnapshotDetailContent
              snapshot={detailTarget}
              siteId={site.id}
            />
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}
