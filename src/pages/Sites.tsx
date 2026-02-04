import { useState } from "react";
import { useSites } from "@/hooks/useSites";
import { useSettings } from "@/hooks/useSettings";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/EmptyState";
import { AddSiteDialog } from "@/components/sites/AddSiteDialog";
import { EditSiteDialog } from "@/components/sites/EditSiteDialog";
import {
  Globe,
  Plus,
  Loader2,
  RefreshCw,
  Edit,
  Trash2,
  CheckCircle,
  XCircle,
  HelpCircle,
  ExternalLink,
  AlertCircle,
} from "lucide-react";
import { api, Site } from "@/lib/api";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useErrorStore } from "@/stores/errorStore";

export default function Sites() {
  const { data: sites, isLoading, error: queryError } = useSites();
  const { data: settings } = useSettings();
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [editingSite, setEditingSite] = useState<Pick<Site, "id" | "name" | "url" | "username"> | null>(null);
  const [testingSiteId, setTestingSiteId] = useState<number | null>(null);

  const debugMode = settings?.logging?.debugMode ?? false;

  const handleDeleteSite = async (id: number) => {
    if (!confirm("Are you sure you want to delete this site?")) return;

    try {
      const response = await api.deleteSite(id);
      if (response.success) {
        toast.success("Site deleted");
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        const captured = captureError(response.error, { endpoint: `/sites/${id}`, method: "DELETE" });
        toast.error(response.error.message, {
          description: "Click for details",
          action: { label: "View Details", onClick: () => openErrorModal(captured) },
          duration: 10000,
        });
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
    setTestingSiteId(id);
    try {
      const response = await api.testConnection(id);
      if (response.success && response.data?.success) {
        toast.success(`Connection successful! WP ${response.data.wpVersion}`);
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        const captured = captureError(response.error, { endpoint: `/sites/${id}/test`, method: "POST" });
        toast.error(response.error.message, {
          description: "Click for details",
          action: { label: "View Details", onClick: () => openErrorModal(captured) },
          duration: 10000,
        });
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else {
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
      setTestingSiteId(null);
    }
  };

  const openEditDialog = (site: Site) => {
    setEditingSite({
      id: site.id,
      name: site.name,
      url: site.url,
      username: site.username,
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

  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case "connected":
        return "bg-primary/10 text-primary border-primary/20";
      case "disconnected":
        return "bg-destructive/10 text-destructive border-destructive/20";
      default:
        return "bg-muted text-muted-foreground border-border";
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

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
            <p className="text-muted-foreground">Manage your WordPress site connections</p>
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
                  <Button variant="outline" size="sm" onClick={() => openErrorModal(errorInfo)}>
                    <AlertCircle className="h-4 w-4 mr-2" />
                    View Error Details
                  </Button>
                  <Button variant="default" size="sm" onClick={() => queryClient.invalidateQueries({ queryKey: ["sites"] })}>
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
          <p className="text-muted-foreground">Manage your WordPress site connections</p>
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
          action={{ label: "Add Site", onClick: () => setShowAddDialog(true) }}
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {sites?.map((site) => (
            <Card key={site.id} className="group hover:shadow-md transition-shadow">
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3 flex-1 min-w-0">
                    <div className="p-2 rounded-lg bg-primary/10 shrink-0">
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
              <CardContent className="pt-0 space-y-3">
                {/* Status Badge */}
                <div className="flex items-center justify-between">
                  <div
                    className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium border ${getStatusBadgeClass(site.connectionStatus)}`}
                  >
                    {getStatusIcon(site.connectionStatus)}
                    <span>{getStatusText(site.connectionStatus)}</span>
                  </div>
                  
                  {/* Retest Button - Always visible for connected sites */}
                  {site.connectionStatus === "connected" && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-7 text-xs"
                      onClick={() => handleTestConnection(site.id)}
                      disabled={testingSiteId === site.id}
                    >
                      {testingSiteId === site.id ? (
                        <Loader2 className="h-3 w-3 animate-spin mr-1" />
                      ) : (
                        <RefreshCw className="h-3 w-3 mr-1" />
                      )}
                      Retest
                    </Button>
                  )}
                  
                  {/* Test Button - For disconnected or unknown */}
                  {site.connectionStatus !== "connected" && (
                    <Button
                      variant="outline"
                      size="sm"
                      className="h-7 text-xs"
                      onClick={() => handleTestConnection(site.id)}
                      disabled={testingSiteId === site.id}
                    >
                      {testingSiteId === site.id ? (
                        <Loader2 className="h-3 w-3 animate-spin mr-1" />
                      ) : (
                        <RefreshCw className="h-3 w-3 mr-1" />
                      )}
                      Test
                    </Button>
                  )}
                </div>

                {/* Last tested info */}
                {site.lastTestedAt && (
                  <p className="text-xs text-muted-foreground">
                    Last tested: {new Date(site.lastTestedAt).toLocaleDateString()}
                  </p>
                )}

                {/* Action buttons */}
                <div className="flex gap-1 pt-2 border-t">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="flex-1"
                    onClick={() => openEditDialog(site)}
                  >
                    <Edit className="h-4 w-4 mr-1" />
                    Edit
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="flex-1 text-destructive hover:text-destructive hover:bg-destructive/10"
                    onClick={() => handleDeleteSite(site.id)}
                  >
                    <Trash2 className="h-4 w-4 mr-1" />
                    Delete
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Dialogs */}
      <AddSiteDialog
        open={showAddDialog}
        onOpenChange={setShowAddDialog}
        debugMode={debugMode}
      />
      <EditSiteDialog
        open={showEditDialog}
        onOpenChange={setShowEditDialog}
        site={editingSite}
      />
    </div>
  );
}
