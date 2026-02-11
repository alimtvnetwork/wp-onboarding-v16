import { useState, useRef } from "react";
import { useExecutionLoggerStore } from "@/hooks/useExecutionLogger";
import { usePlugins, usePluginsPaginated } from "@/hooks/usePlugins";
import { useSites } from "@/hooks/useSites";
import { usePluginFormPersistence } from "@/hooks/usePluginFormPersistence";
import { useQuickPublish } from "@/hooks/useQuickPublish";
import { useBulkQuickPublish } from "@/hooks/useBulkQuickPublish";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { EmptyState } from "@/components/shared/EmptyState";
import { CategorySelect } from "@/components/shared/CategorySelect";
import { CategoryFilter } from "@/components/shared/CategoryFilter";
import { CategoryBadge } from "@/components/shared/CategoryBadge";
import { PublishProgressDialog } from "@/components/plugins/PublishProgressDialog";
import { SyncProgressDialog } from "@/components/plugins/SyncProgressDialog";
import { BackupProgressDialog } from "@/components/backup/BackupProgressDialog";
import { BulkActionsBar } from "@/components/plugins/BulkActionsBar";
import { GitActionsPanel } from "@/components/plugins/GitActionsPanel";
import { VersionHistoryPanel } from "@/components/plugins/VersionHistoryPanel";
import { ScanDirectoryPanel } from "@/components/plugins/ScanDirectoryPanel";
import { QuickPublishIndicator } from "@/components/plugins/QuickPublishIndicator";
import { DiffPreviewDialog } from "@/components/plugins/DiffPreviewDialog";
import { SiteVersionBadge } from "@/components/publish/SiteVersionBadge";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  Package,
  Plus,
  Loader2,
  FolderOpen,
  FileText,
  AlertCircle,
  Eye,
  EyeOff,
  GitBranch,
  RefreshCw,
  Link2,
  Trash2,
  Globe,
  Upload,
  CloudUpload,
  CheckSquare,
  Square,
  Archive,
  Zap,
  Files,
  MoreHorizontal,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api, Plugin } from "@/lib/api";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";
import { EnvelopePagination } from "@/components/shared/EnvelopePagination";

export default function Plugins() {
  const [currentPage, setCurrentPage] = useState(1);
  const { data: paginatedResult, isLoading: pluginsLoading } = usePluginsPaginated(currentPage);
  const plugins = paginatedResult?.data;
  const envelopeMeta = paginatedResult?.envelope;
  const { data: sites } = useSites();
  const queryClient = useQueryClient();
  const { captureError, openErrorModal } = useErrorStore();
  const { quickPublishAll, hasActiveOperation } = useQuickPublish();
  const { bulkQuickPublish } = useBulkQuickPublish();
  
  // Use persistent form hook
  const { formData, handleInputChange, clearForm } = usePluginFormPersistence();
  
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [showMappingDialog, setShowMappingDialog] = useState(false);
  const [selectedPlugin, setSelectedPlugin] = useState<Plugin | null>(null);
  const [selectedSites, setSelectedSites] = useState<number[]>([]);
  const [remoteSlug, setRemoteSlug] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isPulling, setIsPulling] = useState<number | null>(null);
  const [isScanning, setIsScanning] = useState<number | null>(null);
  const [isSyncing, setIsSyncing] = useState<number | null>(null);
  const [isPublishing, setIsPublishing] = useState<number | null>(null);
  const [isScanningAll, setIsScanningAll] = useState(false);
  const [addMethod, setAddMethod] = useState<"path" | "browse">("path");
  const [validationError, setValidationError] = useState<string | null>(null);
  const [showPublishDialog, setShowPublishDialog] = useState(false);
  const [publishPlugin, setPublishPlugin] = useState<Plugin | null>(null);
  const [publishSiteId, setPublishSiteId] = useState<number | null>(null);
  const [showPublishProgress, setShowPublishProgress] = useState(false);
  const [selectedCategories, setSelectedCategories] = useState<string[]>([]);
  
  // Sync progress dialog state
  const [showSyncProgress, setShowSyncProgress] = useState(false);
  const [syncPlugin, setSyncPlugin] = useState<Plugin | null>(null);
  const [syncSiteId, setSyncSiteId] = useState<number | null>(null);
  
  // Backup progress dialog state
  const [showBackupProgress, setShowBackupProgress] = useState(false);
  const [backupPlugin, setBackupPlugin] = useState<Plugin | null>(null);
  
  // Bulk selection state
  const [selectedPluginIds, setSelectedPluginIds] = useState<Set<number>>(new Set());
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);
  const [isBulkDeploying, setIsBulkDeploying] = useState(false);
  const [isBulkScanning, setIsBulkScanning] = useState(false);
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false);
  
  // Diff preview state
  const [showDiffPreview, setShowDiffPreview] = useState(false);
  const [diffPreviewSiteId, setDiffPreviewSiteId] = useState<number | null>(null);
  const [selectedFilesForPublish, setSelectedFilesForPublish] = useState<string[] | undefined>(undefined);
  const handleAddPlugin = async (forceCreate = false) => {
    if (!formData.name || !formData.path) {
      toast.error("Name and path are required");
      return;
    }

    setIsSubmitting(true);
    setValidationError(null);
    
    try {
      const response = await api.createPlugin({
        name: formData.name,
        path: formData.path,
        category: formData.category || undefined,
        gitEnabled: formData.gitEnabled,
        gitRemoteUrl: formData.gitRemoteUrl,
        buildCommand: formData.buildCommand,
        forceCreate, // Allow saving even if path validation fails
      });
      if (response.success) {
        toast.success("Plugin registered successfully");
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
        setShowAddDialog(false);
        clearForm();
        setValidationError(null);
      } else if (response.error) {
        const msg = response.error.message || "";
        const code = response.error.code || "";
        const isDuplicate =
          code === "E2009" ||
          msg.includes("E2009") ||
          msg.toLowerCase().includes("already registered") ||
          msg.toLowerCase().includes("already exist");

        // If the plugin already exists, treat this as success from a UX perspective
        // (refresh list + close dialog) so users aren't blocked.
        if (isDuplicate) {
          toast.info("Plugin is already registered — refreshing list");
          queryClient.invalidateQueries({ queryKey: ["plugins"] });
          setShowAddDialog(false);
          setValidationError(null);
          return;
        }

        // Store error message for "Save Anyway" option (e.g., invalid path)
        setValidationError(response.error.message);

        // Capture to error store and show modal for full details
        const captured = captureError(response.error, {
          endpoint: "/plugins",
          method: "POST",
          requestBody: { name: formData.name, path: formData.path, gitEnabled: formData.gitEnabled },
        });
        openErrorModal(captured);
      }
    } catch (error) {
      toast.error("Failed to register plugin");
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSaveAnyway = () => {
    handleAddPlugin(true);
  };

  const handleDeletePlugin = async (id: number) => {
    if (!confirm("Are you sure you want to remove this plugin?")) return;

    try {
      const response = await api.deletePlugin(id);
      if (response.success) {
        toast.success("Plugin removed");
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
      } else if (response.error) {
        const captured = captureError(response.error, {
          endpoint: `/plugins/${id}`,
          method: 'DELETE',
          context: {
            source: "Plugins.handleDeletePlugin",
            triggerComponent: "Plugins",
            triggerAction: "delete_clicked",
            pluginId: id
          }
        });
        openErrorModal(captured);
      }
    } catch (error) {
      const { captureException, openErrorModal: showModal } = useErrorStore.getState();
      const captured = captureException(error, {
        source: "Plugins.handleDeletePlugin",
        triggerComponent: "Plugins",
        triggerAction: "delete_clicked",
        endpoint: `/plugins/${id}`,
        method: "DELETE",
        context: { pluginId: id }
      });
      toast.error("Failed to remove plugin", {
        action: { label: "Details", onClick: () => showModal(captured) }
      });
    }
  };

  const handleGitPullAll = async () => {
    toast.info("Pulling all plugins...");
    try {
      const response = await api.gitPullAll();
      if (response.success) {
        toast.success(`Git pull completed: ${response.data?.succeeded || 0} succeeded`);
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
      } else {
        toast.error(response.error?.message || "Git pull failed");
      }
    } catch (error) {
      toast.error("Git pull failed");
    }
  };

  const handleGitPull = async (pluginId: number) => {
    setIsPulling(pluginId);
    try {
      const response = await api.gitPull(pluginId);
      if (response.success) {
        toast.success(`Git pull completed: ${response.data?.filesChanged || 0} files changed`);
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
      } else {
        toast.error(response.error?.message || "Git pull failed");
      }
    } catch (error) {
      toast.error("Git pull failed");
    } finally {
      setIsPulling(null);
    }
  };

  const handleRefreshScan = async (pluginId: number) => {
    setIsScanning(pluginId);
    try {
      const response = await api.scanPlugin(pluginId);
      if (response.success) {
        const changes = response.data?.changes?.length || 0;
        toast.success(`Scan complete: ${changes} changes detected`);
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
      } else {
        toast.error(response.error?.message || "Scan failed");
      }
    } catch (error) {
      toast.error("Scan failed");
    } finally {
      setIsScanning(null);
    }
  };

  const handleRefreshAll = async () => {
    setIsScanningAll(true);
    toast.info("Scanning all plugins...");
    try {
      const response = await api.scanAllPlugins();
      if (response.success) {
        toast.success("All plugins scanned");
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
      } else {
        toast.error(response.error?.message || "Scan failed");
      }
    } catch (error) {
      toast.error("Scan failed");
    } finally {
      setIsScanningAll(false);
    }
  };

  const handleSyncPlugin = async (plugin: Plugin) => {
    const execLogger = useExecutionLoggerStore.getState();
    const chainId = execLogger.startChain(`SyncPlugin → ${plugin.name}`);
    execLogger.log({ type: 'handler', name: 'handleSyncPlugin', args: `plugin=${plugin.name}` });

    if (!plugin.mappings || plugin.mappings.length === 0) {
      toast.warning("No sites mapped - add a site first");
      execLogger.endChain(chainId);
      return;
    }

    // Open sync progress dialog for the first mapped site
    const firstMapping = plugin.mappings[0];
    setSyncPlugin(plugin);
    setSyncSiteId(firstMapping.siteId);
    setShowSyncProgress(true);
    setIsSyncing(plugin.id);

    try {
      // Trigger sync via API - the dialog will track progress via WebSocket
      execLogger.log({ type: 'api', name: `checkSync(${plugin.id}, ${firstMapping.siteId})` });
      const response = await api.checkSync(plugin.id, firstMapping.siteId);
      if (!response.success && response.error) {
        execLogger.endChain(chainId, response.error.message);
        toast.error(response.error.message || "Sync check failed");
      } else {
        execLogger.endChain(chainId);
      }
    } catch (error) {
      execLogger.endChain(chainId, error instanceof Error ? error.message : 'Sync failed');
      toast.error("Sync check failed");
    } finally {
      setIsSyncing(null);
    }
  };

  const openPublishDialog = (plugin: Plugin) => {
    // Always open dialog - show guidance if no mappings
    setPublishPlugin(plugin);
    setShowPublishDialog(true);
  };

  const publishGuardRef = useRef(false);
  const publishCooldownRef = useRef<number>(0);

  const handlePublish = async (plugin: Plugin, siteId: number, files?: string[]) => {
    // Absolute guard: prevent any re-invocation once a publish has been initiated
    if (publishGuardRef.current) {
      console.warn('[Publish] Blocked: publish already in progress');
      return;
    }
    
    // Cooldown guard: prevent re-trigger after last successful publish (no auto-retry ever)
    const now = Date.now();
    if (publishCooldownRef.current > 0) {
      console.warn('[Publish] Blocked: cooldown active, last publish was', Math.round((now - publishCooldownRef.current) / 1000), 's ago. Only manual retry allowed.');
      return;
    }
    
    publishGuardRef.current = true;

    // Open progress dialog instead of inline publishing
    setPublishPlugin(plugin);
    setPublishSiteId(siteId);
    setShowPublishDialog(false);
    setShowPublishProgress(true);
    setIsPublishing(plugin.id);

    // Determine publish mode based on whether specific files were selected
    let publishMode: "selected" | "full";
    if (files && files.length > 0) {
      publishMode = "selected";
    } else {
      let uploadMode: "file" | "zip" = "file";
      try {
        const saved = localStorage.getItem("wppp_upload_mode");
        if (saved === "zip") uploadMode = "zip";
      } catch {
        // Default to file mode
      }
      publishMode = uploadMode === "zip" ? "full" : "selected";
    }

    // Get keep ZIP files setting
    let keepZipFiles = false;
    try {
      const saved = localStorage.getItem("wppp_keep_zip_files");
      keepZipFiles = saved === "true";
    } catch {
      // Default to false
    }

    try {
      const response = await api.publishPlugin(plugin.id, siteId, {
        mode: publishMode,
        files: files,
        createBackup: true,
        keepZipFiles,
      });
      if (response.success) {
        toast.success(`Published ${response.data?.filesUpdated || 0} files`);
        // Set permanent cooldown — only user clicking publish again should reset this
        publishCooldownRef.current = Date.now();
        // Refresh data without triggering re-publish
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
      } else if (response.error) {
        const captured = captureError(response.error, {
          endpoint: `/plugins/${plugin.id}/sites/${siteId}/publish`,
          method: "POST",
          context: {
            source: "Plugins.handlePublish",
            triggerComponent: "Plugins",
            triggerAction: "publish_initiated",
            pluginId: plugin.id,
            siteId
          }
        });
        openErrorModal(captured);
      }
    } catch (error) {
      const { captureException, openErrorModal: showModal } = useErrorStore.getState();
      const captured = captureException(error, {
        source: "Plugins.handlePublish",
        triggerComponent: "Plugins",
        triggerAction: "publish_initiated",
        endpoint: `/plugins/${plugin.id}/sites/${siteId}/publish`,
        method: "POST",
        context: { pluginId: plugin.id, siteId }
      });
      toast.error("Publish failed", {
        action: { label: "Details", onClick: () => showModal(captured) }
      });
    } finally {
      setIsPublishing(null);
      publishGuardRef.current = false;
    }
  };

  const handlePublishComplete = (success: boolean) => {
    if (success) {
      // Set permanent cooldown — no auto re-publish allowed
      publishCooldownRef.current = Date.now();
      // Only refresh data, never re-trigger publish
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
    }
    setIsPublishing(null);
    // Do NOT close dialog automatically — let user click Done
  };

  const handleCategoryToggle = (category: string) => {
    setSelectedCategories((prev) =>
      prev.includes(category) ? prev.filter((c) => c !== category) : [...prev, category]
    );
  };

  const filteredPlugins = plugins?.filter((plugin) => {
    if (selectedCategories.length === 0) return true;
    return plugin.category && selectedCategories.includes(plugin.category);
  });

  // Bulk selection handlers
  const togglePluginSelection = (pluginId: number) => {
    setSelectedPluginIds((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(pluginId)) {
        newSet.delete(pluginId);
      } else {
        newSet.add(pluginId);
      }
      return newSet;
    });
  };

  const selectAllPlugins = () => {
    if (filteredPlugins) {
      setSelectedPluginIds(new Set(filteredPlugins.map((p) => p.id)));
    }
  };

  const clearSelection = () => {
    setSelectedPluginIds(new Set());
  };

  const handleBulkEnableWatch = async () => {
    setIsBulkProcessing(true);
    try {
      const ids = Array.from(selectedPluginIds);
      let successCount = 0;
      for (const id of ids) {
        const response = await api.updatePlugin(id, { watchEnabled: true });
        if (response.success) successCount++;
      }
      toast.success(`Enabled watching on ${successCount} plugins`);
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      clearSelection();
    } catch (error) {
      toast.error("Failed to update plugins");
    } finally {
      setIsBulkProcessing(false);
    }
  };

  const handleBulkDisableWatch = async () => {
    setIsBulkProcessing(true);
    try {
      const ids = Array.from(selectedPluginIds);
      let successCount = 0;
      for (const id of ids) {
        const response = await api.updatePlugin(id, { watchEnabled: false });
        if (response.success) successCount++;
      }
      toast.success(`Disabled watching on ${successCount} plugins`);
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      clearSelection();
    } catch (error) {
      toast.error("Failed to update plugins");
    } finally {
      setIsBulkProcessing(false);
    }
  };

  const handleBulkSync = async () => {
    const execLogger = useExecutionLoggerStore.getState();
    const chainId = execLogger.startChain(`BulkSync → ${selectedPluginIds.size} plugins`);
    setIsBulkProcessing(true);
    toast.info("Syncing selected plugins...");
    try {
      const ids = Array.from(selectedPluginIds);
      let totalChanges = 0;
      for (const id of ids) {
        const plugin = plugins?.find((p) => p.id === id);
        if (plugin?.mappings) {
          for (const mapping of plugin.mappings) {
            const response = await api.checkSync(id, mapping.siteId);
            if (response.success && response.data) {
              totalChanges += (response.data.added || 0) + (response.data.modified || 0) + (response.data.deleted || 0);
            }
          }
        }
      }
      execLogger.endChain(chainId);
      toast.success(`Sync complete: ${totalChanges} total changes detected`);
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      clearSelection();
    } catch (error) {
      execLogger.endChain(chainId, error instanceof Error ? error.message : 'Bulk sync failed');
      toast.error("Failed to sync plugins");
    } finally {
      setIsBulkProcessing(false);
    }
  };

  const handleBulkGitPull = async () => {
    setIsBulkProcessing(true);
    toast.info("Pulling from git for selected plugins...");
    try {
      const ids = Array.from(selectedPluginIds);
      let successCount = 0;
      for (const id of ids) {
        const plugin = plugins?.find((p) => p.id === id);
        if (plugin?.gitEnabled) {
          const response = await api.gitPull(id);
          if (response.success) successCount++;
        }
      }
      toast.success(`Git pull complete: ${successCount} plugins updated`);
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      clearSelection();
    } catch (error) {
      toast.error("Git pull failed");
    } finally {
      setIsBulkProcessing(false);
    }
  };

  const handleBulkDelete = async () => {
    setIsBulkProcessing(true);
    try {
      const ids = Array.from(selectedPluginIds);
      let successCount = 0;
      for (const id of ids) {
        const response = await api.deletePlugin(id);
        if (response.success) successCount++;
      }
      toast.success(`Deleted ${successCount} plugins`);
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      clearSelection();
      setShowBulkDeleteConfirm(false);
    } catch (error) {
      toast.error("Failed to delete plugins");
    } finally {
      setIsBulkProcessing(false);
    }
  };

  const handleBulkDeploy = async () => {
    setIsBulkDeploying(true);
    try {
      const ids = Array.from(selectedPluginIds);
      const selectedPlugins = plugins?.filter((p) => ids.includes(p.id)) || [];
      await bulkQuickPublish(selectedPlugins, { concurrency: 2 });
      clearSelection();
    } catch (error) {
      toast.error("Failed to deploy plugins");
    } finally {
      setIsBulkDeploying(false);
    }
  };

  const handleBulkScanDirectories = async () => {
    setIsBulkScanning(true);
    toast.info("Scanning directories for selected plugins...");
    try {
      const ids = Array.from(selectedPluginIds);
      const paths: string[] = [];
      
      // Get parent directories of selected plugins to scan for more plugins
      for (const id of ids) {
        const plugin = plugins?.find((p) => p.id === id);
        if (plugin?.path) {
          // Get parent directory of the plugin
          const parentPath = plugin.path.replace(/[/\\][^/\\]+$/, '');
          if (parentPath && !paths.includes(parentPath)) {
            paths.push(parentPath);
          }
        }
      }
      
      if (paths.length === 0) {
        toast.warning("No valid paths found for selected plugins");
        return;
      }
      
      const response = await api.scanDirectories(paths, false);
      if (response.success && response.data) {
        const { scanned, detected, results } = response.data;
        const newPlugins = results.filter(r => r.isPlugin && !plugins?.find(p => 
          p.path.toLowerCase() === r.path.toLowerCase()
        ));
        
        toast.success(
          `Scanned ${scanned} directories: ${detected} plugins found (${newPlugins.length} new)`,
          { duration: 5000 }
        );
        
        // Refresh plugin list in case any were auto-registered
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
      } else if (response.error) {
        toast.error(response.error.message || "Scan failed");
      }
      
      clearSelection();
    } catch (error) {
      toast.error("Failed to scan directories");
    } finally {
      setIsBulkScanning(false);
    }
  };

  const openMappingDialog = async (plugin: Plugin) => {
    setSelectedPlugin(plugin);
    setShowMappingDialog(true);
    
    // Fetch fresh mappings from API instead of using potentially stale plugin.mappings
    try {
      const response = await api.getPluginMappings(plugin.id);
      if (response.success && response.data) {
        setSelectedSites(response.data.map((m) => m.siteId));
        setRemoteSlug(response.data[0]?.remoteSlug || plugin.name.toLowerCase().replace(/\s+/g, '-'));
      } else {
        // Fallback to local data if API fails
        setSelectedSites(plugin.mappings?.map((m) => m.siteId) || []);
        setRemoteSlug(plugin.mappings?.[0]?.remoteSlug || plugin.name.toLowerCase().replace(/\s+/g, '-'));
      }
    } catch {
      // Fallback to local data on error
      setSelectedSites(plugin.mappings?.map((m) => m.siteId) || []);
      setRemoteSlug(plugin.mappings?.[0]?.remoteSlug || plugin.name.toLowerCase().replace(/\s+/g, '-'));
    }
  };

  const handleSaveMappings = async () => {
    if (!selectedPlugin) return;

    setIsSubmitting(true);
    try {
      const response = await api.updatePluginMappings(selectedPlugin.id, {
        siteIds: selectedSites,
        remoteSlug: remoteSlug,
      });
      if (response.success) {
        toast.success("Site mappings saved!", {
          description: `${selectedSites.length} site(s) linked to ${selectedPlugin.name}`,
          style: {
            background: "linear-gradient(135deg, hsl(142 76% 36%) 0%, hsl(142 76% 30%) 100%)",
            color: "white",
            border: "none",
          },
        });
        // Invalidate AND refetch both plugins and sites to ensure bidirectional sync
        await queryClient.invalidateQueries({ queryKey: ["plugins"] });
        await queryClient.invalidateQueries({ queryKey: ["sites"] });
        // Also invalidate the specific site mappings that may have changed
        for (const siteId of selectedSites) {
          queryClient.invalidateQueries({ queryKey: ["sites", siteId, "mappings"] });
        }
        setShowMappingDialog(false);
      } else if (response.error) {
        const captured = captureError(response.error, {
          endpoint: `/plugins/${selectedPlugin.id}/mappings`,
          method: "PUT",
        });
        openErrorModal(captured);
      }
    } catch (error) {
      toast.error("Failed to update mappings");
    } finally {
      setIsSubmitting(false);
    }
  };

  const toggleSiteSelection = (siteId: number) => {
    setSelectedSites((prev) =>
      prev.includes(siteId)
        ? prev.filter((id) => id !== siteId)
        : [...prev, siteId]
    );
  };

  if (pluginsLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold">Plugins</h1>
          <p className="text-muted-foreground">
            Register local plugin directories for syncing to WordPress sites
          </p>
        </div>
        <div className="flex gap-2">
          {plugins && plugins.length > 0 && (
            <>
              <Button 
                variant="outline" 
                onClick={handleRefreshAll}
                disabled={isScanningAll}
              >
                {isScanningAll ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <RefreshCw className="h-4 w-4 mr-2" />
                )}
                Refresh All
              </Button>
              <Button variant="outline" onClick={handleGitPullAll}>
                <GitBranch className="h-4 w-4 mr-2" />
                Git Pull All
              </Button>
            </>
          )}
          <Button onClick={() => setShowAddDialog(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Register Plugin
          </Button>
        </div>
      </div>

       {/* Scan Directory Panel */}
       <ScanDirectoryPanel
         existingPlugins={plugins || []}
         onPluginAdded={() => queryClient.invalidateQueries({ queryKey: ["plugins"] })}
       />
 
      {/* Category Filter */}
      {plugins && plugins.length > 0 && (
        <CategoryFilter
          selectedCategories={selectedCategories}
          onCategoryToggle={handleCategoryToggle}
          onClearAll={() => setSelectedCategories([])}
        />
      )}

      {/* Bulk Actions Bar */}
      <BulkActionsBar
        selectedCount={selectedPluginIds.size}
        totalCount={filteredPlugins?.length || 0}
        onSelectAll={selectAllPlugins}
        onClearSelection={clearSelection}
        onEnableWatch={handleBulkEnableWatch}
        onDisableWatch={handleBulkDisableWatch}
        onSyncAll={handleBulkSync}
        onGitPullAll={handleBulkGitPull}
        onDeleteAll={() => setShowBulkDeleteConfirm(true)}
        onDeployAll={handleBulkDeploy}
        onScanDirectories={handleBulkScanDirectories}
        isProcessing={isBulkProcessing}
        isDeploying={isBulkDeploying}
        isScanning={isBulkScanning}
      />

      {/* Plugin List */}
      {filteredPlugins?.length === 0 && plugins?.length !== 0 ? (
        <EmptyState
          icon={Package}
          title="No plugins match filter"
          description="Try selecting different categories or clear the filter."
          action={{ label: "Clear Filter", onClick: () => setSelectedCategories([]) }}
        />
      ) : filteredPlugins?.length === 0 ? (
        <EmptyState
          icon={Package}
          title="No plugins registered"
          description="Register a local plugin directory to start syncing with WordPress sites."
          action={{
            label: "Register Plugin",
            onClick: () => setShowAddDialog(true),
          }}
        />
      ) : (
        <div className="space-y-4">
          {filteredPlugins?.map((plugin) => (
            <Card 
              key={plugin.id} 
              className={cn(
                "overflow-hidden transition-colors",
                selectedPluginIds.has(plugin.id) && "border-primary bg-primary/5"
              )}
            >
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-3 min-w-0 flex-1">
                    {/* Selection Checkbox */}
                    <button
                      onClick={() => togglePluginSelection(plugin.id)}
                      className="p-2 rounded-lg hover:bg-muted flex-shrink-0 transition-colors"
                    >
                      {selectedPluginIds.has(plugin.id) ? (
                        <CheckSquare className="h-5 w-5 text-primary" />
                      ) : (
                        <Square className="h-5 w-5 text-muted-foreground" />
                      )}
                    </button>
                    <div className="p-2 rounded-lg bg-primary/10 flex-shrink-0">
                      <Package className="h-5 w-5 text-primary" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <CardTitle className="text-base flex items-center gap-2 flex-wrap">
                        <span className="truncate max-w-[200px]">{plugin.name}</span>
                        <CategoryBadge category={plugin.category} size="sm" />
                        {plugin.gitEnabled && (
                          <Badge variant="secondary" className="text-xs">
                            <GitBranch className="h-3 w-3 mr-1" />
                            Git
                          </Badge>
                        )}
                      </CardTitle>
                      <p 
                        className="text-sm text-muted-foreground font-mono break-all line-clamp-2 cursor-pointer hover:text-foreground transition-colors"
                        onClick={(e) => {
                          e.stopPropagation();
                          navigator.clipboard.writeText(plugin.path);
                          toast.success("Path copied to clipboard");
                        }}
                        title="Click to copy path"
                      >
                        {plugin.path}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-1 flex-shrink-0">
                    {/* Sync button - always visible, disabled if no mappings */}
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleSyncPlugin(plugin)}
                      disabled={!plugin.mappings?.length || isSyncing === plugin.id}
                      title={plugin.mappings?.length ? "Check sync status with sites" : "No sites linked – click Sites to add"}
                      className="h-8"
                    >
                      {isSyncing === plugin.id ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                      ) : (
                        <CloudUpload className="h-4 w-4" />
                      )}
                      <span className="ml-1 hidden sm:inline">Sync</span>
                    </Button>

                    {/* Quick Publish Indicator - shows when publish is in progress */}
                    <QuickPublishIndicator
                      pluginId={plugin.id}
                      pluginName={plugin.name}
                    />

                    {/* Quick Publish All button - publishes to all mapped sites */}
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => quickPublishAll(plugin)}
                          disabled={!plugin.mappings?.length || hasActiveOperation(plugin.id)}
                          title={plugin.mappings?.length 
                            ? `Quick publish to ${plugin.mappings.length} site(s)` 
                            : "No sites mapped"
                          }
                          className={cn(
                            "h-8 text-amber-600 hover:text-amber-700 hover:bg-amber-500/10",
                            !plugin.mappings?.length && "text-muted-foreground"
                          )}
                        >
                          {hasActiveOperation(plugin.id) ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <Zap className="h-4 w-4" />
                          )}
                          <span className="ml-1 hidden sm:inline">Quick</span>
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        {plugin.mappings?.length 
                          ? `Publish to all ${plugin.mappings.length} mapped sites at once`
                          : "Add sites first to enable quick publish"
                        }
                      </TooltipContent>
                    </Tooltip>

                    {/* Publish button - opens dialog for site selection */}
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => openPublishDialog(plugin)}
                      disabled={isPublishing === plugin.id}
                      title={plugin.mappings?.length ? "Publish to WordPress sites" : "Click to see how to add sites"}
                      className={cn(
                        "h-8",
                        plugin.mappings?.length ? "text-primary hover:text-primary" : "text-muted-foreground"
                      )}
                    >
                      {isPublishing === plugin.id ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                      ) : (
                        <Upload className="h-4 w-4" />
                      )}
                      <span className="ml-1 hidden sm:inline">Publish</span>
                    </Button>

                    {/* Grouped secondary actions dropdown (Scan, Pull, Backup) */}
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="h-8 px-2"
                          title="More actions (Scan, Pull, Backup)"
                        >
                          {isScanning === plugin.id || isPulling === plugin.id ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <MoreHorizontal className="h-4 w-4" />
                          )}
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-44">
                        <DropdownMenuItem
                          onClick={() => handleRefreshScan(plugin.id)}
                          disabled={isScanning === plugin.id}
                          className="gap-2"
                        >
                          {isScanning === plugin.id ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <RefreshCw className="h-4 w-4" />
                          )}
                          Scan
                        </DropdownMenuItem>
                        {plugin.gitEnabled && (
                          <DropdownMenuItem
                            onClick={() => handleGitPull(plugin.id)}
                            disabled={isPulling === plugin.id}
                            className="gap-2"
                          >
                            {isPulling === plugin.id ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <GitBranch className="h-4 w-4" />
                            )}
                            Pull
                          </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                          onClick={() => {
                            setBackupPlugin(plugin);
                            setShowBackupProgress(true);
                          }}
                          className="gap-2"
                        >
                          <Archive className="h-4 w-4" />
                          Backup
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>

                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => openMappingDialog(plugin)}
                      className="h-8"
                    >
                      <Link2 className="h-4 w-4" />
                      <span className="ml-1 hidden sm:inline">Sites</span>
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-8 text-destructive hover:text-destructive"
                      onClick={() => handleDeletePlugin(plugin.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </CardHeader>

              <CardContent className="pt-0">
                {/* Mapped Sites */}
                {plugin.mappings && plugin.mappings.length > 0 ? (
                  <div className="flex flex-wrap gap-2 mb-3">
                    {plugin.mappings.map((mapping) => (
                      <Badge
                        key={mapping.id}
                        variant="outline"
                        className="flex items-center gap-1"
                      >
                        <Globe className="h-3 w-3" />
                        {mapping.siteName}
                      </Badge>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground mb-3 italic">
                    No sites mapped – click "Sites" to add mappings
                  </p>
                )}

                {/* Stats */}
                <div className="flex items-center gap-4 pt-3 border-t text-sm">
                  <span className="flex items-center gap-1.5">
                    {plugin.watchEnabled ? (
                      <Eye className="h-4 w-4 text-primary" />
                    ) : (
                      <EyeOff className="h-4 w-4 text-muted-foreground" />
                    )}
                    Watching: {plugin.watchEnabled ? "ON" : "OFF"}
                  </span>

                  <span className="flex items-center gap-1.5 text-muted-foreground">
                    <FileText className="h-4 w-4" />
                    {plugin.fileCount} files
                  </span>

                  {plugin.modifiedCount > 0 && (
                    <span className="flex items-center gap-1.5 text-warning">
                      <AlertCircle className="h-4 w-4" />
                      {plugin.modifiedCount} modified
                    </span>
                  )}
                </div>

                {/* Git Actions Panel */}
                {plugin.gitEnabled && (
                  <div className="mt-3 pt-3 border-t">
                    <GitActionsPanel plugin={plugin} />
                  </div>
                )}

                {/* Version History Panel */}
                <div className="mt-3 pt-3 border-t">
                  <VersionHistoryPanel plugin={plugin} />
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Envelope Pagination */}
      <EnvelopePagination
        meta={envelopeMeta ? { attributes: envelopeMeta.attributes, navigation: envelopeMeta.navigation } : null}
        onPageChange={setCurrentPage}
      />

      {/* Add Plugin Dialog */}
      <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Register Plugin</DialogTitle>
            <DialogDescription>
              Add a local plugin directory to sync with WordPress sites.
            </DialogDescription>
          </DialogHeader>

          <Tabs value={addMethod} onValueChange={(v) => setAddMethod(v as "path" | "browse")}>
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="path">Enter Path</TabsTrigger>
              <TabsTrigger value="browse">Browse Folder</TabsTrigger>
            </TabsList>

            <TabsContent value="path" className="space-y-4 pt-4">
              <div className="space-y-2">
                <Label htmlFor="name">Plugin Name</Label>
                <Input
                  id="name"
                  placeholder="My Custom Plugin"
                  value={formData.name}
                  onChange={(e) => handleInputChange("name", e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="path">Local Path</Label>
                <Input
                  id="path"
                  placeholder="C:\Projects\my-plugin or /home/user/plugins/my-plugin"
                  value={formData.path}
                  onChange={(e) => handleInputChange("path", e.target.value)}
                  className="font-mono text-sm"
                />
                <p className="text-xs text-muted-foreground">
                  Full path to your plugin directory
                </p>
              </div>
              <div className="space-y-2">
                <Label>Category</Label>
                <CategorySelect
                  value={formData.category || null}
                  onValueChange={(val) => handleInputChange("category", val || "")}
                  placeholder="Select category..."
                />
              </div>
            </TabsContent>

            <TabsContent value="browse" className="pt-4">
              <div className="border-2 border-dashed rounded-lg p-8 text-center">
                <FolderOpen className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p className="text-muted-foreground mb-4">
                  Click to select a folder from your system
                </p>
                <Button variant="outline" disabled>
                  <FolderOpen className="h-4 w-4 mr-2" />
                  Browse Folder
                </Button>
                <p className="text-xs text-muted-foreground mt-2">
                  Requires backend folder picker API (coming soon)
                </p>
              </div>
            </TabsContent>
          </Tabs>

          {/* Git Settings */}
          <div className="space-y-4 pt-4 border-t">
            <div className="flex items-center gap-2">
              <Checkbox
                id="git-enabled"
                checked={formData.gitEnabled}
                onCheckedChange={(checked) => handleInputChange("gitEnabled", !!checked)}
              />
              <Label htmlFor="git-enabled" className="cursor-pointer">
                Enable Git integration
              </Label>
            </div>

            {formData.gitEnabled && (
              <>
                <div className="space-y-2">
                  <Label htmlFor="git-url">Git Remote URL (optional)</Label>
                  <Input
                    id="git-url"
                    placeholder="https://github.com/user/plugin.git"
                    value={formData.gitRemoteUrl}
                    onChange={(e) => handleInputChange("gitRemoteUrl", e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="build-cmd">Build Command (optional)</Label>
                  <Input
                    id="build-cmd"
                    placeholder="npm run build"
                    value={formData.buildCommand}
                    onChange={(e) => handleInputChange("buildCommand", e.target.value)}
                    className="font-mono"
                  />
                  <p className="text-xs text-muted-foreground">
                    Command to run after git pull (e.g., npm run build, composer install)
                  </p>
                </div>
              </>
            )}
          </div>

          {/* Validation Error Banner */}
          {validationError && (
            <div className="rounded-lg border border-warning bg-warning/10 p-3">
              <div className="flex items-start gap-2">
                <AlertCircle className="h-4 w-4 text-warning mt-0.5 flex-shrink-0" />
                <div className="text-sm">
                  <p className="font-medium text-warning">Validation Failed</p>
                  <p className="text-muted-foreground">{validationError}</p>
                </div>
              </div>
            </div>
          )}

          <DialogFooter className="pt-4">
            <Button variant="outline" onClick={() => {
              setShowAddDialog(false);
              setValidationError(null);
            }}>
              Cancel
            </Button>
            {validationError ? (
              <Button 
                variant="warning" 
                onClick={handleSaveAnyway} 
                disabled={isSubmitting}
              >
                {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                Save Anyway
              </Button>
            ) : (
              <Button onClick={() => handleAddPlugin(false)} disabled={isSubmitting}>
                {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                Register Plugin
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Site Mapping Dialog */}
      <Dialog open={showMappingDialog} onOpenChange={setShowMappingDialog}>
        <DialogContent className="sm:max-w-md max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Link to Sites</DialogTitle>
            <DialogDescription>
              Select which WordPress sites should receive "{selectedPlugin?.name}".
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4">
            {/* Remote Slug */}
            <div className="space-y-2">
              <Label htmlFor="remote-slug">Plugin Folder Name (on remote sites)</Label>
              <Input
                id="remote-slug"
                placeholder="my-plugin"
                value={remoteSlug}
                onChange={(e) => setRemoteSlug(e.target.value)}
                className="font-mono text-sm"
              />
              <p className="text-xs text-muted-foreground">
                The folder name in wp-content/plugins/ on the target sites
              </p>
            </div>

            {/* Site Selection */}
            {sites && sites.length > 0 ? (
              <div className="space-y-2">
                <Label>Target Sites</Label>
                <div className="space-y-2 max-h-60 overflow-y-auto">
                  {sites.map((site) => (
                    <div
                      key={site.id}
                      className={cn(
                        "flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors",
                        selectedSites.includes(site.id)
                          ? "border-primary bg-primary/5"
                          : "border-border hover:bg-muted/50"
                      )}
                      onClick={() => toggleSiteSelection(site.id)}
                    >
                      <Checkbox
                        checked={selectedSites.includes(site.id)}
                        onCheckedChange={() => toggleSiteSelection(site.id)}
                        onClick={(e) => e.stopPropagation()}
                      />
                      <Globe className="h-4 w-4 text-muted-foreground" />
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm">{site.name}</p>
                        <p className="text-xs text-muted-foreground truncate">
                          {site.url}
                        </p>
                      </div>
                      <span
                        className={cn(
                          "w-2 h-2 rounded-full flex-shrink-0",
                          site.connectionStatus === "connected"
                            ? "bg-primary"
                            : site.connectionStatus === "disconnected"
                            ? "bg-destructive"
                            : "bg-muted-foreground"
                        )}
                      />
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <div className="text-center py-8 text-muted-foreground">
                <Globe className="h-8 w-8 mx-auto mb-2 opacity-50" />
                <p>No sites available</p>
                <p className="text-sm">Add a WordPress site first</p>
              </div>
            )}

            {selectedSites.length > 0 && (
              <p className="text-sm text-muted-foreground">
                {selectedSites.length} site{selectedSites.length !== 1 ? "s" : ""} selected
              </p>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowMappingDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleSaveMappings} disabled={isSubmitting || !remoteSlug}>
              {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Save Mappings
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Publish Dialog */}
      <Dialog open={showPublishDialog} onOpenChange={setShowPublishDialog}>
        <DialogContent className="sm:max-w-lg overflow-hidden">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Upload className="h-5 w-5 text-primary" />
              Publish Plugin
            </DialogTitle>
            <DialogDescription>
              Deploy <strong>{publishPlugin?.name}</strong> to a WordPress site.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4 overflow-x-hidden">
            <p className="text-sm text-muted-foreground">
              Select a site to publish this plugin to:
            </p>
            
            {publishPlugin?.mappings && publishPlugin.mappings.length > 0 ? (
              <div className="space-y-2 max-h-[340px] overflow-y-auto pr-1">
                {publishPlugin.mappings.map((mapping) => (
                  <div
                    key={mapping.id}
                    className="flex items-center gap-3 p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                  >
                    <Globe className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                    <div className="flex-1 min-w-0">
                      <p className="font-medium truncate">{mapping.siteName}</p>
                      <SiteVersionBadge 
                        pluginId={publishPlugin.id} 
                        siteId={mapping.siteId}
                        className="mt-1.5"
                      />
                      <p className="text-xs text-muted-foreground truncate mt-1">{mapping.siteUrl}</p>
                    </div>
                    <div className="flex gap-1 flex-shrink-0">
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                              setDiffPreviewSiteId(mapping.siteId);
                              setShowDiffPreview(true);
                            }}
                            disabled={isPublishing !== null}
                          >
                            <Files className="h-4 w-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>Preview files to deploy</TooltipContent>
                      </Tooltip>
                      <Button
                        size="sm"
                        onClick={() => handlePublish(publishPlugin, mapping.siteId)}
                        disabled={isPublishing !== null}
                      >
                        {isPublishing === publishPlugin.id ? (
                          <Loader2 className="h-4 w-4 animate-spin mr-1" />
                        ) : (
                          <Upload className="h-4 w-4 mr-1" />
                        )}
                        Publish
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-6">
                <Globe className="h-8 w-8 mx-auto mb-2 text-muted-foreground opacity-50" />
                <p className="text-muted-foreground font-medium">No sites linked to this plugin</p>
                <p className="text-sm text-muted-foreground mt-1">
                  Click the "Sites" button on the plugin card to link WordPress sites first.
                </p>
                <Button
                  variant="outline"
                  className="mt-4"
                  onClick={() => {
                    setShowPublishDialog(false);
                    if (publishPlugin) openMappingDialog(publishPlugin);
                  }}
                >
                  <Link2 className="h-4 w-4 mr-2" />
                  Link Sites Now
                </Button>
              </div>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowPublishDialog(false)}>
              Cancel
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Publish Progress Dialog */}
      <PublishProgressDialog
        open={showPublishProgress}
        onOpenChange={setShowPublishProgress}
        pluginName={publishPlugin?.name || ""}
        siteName={publishPlugin?.mappings?.find(m => m.siteId === publishSiteId)?.siteName || ""}
        pluginId={publishPlugin?.id || 0}
        siteId={publishSiteId || 0}
        onComplete={handlePublishComplete}
      />

      {/* Sync Progress Dialog */}
      <SyncProgressDialog
        open={showSyncProgress}
        onOpenChange={setShowSyncProgress}
        pluginName={syncPlugin?.name || ""}
        siteName={syncPlugin?.mappings?.find(m => m.siteId === syncSiteId)?.siteName || ""}
        pluginId={syncPlugin?.id || 0}
        siteId={syncSiteId || 0}
        onComplete={(success) => {
          if (success) {
            queryClient.invalidateQueries({ queryKey: ["plugins"] });
          }
        }}
      />

      {/* Backup Progress Dialog */}
      <BackupProgressDialog
        open={showBackupProgress}
        onOpenChange={setShowBackupProgress}
        operation="create"
        pluginName={backupPlugin?.name}
        mappingId={backupPlugin?.id}
        onComplete={(success) => {
          if (success) {
            toast.success("Backup created successfully");
          }
        }}
      />

      {/* Diff Preview Dialog */}
      <DiffPreviewDialog
        open={showDiffPreview}
        onOpenChange={setShowDiffPreview}
        pluginId={publishPlugin?.id || 0}
        pluginName={publishPlugin?.name || ""}
        siteId={diffPreviewSiteId || 0}
        siteName={publishPlugin?.mappings?.find(m => m.siteId === diffPreviewSiteId)?.siteName || ""}
        onConfirm={(selectedFiles) => {
          if (publishPlugin && diffPreviewSiteId) {
            handlePublish(publishPlugin, diffPreviewSiteId, selectedFiles);
          }
        }}
      />

      {/* Bulk Delete Confirmation Dialog */}
      <AlertDialog open={showBulkDeleteConfirm} onOpenChange={setShowBulkDeleteConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {selectedPluginIds.size} plugins?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. This will permanently delete the selected plugins
              and remove all their site mappings.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleBulkDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {isBulkProcessing ? (
                <Loader2 className="h-4 w-4 animate-spin mr-2" />
              ) : (
                <Trash2 className="h-4 w-4 mr-2" />
              )}
              Delete All
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
