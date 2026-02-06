import { useState, useCallback } from "react";
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
} from "lucide-react";
import { Site, SnapshotRecord, api } from "@/lib/api";
import { useRemoteSnapshots } from "@/hooks/useRemoteSnapshots";

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
        <p className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1">
          {snapshot.error}
        </p>
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

      {/* Schedule */}
      <div className="space-y-1.5">
        <Label className="text-xs font-medium">Schedule</Label>
        <Select
          value={(current.schedule as string) || "manual"}
          onValueChange={(v) => handleChange("schedule", v)}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="manual">Manual Only</SelectItem>
            <SelectItem value="daily">Daily</SelectItem>
            <SelectItem value="weekly">Weekly</SelectItem>
            <SelectItem value="monthly">Monthly</SelectItem>
          </SelectContent>
        </Select>
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
    </div>
  );
}

export function RemoteSnapshotsPanel({ site, open, onOpenChange }: RemoteSnapshotsPanelProps) {
  const {
    snapshots,
    isLoading,
    isError,
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
  } = useRemoteSnapshots(site.id, open);

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
            <TabsList className="w-full grid grid-cols-2 h-8">
              <TabsTrigger value="snapshots" className="text-xs gap-1">
                <Database className="h-3.5 w-3.5" />
                Snapshots
                {snapshots.length > 0 && (
                  <Badge variant="secondary" className="h-4 text-[10px] px-1 ml-1">{snapshots.length}</Badge>
                )}
              </TabsTrigger>
              <TabsTrigger value="settings" className="text-xs gap-1">
                <Settings className="h-3.5 w-3.5" />
                Settings
              </TabsTrigger>
            </TabsList>

            <TabsContent value="snapshots" className="flex-1 flex flex-col min-h-0 mt-2">
              {/* Create Snapshot Controls */}
              <div className="space-y-2 pb-2 border-b mb-2">
                <div className="flex items-center gap-2">
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
                    <AlertCircle className="h-8 w-8" />
                    <p className="text-sm">Failed to load snapshots</p>
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
                <div className="text-xs text-destructive bg-destructive/5 rounded px-2 py-1.5">
                  {detailTarget.error}
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
