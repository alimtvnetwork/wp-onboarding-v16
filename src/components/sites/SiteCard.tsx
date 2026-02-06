import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { CategoryBadge } from "@/components/shared/CategoryBadge";
import {
  Globe,
  Loader2,
  RefreshCw,
  Edit,
  Trash2,
  CheckCircle,
  XCircle,
  HelpCircle,
  ExternalLink,
  Package,
  Upload,
  Eye,
  FlaskConical,
  Database,
} from "lucide-react";
import { useNavigate } from "react-router-dom";
import { api, Site, PluginMapping } from "@/lib/api";
import { toast } from "sonner";
import { useQueryClient } from "@tanstack/react-query";
import { useErrorStore } from "@/stores/errorStore";
import { RemotePluginsPanel } from "./RemotePluginsPanel";
import { RemoteSnapshotsPanel } from "./RemoteSnapshotsPanel";

interface SiteCardProps {
  site: Site;
  onEdit: (site: Site) => void;
  onDelete: (id: number) => void;
}

export function SiteCard({ site, onEdit, onDelete }: SiteCardProps) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { captureError, captureException, openErrorModal } = useErrorStore();
  const [testingSiteId, setTestingSiteId] = useState<number | null>(null);
  const [deployingUploader, setDeployingUploader] = useState(false);
  const [showRemotePlugins, setShowRemotePlugins] = useState(false);
  const [showSnapshots, setShowSnapshots] = useState(false);

  // Fetch linked plugins for this site
  const { data: mappings } = useQuery({
    queryKey: ["sites", site.id, "mappings"],
    queryFn: async () => {
      const response = await api.getSiteMappings(site.id);
      if (response.success) return response.data || [];
      return [];
    },
  });

  const handleTestConnection = async () => {
    setTestingSiteId(site.id);
    try {
      const response = await api.testConnection(site.id);
      if (response.success && response.data?.success) {
        toast.success(`Connection successful! WP ${response.data.wpVersion}`);
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        const captured = captureError(response.error, { endpoint: `/sites/${site.id}/test`, method: "POST" });
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
      const captured = captureException(error, { endpoint: `/sites/${site.id}/test`, method: "POST" });
      toast.error("Connection test failed", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setTestingSiteId(null);
    }
  };

  const handleDeployUploader = async () => {
    setDeployingUploader(true);
    try {
      const response = await api.bootstrapUploader(site.id);
      if (response.success && response.data?.success) {
        toast.success("Riseup Asia Uploader deployed!", {
          description: response.data.activated ? "Plugin is active" : "Plugin uploaded but not activated",
        });
        queryClient.invalidateQueries({ queryKey: ["sites"] });
      } else if (response.error) {
        const captured = captureError(response.error, { endpoint: `/sites/${site.id}/bootstrap-uploader`, method: "POST" });
        toast.error(response.error.message, {
          description: "Click for details",
          action: { label: "View Details", onClick: () => openErrorModal(captured) },
          duration: 10000,
        });
      } else {
        toast.error("Deploy failed");
      }
    } catch (error) {
      const captured = captureException(error, { endpoint: `/sites/${site.id}/bootstrap-uploader`, method: "POST" });
      toast.error("Deploy failed", {
        description: "Click for details",
        action: { label: "View Details", onClick: () => openErrorModal(captured) },
        duration: 10000,
      });
    } finally {
      setDeployingUploader(false);
    }
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

  return (
    <Card className="group hover:shadow-md transition-shadow">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-3 flex-1 min-w-0">
            <div className="p-2 rounded-lg bg-primary/10 shrink-0">
              <Globe className="h-5 w-5 text-primary" />
            </div>
            <div className="flex-1 min-w-0">
              <CardTitle className="text-base truncate flex items-center gap-2">
                {site.name}
                <CategoryBadge category={site.category} size="sm" />
              </CardTitle>
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
        {/* Linked Plugins */}
        {mappings && mappings.length > 0 && (
          <div className="flex flex-wrap gap-1.5">
            {mappings.map((mapping: PluginMapping) => (
              <Badge key={mapping.id} variant="secondary" className="text-xs flex items-center gap-1">
                <Package className="h-3 w-3" />
                {mapping.remoteSlug}
              </Badge>
            ))}
          </div>
        )}

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
              onClick={handleTestConnection}
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
              onClick={handleTestConnection}
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

        {/* Action buttons - responsive grid layout */}
        <div className="grid grid-cols-3 sm:grid-cols-6 gap-1 pt-2 border-t">
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col sm:flex-row items-center justify-center h-auto py-2 px-1 sm:px-2 gap-0.5 sm:gap-1"
            onClick={() => navigate(`/api-explorer?siteId=${site.id}`)}
            disabled={site.connectionStatus !== "connected"}
            title={site.connectionStatus !== "connected" ? "Connect site first" : "Test API endpoints"}
          >
            <FlaskConical className="h-4 w-4" />
            <span className="text-[10px] sm:text-xs">API</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col sm:flex-row items-center justify-center h-auto py-2 px-1 sm:px-2 gap-0.5 sm:gap-1"
            onClick={() => setShowRemotePlugins(true)}
            disabled={site.connectionStatus !== "connected"}
            title={site.connectionStatus !== "connected" ? "Connect site first" : "View plugins on this site"}
          >
            <Eye className="h-4 w-4" />
            <span className="text-[10px] sm:text-xs">Plugins</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col sm:flex-row items-center justify-center h-auto py-2 px-1 sm:px-2 gap-0.5 sm:gap-1"
            onClick={() => setShowSnapshots(true)}
            disabled={site.connectionStatus !== "connected"}
            title={site.connectionStatus !== "connected" ? "Connect site first" : "Manage database snapshots"}
          >
            <Database className="h-4 w-4" />
            <span className="text-[10px] sm:text-xs">Snapshots</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col sm:flex-row items-center justify-center h-auto py-2 px-1 sm:px-2 gap-0.5 sm:gap-1"
            onClick={handleDeployUploader}
            disabled={deployingUploader || site.connectionStatus !== "connected"}
            title={site.connectionStatus !== "connected" ? "Connect site first" : "Deploy Riseup Asia Uploader to this site"}
          >
            {deployingUploader ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Upload className="h-4 w-4" />
            )}
            <span className="text-[10px] sm:text-xs">Deploy</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col sm:flex-row items-center justify-center h-auto py-2 px-1 sm:px-2 gap-0.5 sm:gap-1"
            onClick={() => onEdit(site)}
          >
            <Edit className="h-4 w-4" />
            <span className="text-[10px] sm:text-xs">Edit</span>
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="flex flex-col sm:flex-row items-center justify-center h-auto py-2 px-1 sm:px-2 gap-0.5 sm:gap-1 text-destructive hover:text-destructive hover:bg-destructive/10"
            onClick={() => onDelete(site.id)}
          >
            <Trash2 className="h-4 w-4" />
            <span className="text-[10px] sm:text-xs">Delete</span>
          </Button>
        </div>
      </CardContent>

      <RemotePluginsPanel
        site={site}
        open={showRemotePlugins}
        onOpenChange={setShowRemotePlugins}
      />
      <RemoteSnapshotsPanel
        site={site}
        open={showSnapshots}
        onOpenChange={setShowSnapshots}
      />
    </Card>
  );
}
