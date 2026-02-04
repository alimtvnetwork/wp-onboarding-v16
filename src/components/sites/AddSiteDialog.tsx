import { useState, useCallback, useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { useSiteFormPersistence } from "@/hooks/useSiteFormPersistence";
import { useConnectionTestLogs } from "@/hooks/useConnectionTestLogs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ConnectionTestLogs } from "@/components/sites/ConnectionTestLogs";
import { CategorySelect } from "@/components/shared/CategorySelect";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Loader2,
  TestTube,
  CheckCircle,
  XCircle,
  RefreshCw,
  Package,
  Search,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api, ApiError, Plugin } from "@/lib/api";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

interface AddSiteDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  debugMode?: boolean;
}

export function AddSiteDialog({ open, onOpenChange, debugMode = false }: AddSiteDialogProps) {
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const connectionLogs = useConnectionTestLogs();
  const { formData, handleInputChange, clearForm } = useSiteFormPersistence();
  
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isTestingCredentials, setIsTestingCredentials] = useState(false);
  const [activeTab, setActiveTab] = useState("basic");
  const [category, setCategory] = useState<string | null>(null);
  const [credentialsTestResult, setCredentialsTestResult] = useState<{
    success: boolean;
    message: string;
    siteName?: string;
    canManagePlugins?: boolean;
    testedAt?: string;
  } | null>(null);
  
  // Plugin selection state
  const [selectedPluginIds, setSelectedPluginIds] = useState<number[]>([]);
  const [pluginSearch, setPluginSearch] = useState("");

  // Fetch all plugins for the Plugins tab
  const { data: allPlugins } = useQuery({
    queryKey: ["plugins"],
    queryFn: async () => {
      try {
        const response = await api.getPlugins();
        return response.success ? response.data || [] : [];
      } catch (e) {
        console.warn("[AddSiteDialog] Failed to fetch plugins:", e);
        return [];
      }
    },
    enabled: open,
  });

  // Reset state when dialog closes
  useEffect(() => {
    if (!open) {
      setCredentialsTestResult(null);
      connectionLogs.clearLogs();
      setActiveTab("basic");
      setCategory(null);
      setSelectedPluginIds([]);
      setPluginSearch("");
    }
  }, [open]);

  const showErrorWithModal = (apiError: ApiError, meta?: { endpoint?: string; method?: string; requestBody?: unknown }) => {
    const captured = captureError(apiError, meta);
    toast.error(apiError.message, {
      description: "Click for details",
      action: { label: "View Details", onClick: () => openErrorModal(captured) },
      duration: 10000,
    });
  };

  const handleFieldChange = useCallback((field: "name" | "url" | "username" | "password", value: string) => {
    handleInputChange(field, value);
    // Only clear test result if credentials change
    if (field === "url" || field === "username" || field === "password") {
      setCredentialsTestResult(null);
    }
  }, [handleInputChange]);

  const handleTestCredentials = async () => {
    if (!formData.url || !formData.username || !formData.password) {
      toast.error("URL, username, and password are required to test");
      return;
    }

    setIsTestingCredentials(true);
    setCredentialsTestResult(null);
    connectionLogs.clearLogs();

    try {
      const response = await api.testCredentials({
        url: formData.url,
        username: formData.username,
        password: formData.password,
      });

      if (response.success && response.data) {
        if (response.data.success) {
          setCredentialsTestResult({
            success: true,
            message: response.data.message || "Connection successful",
            siteName: response.data.siteName,
            canManagePlugins: response.data.canManagePlugins,
            testedAt: new Date().toISOString(),
          });
          toast.success("Connection successful!", {
            description: response.data.siteName || response.data.message,
          });
        } else {
          setCredentialsTestResult({
            success: false,
            message: response.data.message || "Connection failed",
          });
          toast.error("Connection failed", { description: response.data.message });
        }
      } else if (response.error) {
        setCredentialsTestResult({ success: false, message: response.error.message });
        showErrorWithModal(response.error, { endpoint: "/sites/test", method: "POST" });
      }
    } catch (error) {
      const captured = captureException(error, { endpoint: "/sites/test", method: "POST", source: "AddSiteDialog.handleTestCredentials" });
      setCredentialsTestResult({
        success: false,
        message: error instanceof Error ? error.message : "Unknown error",
      });
      toast.error("Connection test failed", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setIsTestingCredentials(false);
    }
  };

  const togglePluginSelection = (pluginId: number) => {
    setSelectedPluginIds((prev) =>
      prev.includes(pluginId)
        ? prev.filter((id) => id !== pluginId)
        : [...prev, pluginId]
    );
  };

  const handleAddSite = async () => {
    if (!formData.name || !formData.url || !formData.username || !formData.password) {
      toast.error("All fields are required");
      return;
    }

    const requestBody = {
      name: formData.name,
      url: formData.url,
      username: formData.username,
      applicationPassword: formData.password,
      category: category || undefined,
      // If connection was tested successfully, pass that info
      ...(credentialsTestResult?.success && {
        connectionStatus: "connected",
        testedAt: credentialsTestResult.testedAt,
      }),
    };

    setIsSubmitting(true);
    try {
      const response = await api.createSite(requestBody);
      if (response.success && response.data) {
        const newSiteId = response.data.id;
        
        // Create plugin mappings for selected plugins
        if (selectedPluginIds.length > 0 && newSiteId) {
          for (const pluginId of selectedPluginIds) {
            try {
              const plugin = allPlugins?.find((p) => p.id === pluginId);
              const pluginMappingsRes = await api.getPluginMappings(pluginId);
              if (pluginMappingsRes.success && pluginMappingsRes.data) {
                const currentSiteIds = pluginMappingsRes.data.map((m) => m.siteId);
                if (!currentSiteIds.includes(newSiteId)) {
                  const remoteSlug = pluginMappingsRes.data[0]?.remoteSlug || 
                    (plugin?.name || "plugin").toLowerCase().replace(/\s+/g, '-');
                  await api.updatePluginMappings(pluginId, { 
                    siteIds: [...currentSiteIds, newSiteId], 
                    remoteSlug 
                  });
                }
              }
            } catch (e) {
              console.warn(`[AddSiteDialog] Failed to create mapping for plugin ${pluginId}:`, e);
            }
          }
        }
        
        toast.success("Site added successfully");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
        queryClient.invalidateQueries({ queryKey: ["plugins"] });
        onOpenChange(false);
        clearForm();
      } else if (response.error) {
        showErrorWithModal(response.error, {
          endpoint: "/sites",
          method: "POST",
          requestBody: { ...requestBody, applicationPassword: "***" },
        });
      }
    } catch (error) {
      const captured = captureException(error, {
        endpoint: "/sites",
        method: "POST",
        requestBody: { ...requestBody, applicationPassword: "***" },
        source: "AddSiteDialog.handleAddSite",
      });
      toast.error("Failed to add site", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const canTest = formData.url && formData.username && formData.password;
  const canSave = formData.name && formData.url && formData.username && formData.password;
  
  // Filter plugins by search
  const filteredPlugins = (allPlugins || []).filter((plugin) =>
    plugin.name.toLowerCase().includes(pluginSearch.toLowerCase()) ||
    plugin.path.toLowerCase().includes(pluginSearch.toLowerCase())
  );

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            Add WordPress Site
            {credentialsTestResult?.success && (
              <span className="inline-flex items-center gap-1 text-xs font-normal text-primary bg-primary/10 px-2 py-0.5 rounded-full">
                <CheckCircle className="h-3 w-3" />
                Connected
              </span>
            )}
          </DialogTitle>
          <DialogDescription>
            Connect a WordPress site using its REST API credentials.
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
              <Label htmlFor="name">Site Name</Label>
              <Input
                id="name"
                placeholder="My WordPress Site"
                value={formData.name}
                onChange={(e) => handleFieldChange("name", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="url">Site URL</Label>
              <Input
                id="url"
                placeholder="https://example.com"
                value={formData.url}
                onChange={(e) => handleFieldChange("url", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>Category</Label>
              <CategorySelect
                value={category}
                onValueChange={setCategory}
                placeholder="Select category..."
              />
            </div>
          </TabsContent>

          <TabsContent value="connection" className="space-y-4 pt-4">
            <div className="space-y-2">
              <Label htmlFor="username">Username</Label>
              <Input
                id="username"
                placeholder="admin"
                value={formData.username}
                onChange={(e) => handleFieldChange("username", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Application Password</Label>
              <Input
                id="password"
                type="password"
                placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                value={formData.password}
                onChange={(e) => handleFieldChange("password", e.target.value)}
              />
              <p className="text-xs text-muted-foreground">
                Generate an application password in WordPress under Users → Profile
              </p>
            </div>

            {/* Connection Test Result */}
            {credentialsTestResult && (
              <div
                className={cn(
                  "p-3 rounded-lg border",
                  credentialsTestResult.success
                    ? "bg-primary/5 border-primary/20"
                    : "bg-destructive/5 border-destructive/20"
                )}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {credentialsTestResult.success ? (
                      <CheckCircle className="h-4 w-4 text-primary" />
                    ) : (
                      <XCircle className="h-4 w-4 text-destructive" />
                    )}
                    <span
                      className={cn(
                        "text-sm font-medium",
                        credentialsTestResult.success ? "text-primary" : "text-destructive"
                      )}
                    >
                      {credentialsTestResult.success ? "Connected" : "Connection Failed"}
                    </span>
                  </div>
                  {credentialsTestResult.success && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={handleTestCredentials}
                      disabled={isTestingCredentials}
                      className="h-7 text-xs"
                    >
                      {isTestingCredentials ? (
                        <Loader2 className="h-3 w-3 animate-spin mr-1" />
                      ) : (
                        <RefreshCw className="h-3 w-3 mr-1" />
                      )}
                      Retest
                    </Button>
                  )}
                </div>
                <p className="text-xs text-muted-foreground mt-1">
                  {credentialsTestResult.message}
                </p>
                {credentialsTestResult.siteName && (
                  <p className="text-xs text-muted-foreground mt-1">
                    Site: {credentialsTestResult.siteName}
                  </p>
                )}
                {credentialsTestResult.success && credentialsTestResult.canManagePlugins === false && (
                  <p className="text-xs text-destructive mt-1">
                    ⚠️ User cannot manage plugins - publishing may fail
                  </p>
                )}
              </div>
            )}

            {/* Test Button */}
            {!credentialsTestResult?.success && (
              <Button
                variant="secondary"
                className="w-full"
                onClick={handleTestCredentials}
                disabled={isTestingCredentials || !canTest}
              >
                {isTestingCredentials ? (
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                ) : (
                  <TestTube className="h-4 w-4 mr-2" />
                )}
                Test Connection
              </Button>
            )}

            {/* Connection Test Logs */}
            {connectionLogs.steps.length > 0 && (
              <ConnectionTestLogs
                steps={connectionLogs.steps}
                isActive={connectionLogs.isActive}
                onClear={connectionLogs.clearLogs}
                debugMode={debugMode}
              />
            )}
          </TabsContent>

          <TabsContent value="plugins" className="space-y-4 pt-4">
            <p className="text-sm text-muted-foreground">
              Select plugins to deploy to this site after creation.
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
            
            {filteredPlugins.length > 0 ? (
              <div className="space-y-2 max-h-[200px] overflow-y-auto">
                {filteredPlugins.map((plugin: Plugin) => (
                  <div
                    key={plugin.id}
                    className={cn(
                      "flex items-center space-x-3 p-2 rounded-lg border cursor-pointer transition-colors",
                      selectedPluginIds.includes(plugin.id)
                        ? "border-primary bg-primary/5"
                        : "border-border hover:bg-muted/50"
                    )}
                    onClick={() => togglePluginSelection(plugin.id)}
                  >
                    <Checkbox
                      checked={selectedPluginIds.includes(plugin.id)}
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
            ) : allPlugins && allPlugins.length === 0 ? (
              <div className="text-center py-6 text-muted-foreground">
                <Package className="h-8 w-8 mx-auto mb-2 opacity-50" />
                <p className="text-sm">No plugins registered yet.</p>
                <p className="text-xs">Register plugins from the Plugins page first.</p>
              </div>
            ) : pluginSearch ? (
              <div className="text-center py-6 text-muted-foreground">
                <p className="text-sm">No plugins match "{pluginSearch}"</p>
              </div>
            ) : null}
            
            {selectedPluginIds.length > 0 && (
              <div className="flex flex-wrap gap-1.5 pt-2 border-t">
                {selectedPluginIds.map((pluginId) => {
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

        <DialogFooter className="flex-col sm:flex-row gap-2 pt-4">
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleAddSite} disabled={isSubmitting || !canSave}>
            {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
            {credentialsTestResult?.success ? "Save Site" : "Add Site"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}