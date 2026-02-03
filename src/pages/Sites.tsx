import { useState, useEffect, useCallback } from "react";
import { useSites } from "@/hooks/useSites";
import { useSettings } from "@/hooks/useSettings";
import { useConnectionTestLogs } from "@/hooks/useConnectionTestLogs";
import { useSiteFormPersistence } from "@/hooks/useSiteFormPersistence";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { EmptyState } from "@/components/shared/EmptyState";
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
  Globe,
  Plus,
  Loader2,
  TestTube,
  Edit,
  Trash2,
  CheckCircle,
  XCircle,
  HelpCircle,
  ExternalLink,
  AlertCircle,
  AlertTriangle,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api, ApiError } from "@/lib/api";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

export default function Sites() {
  const { data: sites, isLoading, error: queryError } = useSites();
  const { data: settings } = useSettings();
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const connectionLogs = useConnectionTestLogs();
  const { formData, handleInputChange, clearForm, resetForm, setFormData } = useSiteFormPersistence();
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [editingSiteId, setEditingSiteId] = useState<number | null>(null);
  const [editFormData, setEditFormData] = useState({ name: "", url: "", username: "", password: "" });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isTesting, setIsTesting] = useState<number | null>(null);
  const [isTestingCredentials, setIsTestingCredentials] = useState(false);
  const [credentialsTestResult, setCredentialsTestResult] = useState<{
    success: boolean;
    message: string;
    siteName?: string;
    canManagePlugins?: boolean;
  } | null>(null);

  // Check if debug mode is enabled in settings
  const debugMode = settings?.logging?.debugMode ?? false;

  // Helper to show error toast with clickable action to open modal
  const showErrorWithModal = (apiError: ApiError, meta?: { endpoint?: string; method?: string; requestBody?: unknown }) => {
    const captured = captureError(apiError, meta);
    toast.error(apiError.message, {
      description: "Click for details",
      action: {
        label: "View Details",
        onClick: () => openErrorModal(captured),
      },
      duration: 10000,
    });
  };

  // Wrap handleInputChange to also clear test result
  const handleFieldChange = useCallback((field: "name" | "url" | "username" | "password", value: string) => {
    handleInputChange(field, value);
    setCredentialsTestResult(null);
  }, [handleInputChange]);

  const handleEditFieldChange = (field: keyof typeof editFormData, value: string) => {
    setEditFormData((prev) => ({ ...prev, [field]: value }));
  };

  // Test credentials before saving
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
          });
          toast.success("Connection successful!", {
            description: response.data.siteName || response.data.message,
          });
        } else {
          setCredentialsTestResult({
            success: false,
            message: response.data.message || "Connection failed",
          });
          toast.error("Connection failed", {
            description: response.data.message,
          });
        }
      } else if (response.error) {
        setCredentialsTestResult({
          success: false,
          message: response.error.message,
        });
        showErrorWithModal(response.error, {
          endpoint: "/sites/test",
          method: "POST",
        });
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
    };

    setIsSubmitting(true);
    try {
      const response = await api.createSite(requestBody);
      if (response.success) {
        toast.success("Site added successfully");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
        setShowAddDialog(false);
        clearForm(); // Clear persisted form data on success
      } else if (response.error) {
        showErrorWithModal(response.error, {
          endpoint: "/sites",
          method: "POST",
          requestBody: { ...requestBody, applicationPassword: "***" }, // Mask password
        });
      }
    } catch (error) {
      const captured = captureException(error, { endpoint: "/sites", method: "POST", requestBody: { ...requestBody, applicationPassword: "***" } });
      toast.error("Failed to add site", {
        description: "Click for details",
        action: {
          label: "View Details",
          onClick: () => openErrorModal(captured),
        },
        duration: 10000,
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleEditSite = async () => {
    if (!editingSiteId) return;

    setIsSubmitting(true);
    try {
      const response = await api.updateSite(editingSiteId, editFormData);
      if (response.success) {
        toast.success("Site updated successfully");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
        setShowEditDialog(false);
        setEditingSiteId(null);
        setEditFormData({ name: "", url: "", username: "", password: "" });
      } else if (response.error) {
        showErrorWithModal(response.error, {
          endpoint: `/sites/${editingSiteId}`,
          method: "PUT",
          requestBody: { ...editFormData, password: editFormData.password ? "***" : undefined },
        });
      }
    } catch (error) {
      const captured = captureException(error, { endpoint: `/sites/${editingSiteId}`, method: "PUT", requestBody: { ...editFormData, password: "***" } });
      toast.error("Failed to update site", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteSite = async (id: number) => {
    if (!confirm("Are you sure you want to delete this site?")) return;

    try {
      const response = await api.deleteSite(id);
      if (response.success) {
        toast.success("Site deleted");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        showErrorWithModal(response.error, { endpoint: `/sites/${id}`, method: "DELETE" });
      }
    } catch (error) {
      const captured = captureException(error, { endpoint: `/sites/${id}`, method: "DELETE" });
      toast.error("Failed to delete site", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    }
  };

  const handleTestConnection = async (id: number) => {
    setIsTesting(id);
    try {
      const response = await api.testConnection(id);
      if (response.success && response.data?.success) {
        toast.success(`Connection successful! WP ${response.data.wpVersion}`);
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        showErrorWithModal(response.error, { endpoint: `/sites/${id}/test`, method: "POST" });
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else {
        // API returned success but connection test failed
        toast.error(response.data?.message || "Connection failed");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      }
    } catch (error) {
      const captured = captureException(error, { endpoint: `/sites/${id}/test`, method: "POST" });
      toast.error("Connection test failed", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setIsTesting(null);
    }
  };

  const openEditDialog = (site: { id: number; name: string; url: string; username: string }) => {
    setEditingSiteId(site.id);
    setEditFormData({
      name: site.name,
      url: site.url,
      username: site.username,
      password: "", // Don't populate password for security
    });
    setShowEditDialog(true);
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "connected":
        return <CheckCircle className="h-4 w-4 text-primary" />;
      case "disconnected":
        return <XCircle className="h-4 w-4 text-destructive" />;
      default:
        return <HelpCircle className="h-4 w-4 text-muted-foreground" />;
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case "connected":
        return "Connected";
      case "disconnected":
        return "Disconnected";
      default:
        return "Not tested";
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  // Show error state when site service is unavailable
  if (queryError) {
    const errorInfo = captureError({
      code: "E9001",
      message: "Site service not available",
      details: queryError.message,
      timestamp: new Date().toISOString(),
    }, { endpoint: "/sites", method: "GET" });

    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold">Sites</h1>
            <p className="text-muted-foreground">
              Manage your WordPress site connections
            </p>
          </div>
        </div>
        <Card className="border-destructive/50 bg-destructive/5">
          <CardContent className="pt-6">
            <div className="flex items-start gap-4">
              <AlertCircle className="h-6 w-6 text-destructive flex-shrink-0 mt-0.5" />
              <div className="flex-1 space-y-2">
                <h3 className="font-medium">Site service not available</h3>
                <p className="text-sm text-muted-foreground">
                  Unable to connect to the backend server. Make sure the server is running.
                </p>
                <p className="text-sm text-muted-foreground font-mono bg-muted px-2 py-1 rounded inline-block">
                  {queryError.message}
                </p>
                <div className="flex gap-2 mt-4">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => openErrorModal(errorInfo)}
                  >
                    <AlertCircle className="h-4 w-4 mr-2" />
                    View Error Details
                  </Button>
                  <Button
                    variant="default"
                    size="sm"
                    onClick={() => queryClient.invalidateQueries({ queryKey: ["sites"] })}
                  >
                    Retry Connection
                  </Button>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold">Sites</h1>
          <p className="text-muted-foreground">
            Manage your WordPress site connections
          </p>
        </div>
        <Button onClick={() => setShowAddDialog(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Add Site
        </Button>
      </div>

      {sites?.length === 0 ? (
        <EmptyState
          icon={Globe}
          title="No sites connected"
          description="Add your first WordPress site to start syncing plugins."
          action={{
            label: "Add Site",
            onClick: () => setShowAddDialog(true),
          }}
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {sites?.map((site) => (
            <Card key={site.id} className="group hover:shadow-md transition-shadow">
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-primary/10">
                      <Globe className="h-5 w-5 text-primary" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <CardTitle className="text-base truncate">{site.name}</CardTitle>
                      <a
                        href={site.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-xs text-muted-foreground hover:text-primary flex items-center gap-1 truncate"
                      >
                        {site.url.replace(/^https?:\/\//, "")}
                        <ExternalLink className="h-3 w-3 flex-shrink-0" />
                      </a>
                    </div>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="pt-0">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {getStatusIcon(site.connectionStatus)}
                    <span className="text-sm">{getStatusText(site.connectionStatus)}</span>
                  </div>
                  <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => handleTestConnection(site.id)}
                      disabled={isTesting === site.id}
                    >
                      {isTesting === site.id ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                      ) : (
                        <TestTube className="h-4 w-4" />
                      )}
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => openEditDialog(site)}
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-destructive hover:text-destructive"
                      onClick={() => handleDeleteSite(site.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
                {site.lastTestedAt && (
                  <p className="text-xs text-muted-foreground mt-2">
                    Last tested: {new Date(site.lastTestedAt).toLocaleDateString()}
                  </p>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Connection Test Logs */}
      {connectionLogs.steps.length > 0 && (
        <ConnectionTestLogs
          steps={connectionLogs.steps}
          isActive={connectionLogs.isActive}
          onClear={connectionLogs.clearLogs}
        />
      )}

      {/* Add Site Dialog */}
      <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Add WordPress Site</DialogTitle>
            <DialogDescription>
              Connect a WordPress site using its REST API credentials.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
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

            {/* Test Connection Result */}
            {credentialsTestResult && (
              <div
                className={cn(
                  "p-3 rounded-lg border",
                  credentialsTestResult.success
                    ? "bg-primary/5 border-primary/20"
                    : "bg-destructive/5 border-destructive/20"
                )}
              >
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
                    {credentialsTestResult.success ? "Connection Successful" : "Connection Failed"}
                  </span>
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

            {/* Connection Test Logs inline */}
            {connectionLogs.steps.length > 0 && (
              <ConnectionTestLogs
                steps={connectionLogs.steps}
                isActive={connectionLogs.isActive}
                onClear={connectionLogs.clearLogs}
                debugMode={debugMode}
              />
            )}
          </div>
          <DialogFooter className="flex-col sm:flex-row gap-2">
            <Button variant="outline" onClick={() => setShowAddDialog(false)}>
              Cancel
            </Button>
            <Button
              variant="secondary"
              onClick={handleTestCredentials}
              disabled={isTestingCredentials || !formData.url || !formData.username || !formData.password}
            >
              {isTestingCredentials ? (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              ) : (
                <TestTube className="h-4 w-4 mr-2" />
              )}
              Test Connection
            </Button>
            {/* Show "Save Anyway" when test failed but form is complete */}
            {credentialsTestResult && !credentialsTestResult.success && formData.name && formData.url && formData.username && formData.password && (
              <Button
                variant="outline"
                onClick={handleAddSite}
                disabled={isSubmitting}
                className="border-warning text-warning hover:bg-warning/10"
              >
                {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                <AlertTriangle className="h-4 w-4 mr-2" />
                Save Anyway
              </Button>
            )}
            <Button
              onClick={handleAddSite}
              disabled={isSubmitting || !formData.name || !formData.url || !formData.username || !formData.password}
            >
              {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Add Site
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit Site Dialog */}
      <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Edit Site</DialogTitle>
            <DialogDescription>
              Update your WordPress site connection details.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="edit-name">Site Name</Label>
              <Input
                id="edit-name"
                value={editFormData.name}
                onChange={(e) => handleEditFieldChange("name", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-url">Site URL</Label>
              <Input
                id="edit-url"
                value={editFormData.url}
                onChange={(e) => handleEditFieldChange("url", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-username">Username</Label>
              <Input
                id="edit-username"
                value={editFormData.username}
                onChange={(e) => handleEditFieldChange("username", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-password">New Application Password (optional)</Label>
              <Input
                id="edit-password"
                type="password"
                placeholder="Leave blank to keep current"
                value={editFormData.password}
                onChange={(e) => handleEditFieldChange("password", e.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowEditDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleEditSite} disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Save Changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
