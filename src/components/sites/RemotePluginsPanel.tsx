import { useState } from "react";
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
import {
  Loader2,
  RefreshCw,
  Trash2,
  Search,
  Package,
  ExternalLink,
  AlertCircle,
} from "lucide-react";
import { api, Site, RemotePlugin, requireSuccess } from "@/lib/api";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

interface RemotePluginsPanelProps {
  site: Site;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function RemotePluginsPanel({ site, open, onOpenChange }: RemotePluginsPanelProps) {
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const [searchQuery, setSearchQuery] = useState("");
  const [pluginToDelete, setPluginToDelete] = useState<RemotePlugin | null>(null);
  const [togglingPlugins, setTogglingPlugins] = useState<Set<string>>(new Set());

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
      const response = await api.deleteRemotePlugin(site.id, plugin.plugin);
      return requireSuccess(response, { endpoint: `/sites/${site.id}/remote-plugins/${plugin.plugin}`, method: "DELETE" });
    },
    onSuccess: (_, plugin) => {
      toast.success(`${plugin.name} deleted`);
      queryClient.invalidateQueries({ queryKey });
      setPluginToDelete(null);
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

  const filteredPlugins = plugins?.filter((plugin) => {
    if (!searchQuery) return true;
    const query = searchQuery.toLowerCase();
    return (
      plugin.name.toLowerCase().includes(query) ||
      plugin.slug.toLowerCase().includes(query) ||
      plugin.description.toLowerCase().includes(query)
    );
  });

  const handleToggle = (plugin: RemotePlugin, enable: boolean) => {
    toggleMutation.mutate({ plugin, enable });
  };

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Package className="h-5 w-5 text-primary" />
              Plugins on {site.name}
            </DialogTitle>
            <DialogDescription>
              View and manage plugins installed on this WordPress site.
            </DialogDescription>
          </DialogHeader>

          <div className="flex items-center gap-2 mt-2">
            <div className="relative flex-1">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search plugins..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-8"
              />
            </div>
            <Button variant="outline" size="icon" onClick={() => refetch()} disabled={isLoading}>
              <RefreshCw className={`h-4 w-4 ${isLoading ? "animate-spin" : ""}`} />
            </Button>
          </div>

          {isLoading ? (
            <div className="flex-1 flex items-center justify-center py-8">
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
          ) : isError ? (
            <div className="flex-1 flex flex-col items-center justify-center py-8 text-muted-foreground">
              <AlertCircle className="h-8 w-8 mb-2" />
              <p>Failed to load plugins</p>
              <Button variant="link" onClick={() => refetch()} className="mt-2">
                Try again
              </Button>
            </div>
          ) : !filteredPlugins?.length ? (
            <div className="flex-1 flex flex-col items-center justify-center py-8 text-muted-foreground">
              <Package className="h-8 w-8 mb-2" />
              <p>{searchQuery ? "No plugins match your search" : "No plugins installed"}</p>
            </div>
          ) : (
            <ScrollArea className="flex-1 -mx-6 px-6">
              <div className="space-y-2 pb-2">
                {filteredPlugins.map((plugin) => (
                  <div
                    key={plugin.plugin}
                    className="flex items-center gap-3 p-3 rounded-lg border bg-card hover:bg-accent/50 transition-colors"
                  >
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-medium truncate">{plugin.name}</span>
                        <Badge variant="secondary" className="text-xs shrink-0">
                          v{plugin.version}
                        </Badge>
                        <Badge
                          variant={plugin.status === "active" ? "default" : "outline"}
                          className="text-xs shrink-0"
                        >
                          {plugin.status}
                        </Badge>
                      </div>
                      <p className="text-xs text-muted-foreground truncate">
                        {plugin.description || `by ${plugin.author}`}
                      </p>
                      <p className="text-xs text-muted-foreground mt-0.5">
                        {plugin.slug}
                      </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                      {plugin.pluginUri && (
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-8 w-8"
                          asChild
                        >
                          <a href={plugin.pluginUri} target="_blank" rel="noopener noreferrer">
                            <ExternalLink className="h-4 w-4" />
                          </a>
                        </Button>
                      )}

                      <div className="flex items-center gap-2">
                        {togglingPlugins.has(plugin.plugin) ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Switch
                            checked={plugin.status === "active"}
                            onCheckedChange={(checked) => handleToggle(plugin, checked)}
                          />
                        )}
                      </div>

                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                        onClick={() => setPluginToDelete(plugin)}
                        disabled={deleteMutation.isPending}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </ScrollArea>
          )}

          <div className="flex items-center justify-between pt-4 border-t text-sm text-muted-foreground">
            <span>
              {filteredPlugins?.length ?? 0} plugin{(filteredPlugins?.length ?? 0) !== 1 ? "s" : ""}
              {searchQuery && plugins?.length !== filteredPlugins?.length && ` (of ${plugins?.length})`}
            </span>
            <a
              href={`${site.url}/wp-admin/plugins.php`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1 hover:text-primary"
            >
              Open in WordPress
              <ExternalLink className="h-3 w-3" />
            </a>
          </div>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!pluginToDelete} onOpenChange={() => setPluginToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {pluginToDelete?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently remove the plugin from {site.name}. This action cannot be undone.
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
