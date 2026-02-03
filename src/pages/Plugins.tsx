import { useState } from "react";
import { usePlugins } from "@/hooks/usePlugins";
import { useSites } from "@/hooks/useSites";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { EmptyState } from "@/components/shared/EmptyState";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
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
  Unlink,
  Trash2,
  Settings2,
  Globe,
  Play,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api, Plugin, Site } from "@/lib/api";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

interface PluginFormData {
  name: string;
  path: string;
  gitEnabled: boolean;
  gitRemoteUrl: string;
  buildCommand: string;
}

const initialFormData: PluginFormData = {
  name: "",
  path: "",
  gitEnabled: false,
  gitRemoteUrl: "",
  buildCommand: "",
};

export default function Plugins() {
  const { data: plugins, isLoading: pluginsLoading } = usePlugins();
  const { data: sites } = useSites();
  const queryClient = useQueryClient();
  
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [showMappingDialog, setShowMappingDialog] = useState(false);
  const [selectedPlugin, setSelectedPlugin] = useState<Plugin | null>(null);
  const [formData, setFormData] = useState<PluginFormData>(initialFormData);
  const [selectedSites, setSelectedSites] = useState<number[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isPulling, setIsPulling] = useState<number | null>(null);
  const [isScanning, setIsScanning] = useState<number | null>(null);
  const [isScanningAll, setIsScanningAll] = useState(false);
  const [addMethod, setAddMethod] = useState<"path" | "browse">("path");

  const handleInputChange = (field: keyof PluginFormData, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const { captureError, openErrorModal } = useErrorStore();

  const handleAddPlugin = async () => {
    if (!formData.name || !formData.path) {
      toast.error("Name and path are required");
      return;
    }

    setIsSubmitting(true);
    try {
      const response = await api.createPlugin({
        name: formData.name,
        path: formData.path,
        gitEnabled: formData.gitEnabled,
        gitRemoteUrl: formData.gitRemoteUrl,
        buildCommand: formData.buildCommand,
      });
      if (response.success) {
        toast.success("Plugin registered successfully");
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
        setShowAddDialog(false);
        setFormData(initialFormData);
      } else if (response.error) {
        // Capture to error store and show modal for full details
        const captured = captureError(response.error, {
          endpoint: '/plugins',
          method: 'POST',
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
        });
        openErrorModal(captured);
      }
    } catch (error) {
      toast.error("Failed to remove plugin");
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

  const openMappingDialog = (plugin: Plugin) => {
    setSelectedPlugin(plugin);
    setSelectedSites(plugin.mappings?.map((m) => m.siteId) || []);
    setShowMappingDialog(true);
  };

  const handleSaveMappings = async () => {
    if (!selectedPlugin) return;

    setIsSubmitting(true);
    try {
      // TODO: Call API to update mappings
      toast.success("Site mappings updated");
      queryClient.invalidateQueries({ queryKey: ["plugins"] });
      setShowMappingDialog(false);
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

      {/* Plugin List */}
      {plugins?.length === 0 ? (
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
          {plugins?.map((plugin) => (
            <Card key={plugin.id} className="overflow-hidden">
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-3 min-w-0 flex-1">
                    <div className="p-2 rounded-lg bg-primary/10 flex-shrink-0">
                      <Package className="h-5 w-5 text-primary" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <CardTitle className="text-base flex items-center gap-2">
                        {plugin.name}
                        {plugin.gitEnabled && (
                          <Badge variant="secondary" className="text-xs">
                            <GitBranch className="h-3 w-3 mr-1" />
                            Git
                          </Badge>
                        )}
                      </CardTitle>
                      <p className="text-sm text-muted-foreground font-mono truncate">
                        {plugin.path}
                      </p>
                    </div>
                  </div>

                  <div className="flex gap-1 flex-shrink-0">
                    {/* Refresh/Scan button - always visible */}
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleRefreshScan(plugin.id)}
                      disabled={isScanning === plugin.id}
                      title="Scan for file changes"
                    >
                      {isScanning === plugin.id ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                      ) : (
                        <RefreshCw className="h-4 w-4" />
                      )}
                      <span className="ml-1 hidden sm:inline">Scan</span>
                    </Button>

                    {/* Git Pull button - only for git-enabled plugins */}
                    {plugin.gitEnabled && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleGitPull(plugin.id)}
                        disabled={isPulling === plugin.id}
                        title="Git pull and scan"
                      >
                        {isPulling === plugin.id ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <GitBranch className="h-4 w-4" />
                        )}
                        <span className="ml-1 hidden sm:inline">Pull</span>
                      </Button>
                    )}
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => openMappingDialog(plugin)}
                    >
                      <Link2 className="h-4 w-4" />
                      <span className="ml-1 hidden sm:inline">Sites</span>
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-destructive hover:text-destructive"
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
                      <Eye className="h-4 w-4 text-green-500" />
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
                    <span className="flex items-center gap-1.5 text-amber-600">
                      <AlertCircle className="h-4 w-4" />
                      {plugin.modifiedCount} modified
                    </span>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

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

          <DialogFooter className="pt-4">
            <Button variant="outline" onClick={() => setShowAddDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleAddPlugin} disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Register Plugin
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Site Mapping Dialog */}
      <Dialog open={showMappingDialog} onOpenChange={setShowMappingDialog}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Map to Sites</DialogTitle>
            <DialogDescription>
              Select which WordPress sites should receive this plugin.
            </DialogDescription>
          </DialogHeader>

          <div className="py-4">
            {sites && sites.length > 0 ? (
              <div className="space-y-3">
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
                          ? "bg-green-500"
                          : site.connectionStatus === "disconnected"
                          ? "bg-red-500"
                          : "bg-gray-400"
                      )}
                    />
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-8 text-muted-foreground">
                <Globe className="h-8 w-8 mx-auto mb-2 opacity-50" />
                <p>No sites available</p>
                <p className="text-sm">Add a WordPress site first</p>
              </div>
            )}
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowMappingDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleSaveMappings} disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Save Mappings
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
