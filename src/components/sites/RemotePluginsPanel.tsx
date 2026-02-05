import { useState, useMemo } from "react";
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
} from "lucide-react";
import { api, Site, RemotePlugin, requireSuccess } from "@/lib/api";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

interface RemotePluginsPanelProps {
  site: Site;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const ITEMS_PER_PAGE = 10;

export function RemotePluginsPanel({ site, open, onOpenChange }: RemotePluginsPanelProps) {
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const [searchQuery, setSearchQuery] = useState("");
  const [pluginToDelete, setPluginToDelete] = useState<RemotePlugin | null>(null);
  const [togglingPlugins, setTogglingPlugins] = useState<Set<string>>(new Set());
  const [selectedPlugins, setSelectedPlugins] = useState<Set<string>>(new Set());
  const [currentPage, setCurrentPage] = useState(1);
  const [bulkActionPending, setBulkActionPending] = useState(false);

  const queryKey = ["sites", site.id, "remote-plugins"];

  const { data: plugins, isLoading, isError, refetch } = useQuery({
    queryKey,
    queryFn: async () => {
      const response = await api.getRemotePlugins(site.id);
      return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins`, method: "GET" });
    },
    enabled: open,
  });

  const toggleMutation = useMutation({
    mutationFn: async ({ plugin, enable }: { plugin: RemotePlugin; enable: boolean }) => {
      if (enable) {
        const response = await api.enableRemotePlugin(site.id, plugin.plugin);
        return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}/enable`, method: "POST" });
      } else {
        const response = await api.disableRemotePlugin(site.id, plugin.plugin);
        return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}/disable`, method: "POST" });
      }
    },
    onMutate: ({ plugin }) => {
      setTogglingPlugins((prev) => new Set(prev).add(plugin.plugin));
    },
    onSuccess: (_, { plugin, enable }) => {
      toast.success(`${plugin.name} ${enable ? "activated" : "deactivated"}`);
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (error, { plugin, enable }) => {
      const captured = captureException(error, {
        endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}/${enable ? "enable" : "disable"}`,
        method: "POST",
      });
      toast.error(`Failed to ${enable ? "activate" : "deactivate"} ${plugin.name}`, {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    },
    onSettled: (_, __, { plugin }) => {
      setTogglingPlugins((prev) => {
        const next = new Set(prev);
        next.delete(plugin.plugin);
        return next;
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (plugin: RemotePlugin) => {
      // If plugin is active, deactivate first, then delete
      if (plugin.status === "active") {
        const disableResponse = await api.disableRemotePlugin(site.id, plugin.plugin);
        requireSuccess(disableResponse, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}/disable`, method: "POST" });
      }
      const response = await api.deleteRemotePlugin(site.id, plugin.plugin);
      return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}`, method: "DELETE" });
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
        endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}`,
        method: "DELETE",
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

  // Reset to page 1 when search changes
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
  const handleBulkActivate = async () => {
    if (selectedPlugins.size === 0) return;
    setBulkActionPending(true);
    const selectedList = filteredPlugins.filter((p) => selectedPlugins.has(p.plugin) && p.status !== "active");
    let successCount = 0;
    for (const plugin of selectedList) {
      try {
        const response = await api.enableRemotePlugin(site.id, plugin.plugin);
        requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}/enable`, method: "POST" });
        successCount++;
      } catch (error) {
        console.error(`Failed to activate ${plugin.name}`, error);
      }
    }
    toast.success(`Activated ${successCount} plugin${successCount !== 1 ? "s" : ""}`);
    queryClient.invalidateQueries({ queryKey });
    setBulkActionPending(false);
    setSelectedPlugins(new Set());
  };

  const handleBulkDeactivate = async () => {
    if (selectedPlugins.size === 0) return;
    setBulkActionPending(true);
    const selectedList = filteredPlugins.filter((p) => selectedPlugins.has(p.plugin) && p.status === "active");
    let successCount = 0;
    for (const plugin of selectedList) {
      try {
        const response = await api.disableRemotePlugin(site.id, plugin.plugin);
        requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}/disable`, method: "POST" });
        successCount++;
      } catch (error) {
        console.error(`Failed to deactivate ${plugin.name}`, error);
      }
    }
    toast.success(`Deactivated ${successCount} plugin${successCount !== 1 ? "s" : ""}`);
    queryClient.invalidateQueries({ queryKey });
    setBulkActionPending(false);
    setSelectedPlugins(new Set());
  };

  const handleBulkDelete = async () => {
    if (selectedPlugins.size === 0) return;
    setBulkActionPending(true);
    const selectedList = filteredPlugins.filter((p) => selectedPlugins.has(p.plugin));
    let successCount = 0;
    for (const plugin of selectedList) {
      try {
        // Deactivate first if active
        if (plugin.status === "active") {
          const disableResponse = await api.disableRemotePlugin(site.id, plugin.plugin);
          requireSuccess(disableResponse, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}/disable`, method: "POST" });
        }
        const response = await api.deleteRemotePlugin(site.id, plugin.plugin);
        requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}`, method: "DELETE" });
        successCount++;
      } catch (error) {
        console.error(`Failed to delete ${plugin.name}`, error);
      }
    }
    toast.success(`Deleted ${successCount} plugin${successCount !== 1 ? "s" : ""}`);
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
        <DialogContent className="max-w-4xl max-h-[90vh] flex flex-col bg-background/95 backdrop-blur-sm border-border/50">
          <DialogHeader className="pb-2">
            <DialogTitle className="flex items-center gap-2 text-xl">
              <Package className="h-6 w-6 text-primary" />
              Plugins on {site.name}
            </DialogTitle>
            <DialogDescription>
              View and manage plugins installed on this WordPress site.
            </DialogDescription>
          </DialogHeader>

          {/* Search and Actions Bar */}
          <div className="flex items-center gap-2">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search plugins by name, slug, or author..."
                value={searchQuery}
                onChange={(e) => handleSearchChange(e.target.value)}
                className="pl-10 bg-muted/50 border-border/50 focus-visible:ring-primary/50"
              />
            </div>
            <Button variant="outline" size="icon" onClick={() => refetch()} disabled={isLoading} className="shrink-0">
              <RefreshCw className={`h-4 w-4 ${isLoading ? "animate-spin" : ""}`} />
            </Button>
          </div>

          {/* Bulk Actions Bar - visible when plugins selected */}
          {selectedPlugins.size > 0 && (
            <div className="flex items-center justify-between gap-2 p-3 rounded-lg bg-primary/10 border border-primary/20">
              <span className="text-sm font-medium">
                {selectedPlugins.size} plugin{selectedPlugins.size !== 1 ? "s" : ""} selected
              </span>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleBulkActivate}
                  disabled={bulkActionPending}
                  className="gap-1"
                >
                  <Power className="h-3.5 w-3.5" />
                  Activate
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleBulkDeactivate}
                  disabled={bulkActionPending}
                  className="gap-1"
                >
                  <PowerOff className="h-3.5 w-3.5" />
                  Deactivate
                </Button>
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={handleBulkDelete}
                  disabled={bulkActionPending}
                  className="gap-1"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  Delete
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={deselectAll}
                  disabled={bulkActionPending}
                >
                  Clear
                </Button>
              </div>
            </div>
          )}

          {/* Select All / Deselect All */}
          {!isLoading && !isError && filteredPlugins.length > 0 && (
            <div className="flex items-center justify-between text-sm">
              <div className="flex items-center gap-3">
                <Button variant="ghost" size="sm" onClick={selectAllVisible} className="h-7 px-2 text-xs">
                  Select page ({paginatedPlugins.length})
                </Button>
                {selectedPlugins.size > 0 && (
                  <Button variant="ghost" size="sm" onClick={deselectAll} className="h-7 px-2 text-xs text-muted-foreground">
                    Deselect all
                  </Button>
                )}
              </div>
              {totalPages > 1 && (
                <span className="text-muted-foreground text-xs">
                  Page {currentPage} of {totalPages}
                </span>
              )}
            </div>
          )}

          {/* Plugin List */}
          {isLoading ? (
            <div className="flex-1 flex items-center justify-center py-12">
              <div className="flex flex-col items-center gap-3">
                <Loader2 className="h-10 w-10 animate-spin text-primary" />
                <span className="text-sm text-muted-foreground">Loading plugins...</span>
              </div>
            </div>
          ) : isError ? (
            <div className="flex-1 flex flex-col items-center justify-center py-12 text-muted-foreground">
              <AlertCircle className="h-10 w-10 mb-3 text-destructive" />
              <p className="font-medium">Failed to load plugins</p>
              <Button variant="link" onClick={() => refetch()} className="mt-2">
                Try again
              </Button>
            </div>
          ) : !filteredPlugins.length ? (
            <div className="flex-1 flex flex-col items-center justify-center py-12 text-muted-foreground">
              <Package className="h-10 w-10 mb-3" />
              <p className="font-medium">{searchQuery ? "No plugins match your search" : "No plugins installed"}</p>
            </div>
          ) : (
            <ScrollArea className="flex-1 -mx-6 px-6" style={{ minHeight: "300px", maxHeight: "calc(90vh - 320px)" }}>
              <div className="space-y-2 pb-2">
                {paginatedPlugins.map((plugin) => {
                  const isSelected = selectedPlugins.has(plugin.plugin);
                  const isToggling = togglingPlugins.has(plugin.plugin);
                  
                  return (
                    <div
                      key={plugin.plugin}
                      className={`
                        group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200
                        ${isSelected 
                          ? "bg-primary/10 border-primary/30 shadow-sm" 
                          : "bg-card/50 border-border/50 hover:bg-accent/80 hover:border-accent hover:shadow-md"
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
                            variant={plugin.status === "active" ? "default" : "outline"}
                            className={`text-xs shrink-0 ${
                              plugin.status === "active" 
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
                          {isToggling ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <Switch
                              checked={plugin.status === "active"}
                              onCheckedChange={(checked) => handleToggle(plugin, checked)}
                            />
                          )}
                        </div>

                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8">
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem 
                              onClick={() => handleToggle(plugin, plugin.status !== "active")}
                              disabled={isToggling}
                            >
                              {plugin.status === "active" ? (
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

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex items-center justify-center gap-2 pt-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                disabled={currentPage === 1}
              >
                Previous
              </Button>
              <div className="flex items-center gap-1">
                {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                  let pageNum: number;
                  if (totalPages <= 5) {
                    pageNum = i + 1;
                  } else if (currentPage <= 3) {
                    pageNum = i + 1;
                  } else if (currentPage >= totalPages - 2) {
                    pageNum = totalPages - 4 + i;
                  } else {
                    pageNum = currentPage - 2 + i;
                  }
                  return (
                    <Button
                      key={pageNum}
                      variant={currentPage === pageNum ? "default" : "outline"}
                      size="sm"
                      className="w-8 h-8 p-0"
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
              >
                Next
              </Button>
            </div>
          )}

          {/* Footer */}
          <div className="flex items-center justify-between pt-3 border-t border-border/50 text-sm text-muted-foreground">
            <span>
              {filteredPlugins.length} plugin{filteredPlugins.length !== 1 ? "s" : ""}
              {searchQuery && plugins?.length !== filteredPlugins.length && ` (of ${plugins?.length} total)`}
            </span>
            <a
              href={`${site.url}/wp-admin/plugins.php`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1 hover:text-primary transition-colors"
            >
              Open in WordPress
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
              {pluginToDelete?.status === "active" && " The plugin will be deactivated first."}
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
    </>
  );
}
