import { useState, useMemo, useRef, useEffect } from "react";
import { useSites } from "@/hooks/useSites";
import { useSettings } from "@/hooks/useSettings";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/shared/EmptyState";
import { AddSiteDialog } from "@/components/sites/AddSiteDialog";
import { EditSiteDialog } from "@/components/sites/EditSiteDialog";
import { SiteCard } from "@/components/sites/SiteCard";
import { CategoryFilter } from "@/components/shared/CategoryFilter";
import { CategoryBadge } from "@/components/shared/CategoryBadge";
import {
  Globe,
  Plus,
  Loader2,
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
  const hasReportedError = useRef(false);
  
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [editingSite, setEditingSite] = useState<Pick<Site, "id" | "name" | "url" | "username" | "category" | "connectionStatus" | "lastTestedAt"> | null>(null);
  const [selectedCategories, setSelectedCategories] = useState<string[]>([]);

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

  const openEditDialog = (site: Site) => {
    setEditingSite({
      id: site.id,
      name: site.name,
      url: site.url,
      username: site.username,
      category: site.category,
      connectionStatus: site.connectionStatus,
      lastTestedAt: site.lastTestedAt,
    });
    setShowEditDialog(true);
  };

  const handleCategoryToggle = (category: string) => {
    setSelectedCategories((prev) =>
      prev.includes(category) ? prev.filter((c) => c !== category) : [...prev, category]
    );
  };

  const filteredSites = sites?.filter((site) => {
    if (selectedCategories.length === 0) return true;
    return site.category && selectedCategories.includes(site.category);
  });

  // Compute error info outside of render to avoid triggering captureError during render
  // IMPORTANT: Must be before any early returns to avoid hook ordering violations
  const queryErrorInfo = useMemo(() => {
    if (!queryError) return null;
    return {
      code: "E9001" as const,
      message: "Site service not available",
      details: queryError.message,
      timestamp: new Date().toISOString(),
    };
  }, [queryError]);

  // Report error once when it first appears (outside render phase)
  useEffect(() => {
    if (queryErrorInfo && !hasReportedError.current) {
      hasReportedError.current = true;
    }
    // Reset flag when error clears
    if (!queryError) {
      hasReportedError.current = false;
    }
  }, [queryError, queryErrorInfo]);

  const handleViewErrorDetails = () => {
    if (queryErrorInfo) {
      const captured = captureError(queryErrorInfo, { endpoint: "/sites", method: "GET" });
      openErrorModal(captured);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (queryError && queryErrorInfo) {
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
                  <Button variant="outline" size="sm" onClick={handleViewErrorDetails}>
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

        {/* Add Site Dialog - available even in error state */}
        <AddSiteDialog
          open={showAddDialog}
          onOpenChange={setShowAddDialog}
          debugMode={debugMode}
        />
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

      {/* Category Filter */}
      {sites && sites.length > 0 && (
        <CategoryFilter
          selectedCategories={selectedCategories}
          onCategoryToggle={handleCategoryToggle}
          onClearAll={() => setSelectedCategories([])}
        />
      )}

      {filteredSites?.length === 0 && sites?.length !== 0 ? (
        <EmptyState
          icon={Globe}
          title="No sites match filter"
          description="Try selecting different categories or clear the filter."
          action={{ label: "Clear Filter", onClick: () => setSelectedCategories([]) }}
        />
      ) : filteredSites?.length === 0 ? (
        <EmptyState
          icon={Globe}
          title="No sites connected"
          description="Add your first WordPress site to start syncing plugins."
          action={{ label: "Add Site", onClick: () => setShowAddDialog(true) }}
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {filteredSites?.map((site) => (
            <SiteCard
              key={site.id}
              site={site}
              onEdit={openEditDialog}
              onDelete={handleDeleteSite}
            />
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
