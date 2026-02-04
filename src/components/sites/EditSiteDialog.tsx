import { useState, useEffect } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { CategorySelect } from "@/components/shared/CategorySelect";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Loader2, RefreshCw, CheckCircle, Package, Globe, Search } from "lucide-react";
import { api, ApiError, Site, Plugin, PluginMapping } from "@/lib/api";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";
import { ConnectionTestLogs } from "./ConnectionTestLogs";
import { useConnectionTestLogs } from "@/hooks/useConnectionTestLogs";
import { cn } from "@/lib/utils";

interface EditSiteDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  site: Pick<Site, "id" | "name" | "url" | "username" | "category" | "connectionStatus" | "lastTestedAt"> | null;
}

export function EditSiteDialog({ open, onOpenChange, site }: EditSiteDialogProps) {
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isTesting, setIsTesting] = useState(false);
  const [testSuccess, setTestSuccess] = useState(false);
  const [activeTab, setActiveTab] = useState("basic");
  const [formData, setFormData] = useState({
    name: "",
    url: "",
    username: "",
    password: "",
    category: null as string | null,
  });
  const [selectedPlugins, setSelectedPlugins] = useState<number[]>([]);
  const [pluginSearch, setPluginSearch] = useState("");

  const { steps, isActive: testActive, clearLogs } = useConnectionTestLogs();

  // Fetch all plugins for the Plugins tab
  const { data: allPlugins } = useQuery({
    queryKey: ["plugins"],
    queryFn: async () => {
      const response = await api.getPlugins();
      return response.success ? response.data || [] : [];
    },
  });

  // Fetch current site mappings
  const { data: currentMappings } = useQuery({
    queryKey: ["sites", site?.id, "mappings"],
    queryFn: async () => {
      if (!site?.id) return [];
      const response = await api.getSiteMappings(site.id);
      return response.success ? response.data || [] : [];
    },
    enabled: !!site?.id,
  });

  // Populate form when site changes
  useEffect(() => {
    if (site) {
      setFormData({
        name: site.name,
        url: site.url,
        username: site.username,
        password: "",
        category: site.category || null,
      });
      setActiveTab("basic");
      setTestSuccess(false);
      setPluginSearch("");
      clearLogs();
    }
  }, [site, clearLogs]);

  // Initialize selected plugins from mappings
  useEffect(() => {
    if (currentMappings && currentMappings.length > 0) {
      setSelectedPlugins(currentMappings.map((m: PluginMapping) => m.pluginId));
    } else {
      setSelectedPlugins([]);
    }
  }, [currentMappings]);

  const showErrorWithModal = (apiError: ApiError, meta?: { endpoint?: string; method?: string; requestBody?: unknown }) => {
    const captured = captureError(apiError, meta);
    toast.error(apiError.message, {
      description: "Click for details",
      action: { label: "View Details", onClick: () => openErrorModal(captured) },
      duration: 10000,
    });
  };

  const handleFieldChange = (field: keyof typeof formData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleTestConnection = async () => {
    if (!site) return;

    setIsTesting(true);
    setTestSuccess(false);
    clearLogs();

    try {
      const response = await api.testConnection(site.id);
      if (response.success && response.data?.success) {
        setTestSuccess(true);
        toast.success(`Connection successful! WP ${response.data.wpVersion}`);
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        showErrorWithModal(response.error, { endpoint: `/sites/${site.id}/test`, method: "POST" });
      } else {
        toast.error(response.data?.message || "Connection failed");
      }
    } catch (error) {
      const captured = captureException(error, { 
        source: "EditSiteDialog.handleTestConnection",
        triggerComponent: "EditSiteDialog",
        triggerAction: "test_connection",
        endpoint: `/sites/${site.id}/test`, 
        method: "POST",
        context: { siteId: site.id }
      });
      toast.error("Connection test failed", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setIsTesting(false);
    }
  };

  const togglePluginSelection = (pluginId: number) => {
    setSelectedPlugins((prev) =>
      prev.includes(pluginId)
        ? prev.filter((id) => id !== pluginId)
        : [...prev, pluginId]
    );
  };

  const handleEditSite = async () => {
    if (!site) return;

    setIsSubmitting(true);
    try {
      // Update site details
      const response = await api.updateSite(site.id, {
        ...formData,
        category: formData.category || undefined,
      });
      if (response.success) {
        // Use the new robust endpoint to update all site mappings in one call
        const mappingRes = await api.updateSiteMappings(site.id, selectedPlugins);
        if (!mappingRes.success && mappingRes.error) {
          console.warn("[EditSiteDialog] Mapping update warning:", mappingRes.error.message);
          toast.warning("Site saved, but some plugin mappings may not have updated");
        }

        toast.success("Site updated successfully");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
        onOpenChange(false);
      } else if (response.error) {
        showErrorWithModal(response.error, {
          endpoint: `/sites/${site.id}`,
          method: "PUT",
          requestBody: { ...formData, password: formData.password ? "***" : undefined },
        });
      }
    } catch (error) {
      const captured = captureException(error, {
        source: "EditSiteDialog.handleEditSite",
        triggerComponent: "EditSiteDialog",
        triggerAction: "save_clicked",
        endpoint: `/sites/${site.id}`,
        method: "PUT",
        requestBody: { ...formData, password: "***" },
        context: { siteId: site.id, selectedPluginCount: selectedPlugins.length }
      });
      toast.error("Failed to update site", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const isConnected = site?.connectionStatus === "connected" || testSuccess;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            Edit Site
            {isConnected && (
              <Badge variant="secondary" className="bg-primary/10 text-primary border-primary/20">
                <CheckCircle className="h-3 w-3 mr-1" />
                Connected
              </Badge>
            )}
          </DialogTitle>
          <DialogDescription>
            Update your WordPress site connection details and manage linked plugins.
          </DialogDescription>
        </DialogHeader>

        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="basic">Basic</TabsTrigger>
            <TabsTrigger value="connection">Connection</TabsTrigger>
            <TabsTrigger value="plugins">Plugins</TabsTrigger>
          </TabsList>

          <TabsContent value="basic" className="space-y-4 pt-4">
            <div className="space-y-2">
              <Label htmlFor="edit-name">Site Name</Label>
              <Input
                id="edit-name"
                value={formData.name}
                onChange={(e) => handleFieldChange("name", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-url">Site URL</Label>
              <Input
                id="edit-url"
                value={formData.url}
                onChange={(e) => handleFieldChange("url", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>Category</Label>
              <CategorySelect
                value={formData.category}
                onValueChange={(val) => handleFieldChange("category", val || "")}
                placeholder="Select category..."
              />
            </div>
          </TabsContent>

          <TabsContent value="connection" className="space-y-4 pt-4">
            <div className="space-y-2">
              <Label htmlFor="edit-username">Username</Label>
              <Input
                id="edit-username"
                value={formData.username}
                onChange={(e) => handleFieldChange("username", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-password">New Application Password (optional)</Label>
              <Input
                id="edit-password"
                type="password"
                placeholder="Leave blank to keep current"
                value={formData.password}
                onChange={(e) => handleFieldChange("password", e.target.value)}
              />
              <p className="text-xs text-muted-foreground">
                Only enter a new password if you want to change it.
              </p>
            </div>

            {/* Retest Connection Button */}
            <div className="pt-2 border-t">
              <Button
                type="button"
                variant={isConnected ? "outline" : "default"}
                onClick={handleTestConnection}
                disabled={isTesting}
                className="w-full"
              >
                {isTesting ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    Testing Connection...
                  </>
                ) : isConnected ? (
                  <>
                    <RefreshCw className="h-4 w-4 mr-2" />
                    Retest Connection
                  </>
                ) : (
                  <>
                    <Globe className="h-4 w-4 mr-2" />
                    Test Connection
                  </>
                )}
              </Button>
            </div>

            {/* Connection Test Logs */}
            <ConnectionTestLogs steps={steps} isActive={testActive} />
          </TabsContent>

          <TabsContent value="plugins" className="space-y-4 pt-4">
            <p className="text-sm text-muted-foreground">
              Select plugins to deploy to this site.
            </p>
            
            {/* Search */}
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search plugins..."
                value={pluginSearch}
                onChange={(e) => setPluginSearch(e.target.value)}
                className="pl-9"
              />
            </div>
            
            {(() => {
              const filtered = (allPlugins || []).filter((plugin: Plugin) =>
                plugin.name.toLowerCase().includes(pluginSearch.toLowerCase()) ||
                plugin.path.toLowerCase().includes(pluginSearch.toLowerCase())
              );
              
              if (filtered.length > 0) {
                return (
                  <div className="space-y-2 max-h-[200px] overflow-y-auto">
                    {filtered.map((plugin: Plugin) => (
                      <div
                        key={plugin.id}
                        className={cn(
                          "flex items-center space-x-3 p-2 rounded-lg border cursor-pointer transition-colors",
                          selectedPlugins.includes(plugin.id)
                            ? "border-primary bg-primary/5"
                            : "border-border hover:bg-muted/50"
                        )}
                        onClick={() => togglePluginSelection(plugin.id)}
                      >
                        <Checkbox
                          checked={selectedPlugins.includes(plugin.id)}
                          onCheckedChange={() => togglePluginSelection(plugin.id)}
                          onClick={(e) => e.stopPropagation()}
                        />
                        <div className="flex items-center gap-2 flex-1 min-w-0">
                          <Package className="h-4 w-4 text-primary shrink-0" />
                          <div className="min-w-0">
                            <p className="text-sm font-medium truncate">{plugin.name}</p>
                            <p className="text-xs text-muted-foreground truncate">{plugin.path}</p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                );
              }
              
              if (allPlugins && allPlugins.length === 0) {
                return (
                  <div className="text-center py-6 text-muted-foreground">
                    <Package className="h-8 w-8 mx-auto mb-2 opacity-50" />
                    <p className="text-sm">No plugins registered yet.</p>
                    <p className="text-xs">Register plugins from the Plugins page first.</p>
                  </div>
                );
              }
              
              if (pluginSearch) {
                return (
                  <div className="text-center py-6 text-muted-foreground">
                    <p className="text-sm">No plugins match "{pluginSearch}"</p>
                  </div>
                );
              }
              
              return null;
            })()}
            
            {selectedPlugins.length > 0 && (
              <div className="flex flex-wrap gap-1.5 pt-2 border-t">
                {selectedPlugins.map((pluginId) => {
                  const plugin = allPlugins?.find((p: Plugin) => p.id === pluginId);
                  return plugin ? (
                    <Badge key={pluginId} variant="secondary" className="text-xs">
                      <Package className="h-3 w-3 mr-1" />
                      {plugin.name}
                    </Badge>
                  ) : null;
                })}
              </div>
            )}
          </TabsContent>
        </Tabs>

        <DialogFooter className="pt-4">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleEditSite} disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
            Save Changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
