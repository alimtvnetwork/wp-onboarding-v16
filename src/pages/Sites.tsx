import { useState } from "react";
import { useSites } from "@/hooks/useSites";
import { useConnectionTestLogs } from "@/hooks/useConnectionTestLogs";
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
} from "lucide-react";
import { cn } from "@/lib/utils";
import { api, ApiError } from "@/lib/api";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

interface SiteFormData {
  name: string;
  url: string;
  username: string;
  password: string;
}

const initialFormData: SiteFormData = {
  name: "",
  url: "",
  username: "",
  password: "",
};

export default function Sites() {
  const { data: sites, isLoading, error: queryError } = useSites();
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const connectionLogs = useConnectionTestLogs();
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [editingSiteId, setEditingSiteId] = useState<number | null>(null);
  const [formData, setFormData] = useState<SiteFormData>(initialFormData);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isTesting, setIsTesting] = useState<number | null>(null);

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

  const handleInputChange = (field: keyof SiteFormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
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
        setFormData(initialFormData);
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
      const response = await api.updateSite(editingSiteId, formData);
      if (response.success) {
        toast.success("Site updated successfully");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
        setShowEditDialog(false);
        setEditingSiteId(null);
        setFormData(initialFormData);
      } else if (response.error) {
        showErrorWithModal(response.error, {
          endpoint: `/sites/${editingSiteId}`,
          method: "PUT",
          requestBody: { ...formData, password: formData.password ? "***" : undefined },
        });
      }
    } catch (error) {
      const captured = captureException(error, { endpoint: `/sites/${editingSiteId}`, method: "PUT", requestBody: { ...formData, password: "***" } });
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
    setFormData({
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
        return <CheckCircle className="h-4 w-4 text-green-500" />;
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
                onChange={(e) => handleInputChange("name", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="url">Site URL</Label>
              <Input
                id="url"
                placeholder="https://example.com"
                value={formData.url}
                onChange={(e) => handleInputChange("url", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="username">Username</Label>
              <Input
                id="username"
                placeholder="admin"
                value={formData.username}
                onChange={(e) => handleInputChange("username", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Application Password</Label>
              <Input
                id="password"
                type="password"
                placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                value={formData.password}
                onChange={(e) => handleInputChange("password", e.target.value)}
              />
              <p className="text-xs text-muted-foreground">
                Generate an application password in WordPress under Users → Profile
              </p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowAddDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleAddSite} disabled={isSubmitting}>
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
                value={formData.name}
                onChange={(e) => handleInputChange("name", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-url">Site URL</Label>
              <Input
                id="edit-url"
                value={formData.url}
                onChange={(e) => handleInputChange("url", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-username">Username</Label>
              <Input
                id="edit-username"
                value={formData.username}
                onChange={(e) => handleInputChange("username", e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-password">New Application Password (optional)</Label>
              <Input
                id="edit-password"
                type="password"
                placeholder="Leave blank to keep current"
                value={formData.password}
                onChange={(e) => handleInputChange("password", e.target.value)}
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
