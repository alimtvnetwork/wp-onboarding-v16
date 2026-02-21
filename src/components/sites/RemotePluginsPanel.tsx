import { useState, useMemo, useRef, useCallback } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
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
import { Switch } from "@/components/ui/switch";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Loader2,
  RefreshCw,
  Trash2,
  Search,
  Package,
  ExternalLink,
  AlertCircle,
  ChevronDown,
  Power,
  PowerOff,
  MoreHorizontal,
  User,
  Puzzle,
  Clock,
  Database,
  Zap,
  Upload,
  FileArchive,
  X,
} from "lucide-react";
import { api, Site, RemotePlugin, requireSuccess } from "@/lib/api";
import { RemotePluginStatus } from "@/lib/constants";
import { toast } from "sonner";
import { useErrorStore, PHPStackFrame } from "@/stores/errorStore";
import { useRemotePluginEvents } from "@/hooks/useRemotePluginEvents";
import { RemotePluginFileBrowser } from "./RemotePluginFileBrowser";
import { FolderOpen, AlertTriangle } from "lucide-react";
import { compareVersions } from "@/lib/versionUtils";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";

interface RemotePluginsPanelProps {
  site: Site;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const ITEMS_PER_PAGE = 10;

// The expected latest version of the Riseup Asia Uploader plugin.
// Keep this in sync with wp-plugins/riseup-asia-uploader/includes/constants.php RISEUP_VERSION.
const EXPECTED_UPLOADER_VERSION = "1.21.0";
const UPLOADER_SLUG = "riseup-asia-uploader";

// Format relative time (e.g., "2m ago", "1h ago")
function formatTimeAgo(date: Date): string {
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSecs = Math.floor(diffMs / 1000);
  
  if (diffSecs < 60) return `${diffSecs}s ago`;
  const diffMins = Math.floor(diffSecs / 60);
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
}

export function RemotePluginsPanel({ site, open, onOpenChange }: RemotePluginsPanelProps) {
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const [searchQuery, setSearchQuery] = useState("");
  const [pluginToDelete, setPluginToDelete] = useState<RemotePlugin | null>(null);
  const [successPlugins, setSuccessPlugins] = useState<Set<string>>(new Set());
  const [selectedPlugins, setSelectedPlugins] = useState<Set<string>>(new Set());
  const [currentPage, setCurrentPage] = useState(1);
  const [bulkActionPending, setBulkActionPending] = useState(false);
  const [fileBrowserPlugin, setFileBrowserPlugin] = useState<RemotePlugin | null>(null);
  const [uploadFiles, setUploadFiles] = useState<File[]>([]);
  const [activateAfterInstall, setActivateAfterInstall] = useState(true);
  const [uploadPending, setUploadPending] = useState(false);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [uploadProgress, setUploadProgress] = useState<Record<string, number>>({});
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Subscribe to remote plugin WebSocket events for this site
  useRemotePluginEvents(site.id);

  const queryKey = ["sites", site.id, "remote-plugins"];
  const [lastFetchedAt, setLastFetchedAt] = useState<Date | null>(null);
  const [isFromCache, setIsFromCache] = useState(false);

  const { data: plugins, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey,
    queryFn: async () => {
      const response = await api.getRemotePlugins(site.id);
      const data = requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins`, method: "GET" });
      setLastFetchedAt(new Date());
      // Check if response indicates cache usage (backend can include metadata)
      setIsFromCache(false); // Will be set to true only on initial load
      return data;
    },
    enabled: open,
  });

  // Force sync mutation (bypasses cache)
  const forceSyncMutation = useMutation({
    mutationFn: async () => {
      const response = await api.forceSyncRemotePlugins(site.id);
      return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/force-sync`, method: "POST" });
    },
    onSuccess: (data) => {
      toast.success("Plugin list refreshed from site");
      queryClient.setQueryData(queryKey, data);
      setLastFetchedAt(new Date());
      setIsFromCache(false);
    },
    onError: (error) => {
      const captured = captureException(error, {
        endpoint: `/sites/${site.id}/remote-plugins/force-sync`,
        method: "POST",
      });
      toast.error("Failed to refresh plugin list", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    },
  });

  /**
   * Pre-flight guard: validates a plugin identifier exists both in the local cache
   * AND on the remote WordPress site via a lightweight server-side check.
   * Prevents unnecessary 404s from stale UI state or cache mismatches.
   */
  const validatePluginExists = useCallback(async (pluginIdentifier: string, actionLabel: string): Promise<boolean> => {
    if (!pluginIdentifier || pluginIdentifier.trim() === "") {
      toast.error(`Cannot ${actionLabel}: plugin identifier is empty`);
      console.warn(`[guard] Blocked ${actionLabel} — empty plugin identifier`);
      return false;
    }

    // Fast local check first
    const cachedPlugins = queryClient.getQueryData<RemotePlugin[]>(queryKey);
    if (cachedPlugins && !cachedPlugins.some((p) => p.plugin === pluginIdentifier)) {
      toast.error(`Cannot ${actionLabel}: plugin not found in current list`, {
        description: `"${pluginIdentifier}" is not in the synced plugin list. Try Force Sync first.`,
        duration: 8000,
      });
      console.warn(`[guard] Blocked ${actionLabel} — "${pluginIdentifier}" not in cached list of ${cachedPlugins.length} plugins`);
      return false;
    }

    // Server-side pre-flight check against the actual WordPress site
    try {
      const response = await api.checkRemotePluginExists(site.id, pluginIdentifier);
      const result = requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/exists`, method: "POST" });
      if (!result.exists) {
        toast.error(`Cannot ${actionLabel}: plugin not installed on remote site`, {
          description: `"${pluginIdentifier}" was not found on the WordPress site. It may have been removed externally.`,
          duration: 8000,
        });
        console.warn(`[guard] Server pre-flight blocked ${actionLabel} — "${pluginIdentifier}" not installed remotely`);
        // Invalidate local cache since it's stale
        queryClient.invalidateQueries({ queryKey });
        return false;
      }
      return true;
    } catch (err: unknown) {
      // If pre-flight check itself fails, log but allow the action to proceed
      // (the actual action will produce its own error if the plugin is missing)
      console.warn(`[guard] Pre-flight check failed for "${pluginIdentifier}", proceeding with ${actionLabel}:`, err);
      return true;
    }
  }, [queryClient, queryKey, site.id]);

  const toggleMutation = useMutation({
    mutationFn: async ({ plugin, enable }: { plugin: RemotePlugin; enable: boolean }) => {
      const action = enable ? "activate" : "deactivate";
      if (!(await validatePluginExists(plugin.plugin, action))) {
        throw new Error(`Plugin "${plugin.plugin}" not found — ${action} blocked by pre-flight check`);
      }
      if (enable) {
        const response = await api.enableRemotePlugin(site.id, plugin.plugin, plugin.version);
        return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/enable`, method: "POST" });
      } else {
        const response = await api.disableRemotePlugin(site.id, plugin.plugin, plugin.version);
        return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/disable`, method: "POST" });
      }
    },
    onMutate: async ({ plugin, enable }) => {
      // Cancel any outgoing refetches to avoid overwriting optimistic update
      await queryClient.cancelQueries({ queryKey });

      // Snapshot current data for rollback
      const previousPlugins = queryClient.getQueryData<RemotePlugin[]>(queryKey);

      // Optimistically update the cache
      queryClient.setQueryData<RemotePlugin[]>(queryKey, (old) =>
        old?.map((p) =>
          p.plugin === plugin.plugin
            ? { ...p, status: enable ? RemotePluginStatus.Active : RemotePluginStatus.Inactive }
            : p
        )
      );

      return { previousPlugins };
    },
    onSuccess: (_, { plugin, enable }) => {
      // Trigger success pulse animation
      setSuccessPlugins((prev) => new Set(prev).add(plugin.plugin));
      setTimeout(() => {
        setSuccessPlugins((prev) => {
          const next = new Set(prev);
          next.delete(plugin.plugin);
          return next;
        });
      }, 400); // Match animation duration
      // Silent success for audit trail
      console.info(`[audit] ${plugin.name} ${enable ? "activated" : "deactivated"} successfully`);
    },
    onError: (error, { plugin, enable }, context) => {
      // Rollback to previous state
      if (context?.previousPlugins) {
        queryClient.setQueryData(queryKey, context.previousPlugins);
      }
      const captured = captureException(error, {
        endpoint: `/sites/${site.id}/remote-plugins/${enable ? "enable" : "disable"}`,
        method: "POST",
      });
      toast.error(`Failed to ${enable ? "activate" : "deactivate"} ${plugin.name}`, {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    },
    onSettled: () => {
      // Refetch to ensure server state consistency
      queryClient.invalidateQueries({ queryKey });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (plugin: RemotePlugin) => {
      if (!(await validatePluginExists(plugin.plugin, "delete"))) {
        throw new Error(`Plugin "${plugin.plugin}" not found — delete blocked by pre-flight check`);
      }
      // If plugin is active, deactivate first, then delete
      if (plugin.status === RemotePluginStatus.Active) {
        const disableResponse = await api.disableRemotePlugin(site.id, plugin.plugin, plugin.version);
        requireSuccess(disableResponse, { endpoint: `/sites/${site.id}/remote-plugins/disable`, method: "POST" });
      }
      const response = await api.deleteRemotePlugin(site.id, plugin.plugin, plugin.version);
      return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/delete`, method: "POST" });
    },
    onSuccess: (_, plugin) => {
      toast.success(`${plugin.name} deleted`);
      queryClient.invalidateQueries({ queryKey });
      setPluginToDelete(null);
      // Remove from selection if selected
      setSelectedPlugins((prev) => {
        const next = new Set(prev);
        next.delete(plugin.plugin);
        return next;
      });
    },
    onError: (error, plugin) => {
      const captured = captureException(error, {
        endpoint: `/sites/${site.id}/remote-plugins/delete`,
        method: "POST",
      });
      toast.error(`Failed to delete ${plugin.name}`, {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
      setPluginToDelete(null);
    },
  });

  // Filter and paginate plugins
  const filteredPlugins = useMemo(() => {
    if (!plugins) return [];
    if (!searchQuery) return plugins;
    const query = searchQuery.toLowerCase();
    return plugins.filter((plugin) => 
      plugin.name.toLowerCase().includes(query) ||
      plugin.slug.toLowerCase().includes(query) ||
      plugin.description.toLowerCase().includes(query) ||
      plugin.author.toLowerCase().includes(query)
    );
  }, [plugins, searchQuery]);

  const totalPages = Math.ceil(filteredPlugins.length / ITEMS_PER_PAGE);
  const paginatedPlugins = useMemo(() => {
    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    return filteredPlugins.slice(start, start + ITEMS_PER_PAGE);
  }, [filteredPlugins, currentPage]);

  // Check if the Riseup Asia Uploader on the remote site is outdated
  const uploaderVersionInfo = useMemo(() => {
    if (!plugins) return null;
    const uploader = plugins.find(
      (p) => p.slug === UPLOADER_SLUG || p.plugin.startsWith(UPLOADER_SLUG + "/")
    );
    if (!uploader) return { found: false, version: null, isOutdated: false } as const;
    const cmp = compareVersions(uploader.version, EXPECTED_UPLOADER_VERSION);
    return {
      found: true,
      version: uploader.version,
      isOutdated: cmp < 0,
      isActive: uploader.status === RemotePluginStatus.Active,
    } as const;
  }, [plugins]);

  const handleSearchChange = (value: string) => {
    setSearchQuery(value);
    setCurrentPage(1);
  };

  const handleToggle = (plugin: RemotePlugin, enable: boolean) => {
    toggleMutation.mutate({ plugin, enable });
  };

  const toggleSelectPlugin = (pluginKey: string) => {
    setSelectedPlugins((prev) => {
      const next = new Set(prev);
      if (next.has(pluginKey)) {
        next.delete(pluginKey);
      } else {
        next.add(pluginKey);
      }
      return next;
    });
  };

  const selectAllVisible = () => {
    const visibleKeys = paginatedPlugins.map((p) => p.plugin);
    setSelectedPlugins((prev) => {
      const next = new Set(prev);
      visibleKeys.forEach((key) => next.add(key));
      return next;
    });
  };

  const deselectAll = () => {
    setSelectedPlugins(new Set());
  };

  // Bulk actions
  // Parallel bulk actions using Promise.allSettled
  const handleBulkActivate = async () => {
    if (selectedPlugins.size === 0) return;
    setBulkActionPending(true);
    const selectedList = filteredPlugins.filter((p) => selectedPlugins.has(p.plugin) && p.status !== RemotePluginStatus.Active);
    if (selectedList.length === 0) { setBulkActionPending(false); return; }

    // Optimistic update
    queryClient.setQueryData<RemotePlugin[]>(queryKey, (old) =>
      old?.map((p) => selectedPlugins.has(p.plugin) ? { ...p, status: RemotePluginStatus.Active } : p)
    );

    const results = await Promise.allSettled(
      selectedList.map(async (plugin) => {
        if (!(await validatePluginExists(plugin.plugin, "bulk activate"))) {
          throw new Error(`Plugin "${plugin.plugin}" not found — bulk activate blocked`);
        }
        const response = await api.enableRemotePlugin(site.id, plugin.plugin, plugin.version);
        requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/enable`, method: "POST" });
        return plugin.name;
      })
    );

    const succeeded = results.filter((r) => r.status === "fulfilled").length;
    const failed = results.filter((r) => r.status === "rejected").length;
    if (succeeded > 0) toast.success(`Activated ${succeeded} plugin${succeeded !== 1 ? "s" : ""}`);
    if (failed > 0) toast.error(`Failed to activate ${failed} plugin${failed !== 1 ? "s" : ""}`);
    queryClient.invalidateQueries({ queryKey });
    setBulkActionPending(false);
    setSelectedPlugins(new Set());
  };

  const handleBulkDeactivate = async () => {
    if (selectedPlugins.size === 0) return;
    setBulkActionPending(true);
    const selectedList = filteredPlugins.filter((p) => selectedPlugins.has(p.plugin) && p.status === RemotePluginStatus.Active);
    if (selectedList.length === 0) { setBulkActionPending(false); return; }

    // Optimistic update
    queryClient.setQueryData<RemotePlugin[]>(queryKey, (old) =>
      old?.map((p) => selectedPlugins.has(p.plugin) ? { ...p, status: RemotePluginStatus.Inactive } : p)
    );

    const results = await Promise.allSettled(
      selectedList.map(async (plugin) => {
        if (!(await validatePluginExists(plugin.plugin, "bulk deactivate"))) {
          throw new Error(`Plugin "${plugin.plugin}" not found — bulk deactivate blocked`);
        }
        const response = await api.disableRemotePlugin(site.id, plugin.plugin, plugin.version);
        requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/disable`, method: "POST" });
        return plugin.name;
      })
    );

    const succeeded = results.filter((r) => r.status === "fulfilled").length;
    const failed = results.filter((r) => r.status === "rejected").length;
    if (succeeded > 0) toast.success(`Deactivated ${succeeded} plugin${succeeded !== 1 ? "s" : ""}`);
    if (failed > 0) toast.error(`Failed to deactivate ${failed} plugin${failed !== 1 ? "s" : ""}`);
    queryClient.invalidateQueries({ queryKey });
    setBulkActionPending(false);
    setSelectedPlugins(new Set());
  };

  const handleBulkDelete = async () => {
    if (selectedPlugins.size === 0) return;
    setBulkActionPending(true);
    const selectedList = filteredPlugins.filter((p) => selectedPlugins.has(p.plugin));
    if (selectedList.length === 0) { setBulkActionPending(false); return; }

    // Optimistic update — remove from list
    queryClient.setQueryData<RemotePlugin[]>(queryKey, (old) =>
      old?.filter((p) => !selectedPlugins.has(p.plugin))
    );

    const results = await Promise.allSettled(
      selectedList.map(async (plugin) => {
        if (!(await validatePluginExists(plugin.plugin, "bulk delete"))) {
          throw new Error(`Plugin "${plugin.plugin}" not found — bulk delete blocked`);
        }
        if (plugin.status === RemotePluginStatus.Active) {
          const disableResponse = await api.disableRemotePlugin(site.id, plugin.plugin, plugin.version);
          requireSuccess(disableResponse, { endpoint: `/sites/${site.id}/remote-plugins/disable`, method: "POST" });
        }
        const response = await api.deleteRemotePlugin(site.id, plugin.plugin, plugin.version);
        requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/delete`, method: "POST" });
        return plugin.name;
      })
    );

    const succeeded = results.filter((r) => r.status === "fulfilled").length;
    const failed = results.filter((r) => r.status === "rejected").length;
    if (succeeded > 0) toast.success(`Deleted ${succeeded} plugin${succeeded !== 1 ? "s" : ""}`);
    if (failed > 0) toast.error(`Failed to delete ${failed} plugin${failed !== 1 ? "s" : ""}`);
    queryClient.invalidateQueries({ queryKey });
    setBulkActionPending(false);
    setSelectedPlugins(new Set());
  };

  // Get plugin icon - fallback to first letter avatar
  const getPluginAvatar = (plugin: RemotePlugin) => {
    const firstLetter = plugin.name.charAt(0).toUpperCase();
    // Generate a consistent color based on plugin name
    const colors = [
      "bg-blue-500", "bg-green-500", "bg-purple-500", "bg-orange-500", 
      "bg-pink-500", "bg-cyan-500", "bg-indigo-500", "bg-teal-500"
    ];
    const colorIndex = plugin.name.split("").reduce((acc, char) => acc + char.charCodeAt(0), 0) % colors.length;
    return (
      <div className={`flex items-center justify-center h-10 w-10 rounded-lg ${colors[colorIndex]} text-white font-semibold text-lg shrink-0`}>
        {firstLetter}
      </div>
    );
  };

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="w-screen h-screen max-w-none max-h-none rounded-none flex flex-col bg-background/95 backdrop-blur-sm border-border/50 p-4 sm:p-6">
          <DialogHeader className="pb-2 shrink-0">
            <DialogTitle className="flex items-center gap-2 text-lg sm:text-xl">
              <Package className="h-5 w-5 sm:h-6 sm:w-6 text-primary shrink-0" />
              <span className="truncate">Plugins on {site.name}</span>
            </DialogTitle>
            <DialogDescription className="text-xs sm:text-sm">
              View and manage plugins installed on this WordPress site.
            </DialogDescription>
          </DialogHeader>

          {/* Search and Actions Bar - responsive layout */}
          <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search plugins..."
                value={searchQuery}
                onChange={(e) => handleSearchChange(e.target.value)}
                className="pl-10 bg-muted/50 border-border/50 focus-visible:ring-primary/50 text-sm"
              />
            </div>
            
            {/* Cache Status & Actions - stacked on mobile */}
            <div className="flex items-center justify-between sm:justify-end gap-1 sm:gap-2">
              {lastFetchedAt && (
                <Badge variant="outline" className="text-xs gap-1 text-muted-foreground shrink-0 hidden sm:flex">
                  <Clock className="h-3 w-3" />
                  {formatTimeAgo(lastFetchedAt)}
                </Badge>
              )}
              <Button 
                variant="outline" 
                size="sm" 
                onClick={() => forceSyncMutation.mutate()} 
                disabled={forceSyncMutation.isPending || isFetching}
                className="shrink-0 gap-1.5 text-xs sm:text-sm h-8 sm:h-9"
                title="Force refresh from site (bypass cache)"
              >
                <Zap className="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0 will-change-transform" />
                <span className="hidden xs:inline">Force</span> Sync
              </Button>
              <Button variant="outline" size="icon" onClick={() => refetch()} disabled={isLoading || isFetching} className="shrink-0 h-8 w-8 sm:h-9 sm:w-9" title="Refresh (may use cache)">
                <RefreshCw className={`h-4 w-4 shrink-0 will-change-transform ${isFetching ? "animate-spin" : ""}`} />
              </Button>
            </div>
          </div>

          {/* Uploader Version Warning */}
          {uploaderVersionInfo && uploaderVersionInfo.isOutdated && (
            <div className="flex items-start gap-2 p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-200 shrink-0">
              <AlertTriangle className="h-4 w-4 text-amber-400 shrink-0 mt-0.5" />
              <div className="text-xs sm:text-sm">
                <p className="font-medium text-amber-300">
                  Riseup Asia Uploader is outdated (v{uploaderVersionInfo.version} → v{EXPECTED_UPLOADER_VERSION})
                </p>
                <p className="text-amber-400/80 mt-0.5">
                  Some features like enable/disable/delete may not work. Republish the uploader to this site to update.
                </p>
              </div>
            </div>
          )}
          {uploaderVersionInfo && !uploaderVersionInfo.found && !isLoading && !isError && (
            <div className="flex items-start gap-2 p-3 rounded-lg bg-destructive/10 border border-destructive/30 text-destructive shrink-0">
              <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
              <div className="text-xs sm:text-sm">
                <p className="font-medium">Riseup Asia Uploader not found</p>
                <p className="text-destructive/80 mt-0.5">
                  The companion plugin is not installed on this site. Deploy it to enable full plugin management.
                </p>
              </div>
            </div>
          )}

          {/* ZIP Upload Zone */}
          <Collapsible open={uploadOpen} onOpenChange={setUploadOpen} className="shrink-0">
            <CollapsibleTrigger asChild>
              <Button variant="outline" size="sm" className="gap-1.5 text-xs w-full justify-start">
                <Upload className="h-3.5 w-3.5 shrink-0" />
                Upload Plugins (.zip)
                <ChevronDown className={`h-3.5 w-3.5 ml-auto transition-transform shrink-0 ${uploadOpen ? "rotate-180" : ""}`} />
              </Button>
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-2">
              <div
                className="border-2 border-dashed border-border/60 rounded-lg p-4 text-center hover:border-primary/40 transition-colors"
                onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); }}
                onDrop={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  const files = Array.from(e.dataTransfer.files).filter((f) => f.name.endsWith(".zip"));
                  if (files.length) setUploadFiles((prev) => [...prev, ...files]);
                }}
              >
                <FileArchive className="h-8 w-8 mx-auto mb-2 text-muted-foreground" />
                <p className="text-sm text-muted-foreground mb-2">
                  Drag & drop .zip files here, or{" "}
                  <button
                    type="button"
                    className="text-primary underline underline-offset-2"
                    onClick={() => fileInputRef.current?.click()}
                  >
                    browse
                  </button>
                </p>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".zip"
                  multiple
                  className="hidden"
                  onChange={(e) => {
                    const files = Array.from(e.target.files || []);
                    if (files.length) setUploadFiles((prev) => [...prev, ...files]);
                    e.target.value = "";
                  }}
                />
              </div>

              {uploadFiles.length > 0 && (
                <div className="mt-2 space-y-1">
                  {uploadFiles.map((file, i) => {
                    const fileKey = `${file.name}-${i}`;
                    const progress = uploadProgress[fileKey];
                    const isUploading = uploadPending && progress !== undefined;
                    const isDone = progress === 100;
                    return (
                      <div key={fileKey} className="space-y-0.5">
                        <div className="flex items-center gap-2 text-xs p-1.5 rounded bg-muted/50">
                          <FileArchive className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                          <span className="truncate flex-1">{file.name}</span>
                          {isUploading && (
                            <span className="text-primary shrink-0 font-medium tabular-nums">{progress}%</span>
                          )}
                          <span className="text-muted-foreground shrink-0">{(file.size / 1024).toFixed(0)} KB</span>
                          {!uploadPending && (
                            <button
                              type="button"
                              className="text-muted-foreground hover:text-destructive shrink-0"
                              onClick={() => setUploadFiles((prev) => prev.filter((_, idx) => idx !== i))}
                            >
                              <X className="h-3.5 w-3.5" />
                            </button>
                          )}
                        </div>
                        {isUploading && (
                          <div className="h-1 rounded-full bg-muted overflow-hidden mx-1.5">
                            <div
                              className={`h-full rounded-full transition-all duration-300 ease-out ${isDone ? "bg-success" : "bg-primary"}`}
                              style={{ width: `${progress}%` }}
                            />
                          </div>
                        )}
                      </div>
                    );
                  })}

                  <div className="flex items-center justify-between mt-2">
                    <label className="flex items-center gap-2 text-xs cursor-pointer">
                      <Checkbox
                        checked={activateAfterInstall}
                        onCheckedChange={(v) => setActivateAfterInstall(!!v)}
                      />
                      Activate after install
                    </label>
                    <Button
                      size="sm"
                      className="gap-1.5 text-xs h-7"
                      disabled={uploadPending}
                      onClick={async () => {
                        setUploadPending(true);
                        const progressMap: Record<string, number> = {};
                        uploadFiles.forEach((f, i) => { progressMap[`${f.name}-${i}`] = 0; });
                        setUploadProgress({ ...progressMap });

                        const results = await Promise.allSettled(
                          uploadFiles.map((file, i) => {
                            const fileKey = `${file.name}-${i}`;
                            return api.uploadRemotePluginWithProgress(
                              site.id,
                              file,
                              activateAfterInstall,
                              (percent) => {
                                setUploadProgress((prev) => ({ ...prev, [fileKey]: percent }));
                              }
                            );
                          })
                        );
                        const succeeded = results.filter((r) => r.status === "fulfilled" && r.value.success).length;
                        const failed = results.length - succeeded;
                        if (succeeded > 0) toast.success(`Uploaded ${succeeded} plugin${succeeded !== 1 ? "s" : ""}`);
                        if (failed > 0) toast.error(`Failed to upload ${failed} plugin${failed !== 1 ? "s" : ""}`);
                        setUploadFiles([]);
                        setUploadPending(false);
                        setUploadProgress({});
                        queryClient.invalidateQueries({ queryKey });
                      }}
                    >
                      {uploadPending ? <Loader2 className="h-3 w-3 animate-spin" /> : <Upload className="h-3 w-3" />}
                      Upload {uploadFiles.length} file{uploadFiles.length !== 1 ? "s" : ""}
                    </Button>
                  </div>
                </div>
              )}
            </CollapsibleContent>
          </Collapsible>

          {/* Bulk Actions Bar - responsive layout */}
          {selectedPlugins.size > 0 && (
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 p-2 sm:p-3 rounded-lg bg-primary/10 border border-primary/20 shrink-0">
              <span className="text-xs sm:text-sm font-medium">
                {selectedPlugins.size} selected
              </span>
              <div className="flex flex-wrap items-center gap-1 sm:gap-2 w-full sm:w-auto">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleBulkActivate}
                  disabled={bulkActionPending}
                  className="gap-1 text-xs h-7 sm:h-8 flex-1 sm:flex-none"
                >
                  <Power className="h-3 w-3" />
                  <span className="hidden xs:inline">Activate</span>
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleBulkDeactivate}
                  disabled={bulkActionPending}
                  className="gap-1 text-xs h-7 sm:h-8 flex-1 sm:flex-none"
                >
                  <PowerOff className="h-3 w-3" />
                  <span className="hidden xs:inline">Deactivate</span>
                </Button>
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={handleBulkDelete}
                  disabled={bulkActionPending}
                  className="gap-1 text-xs h-7 sm:h-8 flex-1 sm:flex-none"
                >
                  <Trash2 className="h-3 w-3" />
                  <span className="hidden xs:inline">Delete</span>
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={deselectAll}
                  disabled={bulkActionPending}
                  className="text-xs h-7 sm:h-8"
                >
                  Clear
                </Button>
              </div>
            </div>
          )}

          {/* Select All / Deselect All - compact responsive */}
          {!isLoading && !isError && filteredPlugins.length > 0 && (
            <div className="flex items-center justify-between text-xs sm:text-sm shrink-0">
              <div className="flex items-center gap-1 sm:gap-3">
                <Button variant="ghost" size="sm" onClick={selectAllVisible} className="h-6 sm:h-7 px-1.5 sm:px-2 text-xs">
                  Select ({paginatedPlugins.length})
                </Button>
                {selectedPlugins.size > 0 && (
                  <Button variant="ghost" size="sm" onClick={deselectAll} className="h-6 sm:h-7 px-1.5 sm:px-2 text-xs text-muted-foreground">
                    Clear
                  </Button>
                )}
              </div>
              {totalPages > 1 && (
                <span className="text-muted-foreground text-xs">
                  {currentPage}/{totalPages}
                </span>
              )}
            </div>
          )}

          {/* Plugin List - flexible height */}
          {isLoading ? (
            <div className="flex-1 flex items-center justify-center py-8 sm:py-12 min-h-[200px]">
              <div className="flex flex-col items-center gap-2 sm:gap-3">
                <Loader2 className="h-8 w-8 sm:h-10 sm:w-10 animate-spin text-primary" />
                <span className="text-xs sm:text-sm text-muted-foreground">Loading plugins...</span>
              </div>
            </div>
          ) : isError ? (
            <div className="flex-1 flex flex-col items-center justify-center py-8 sm:py-12 text-muted-foreground min-h-[200px]">
              <AlertCircle className="h-8 w-8 sm:h-10 sm:w-10 mb-2 sm:mb-3 text-destructive" />
              <p className="font-medium text-sm sm:text-base">Failed to load plugins</p>
              <Button variant="link" onClick={() => refetch()} className="mt-2 text-sm">
                Try again
              </Button>
            </div>
          ) : !filteredPlugins.length ? (
            <div className="flex-1 flex flex-col items-center justify-center py-8 sm:py-12 text-muted-foreground min-h-[200px]">
              <Package className="h-8 w-8 sm:h-10 sm:w-10 mb-2 sm:mb-3" />
              <p className="font-medium text-sm sm:text-base">{searchQuery ? "No plugins match your search" : "No plugins installed"}</p>
            </div>
          ) : (
            <ScrollArea className="flex-1 min-h-0 -mx-4 sm:-mx-6 px-4 sm:px-6 touch-pan-y">
              <div className="space-y-2 pb-2">
                {paginatedPlugins.map((plugin) => {
                  const isSelected = selectedPlugins.has(plugin.plugin);
                  const hasSuccessPulse = successPlugins.has(plugin.plugin);

                  return (
                    <div
                      key={plugin.plugin}
                      className={`
                        group flex items-center gap-3 p-3 rounded-xl border transition-colors duration-200
                        ${isSelected 
                          ? "bg-primary/10 border-primary/30" 
                          : "bg-card/50 border-border/50 hover:bg-secondary/50 hover:border-primary/30"
                        }
                      `}
                    >
                      {/* Selection Checkbox */}
                      <Checkbox
                        checked={isSelected}
                        onCheckedChange={() => toggleSelectPlugin(plugin.plugin)}
                        className="shrink-0"
                      />

                      {/* Plugin Avatar */}
                      {getPluginAvatar(plugin)}

                      {/* Plugin Info */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <span className="font-semibold truncate">{plugin.name}</span>
                          <Badge variant="secondary" className="text-xs shrink-0 font-mono">
                            v{plugin.version}
                          </Badge>
                          <Badge
                            variant={plugin.status === RemotePluginStatus.Active ? "default" : "outline"}
                            className={`text-xs shrink-0 ${
                              plugin.status === RemotePluginStatus.Active 
                                ? "bg-green-500/20 text-green-400 border-green-500/30" 
                                : "text-muted-foreground"
                            }`}
                          >
                            {plugin.status}
                          </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground line-clamp-1">
                          {plugin.description || "No description available"}
                        </p>
                        <div className="flex items-center gap-3 mt-1 text-xs text-muted-foreground">
                          <span className="flex items-center gap-1">
                            <User className="h-3 w-3" />
                            {plugin.author || "Unknown"}
                          </span>
                          <span className="flex items-center gap-1">
                            <Puzzle className="h-3 w-3" />
                            {plugin.slug}
                          </span>
                        </div>
                      </div>

                      {/* Actions */}
                      <div className="flex items-center gap-1 shrink-0 opacity-70 group-hover:opacity-100 transition-opacity">
                        {plugin.pluginUri && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8"
                            asChild
                          >
                            <a href={plugin.pluginUri} target="_blank" rel="noopener noreferrer" title="Visit plugin page">
                              <ExternalLink className="h-4 w-4" />
                            </a>
                          </Button>
                        )}

                        <div className="flex items-center gap-1 px-1">
                          <Switch
                            checked={plugin.status === RemotePluginStatus.Active}
                            onCheckedChange={(checked) => handleToggle(plugin, checked)}
                            className={hasSuccessPulse ? "animate-success-pulse" : ""}
                          />
                        </div>

                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8">
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem 
                              onClick={() => handleToggle(plugin, plugin.status !== RemotePluginStatus.Active)}
                              disabled={toggleMutation.isPending}
                            >
                              {plugin.status === RemotePluginStatus.Active ? (
                                <>
                                  <PowerOff className="h-4 w-4 mr-2" />
                                  Deactivate
                                </>
                              ) : (
                                <>
                                  <Power className="h-4 w-4 mr-2" />
                                  Activate
                                </>
                              )}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => setFileBrowserPlugin(plugin)}
                            >
                              <FolderOpen className="h-4 w-4 mr-2" />
                              Browse Files
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() => setPluginToDelete(plugin)}
                              className="text-destructive focus:text-destructive"
                            >
                              <Trash2 className="h-4 w-4 mr-2" />
                              Delete
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </div>
                  );
                })}
              </div>
            </ScrollArea>
          )}

          {/* Pagination - compact on mobile */}
          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-1 sm:gap-2 pt-2 shrink-0">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                disabled={currentPage === 1}
                className="h-7 sm:h-8 px-2 sm:px-3 text-xs"
              >
                Prev
              </Button>
              <div className="flex items-center gap-0.5 sm:gap-1">
                {Array.from({ length: Math.min(3, totalPages) }, (_, i) => {
                  let pageNum: number;
                  if (totalPages <= 3) {
                    pageNum = i + 1;
                  } else if (currentPage <= 2) {
                    pageNum = i + 1;
                  } else if (currentPage >= totalPages - 1) {
                    pageNum = totalPages - 2 + i;
                  } else {
                    pageNum = currentPage - 1 + i;
                  }
                  return (
                    <Button
                      key={pageNum}
                      variant={currentPage === pageNum ? "default" : "outline"}
                      size="sm"
                      className="w-7 h-7 sm:w-8 sm:h-8 p-0 text-xs"
                      onClick={() => setCurrentPage(pageNum)}
                    >
                      {pageNum}
                    </Button>
                  );
                })}
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                disabled={currentPage === totalPages}
                className="h-7 sm:h-8 px-2 sm:px-3 text-xs"
              >
                Next
              </Button>
            </div>
          )}

          {/* Footer - responsive */}
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 pt-2 sm:pt-3 border-t border-border/50 text-xs sm:text-sm text-muted-foreground shrink-0">
            <div className="flex items-center gap-2">
              <span>
                {filteredPlugins.length} plugin{filteredPlugins.length !== 1 ? "s" : ""}
                {searchQuery && plugins?.length !== filteredPlugins.length && (
                  <span className="hidden sm:inline"> (of {plugins?.length} total)</span>
                )}
              </span>
              {isFromCache && (
                <Badge variant="secondary" className="text-xs gap-1">
                  <Database className="h-3 w-3" />
                  Cached
                </Badge>
              )}
            </div>
            <a
              href={`${site.url}/wp-admin/plugins.php`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1 hover:text-primary transition-colors text-xs sm:text-sm"
            >
              <span className="hidden sm:inline">Open in WordPress</span>
              <span className="sm:hidden">WP Admin</span>
              <ExternalLink className="h-3 w-3" />
            </a>
          </div>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <AlertDialog open={!!pluginToDelete} onOpenChange={() => setPluginToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {pluginToDelete?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently remove the plugin from {site.name}. 
              {pluginToDelete?.status === RemotePluginStatus.Active && " The plugin will be deactivated first."}
              {" "}This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => pluginToDelete && deleteMutation.mutate(pluginToDelete)}
              disabled={deleteMutation.isPending}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {deleteMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* File Browser */}
      {fileBrowserPlugin && (
        <RemotePluginFileBrowser
          siteId={site.id}
          siteName={site.name}
          pluginSlug={fileBrowserPlugin.slug}
          pluginName={fileBrowserPlugin.name}
          open={!!fileBrowserPlugin}
          onOpenChange={(open) => !open && setFileBrowserPlugin(null)}
        />
      )}
    </>
  );
}
