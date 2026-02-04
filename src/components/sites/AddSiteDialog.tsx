import { useState, useCallback, useEffect } from "react";
import { useSiteFormPersistence } from "@/hooks/useSiteFormPersistence";
import { useConnectionTestLogs } from "@/hooks/useConnectionTestLogs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ConnectionTestLogs } from "@/components/sites/ConnectionTestLogs";
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
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api, ApiError } from "@/lib/api";
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
  const [credentialsTestResult, setCredentialsTestResult] = useState<{
    success: boolean;
    message: string;
    siteName?: string;
    canManagePlugins?: boolean;
    testedAt?: string;
  } | null>(null);

  // Reset state when dialog closes
  useEffect(() => {
    if (!open) {
      setCredentialsTestResult(null);
      connectionLogs.clearLogs();
      setActiveTab("basic");
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
      const captured = captureException(error, { endpoint: "/sites/test", method: "POST" });
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
      // If connection was tested successfully, pass that info
      ...(credentialsTestResult?.success && {
        connectionStatus: "connected",
        testedAt: credentialsTestResult.testedAt,
      }),
    };

    setIsSubmitting(true);
    try {
      const response = await api.createSite(requestBody);
      if (response.success) {
        toast.success("Site added successfully");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
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
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="basic">Basic</TabsTrigger>
            <TabsTrigger value="connection">Connection</TabsTrigger>
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
